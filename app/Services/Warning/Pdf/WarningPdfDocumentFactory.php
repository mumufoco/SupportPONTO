<?php

namespace App\Services\Warning\Pdf;

use App\Services\Pdf\GotenbergPdfDocument;

class WarningPdfDocumentFactory
{
    public function create(string $companyName, string $issuerName, string $title, string $subject): GotenbergPdfDocument
    {
        $pdf = new GotenbergPdfDocument('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator($companyName);
        $pdf->SetAuthor($issuerName);
        $pdf->SetTitle($title);
        $pdf->SetSubject($subject);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        return $pdf;
    }
}
