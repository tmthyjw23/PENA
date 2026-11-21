<?php
declare(strict_types=1);

namespace Pena\Service;

use InvalidArgumentException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

final class WordDocumentService
{
    /**
     * Controlled blocks: paragraph {text}, table {rows}, signatures {signers}.
     * The caller supplies validated data from one document version.
     */
    public function save(string $path, array $letterhead, array $metadata, array $blocks): void
    {
        Settings::setOutputEscapingEnabled(true);
        $document = new PhpWord();
        $document->setDefaultFontName('Times New Roman');
        $document->setDefaultFontSize(12);
        // PHPWord 1.4 reads numStyle as an array key; use an explicit empty name on PHP 8.5.
        // Zero spacing keeps table rows one line high; Word's default adds space after each paragraph.
        $document->addParagraphStyle('PenaBase', ['numStyle' => '', 'spaceBefore' => 0, 'spaceAfter' => 0]);
        $document->addParagraphStyle('PenaCenter', ['numStyle' => '', 'alignment' => 'center', 'spaceBefore' => 0, 'spaceAfter' => 0]);
        $sign = ['numStyle' => '', 'alignment' => 'center', 'spaceBefore' => 0, 'spaceAfter' => 0];
        $document->addParagraphStyle('PenaSign', $sign + ['keepNext' => true, 'keepLines' => true]);
        $document->addParagraphStyle('PenaSignRole', ['spaceBefore' => 360] + $sign + ['keepNext' => true, 'keepLines' => true]);
        $document->addParagraphStyle('PenaSignLast', $sign);
        $document->addParagraphStyle('PenaBody',['numStyle' => '', 'spaceAfter' => 120, 'alignment' => 'both']);
        $section = $document->addSection([
            'paperSize' => 'A4', 'marginTop' => 1700, 'marginBottom' => 1440,
            'marginLeft' => 1440, 'marginRight' => 1440, 'headerHeight' => 500,
        ]);
        $kopImage = self::dataUriBytes($letterhead['image_src'] ?? null);
        $logo = self::dataUriBytes($letterhead['logo_src'] ?? null);
        $header = null;
        if (($letterhead['kind'] ?? 'text') === 'image' && $kopImage !== null) {
            // Full kop image across the text width (A4 minus 2.54 cm margins ≈ 451 pt).
            [$w, $h] = getimagesizefromstring($kopImage) ?: [1, 1];
            $header = $section->addHeader();
            $header->addImage($kopImage, ['width' => 451, 'height' => (int)round(451 * $h / max(1, $w)), 'alignment' => 'center']);
        } elseif (!empty($letterhead['name'])) {
            $header = $section->addHeader();
            $lines = [[$letterhead['name'], ['bold' => true, 'size' => 14]]];
            foreach (array_filter(explode("\n", (string)($letterhead['subtitle'] ?? ''))) as $line) $lines[] = [$line, ['bold' => true, 'size' => 12]];
            foreach (['address', 'contact'] as $field) if (!empty($letterhead[$field])) $lines[] = [$letterhead[$field], ['size' => 10]];
            if ($logo !== null) {
                // Logo left, text centred in the remaining width; an empty right cell keeps the text visually centred.
                $table = $header->addTable(['alignment' => 'center']);
                $table->addRow();
                [$w, $h] = getimagesizefromstring($logo) ?: [1, 1];
                $size = 60; $scale = $size / max($w, $h, 1);
                $table->addCell(1400, ['valign' => 'center'])->addImage($logo, ['width' => (int)round($w * $scale), 'height' => (int)round($h * $scale), 'alignment' => 'center']);
                $cell = $table->addCell(6200, ['valign' => 'center']);
                foreach ($lines as [$text, $font]) $cell->addText($this->text($text), $font, 'PenaCenter');
                $table->addCell(1400);
            } else {
                foreach ($lines as [$text, $font]) $header->addText($this->text($text), $font, 'PenaCenter');
            }
        }
        // Double rule under the kop (as on the preview) and room before the letter starts.
        if ($header !== null) {
            $header->addText('', ['size' => 2], ['numStyle' => '', 'spaceBefore' => 0, 'spaceAfter' => 240,
                'borderBottomSize' => 18, 'borderBottomStyle' => 'double', 'borderBottomColor' => '000000']);
        }
        if ($metadata !== []) {
            // No border options: borderSize 0 still renders a hairline grid in Word.
            $table = $section->addTable();
            foreach ($metadata as $label => $value) {
                $table->addRow();
                $table->addCell(1500)->addText($this->text($label), [], 'PenaBase');
                $table->addCell(100)->addText(':', [], 'PenaBase');
                $table->addCell(7000)->addText($this->text($value), [], 'PenaBase');
            }
        }
        foreach ($blocks as $block) {
            switch ($block['type'] ?? '') {
                case 'paragraph':
                    $section->addText($this->text($block['text'] ?? null), [], 'PenaBody');
                    break;
                case 'table':
                    $rows = $block['rows'] ?? null;
                    if (!is_array($rows) || $rows === []) throw new InvalidArgumentException('Tabel harus memiliki baris.');
                    $table = $section->addTable(['borderSize' => 4, 'borderColor' => '000000', 'cellMargin' => 80]);
                    if (!isset($rows[0]) || !is_array($rows[0])) throw new InvalidArgumentException('Baris tabel harus berupa daftar sel.');
                    $columns = count($rows[0]);
                    if ($columns === 0) throw new InvalidArgumentException('Tabel harus memiliki kolom.');
                    foreach ($rows as $index => $row) {
                        if (!is_array($row) || count($row) !== $columns) throw new InvalidArgumentException('Jumlah kolom tabel tidak konsisten.');
                        $table->addRow(null, ['tblHeader' => $index === 0, 'cantSplit' => true]);
                        foreach ($row as $cell) $table->addCell((int)(9000 / $columns))->addText($this->text($cell), [], 'PenaBase');
                    }
                    break;
                case 'signatures':
                    $signers = $block['signers'] ?? [];
                    if (!is_array($signers) || $signers === []) throw new InvalidArgumentException('Daftar penanda tangan kosong.');
                    // Two columns avoid unreadable names when a letter has many signers.
                    // One table whose paragraphs all "keep with next" stays on a single page,
                    // so a signer is never separated from the others.
                    $groups = array_chunk($signers, 2);
                    $table = $section->addTable();
                    foreach ($groups as $index => $group) {
                        $isLast = $index === count($groups) - 1;
                        $keep = $isLast ? 'PenaSignLast' : 'PenaSign';
                        $table->addRow(null, ['cantSplit' => true]);
                        // Every row keeps the same two-column grid; mixing cell counts
                        // makes Word shrink the columns. The odd signer spans both and centres.
                        $span = count($group) === 1 ? ['gridSpan' => 2] : [];
                        foreach ($group as $signer) {
                            $cell = $table->addCell($span ? 9000 : 4500, $span);
                            $cell->addText($this->text($signer['role'] ?? ''), [], 'PenaSignRole');
                            if(!empty($signer['signature_png'])){
                                $png=base64_decode(substr($signer['signature_png'],22),true);
                                if($png===false)throw new InvalidArgumentException('Gambar tanda tangan tidak valid.');
                                $size=getimagesizefromstring($png);$scale=min(180/$size[0],64/$size[1]);
                                $cell->addTextRun('PenaSign')->addImage($png,['width'=>$size[0]*$scale,'height'=>$size[1]*$scale]);
                            }else $cell->addTextBreak(3, [], 'PenaSign');
                            $cell->addText($this->text($signer['name'] ?? ''), ['bold' => true], $keep);
                        }
                    }
                    break;
                default:
                    throw new InvalidArgumentException('Jenis blok dokumen belum didukung.');
            }
        }
        IOFactory::createWriter($document, 'Word2007')->save($path);
    }

    /** PNG bytes from a data:image/png;base64 URI made by LetterheadService, or null. */
    private static function dataUriBytes(mixed $uri): ?string
    {
        if (!is_string($uri) || !str_starts_with($uri, 'data:image/png;base64,')) return null;
        $bytes = base64_decode(substr($uri, 22), true);
        return is_string($bytes) && str_starts_with($bytes, "\x89PNG") ? $bytes : null;
    }

    private function text(mixed $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException('Isi dokumen harus berupa teks atau angka.');
        }
        return (string)$value;
    }
}
