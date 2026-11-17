<?php
declare(strict_types=1);

namespace Pena\Service;

/**
 * Turns a saved DOCX into the PDF that reviewers approve.
 * Kept as an interface so the Word engine can be swapped for LibreOffice on a server.
 */
interface PdfConverter
{
    public function isAvailable(): bool;

    public function convert(string $docxPath, string $pdfPath): void;
}
