<?php
declare(strict_types=1);

namespace Pena\Service;

use Closure;
use DateTimeImmutable;
use Pena\Support\Db;

/**
 * Reviewer side, reached through a link without an account.
 * Holding the link is the only proof of identity; decisions are recorded with auth_method = 'link'
 * plus IP and user agent, and the UI must say so instead of presenting it as a verified signature.
 */
final class ApprovalService
{
    private readonly Closure $clock;
    private readonly ApprovalFlow $flow;

    public function __construct(private readonly Db $db, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn() => new DateTimeImmutable();
        $this->flow = new ApprovalFlow($db);
    }

    /** Everything the review page shows. Read-only; validity is checked again on every decision. */
    public function open(string $token): array
    {
        $request = $this->findByToken($token, false);
        $version = $this->db->one('SELECT id, version_no, fields, blocks, snapshot, content_hash, change_summary, submitted_at
                                   FROM letter_versions WHERE id = ?', [$request['version_id']]);
        $letter = $this->db->one('SELECT id, title, letter_number, status FROM letters WHERE id = ?', [$request['letter_id']]);
        $previous = null;
        if ($request['kind'] === 'reconfirm') {
            // Reconfirming reviewers see what changed plus the full new document.
            $previous = $this->db->one('SELECT version_no, fields, blocks FROM letter_versions
                                        WHERE letter_id = ? AND version_no < ? AND submitted_at IS NOT NULL
                                        ORDER BY version_no DESC LIMIT 1', [$letter['id'], $version['version_no']]);
        }
        $this->event((int)$letter['id'], (int)$version['id'], $request, 'review_opened');
        return [
            'request' => ['id' => (int)$request['id'], 'kind' => $request['kind'], 'status' => $request['status'],
                          'signer_name' => $request['signer_name'], 'signer_position' => $request['signer_position'],
                          'expires_at' => $request['token_expires_at']],
            'letter' => $letter,
            'version' => [
                'id' => (int)$version['id'], 'version_no' => (int)$version['version_no'],
                'fields' => json_decode($version['fields'], true), 'blocks' => json_decode($version['blocks'], true),
                'snapshot' => json_decode($version['snapshot'], true), 'content_hash' => $version['content_hash'],
                'change_summary' => $version['change_summary'],
            ],
            'previous' => $previous === null ? null : [
                'version_no' => (int)$previous['version_no'],
                'fields' => json_decode($previous['fields'], true), 'blocks' => json_decode($previous['blocks'], true),
            ],
            'comments' => $this->db->all('SELECT author_name, kind, block_ref, body, created_at FROM letter_comments
                                          WHERE letter_id = ? ORDER BY created_at, id', [$letter['id']]),
            // Everyone's place in this version's schedule, so the reviewer sees where the letter stands.
            'schedule' => $this->db->all('SELECT r.id, s.stage_order, r.signer_name, r.signer_position, r.kind, r.status, r.decided_at, r.created_at
                                            FROM approval_requests r JOIN approval_stages s ON s.id = r.stage_id
                                           WHERE r.version_id = ? AND r.status <> \'superseded\'
                                           ORDER BY s.stage_order, r.id', [$version['id']]),
        ];
    }

    /**
     * @param string $contentHash the hash shown on the page the reviewer read; guards against
     *                            approving a different version than the one on screen
     * Opening the next stage does not create links: the reviewer would receive them, not the secretary.
     * The secretary creates them from the letter page (LetterService::reissueLink).
     * @return array{letter_status:string, opened:int} number of requests that became pending
     */
    public function approve(string $token, string $contentHash, ?string $note, string $ip, string $userAgent): array
    {
        return $this->db->transaction(function (Db $db) use ($token, $contentHash, $note, $ip, $userAgent) {
            $now = ($this->clock)();
            $request = $this->findByToken($token, true);
            $this->assertSameVersion($request, $contentHash);
            $this->decide($request, 'approved', $note, $ip, $userAgent, $now);
            $this->event((int)$request['letter_id'], (int)$request['version_id'], $request, 'approved');

            $pendingBefore = (int)$db->value("SELECT COUNT(*) FROM approval_requests WHERE version_id = ? AND status = 'pending'", [$request['version_id']]);
            $this->flow->activate((int)$request['version_id'], $now, false);
            $opened = (int)$db->value("SELECT COUNT(*) FROM approval_requests WHERE version_id = ? AND status = 'pending'", [$request['version_id']]) - $pendingBefore;
            $status = 'in_review';
            if ($this->flow->isComplete((int)$request['version_id'])) {
                // Optional reviewers who had not answered are no longer needed.
                $db->run("UPDATE approval_requests SET status = 'cancelled', token_hash = NULL
                          WHERE version_id = ? AND status IN ('waiting', 'pending')", [$request['version_id']]);
                $db->run("UPDATE letters SET status = 'approved', approved_at = ? WHERE id = ?",
                         [$now->format('Y-m-d H:i:s'), $request['letter_id']]);
                $status = 'approved';
                $opened = 0;
                $this->event((int)$request['letter_id'], (int)$request['version_id'], null, 'letter_approved');
            }
            return ['letter_status' => $status, 'opened' => max(0, $opened)];
        });
    }

    /** Stops the process: the secretary must start a revision. */
    public function requestRevision(string $token, string $contentHash, string $note, ?string $blockRef, string $ip, string $userAgent): void
    {
        $this->db->transaction(function (Db $db) use ($token, $contentHash, $note, $blockRef, $ip, $userAgent) {
            if (trim($note) === '') throw new PenaException('Tuliskan apa yang perlu direvisi.');
            $now = ($this->clock)();
            $request = $this->findByToken($token, true);
            $this->assertSameVersion($request, $contentHash);
            $this->decide($request, 'revision_requested', $note, $ip, $userAgent, $now);
            $db->insert("INSERT INTO letter_comments (letter_id, version_id, request_id, author_name, kind, block_ref, body)
                         VALUES (?, ?, ?, ?, 'revision_request', ?, ?)",
                        [$request['letter_id'], $request['version_id'], $request['id'], $request['signer_name'], $blockRef, trim($note)]);
            $db->run("UPDATE letters SET status = 'revision_requested' WHERE id = ?", [$request['letter_id']]);
            $this->event((int)$request['letter_id'], (int)$request['version_id'], $request, 'revision_requested', ['block' => $blockRef]);
        });
    }

    /** A question or remark that does not stop the process. */
    public function comment(string $token, string $body, ?string $blockRef): void
    {
        $this->db->transaction(function (Db $db) use ($token, $body, $blockRef) {
            if (trim($body) === '') throw new PenaException('Komentar kosong.');
            $request = $this->findByToken($token, true);
            $db->insert("INSERT INTO letter_comments (letter_id, version_id, request_id, author_name, kind, block_ref, body)
                         VALUES (?, ?, ?, ?, 'comment', ?, ?)",
                        [$request['letter_id'], $request['version_id'], $request['id'], $request['signer_name'], $blockRef, trim($body)]);
        });
    }

    private function decide(array $request, string $status, ?string $note, string $ip, string $userAgent, DateTimeImmutable $now): void
    {
        $this->db->run("UPDATE approval_requests SET status = ?, decided_at = ?, decision_note = ?, auth_method = 'link',
                               decided_ip = ?, decided_user_agent = ?, token_hash = NULL
                         WHERE id = ? AND status = 'pending'",
                       [$status, $now->format('Y-m-d H:i:s'), $note === null ? null : trim($note), substr($ip, 0, 45),
                        substr($userAgent, 0, 255), $request['id']]);
    }

    /**
     * Resolves a link. With $forDecision the request and its letter are locked and must still be actionable:
     * pending, unexpired, on the letter's current version, and the letter still under review.
     */
    private function findByToken(string $token, bool $forDecision): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) throw new PenaException('Tautan tidak valid.');
        $lock = $forDecision ? ' FOR UPDATE' : '';
        $request = $this->db->one('SELECT * FROM approval_requests WHERE token_hash = ?' . $lock, [hash('sha256', $token)]);
        if (!$request) {
            throw new PenaException('Tautan tidak valid atau sudah tidak berlaku. Jika surat direvisi, gunakan tautan terbaru.');
        }
        if ($request['token_expires_at'] !== null && new DateTimeImmutable($request['token_expires_at']) < ($this->clock)()) {
            throw new PenaException('Tautan sudah kedaluwarsa. Minta tautan baru kepada pembuat surat.');
        }
        $letter = $this->db->one('SELECT status, current_version_id FROM letters WHERE id = ?' . $lock, [$request['letter_id']]);
        if ((int)$letter['current_version_id'] !== (int)$request['version_id']) {
            throw new PenaException('Versi ini sudah digantikan revisi. Buka tautan versi terbaru.');
        }
        if ($forDecision && ($request['status'] !== 'pending' || $letter['status'] !== 'in_review')) {
            throw new PenaException('Surat ini sedang tidak menunggu keputusan Anda.');
        }
        return $request;
    }

    private function assertSameVersion(array $request, string $contentHash): void
    {
        $current = $this->db->value('SELECT content_hash FROM letter_versions WHERE id = ?', [$request['version_id']]);
        if (!hash_equals((string)$current, $contentHash)) {
            throw new PenaException('Dokumen berubah sejak halaman dibuka. Muat ulang dan baca versi terbaru.');
        }
    }

    private function event(int $letterId, int $versionId, ?array $request, string $event, array $detail = []): void
    {
        $label = $request === null ? 'sistem' : $request['signer_name'] . ' (via tautan)';
        $this->db->insert('INSERT INTO letter_events (letter_id, version_id, actor_label, event, detail) VALUES (?, ?, ?, ?, ?)',
                          [$letterId, $versionId, $label, $event, $detail === [] ? null : LetterService::json($detail)]);
    }
}
