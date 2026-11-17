<?php
declare(strict_types=1);

namespace Pena\Service;

use RuntimeException;
use Throwable;

/**
 * DOCX → PDF through Microsoft Word automation (Windows + Office only).
 * Works when PHP runs as the signed-in user (CLI or `composer serve`);
 * Word automation usually fails when PHP runs as a Windows service.
 */
final class WordPdfConverter implements PdfConverter
{
    private const FORMAT_PDF = 17;   // wdExportFormatPDF
    private const NO_SAVE = 0;       // wdDoNotSaveChanges

    public function isAvailable(): bool
    {
        if (PHP_OS_FAMILY !== 'Windows' || !class_exists(\COM::class)) {
            return false;
        }
        try {
            $word = new \COM('Word.Application');
            $word->Quit();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function convert(string $docxPath, string $pdfPath): void
    {
        if (!class_exists(\COM::class)) {
            throw new RuntimeException('Ekstensi com_dotnet belum aktif; konversi PDF lewat Word tidak tersedia.');
        }
        $source = realpath($docxPath);
        if ($source === false) {
            throw new RuntimeException('File Word sumber tidak ditemukan.');
        }
        $targetDir = realpath(dirname($pdfPath));
        if ($targetDir === false) {
            throw new RuntimeException('Folder tujuan PDF tidak ditemukan.');
        }
        // Word needs absolute Windows paths.
        $target = $targetDir . DIRECTORY_SEPARATOR . basename($pdfPath);

        $word = null;
        $document = null;
        try {
            $word = new \COM('Word.Application');
            $word->Visible = false;
            $word->DisplayAlerts = 0;
            // Open(FileName, ConfirmConversions, ReadOnly)
            $document = $word->Documents->Open($source, false, true);
            $document->ExportAsFixedFormat($target, self::FORMAT_PDF);
        } catch (Throwable $error) {
            throw new RuntimeException('Konversi PDF lewat Word gagal: ' . $error->getMessage(), 0, $error);
        } finally {
            // Always release Word, or orphaned WINWORD.EXE processes pile up.
            try { $document?->Close(self::NO_SAVE); } catch (Throwable) {}
            try { $word?->Quit(self::NO_SAVE); } catch (Throwable) {}
        }

        if (!is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('Word tidak menghasilkan file PDF.');
        }
    }
}
