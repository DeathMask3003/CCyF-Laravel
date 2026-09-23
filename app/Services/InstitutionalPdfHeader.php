<?php

namespace App\Services;

use Mpdf\Mpdf;

class InstitutionalPdfHeader
{
    public static function apply(Mpdf $pdf, int $imageWidthMm = 190): void
    {
        $image = base64_encode(file_get_contents(resource_path('images/cabezera.png')));
        $header = '<div style="text-align:center;border-bottom:1px solid #8c2236;padding-bottom:4px">'
            .'<img src="data:image/png;base64,'.$image.'" style="width:'.$imageWidthMm.'mm;height:auto">'
            .'</div>';

        $pdf->SetHTMLHeader($header, 'O');
        $pdf->SetHTMLHeader($header, 'E');
    }
}
