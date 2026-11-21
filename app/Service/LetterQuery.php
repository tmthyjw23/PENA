<?php
declare(strict_types=1);

namespace Pena\Service;

use Pena\Support\Db;

/** Read-only queries for the letter pages. Writes go through LetterService / ApprovalService. */
final class LetterQuery
{
    public function __construct(private readonly Db $db, private readonly LetterRenderer $renderer = new LetterRenderer()) {}

    public function letters(int $orgId): array
    {
        return $this->db->all('SELECT l.id, l.title, l.status, l.letter_number, l.updated_at, t.name AS template_name,
                                      CASE WHEN t.is_generated=1 THEN \'AI\' ELSE t.code END AS template_code, v.version_no
                                 FROM letters l
                                 JOIN letter_templates t ON t.id = l.template_id
                                 LEFT JOIN letter_versions v ON v.id = l.current_version_id
                                WHERE l.organization_id = ? ORDER BY l.updated_at DESC, l.id DESC', [$orgId]);
    }

    /**
     * Letters under review where a pending reviewer has no usable link (never issued, or expired).
     * @return array<int, array{stage:int, names:string[]}> keyed by letter id
     */
    public function lettersNeedingLink(int $orgId): array
    {
        $out = [];
        foreach ($this->db->all("SELECT l.id, s.stage_order, r.signer_name FROM letters l
                                   JOIN approval_requests r ON r.version_id = l.current_version_id
                                   JOIN approval_stages s ON s.id = r.stage_id
                                  WHERE l.organization_id = ? AND l.status = 'in_review' AND r.status = 'pending'
                                    AND (r.token_hash IS NULL OR r.token_expires_at < NOW())
                                  ORDER BY s.stage_order, r.id", [$orgId]) as $row) {
            $out[(int)$row['id']]['stage'] ??= (int)$row['stage_order'];
            $out[(int)$row['id']]['names'][] = $row['signer_name'];
        }
        return $out;
    }

    /** Built-in and workspace templates meant for this kind of workspace (personal or organization). */
    public function templates(int $orgId): array
    {
        return array_map(static function (array $t): array {
            $t['definition'] = json_decode($t['definition'], true);
            return $t;
        }, $this->db->all('SELECT id, code, name, description, definition FROM letter_templates
                           WHERE is_active = 1 AND is_generated = 0 AND (organization_id IS NULL OR organization_id = ?)
                             AND audience = (SELECT kind FROM organizations WHERE id = ?) ORDER BY name', [$orgId, $orgId]));
    }

    /** Includes private AI templates when opening their saved letter, without adding them to the catalogue. */
    public function template(int $orgId,int $templateId): ?array {
        $row=$this->db->one('SELECT id,code,name,description,definition,is_generated FROM letter_templates WHERE id=? AND is_active=1 AND (organization_id IS NULL OR organization_id=?) AND audience=(SELECT kind FROM organizations WHERE id=?)',[$templateId,$orgId,$orgId]);
        if($row)$row['definition']=json_decode($row['definition'],true);
        return $row;
    }

    public function signers(int $orgId): array
    {
        return $this->db->all('SELECT id, name, position FROM signers WHERE organization_id = ? AND is_active = 1 ORDER BY id', [$orgId]);
    }

    public function letterheads(int $orgId): array
    {
        // Default kop first: forms pre-select it, and the AI result page previews it.
        return $this->db->all('SELECT id, kind, name, subtitle, address, contact, is_default FROM letterheads
                                WHERE organization_id = ? AND is_active = 1 ORDER BY is_default DESC, id', [$orgId]);
    }

    /** Full detail of a letter in $orgId, or null when it belongs elsewhere. */
    public function detail(int $letterId, int $orgId): ?array
    {
        $letter = $this->db->one('SELECT l.*, t.name AS template_name, CASE WHEN t.is_generated=1 THEN \'AI\' ELSE t.code END AS template_code, t.definition AS template_definition,
                                         o.kind AS workspace_kind, u.nama_lengkap AS author_name
                                    FROM letters l JOIN letter_templates t ON t.id = l.template_id
                                    JOIN organizations o ON o.id = l.organization_id
                                    JOIN users u ON u.id = l.created_by
                                   WHERE l.id = ? AND l.organization_id = ?', [$letterId, $orgId]);
        if (!$letter) return null;
        $version = $this->db->one('SELECT * FROM letter_versions WHERE id = ?', [$letter['current_version_id']]);
        $version['fields'] = json_decode($version['fields'], true);
        $version['blocks'] = json_decode($version['blocks'], true);
        $version['snapshot'] = $version['snapshot'] ? json_decode($version['snapshot'], true) : null;

        $stages = [];
        foreach ($this->db->all('SELECT s.id AS stage_id, s.stage_order, s.name AS stage_name, m.id AS member_id,
                                        g.name AS signer_name, g.position AS signer_position
                                   FROM approval_stages s
                                   JOIN approval_stage_members m ON m.stage_id = s.id AND m.removed_at IS NULL
                                   JOIN signers g ON g.id = m.signer_id
                                  WHERE s.letter_id = ? ORDER BY s.stage_order, m.id', [$letterId]) as $row) {
            $stages[(int)$row['stage_id']] ??= ['order' => (int)$row['stage_order'], 'name' => $row['stage_name'], 'members' => []];
            $row['request'] = $this->db->one('SELECT id, kind, status, decided_at, decision_note, auth_method, token_expires_at,
                                                     token_hash IS NOT NULL AS has_link
                                                FROM approval_requests WHERE version_id = ? AND stage_member_id = ?
                                               ORDER BY id DESC LIMIT 1', [$version['id'], $row['member_id']]);
            $stages[(int)$row['stage_id']]['members'][] = $row;
        }

        return [
            'letter' => $letter,
            'version' => $version,
            'stages' => array_values($stages),
            'preview' => (new SignatureService($this->db))->decorate((int)$version['id'],$this->preview($letter, $version),$version['snapshot']['author']??null),
            'comments' => $this->db->all('SELECT c.*, v.version_no FROM letter_comments c JOIN letter_versions v ON v.id = c.version_id
                                          WHERE c.letter_id = ? ORDER BY c.created_at DESC, c.id DESC', [$letterId]),
            'events' => $this->db->all('SELECT e.*, v.version_no FROM letter_events e LEFT JOIN letter_versions v ON v.id = e.version_id
                                        WHERE e.letter_id = ? ORDER BY e.id DESC LIMIT 40', [$letterId]),
            'versions' => $this->db->all('SELECT version_no, change_summary, submitted_at, created_at FROM letter_versions
                                          WHERE letter_id = ? ORDER BY version_no DESC', [$letterId]),
        ];
    }

    /**
     * A submitted version renders from its frozen snapshot; a working draft renders from live data,
     * which is what it would freeze if submitted now.
     */
    public function preview(array $letter, array $version): array
    {
        $snapshot = $version['snapshot'];
        if ($snapshot !== null) {
            $personal = ($snapshot['organization']['kind'] ?? 'organization') === 'personal';
            return $this->renderer->render($snapshot['template']['definition'], $version['fields'], $version['blocks'],
                (new LetterheadService($this->db))->decorate($snapshot['letterhead'] ?? []), $snapshot['letter_number'], $snapshot['title']??$version['title']??$letter['title'],
                array_merge($snapshot['approval'] ?? [], !empty($snapshot['author']) ? [$snapshot['author']] : []),
                $version['submitted_at'], !$personal);
        }
        $personal = ($letter['workspace_kind'] ?? 'organization') === 'personal';
        $signers = $this->db->all('SELECT g.name, g.position FROM approval_stages s
                                     JOIN approval_stage_members m ON m.stage_id = s.id AND m.removed_at IS NULL
                                     JOIN signers g ON g.id = m.signer_id WHERE s.letter_id = ? ORDER BY s.stage_order, m.id', [$letter['id']]);
        $kop = new LetterheadService($this->db);
        $letterhead = $letter['letterhead_id'] ? $kop->decorate($kop->snapshot($kop->latest((int)$letter['letterhead_id'])) ?? []) : [];
        // A personal letter is signed by its author below any "Mengetahui" reviewers.
        if ($personal) $signers[] = ['name' => (string)($version['fields']['nama_pengirim'] ?? $version['fields']['nama_mahasiswa'] ?? $letter['author_name'] ?? ''), 'position' => (json_decode($letter['template_definition'],true)['presentation']['signature_label']??'Hormat saya')];
        return $this->renderer->render(json_decode($letter['template_definition'], true), $version['fields'], $version['blocks'],
            $letterhead ?? [], $letter['letter_number'], $letter['title'], $signers, null, !$personal);
    }
}
