<?php
declare(strict_types=1);

namespace Pena\Service;

/**
 * Turns one version (fields + blocks + template) into the document the reviewer reads.
 * Output blocks use the same shape as WordDocumentService (paragraph / table / signatures),
 * so the HTML preview and the Word/PDF export come from the same data.
 */
final class LetterRenderer
{
    private const MONTHS = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
        'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public function __construct(private readonly TemplateValidator $validator = new TemplateValidator()) {}

    /**
     * @param array  $signers [['name' => ..., 'position' => ...], ...] in approval order
     * @param bool   $numbered false for personal letters, which carry no letter number
     * @return array{letterhead:array, metadata:array<string,string>, recipient:string[], date:string, blocks:array}
     */
    public function render(array $definition, array $fields, array $blocks, array $letterhead,
                           ?string $number, string $subject, array $signers, ?string $dateIso, bool $numbered = true): array
    {
        $specs = [];
        foreach ($definition['blocks'] ?? [] as $spec) $specs[$spec['id']] = $spec;
        $fieldSpecs = [];
        foreach ($definition['fields'] ?? [] as $spec) $fieldSpecs[$spec['key']] = $spec;

        $out = [];
        foreach ($blocks as $block) {
            $spec = $specs[$block['id'] ?? ''] ?? null;
            if ($spec === null) continue;
            switch ($spec['type']) {
                case 'paragraph':
                    $text = $spec['source'] === 'fixed' ? ($spec['text'] ?? '') : (string)($block['text'] ?? '');
                    $text = trim($this->validator->render($text, $definition, $fields));
                    if ($text !== '') $out[] = ['type' => 'paragraph', 'text' => $text, 'id' => $spec['id']];
                    break;
                case 'table':
                    $field = $fieldSpecs[$spec['field']] ?? null;
                    $rows = $fields[$spec['field']] ?? [];
                    if ($field === null || !is_array($rows) || $rows === []) break;
                    $columns = $field['columns'] ?? [];
                    $table = [array_merge(['No'], array_column($columns, 'label'))];
                    foreach (array_values($rows) as $i => $row) {
                        $line = [(string)($i + 1)];
                        foreach ($columns as $column) $line[] = (string)($row[$column['key']] ?? '');
                        $table[] = $line;
                    }
                    $out[] = ['type' => 'table', 'rows' => $table, 'id' => $spec['id']];
                    break;
                case 'signatures':
                    if ($signers !== []) {
                        $out[] = ['type' => 'signatures', 'id' => $spec['id'], 'signers' => array_map(
                            static fn($s) => ['role' => $s['position'], 'name' => $s['name']], $signers)];
                    }
                    break;
            }
        }

        $recipient = array_values(array_filter([
            isset($fields['penerima_jabatan']) ? 'Yth. ' . $fields['penerima_jabatan'] : null,
            $fields['penerima_instansi'] ?? null,
        ]));
        return [
            'letterhead' => $letterhead,
            'kop' => $numbered, // personal letters have neither a letterhead nor a number
            'metadata' => $numbered ? ['Nomor' => $number ?? '(diberikan saat diajukan)', 'Perihal' => $subject] : ['Perihal' => $subject],
            'recipient' => $recipient,
            'date' => $dateIso ? self::longDate($dateIso) : '(tanggal saat diajukan)',
            'blocks' => $out,
        ];
    }

    public static function longDate(string $iso): string
    {
        $t = strtotime($iso);
        return $t ? date('j', $t) . ' ' . self::MONTHS[(int)date('n', $t)] . ' ' . date('Y', $t) : $iso;
    }
}
