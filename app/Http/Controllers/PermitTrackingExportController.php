<?php

namespace App\Http\Controllers;

use App\Services\LegacyMenu;
use App\Services\InstitutionalPdfHeader;
use App\Services\PermitTrackingRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class PermitTrackingExportController extends Controller
{
    private const HEADERS = [
        'Convocatoria', 'Plantel', 'Nombre del Permisionario', 'CURP del Permisionario',
        'Dirección del Permisionario', 'Teléfono del Permisionario', 'Correo Electrónico del Permisionario',
        'Validez del Contrato', 'Espacio en m²', 'Monto Actual', 'Registro', 'Estado',
    ];

    public function download(string $format, Request $request, LegacyMenu $menu, PermitTrackingRecords $records): Response
    {
        abort_unless($menu->allows($request->user(), 'seguimiento_permisionarios'), 403);
        abort_unless(in_array($format, ['xlsx', 'pdf', 'csv'], true), 404);
        $request->validate([
            'servicio' => ['required', Rule::in(['cafeteria', 'fotocopiado'])],
            'mes_inicio' => ['nullable', 'date_format:Y-m'], 'mes_fin' => ['nullable', 'date_format:Y-m'],
            'buscar' => ['nullable', 'string', 'max:150'],
        ]);
        $rows = $records->filtered($request)->map(fn ($item) => $this->row($item))->all();
        $service = $request->query('servicio') === 'cafeteria' ? 'Cafetería' : 'Fotocopiado';
        $filename = 'CCyF-permisionarios-'.$request->query('servicio').'-'.now()->format('Ymd').'.'.$format;
        return match ($format) {
            'xlsx' => $this->excel($rows, $service, $filename),
            'pdf' => $this->pdf($rows, $service, $filename),
            default => $this->csv($rows, $filename),
        };
    }

    private function row(object $item): array
    {
        $start = $item->fecha_inicio ? Carbon::parse($item->fecha_inicio)->format('d/m/Y') : '';
        $end = $item->fecha_fin ? Carbon::parse($item->fecha_fin)->format('d/m/Y') : '';
        return [
            $item->registro_id.' - '.$item->convocatoria, $item->plantel, $item->nombre, $item->curp,
            $item->direccion, $item->telefono, $item->correo, trim($start.' al '.$end),
            $item->seguimiento?->metros_cuadrados ?? '', $item->seguimiento?->monto ?? $item->monto_base ?? '',
            $item->registro_at ? Carbon::parse($item->registro_at)->format('d/m/Y H:i:s') : '', $item->estado,
        ];
    }

    private function excel(array $rows, string $service, string $filename): Response
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Permisionarios');
        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', 'CCyF · Seguimiento de permisionarios · '.$service);
        $sheet->mergeCells('A2:L2');
        $sheet->setCellValue('A2', count($rows).' registros · '.now()->format('d/m/Y H:i'));
        foreach (self::HEADERS as $column => $header) {
            $sheet->setCellValueExplicit([$column + 1, 4], $header, DataType::TYPE_STRING);
        }
        foreach ($rows as $index => $values) {
            $line = $index + 5;
            foreach ($values as $column => $value) {
                $sheet->setCellValueExplicit([$column + 1, $line], (string) ($value ?? ''), DataType::TYPE_STRING);
            }
            if ($index % 2) {
                $sheet->getStyle("A{$line}:L{$line}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8F4F6');
            }
        }
        $last = max(4, count($rows) + 4);
        $sheet->getStyle('A1:L1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('611232');
        $sheet->getStyle('A4:L4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('611232');
        $sheet->getStyle('A4:L4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A4:L{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        foreach (['A'=>26,'B'=>28,'C'=>32,'D'=>24,'E'=>45,'F'=>20,'G'=>34,'H'=>28,'I'=>16,'J'=>18,'K'=>22,'L'=>16] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->freezePane('C5');
        $sheet->setAutoFilter("A4:L{$last}");
        return response()->streamDownload(function () use ($book): void {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function pdf(array $rows, string $service, string $filename): Response
    {
        $html = '<h1>Seguimiento de permisionarios</h1><p>'.$service.' · '.count($rows).' registros · '.now()->format('d/m/Y H:i').'</p><table><thead><tr>';
        foreach (self::HEADERS as $header) {
            $html .= '<th>'.e($header).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $value) {
                $html .= '<td>'.e((string) ($value ?? '')).'</td>';
            }
            $html .= '</tr>';
        }
        if (! $rows) {
            $html .= '<tr><td colspan="12">Sin registros para este filtro.</td></tr>';
        }
        $html .= '</tbody></table>';
        $temp = storage_path('app/mpdf');
        File::ensureDirectoryExists($temp);
        $pdf = new Mpdf(['mode'=>'utf-8','format'=>'Legal-L','tempDir'=>$temp,'margin_left'=>7,'margin_right'=>7,'margin_top'=>31,'margin_bottom'=>13,'margin_header'=>6,'default_font'=>'dejavusans']);
        $pdf->SetTitle('CCyF - Seguimiento de permisionarios');
        InstitutionalPdfHeader::apply($pdf);
        $pdf->SetHTMLFooter('<div style="font-size:7pt;text-align:right;color:#74646c">CCyF · Página {PAGENO} de {nbpg}</div>');
        $pdf->WriteHTML('<style>body{font-family:dejavusans;font-size:6pt;color:#2b2026}h1{font-size:15pt;color:#611232;margin:0 0 4px}p{font-size:8pt;color:#75666e}table{border-collapse:collapse;width:100%;table-layout:fixed}thead{display:table-header-group}th{background:#611232;color:white;font-size:5.5pt;padding:5px 3px;text-align:left}td{font-size:5.5pt;padding:4px 3px;border-bottom:1px solid #e7dce1;word-wrap:break-word}tr:nth-child(even) td{background:#f9f5f7}</style>');
        $pdf->WriteHTML($html);
        return response($pdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function csv(array $rows, string $filename): Response
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::HEADERS);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
