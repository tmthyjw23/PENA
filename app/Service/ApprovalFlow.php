<?php
declare(strict_types=1);

namespace Pena\Service;

use DateTimeImmutable;
use Pena\Support\Db;

/**
 * Stage engine shared by LetterService and ApprovalService.
 *
 * Rules (decided with the user):
 *  - stages run in order; every required reviewer of a stage must approve before the next stage opens;
 *  - after a revision, people who approved the previous version get a "reconfirm" request
 *    that is open immediately (in parallel), not re-queued behind earlier stages;
 *  - an approval counts only for the version it was given on.
 * All methods expect to run inside the caller's transaction.
 */
final class ApprovalFlow
{
    public const LINK_DAYS = 7;
    private const OPEN = ['waiting', 'pending', 'approved', 'revision_requested'];

    public function __construct(private readonly Db $db) {}

    /**
     * Opens every stage whose predecessors are complete. With $issueTokens, pending requests
     * without a link get one now; only do that when the secretary is there to receive the raw
     * tokens (only their hashes are stored, so a token nobody sees is lost).
     * @return array<int, string> requestId => raw token (shown once)
     */
    public function activate(int $versionId, DateTimeImmutable $now, bool $issueTokens = true): array
    {
        $issued = [];
        $previousDone = true;
        foreach ($this->stages($versionId) as $stage) {
            if ($previousDone) {
                foreach ($stage['requests'] as $request) {
                    if ($request['status'] === 'waiting') {
                        $this->db->run("UPDATE approval_requests SET status = 'pending' WHERE id = ?", [$request['id']]);
                        $request['status'] = 'pending';
                    }
                }
            }
            $previousDone = $previousDone && $stage['complete'];
        }
        if (!$issueTokens) return [];
        // Reconfirm requests start pending without a token; any pending request lacking one gets it here.
        $needToken = $this->db->all("SELECT id FROM approval_requests
                                     WHERE version_id = ? AND status = 'pending' AND token_hash IS NULL", [$versionId]);
        foreach ($needToken as $row) {
            $issued[(int)$row['id']] = $this->issueToken((int)$row['id'], $now);
        }
        return $issued;
    }

    public function isComplete(int $versionId): bool
    {
        foreach ($this->stages($versionId) as $stage) {
            if (!$stage['complete']) return false;
        }
        return true;
    }

    public function issueToken(int $requestId, DateTimeImmutable $now): string
    {
        $raw = bin2hex(random_bytes(32));
        $this->db->run('UPDATE approval_requests SET token_hash = ?, token_expires_at = ? WHERE id = ?', [
            hash('sha256', $raw),
            $now->modify('+' . self::LINK_DAYS . ' days')->format('Y-m-d H:i:s'),
            $requestId,
        ]);
        return $raw;
    }

    /** Stages of the letter that owns $versionId, with this version's live requests. */
    private function stages(int $versionId): array
    {
        $rows = $this->db->all(
            "SELECT s.id AS stage_id, s.stage_order, r.id, r.status, m.is_required
               FROM approval_stages s
               JOIN letter_versions v ON v.letter_id = s.letter_id AND v.id = ?
               LEFT JOIN approval_requests r ON r.stage_id = s.id AND r.version_id = v.id
                    AND r.status IN ('" . implode("','", self::OPEN) . "')
               LEFT JOIN approval_stage_members m ON m.id = r.stage_member_id
              ORDER BY s.stage_order, r.id", [$versionId]);
        $stages = [];
        foreach ($rows as $row) {
            $id = (int)$row['stage_id'];
            $stages[$id] ??= ['requests' => [], 'complete' => true];
            if ($row['id'] === null) continue;
            $stages[$id]['requests'][] = ['id' => (int)$row['id'], 'status' => $row['status']];
            if ((int)$row['is_required'] === 1 && $row['status'] !== 'approved') {
                $stages[$id]['complete'] = false;
            }
        }
        return array_values($stages);
    }
}
