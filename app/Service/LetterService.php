<?php
declare(strict_types=1);

namespace Pena\Service;

use Closure;
use DateTimeImmutable;
use Pena\Support\Db;

/**
 * Letter lifecycle on the secretary's side: draft → submit → (revise → resubmit) → approved / cancelled.
 *
 * Workspace kinds: an organization letter gets a serial number, needs a letterhead and at least one reviewer.
 * A personal letter has no number and no letterhead; without reviewers it is final when submitted
 * (signed by its author), with reviewers it goes through the same staged approval.
 *
 * Invariants enforced here (the database cannot express them):
 *  - a submitted version is never edited; any change after submission is a new version;
 *  - the facts, blocks, letterhead, template and signers reviewers see are frozen in the version snapshot;
 *  - approvals belong to one version; a revision supersedes every open request on the old one.
 */
final class LetterService
{
    private readonly Closure $clock;
    private readonly TemplateValidator $validator;
    private readonly ApprovalFlow $flow;
    private readonly LetterNumberService $numbers;

    public function __construct(private readonly Db $db, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn() => new DateTimeImmutable();
        $this->validator = new TemplateValidator();
        $this->flow = new ApprovalFlow($db);
        $this->numbers = new LetterNumberService($db);
    }

    /**
     * @param array $stages [['name' => 'Ketua', 'signer_ids' => [1]], ...] in approval order
     */
    public function createDraft(int $userId, int $organizationId, int $templateId, string $title,
                                array $fields, array $blocks, array $stages,
                                ?int $letterheadId = null, ?string $sourcePrompt = null): int
    {
        return $this->db->transaction(function (Db $db) use ($userId, $organizationId, $templateId, $title, $fields, $blocks, $stages, $letterheadId, $sourcePrompt) {
            $this->requireManagerOf($organizationId, $userId);
            $template = $this->template($templateId, $organizationId);
            $this->assertValid($template, $fields, $blocks, false);
            $title = trim($title);
            if ($title === '' || mb_strlen($title) > 200) throw new PenaException('Judul surat wajib diisi (maksimal 200 karakter).');
            if ($letterheadId !== null && !$db->one('SELECT id FROM letterheads WHERE id = ? AND organization_id = ? AND is_active = 1', [$letterheadId, $organizationId])) {
                throw new PenaException('Kop surat tidak ditemukan di organisasi ini.');
            }
            $this->assertStages($organizationId, $stages, $this->kind($organizationId) === 'personal');

            $letterId = $db->insert('INSERT INTO letters (organization_id, template_id, letterhead_id, created_by, title, source_prompt)
                                     VALUES (?, ?, ?, ?, ?, ?)',
                                    [$organizationId, $templateId, $letterheadId, $userId, $title, $sourcePrompt]);
            $versionId = $db->insert('INSERT INTO letter_versions (letter_id, version_no, fields, blocks, created_by) VALUES (?, 1, ?, ?, ?)',
                                     [$letterId, self::json($fields), self::json($blocks), $userId]);
            $db->run('UPDATE letters SET current_version_id = ? WHERE id = ?', [$versionId, $letterId]);
            foreach (array_values($stages) as $order => $stage) {
                $stageId = $db->insert('INSERT INTO approval_stages (letter_id, stage_order, name) VALUES (?, ?, ?)',
                                       [$letterId, $order + 1, trim($stage['name'])]);
                foreach ($stage['signer_ids'] as $signerId) {
                    $db->insert('INSERT INTO approval_stage_members (stage_id, signer_id, is_required) VALUES (?, ?, 1)', [$stageId, (int)$signerId]);
                }
            }
            $this->event($letterId, $versionId, $userId, 'draft_created', ['template' => $template['code']]);
            return $letterId;
        });
    }

    /** Edits the working (unsubmitted) version only. */
    public function updateDraft(int $letterId, int $userId, array $fields, array $blocks, ?string $changeSummary = null): void
    {
        $this->db->transaction(function (Db $db) use ($letterId, $userId, $fields, $blocks, $changeSummary) {
            $letter = $this->lockLetter($letterId);
            $this->requireManagerOf((int)$letter['organization_id'], $userId);
            $version = $this->currentVersion($letter);
            if ($version['submitted_at'] !== null) {
                throw new PenaException('Versi ini sudah diajukan dan tidak bisa diubah. Mulai revisi untuk membuat versi baru.');
            }
            $this->assertValid($this->template((int)$letter['template_id'], (int)$letter['organization_id']), $fields, $blocks, false);
            $db->run('UPDATE letter_versions SET fields = ?, blocks = ?, change_summary = COALESCE(?, change_summary) WHERE id = ?',
                     [self::json($fields), self::json($blocks), $changeSummary, $version['id']]);
            $this->event($letterId, (int)$version['id'], $userId, 'draft_updated');
        });
    }

    /**
     * Freezes the working version, assigns the number on first submission, and opens review.
     * @return array{letter_number:?string, version_id:int, links:array<int,string>, approved:bool} links: requestId => raw token
     */
    public function submit(int $letterId, int $userId): array
    {
        return $this->db->transaction(function (Db $db) use ($letterId, $userId) {
            $now = ($this->clock)();
            $letter = $this->lockLetter($letterId);
            $orgId = (int)$letter['organization_id'];
            $this->requireManagerOf($orgId, $userId);
            if (!in_array($letter['status'], ['draft', 'revision_requested'], true)) {
                throw new PenaException('Surat dengan status ini tidak bisa diajukan.');
            }
            $version = $this->currentVersion($letter);
            if ($version['submitted_at'] !== null) {
                throw new PenaException('Versi ini sudah diajukan. Mulai revisi untuk mengajukan perubahan.');
            }
            $template = $this->template((int)$letter['template_id'], $orgId);
            $fields = json_decode($version['fields'], true);
            $blocks = json_decode($version['blocks'], true);
            $this->assertValid($template, $fields, $blocks, true);
            $org = $db->one('SELECT kind, name, short_name FROM organizations WHERE id = ?', [$orgId]);
            $personal = $org['kind'] === 'personal';
            if (!$personal && $letter['letterhead_id'] === null) throw new PenaException('Pilih kop surat sebelum mengajukan.');

            if (!$personal && $letter['letter_number'] === null) {
                $n = $this->numbers->next($orgId, $org['short_name'], $template['code'], $template['number_series'], $template['number_format'], $now);
                $db->run('UPDATE letters SET number_series = ?, number_year = ?, number_seq = ?, letter_number = ?, number_assigned_at = ? WHERE id = ?',
                         [$n['series'], $n['year'], $n['seq'], $n['number'], $now->format('Y-m-d H:i:s'), $letterId]);
                $letter['letter_number'] = $n['number'];
                $this->event($letterId, (int)$version['id'], $userId, 'number_assigned', ['number' => $n['number']]);
            }

            $members = $this->activeMembers($letterId);
            if ($members === [] && !$personal) throw new PenaException('Alur persetujuan belum memiliki pemeriksa.');
            $snapshot = [
                'organization' => $org,
                'letter_number' => $letter['letter_number'],
                'letterhead' => $letter['letterhead_id'] === null ? null
                    : $db->one('SELECT name, address, contact, logo_path FROM letterheads WHERE id = ?', [$letter['letterhead_id']]),
                // A personal letter is signed by its author, after any "Mengetahui" reviewers.
                'author' => $personal ? ['name' => (string)($fields['nama_mahasiswa'] ?? $db->value('SELECT nama_lengkap FROM users WHERE id = ?', [$userId])),
                                         'position' => 'Hormat saya'] : null,
                'template' => ['id' => (int)$template['id'], 'code' => $template['code'], 'revision' => (int)$template['revision'],
                               'name' => $template['name'], 'definition' => json_decode($template['definition'], true)],
                'approval' => array_map(static fn($m) => [
                    'stage_order' => (int)$m['stage_order'], 'stage' => $m['stage_name'],
                    'name' => $m['signer_name'], 'position' => $m['signer_position'],
                ], $members),
            ];
            $hash = hash('sha256', self::json(['fields' => $fields, 'blocks' => $blocks, 'snapshot' => $snapshot]));
            $db->run('UPDATE letter_versions SET snapshot = ?, content_hash = ?, submitted_at = ? WHERE id = ?',
                     [self::json($snapshot), $hash, $now->format('Y-m-d H:i:s'), $version['id']]);

            // Whoever approved the previous submitted version reconfirms in parallel.
            $approvedBefore = [];
            $previous = $db->one('SELECT id FROM letter_versions WHERE letter_id = ? AND version_no < ? AND submitted_at IS NOT NULL
                                  ORDER BY version_no DESC LIMIT 1', [$letterId, $version['version_no']]);
            if ($previous) {
                foreach ($db->all("SELECT stage_member_id FROM approval_requests WHERE version_id = ? AND status = 'approved'", [$previous['id']]) as $row) {
                    $approvedBefore[(int)$row['stage_member_id']] = true;
                }
            }
            foreach ($members as $m) {
                $reconfirm = isset($approvedBefore[(int)$m['member_id']]);
                $this->insertRequest($letterId, (int)$version['id'], $m, $reconfirm ? 'reconfirm' : 'review', $reconfirm ? 'pending' : 'waiting');
            }
            $this->event($letterId, (int)$version['id'], $userId, 'submitted', ['version_no' => (int)$version['version_no'], 'hash' => $hash]);
            if ($members === []) {
                // Personal letter without reviewers: final as soon as its author submits it.
                $db->run("UPDATE letters SET status = 'approved', approved_at = ? WHERE id = ?", [$now->format('Y-m-d H:i:s'), $letterId]);
                $this->event($letterId, (int)$version['id'], $userId, 'approved', ['self_signed' => true]);
                return ['letter_number' => null, 'version_id' => (int)$version['id'], 'links' => [], 'approved' => true];
            }
            $db->run("UPDATE letters SET status = 'in_review' WHERE id = ?", [$letterId]);
            $links = $this->flow->activate((int)$version['id'], $now);
            return ['letter_number' => $letter['letter_number'], 'version_id' => (int)$version['id'], 'links' => $links, 'approved' => false];
        });
    }

    /**
     * Withdraws the version under review (or answers a revision request) by starting a new working version.
     * Every open request on the old version is superseded; approvals already given stay recorded there.
     */
    public function startRevision(int $letterId, int $userId, string $changeSummary): int
    {
        return $this->db->transaction(function (Db $db) use ($letterId, $userId, $changeSummary) {
            $letter = $this->lockLetter($letterId);
            $this->requireManagerOf((int)$letter['organization_id'], $userId);
            if (!in_array($letter['status'], ['in_review', 'revision_requested'], true)) {
                throw new PenaException('Revisi hanya bisa dimulai saat surat sedang direview atau diminta revisi.');
            }
            if (trim($changeSummary) === '') throw new PenaException('Tuliskan ringkasan perubahan untuk para pemeriksa.');
            $old = $this->currentVersion($letter);
            $db->run("UPDATE approval_requests SET status = 'superseded', token_hash = NULL
                      WHERE version_id = ? AND status IN ('waiting', 'pending')", [$old['id']]);
            $versionId = $db->insert('INSERT INTO letter_versions (letter_id, version_no, fields, blocks, change_summary, created_by)
                                      VALUES (?, ?, ?, ?, ?, ?)',
                                     [$letterId, (int)$old['version_no'] + 1, $old['fields'], $old['blocks'], trim($changeSummary), $userId]);
            $db->run("UPDATE letters SET current_version_id = ?, status = 'draft' WHERE id = ?", [$versionId, $letterId]);
            $this->event($letterId, $versionId, $userId, 'revision_started', ['from_version' => (int)$old['version_no']]);
            return $versionId;
        });
    }

    public function cancel(int $letterId, int $userId, string $reason): void
    {
        $this->db->transaction(function (Db $db) use ($letterId, $userId, $reason) {
            $letter = $this->lockLetter($letterId);
            $this->requireManagerOf((int)$letter['organization_id'], $userId);
            if (in_array($letter['status'], ['approved', 'cancelled'], true)) throw new PenaException('Surat ini sudah selesai atau dibatalkan.');
            if (trim($reason) === '') throw new PenaException('Alasan pembatalan wajib diisi.');
            $db->run("UPDATE approval_requests SET status = 'cancelled', token_hash = NULL
                      WHERE letter_id = ? AND status IN ('waiting', 'pending')", [$letterId]);
            // The number (if any) stays on the letter: a cancelled letter still used it.
            $db->run("UPDATE letters SET status = 'cancelled', cancelled_at = ?, cancel_reason = ? WHERE id = ?",
                     [($this->clock)()->format('Y-m-d H:i:s'), trim($reason), $letterId]);
            $this->event($letterId, (int)$letter['current_version_id'], $userId, 'cancelled', ['reason' => trim($reason)]);
        });
    }

    /**
     * Replaces a reviewer. The old person's open request is cancelled and their approval is not transferred.
     * If the letter is under review it moves to revision_requested: the frozen snapshot names the
     * old signer, so the change must go out as a new version.
     */
    public function replaceSigner(int $letterId, int $userId, int $stageMemberId, int $newSignerId): void
    {
        $this->db->transaction(function (Db $db) use ($letterId, $userId, $stageMemberId, $newSignerId) {
            $now = ($this->clock)();
            $letter = $this->lockLetter($letterId);
            $orgId = (int)$letter['organization_id'];
            $this->requireManagerOf($orgId, $userId);
            if (in_array($letter['status'], ['approved', 'cancelled'], true)) throw new PenaException('Surat ini sudah selesai atau dibatalkan.');
            $member = $db->one('SELECT m.id, m.stage_id, m.is_required FROM approval_stage_members m
                                JOIN approval_stages s ON s.id = m.stage_id
                                WHERE m.id = ? AND s.letter_id = ? AND m.removed_at IS NULL', [$stageMemberId, $letterId]);
            if (!$member) throw new PenaException('Pemeriksa tidak ditemukan pada alur surat ini.');
            $this->assertSigner($orgId, $newSignerId);

            $db->run('UPDATE approval_stage_members SET removed_at = ? WHERE id = ?', [$now->format('Y-m-d H:i:s'), $stageMemberId]);
            $db->run("UPDATE approval_requests SET status = 'cancelled', token_hash = NULL
                      WHERE stage_member_id = ? AND status IN ('waiting', 'pending')", [$stageMemberId]);
            $newMemberId = $db->insert('INSERT INTO approval_stage_members (stage_id, signer_id, is_required) VALUES (?, ?, ?)',
                                       [$member['stage_id'], $newSignerId, $member['is_required']]);
            $this->event($letterId, (int)$letter['current_version_id'], $userId, 'signer_replaced',
                         ['old_member' => $stageMemberId, 'new_member' => $newMemberId, 'signer' => $newSignerId]);

            if ($letter['status'] === 'in_review') {
                $db->run("UPDATE letters SET status = 'revision_requested' WHERE id = ?", [$letterId]);
                $db->run("UPDATE approval_requests SET status = 'superseded', token_hash = NULL
                          WHERE version_id = ? AND status IN ('waiting', 'pending')", [$letter['current_version_id']]);
            }
        });
    }

    /** New link for a pending request on the current version (e.g. the old one expired). */
    public function reissueLink(int $letterId, int $userId, int $requestId): string
    {
        return $this->db->transaction(function (Db $db) use ($letterId, $userId, $requestId) {
            $letter = $this->lockLetter($letterId);
            $this->requireManagerOf((int)$letter['organization_id'], $userId);
            $request = $db->one("SELECT id FROM approval_requests WHERE id = ? AND letter_id = ? AND version_id = ? AND status = 'pending'",
                                [$requestId, $letterId, $letter['current_version_id']]);
            if (!$request) throw new PenaException('Tautan hanya bisa dibuat ulang untuk pemeriksa yang sedang menunggu pada versi terbaru.');
            $token = $this->flow->issueToken($requestId, ($this->clock)());
            $this->event($letterId, (int)$letter['current_version_id'], $userId, 'link_reissued', ['request' => $requestId]);
            return $token;
        });
    }

    // ---------------------------------------------------------------- helpers

    private function insertRequest(int $letterId, int $versionId, array $m, string $kind, string $status): void
    {
        $this->db->insert('INSERT INTO approval_requests (letter_id, version_id, stage_id, stage_member_id, signer_id,
                               signer_name, signer_position, kind, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                          [$letterId, $versionId, $m['stage_id'], $m['member_id'], $m['signer_id'],
                           $m['signer_name'], $m['signer_position'], $kind, $status]);
    }

    private function activeMembers(int $letterId): array
    {
        return $this->db->all('SELECT s.id AS stage_id, s.stage_order, s.name AS stage_name, m.id AS member_id,
                                      g.id AS signer_id, g.name AS signer_name, g.position AS signer_position
                                 FROM approval_stages s
                                 JOIN approval_stage_members m ON m.stage_id = s.id AND m.removed_at IS NULL
                                 JOIN signers g ON g.id = m.signer_id
                                WHERE s.letter_id = ? ORDER BY s.stage_order, m.id', [$letterId]);
    }

    private function assertStages(int $orgId, array $stages, bool $optional = false): void
    {
        if ($stages === [] && !$optional) throw new PenaException('Tentukan minimal satu tahap persetujuan.');
        $seen = [];
        foreach ($stages as $stage) {
            if (trim((string)($stage['name'] ?? '')) === '' || empty($stage['signer_ids']) || !is_array($stage['signer_ids'])) {
                throw new PenaException('Setiap tahap persetujuan perlu nama dan minimal satu pemeriksa.');
            }
            foreach ($stage['signer_ids'] as $signerId) {
                if (isset($seen[(int)$signerId])) throw new PenaException('Satu pemeriksa hanya boleh muncul sekali dalam alur.');
                $seen[(int)$signerId] = true;
                $this->assertSigner($orgId, (int)$signerId);
            }
        }
    }

    private function assertSigner(int $orgId, int $signerId): void
    {
        if (!$this->db->one('SELECT id FROM signers WHERE id = ? AND organization_id = ? AND is_active = 1', [$signerId, $orgId])) {
            throw new PenaException('Penanda tangan tidak ditemukan di direktori organisasi ini.');
        }
    }

    private function assertValid(array $template, array $fields, array $blocks, bool $forSubmission): void
    {
        $errors = $this->validator->validate(json_decode($template['definition'], true), $fields, $blocks, $forSubmission);
        if ($errors !== []) throw new PenaException('Isi surat belum sesuai kerangka.', $errors);
    }

    private function template(int $templateId, int $orgId): array
    {
        $template = $this->db->one('SELECT * FROM letter_templates WHERE id = ? AND is_active = 1
                                    AND (organization_id IS NULL OR organization_id = ?)
                                    AND audience = (SELECT kind FROM organizations WHERE id = ?)', [$templateId, $orgId, $orgId]);
        if (!$template) throw new PenaException('Kerangka surat tidak tersedia untuk ruang kerja ini.');
        return $template;
    }

    private function lockLetter(int $letterId): array
    {
        $letter = $this->db->one('SELECT * FROM letters WHERE id = ? FOR UPDATE', [$letterId]);
        if (!$letter) throw new PenaException('Surat tidak ditemukan.');
        return $letter;
    }

    private function currentVersion(array $letter): array
    {
        return $this->db->one('SELECT * FROM letter_versions WHERE id = ?', [$letter['current_version_id']]);
    }

    /** Every member of a workspace is a secretary; there is no admin role. */
    private function requireManagerOf(int $orgId, int $userId): void
    {
        if ($this->db->value('SELECT role FROM organization_members WHERE organization_id = ? AND user_id = ?', [$orgId, $userId]) === null) {
            throw new PenaException('Anda tidak berwenang mengelola surat di ruang kerja ini.');
        }
    }

    private function kind(int $orgId): string
    {
        return (string)$this->db->value('SELECT kind FROM organizations WHERE id = ?', [$orgId]);
    }

    private function event(int $letterId, ?int $versionId, ?int $userId, string $event, array $detail = []): void
    {
        $label = $userId === null ? 'sistem'
            : (string)($this->db->value('SELECT nama_lengkap FROM users WHERE id = ?', [$userId]) ?? 'pengguna');
        $this->db->insert('INSERT INTO letter_events (letter_id, version_id, actor_user_id, actor_label, event, detail) VALUES (?, ?, ?, ?, ?, ?)',
                          [$letterId, $versionId, $userId, $label, $event, $detail === [] ? null : self::json($detail)]);
    }

    /** Stable JSON: same data always gives the same string, so content hashes are comparable. */
    public static function json(mixed $value): string
    {
        return json_encode(self::sortKeys($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value);
        return array_map([self::class, 'sortKeys'], $value);
    }
}
