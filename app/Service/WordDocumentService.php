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
        if (!empty($letterhead['name'])) {
            $header = $section->addHeader();
            $header->addText($this->text($letterhead['name'] ?? ''), ['bold' => true, 'size' => 14], 'PenaCenter');
            foreach (['address', 'contact'] as $field) {
                if (!empty($letterhead[$field])) {
                    $header->addText($this->text($letterhead[$field]), ['size' => 10], 'PenaCenter');
                }
            }
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

    private function text(mixed $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException('Isi dokumen harus berupa teks atau angka.');
        }
        return (string)$value;
    }
}
