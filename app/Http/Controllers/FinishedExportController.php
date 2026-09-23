<?php

namespace App\Http\Controllers;

use App\Services\FinishedRecords;
use App\Services\InstitutionalPdfHeader;
use App\Services\LegacyMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class FinishedExportController extends Controller
{
    private const HEADERS = [
        '# Registro', 'Nombre del Permisionario', 'Correo Electrónico', 'Teléfono',
        'Servicio en', '# Convocatoria', 'Participó en', 'Estado',
        'Fecha Conclusión', 'Documento',
    ];

    public function download(string $format, Request $request, LegacyMenu $menu, FinishedRecords $records): Response
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $items = $records->forRequest($request, $menu);
        $rows = $items->map(fn ($item) => $this->row($item))->all();
        $service = $request->filled('servicio')
            ? DB::table('ccyf_tipos_servicio')->where('id', (int) $request->input('servicio'))->value('nombre')
            : null;
        $convocation = $request->filled('convocatoria')
            ? DB::table('ccyf_convocatorias')->where('id', (int) $request->input('convocatoria'))->value('numero')
            : null;
        $scope = trim(($service ?: 'Cafetería y Fotocopiado').($convocation ? ' · '.$convocation : ''));
        $filename = 'CCyF-finalizados-'.($service ? str($service)->slug() : 'ambos-servicios').'-'.now()->format('Ymd');

        return $format === 'pdf'
            ? $this->pdf($rows, $scope, $filename.'.pdf')
            : $this->excel($rows, $scope, $filename.'.xlsx');
    }

    private function row(object $item): array
    {
        return [
            (string) $item->folio_original,
            (string) ($item->solicitante ?: ''),
            (string) ($item->correo ?: ''),
            (string) ($item->telefono ?: ''),
            (string) ($item->servicio_nombre ?: ''),
            (string) ($item->convocatoria_nombre ?: ''),
            (string) ($item->plantel_nombre ?: ''),
            (string) $item->estado_texto,
            $item->finalizado_at ? Carbon::parse($item->finalizado_at)->format('d-m-Y H:i:s') : 'Sin finalizar',
            (string) $item->documento_url,
        ];
    }

    private function excel(array $rows, string $scope, string $filename): Response
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Finalizados');
        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', 'CCyF · Convocatorias finalizadas');
        $sheet->mergeCells('A2:J2');
        $sheet->setCellValue('A2', $scope.'  |  '.count($rows).' registros  |  '.now()->format('d/m/Y H:i'));
        foreach (self::HEADERS as $index => $label) {
            $sheet->setCellValueExplicit([$index + 1, 4], $label, DataType::TYPE_STRING);
        }
        foreach ($rows as $index => $values) {
            $line = $index + 5;
            foreach ($values as $column => $value) {
                if ($column === 9) {
                    $sheet->setCellValueExplicit([10, $line], 'Ver expediente', DataType::TYPE_STRING);
                    $sheet->getCell([10, $line])->getHyperlink()->setUrl($value);
                } else {
                    $sheet->setCellValueExplicit([$column + 1, $line], $value, DataType::TYPE_STRING);
                }
            }
            if ($index % 2 === 1) {
                $sheet->getStyle("A{$line}:J{$line}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8F4F6');
            }
        }
        $last = max(4, count($rows) + 4);
        $sheet->getStyle('A1:J1')->getFont()->setBold(true)->setSize(17)->getColor()->setRGB('611232');
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(24);
        $sheet->getStyle('A2:J2')->getFont()->setSize(10)->getColor()->setRGB('75666E');
        $sheet->getStyle('A4:J4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('611232');
        $sheet->getStyle('A4:J4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A4:J{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("A4:J{$last}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('E5DCE0');
        $widths = ['A' => 17, 'B' => 32, 'C' => 31, 'D' => 18, 'E' => 18, 'F' => 30, 'G' => 32, 'H' => 32, 'I' => 22, 'J' => 19];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->freezePane('A5');
        $sheet->setAutoFilter("A4:J{$last}");
        $sheet->getPageSetup()->setOrientation('landscape');
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);

        return response()->streamDownload(function () use ($book): void {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function pdf(array $rows, string $scope, string $filename): Response
    {
        $html = '<h1>Convocatorias finalizadas</h1>';
        $html .= '<p class="meta">'.e($scope).' &nbsp; | &nbsp; '.count($rows).' registros &nbsp; | &nbsp; '.now()->format('d/m/Y H:i').'</p>';
        $html .= '<table><thead><tr>';
        foreach (self::HEADERS as $header) {
            $html .= '<th>'.e($header).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $index => $value) {
                $html .= $index === 9
                    ? '<td><a href="'.e($value).'">Ver expediente</a></td>'
                    : '<td>'.e($value).'</td>';
            }
            $html .= '</tr>';
        }
        if (! $rows) {
            $html .= '<tr><td colspan="10" class="empty">No hay registros con los filtros seleccionados.</td></tr>';
        }
        $html .= '</tbody></table>';

        $temp = storage_path('app/mpdf');
        File::ensureDirectoryExists($temp);
        $pdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'Legal-L', 'tempDir' => $temp,
            'margin_left' => 9, 'margin_right' => 9, 'margin_top' => 31,
            'margin_bottom' => 15, 'margin_header' => 6,
            'default_font' => 'dejavusans',
        ]);
        $pdf->SetTitle('CCyF - Convocatorias finalizadas');
        $pdf->SetAuthor('CCyF CoBaEMex');
        InstitutionalPdfHeader::apply($pdf);
        $pdf->SetHTMLFooter('<div style="border-top:1px solid #d9c9cf;padding-top:5px;color:#74646c;font-size:7pt;text-align:right">CCyF · Página {PAGENO} de {nbpg}</div>');
        $pdf->WriteHTML('<style>
            body{font-family:dejavusans;color:#2b2026;font-size:8pt}
            h1{font-size:17pt;color:#611232;margin:5px 0 3px}.meta{font-size:8pt;color:#71646b;margin:0 0 12px}
            table{border-collapse:collapse;width:100%;table-layout:fixed}thead{display:table-header-group}
            th{background:#611232;color:#fff;padding:7px 5px;font-size:7pt;text-align:left;border:1px solid #611232}
            td{padding:5px;border-bottom:1px solid #e5dce0;vertical-align:top;font-size:7pt;word-wrap:break-word}
            tr:nth-child(even) td{background:#f8f4f6}a{color:#611232;text-decoration:none}.empty{text-align:center;padding:20px}
            th:nth-child(1){width:7%}th:nth-child(2){width:13%}th:nth-child(3){width:14%}
            th:nth-child(4){width:8%}th:nth-child(5){width:8%}th:nth-child(6){width:12%}
            th:nth-child(7){width:12%}th:nth-child(8){width:12%}th:nth-child(9){width:8%}th:nth-child(10){width:6%}
        </style>');
        $pdf->WriteHTML($html);
        $bytes = $pdf->Output('', Destination::STRING_RETURN);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
