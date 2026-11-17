<?php
declare(strict_types=1);

namespace Pena\Service;

use DateTimeImmutable;
use Pena\Support\Db;

/**
 * Assigns letter numbers. Rules:
 *  - one counter per (organization, series, year), reset each calendar year;
 *  - a number is taken when a letter is first submitted and kept across revisions;
 *  - cancelled letters keep their number (it stays "used").
 * Must run inside the caller's transaction: the counter row is locked FOR UPDATE.
 */
final class LetterNumberService
{
    private const ROMAN = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    public function __construct(private readonly Db $db) {}

    /** @return array{series:string, year:int, seq:int, number:string} */
    public function next(int $organizationId, string $orgShortName, string $templateCode,
                         string $series, string $format, DateTimeImmutable $at): array
    {
        return $this->db->transaction(function (Db $db) use ($organizationId, $orgShortName, $templateCode, $series, $format, $at) {
            $year = (int)$at->format('Y');
            $db->run('INSERT INTO number_counters (organization_id, series, year, last_value) VALUES (?, ?, ?, 0)
                      ON DUPLICATE KEY UPDATE last_value = last_value', [$organizationId, $series, $year]);
            $last = (int)$db->value('SELECT last_value FROM number_counters
                                     WHERE organization_id = ? AND series = ? AND year = ? FOR UPDATE',
                                    [$organizationId, $series, $year]);
            $seq = $last + 1;
            $db->run('UPDATE number_counters SET last_value = ? WHERE organization_id = ? AND series = ? AND year = ?',
                     [$seq, $organizationId, $series, $year]);
            return ['series' => $series, 'year' => $year, 'seq' => $seq,
                    'number' => self::format($format, $seq, $orgShortName, $templateCode, $at)];
        });
    }

    public static function format(string $format, int $seq, string $org, string $code, DateTimeImmutable $at): string
    {
        return strtr($format, [
            '{seq}' => str_pad((string)$seq, 3, '0', STR_PAD_LEFT),
            '{org}' => $org,
            '{code}' => $code,
            '{month_roman}' => self::ROMAN[(int)$at->format('n')],
            '{year}' => $at->format('Y'),
        ]);
    }
}
