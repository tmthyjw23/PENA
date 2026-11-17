<?php
declare(strict_types=1);

namespace Pena\Service;

/**
 * Checks a version's fields and blocks against its template definition.
 *
 * Version format:
 *   fields: {"nama_kegiatan": "FilkomDay", "barang": [{"nama": "...", "jumlah": "2", ...}], ...}
 *   blocks: [{"id": "maksud", "text": "... {{nama_kegiatan}} ..."}, {"id": "daftar_barang"}, ...]
 * Paragraph text may only reference facts through {{field}} placeholders declared by the template.
 * This limits free-written facts; it cannot prove a placeholder is used in the right sentence.
 */
final class TemplateValidator
{
    private const PLACEHOLDER = '/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/';

    /** @return array<string, string> errors keyed by "field.<key>" / "block.<id>" (empty = valid) */
    public function validate(array $definition, array $fields, array $blocks, bool $forSubmission): array
    {
        $errors = [];
        $declared = [];
        foreach ($definition['fields'] ?? [] as $field) {
            $declared[$field['key']] = $field;
        }

        foreach ($fields as $key => $value) {
            if (!isset($declared[$key])) {
                $errors["field.$key"] = 'Isian tidak dikenal oleh kerangka ini.';
                continue;
            }
            $error = $this->checkFieldValue($declared[$key], $value);
            if ($error !== null) $errors["field.$key"] = $error;
        }
        if ($forSubmission) {
            foreach ($declared as $key => $field) {
                if (($field['required'] ?? false) && $this->isEmpty($fields[$key] ?? null)) {
                    $errors["field.$key"] ??= ($field['label'] ?? $key) . ' wajib diisi.';
                }
            }
        }

        $templateBlocks = [];
        foreach ($definition['blocks'] ?? [] as $block) {
            $templateBlocks[$block['id']] = $block;
        }
        $seen = [];
        foreach ($blocks as $index => $block) {
            $id = is_array($block) ? ($block['id'] ?? null) : null;
            if (!is_string($id) || !isset($templateBlocks[$id])) {
                $errors["block.$index"] = 'Blok tidak dikenal oleh kerangka ini.';
                continue;
            }
            if (isset($seen[$id])) {
                $errors["block.$id"] = 'Blok muncul lebih dari sekali.';
                continue;
            }
            $seen[$id] = true;
            $spec = $templateBlocks[$id];
            if ($spec['type'] !== 'paragraph') {
                // Tables and signatures come from fields and the approval flow, never free text.
                if (array_key_exists('text', $block)) $errors["block.$id"] = 'Blok ini diisi otomatis dan tidak menerima teks.';
                continue;
            }
            $text = $spec['source'] === 'fixed' ? ($spec['text'] ?? '') : ($block['text'] ?? '');
            if ($spec['source'] === 'fixed' && isset($block['text']) && $block['text'] !== $spec['text']) {
                $errors["block.$id"] = 'Teks baku kerangka tidak boleh diubah.';
                continue;
            }
            if (!is_string($text)) {
                $errors["block.$id"] = 'Isi blok harus berupa teks.';
                continue;
            }
            if ($forSubmission && trim($text) === '') {
                $errors["block.$id"] = 'Isi blok masih kosong.';
                continue;
            }
            preg_match_all(self::PLACEHOLDER, $text, $matches);
            foreach (array_unique($matches[1]) as $key) {
                if (!isset($declared[$key])) {
                    $errors["block.$id"] = "Penanda {{{$key}}} tidak ada di daftar isian.";
                } elseif ($declared[$key]['type'] === 'table') {
                    $errors["block.$id"] = "Isian tabel {{{$key}}} tidak bisa dipakai di dalam paragraf.";
                } elseif ($forSubmission && $this->isEmpty($fields[$key] ?? null)) {
                    $errors["block.$id"] = "Penanda {{{$key}}} dipakai tetapi isiannya kosong.";
                }
            }
        }
        if ($forSubmission) {
            foreach ($templateBlocks as $id => $spec) {
                if (($spec['required'] ?? false) && !isset($seen[$id])) {
                    $errors["block.$id"] = 'Blok wajib belum ada.';
                }
            }
        }
        return $errors;
    }

    /** Replaces {{field}} with formatted values. Used by preview/export. */
    public function render(string $text, array $definition, array $fields): string
    {
        $types = array_column($definition['fields'] ?? [], 'type', 'key');
        return (string)preg_replace_callback(self::PLACEHOLDER, function (array $m) use ($types, $fields): string {
            $value = $fields[$m[1]] ?? '';
            return match ($types[$m[1]] ?? 'text') {
                'date' => self::formatDate((string)$value),
                'money' => $value === '' ? '' : 'Rp' . number_format((float)$value, 0, ',', '.'),
                default => (string)$value,
            };
        }, $text);
    }

    public static function formatDate(string $iso): string
    {
        $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
            'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
        return $date ? $date->format('j') . ' ' . $months[(int)$date->format('n')] . ' ' . $date->format('Y') : $iso;
    }

    private function checkFieldValue(array $field, mixed $value): ?string
    {
        if ($this->isEmpty($value)) return null;
        switch ($field['type']) {
            case 'date':
                $date = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
                return ($date && $date->format('Y-m-d') === $value) ? null : 'Tanggal harus berformat YYYY-MM-DD.';
            case 'money':
                return (is_int($value) || (is_string($value) && ctype_digit($value))) ? null : 'Nominal harus berupa angka rupiah tanpa titik.';
            case 'table':
                if (!is_array($value) || !array_is_list($value)) return 'Isian tabel harus berupa daftar baris.';
                $columns = array_column($field['columns'] ?? [], 'key');
                foreach ($value as $row) {
                    if (!is_array($row) || array_diff(array_keys($row), $columns) !== []) return 'Kolom tabel tidak sesuai kerangka.';
                    foreach ($row as $cell) if (!is_string($cell) && !is_int($cell)) return 'Isi sel tabel harus teks atau angka.';
                }
                return null;
            default:
                return is_string($value) && mb_strlen($value) <= 500 ? null : 'Isian harus teks (maksimal 500 karakter).';
        }
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
