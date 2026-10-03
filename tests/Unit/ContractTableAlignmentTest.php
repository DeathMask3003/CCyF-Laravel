<?php

namespace Tests\Unit;

use App\Services\ContractDocuments;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ContractTableAlignmentTest extends TestCase
{
    public function test_pdf_keeps_table_and_cell_text_alignment(): void
    {
        $contracts = new ContractDocuments;
        $edited = $contracts->editorHtml('<table style="text-align:center"><tbody><tr>'
            .'<td><p>Primera columna</p></td>'
            .'<td><p style="text-align:right">Segunda columna</p></td>'
            .'</tr></tbody></table><p>Párrafo exterior</p>');
        $this->assertStringContainsString('<table style="text-align:center">', $edited);

        $pdfHtml = (new ReflectionMethod(ContractDocuments::class, 'alignTextForPdf'))->invoke($contracts, $edited);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8"?><html><body>'.$pdfHtml.'</body></html>');
        $xpath = new DOMXPath($document);

        $this->assertSame('center', $xpath->query('//td')->item(0)->getAttribute('align'));
        $this->assertSame('center', $xpath->query('//td/p')->item(0)->getAttribute('align'));
        $this->assertSame('right', $xpath->query('//td')->item(1)->getAttribute('align'));
        $this->assertSame('right', $xpath->query('//td/p')->item(1)->getAttribute('align'));
        $this->assertSame('justify', $xpath->query('/html/body/p')->item(0)->getAttribute('align'));
    }
}
