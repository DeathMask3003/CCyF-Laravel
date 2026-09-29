<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class ResultLetterPdf
{
    public function __construct(private readonly SignatureImages $signatures) {}

    public function render(object $record): string
    {
        return match ($record->decision) {
            'designado' => $this->designated($record),
            'no_designado', 'no_aceptado' => $this->notDesignated($record),
            default => throw new InvalidArgumentException('El expediente no tiene una resolución válida.'),
        };
    }

    private function designated(object $record): string
    {
        $pdf = $this->officePdf();
        $date = $this->letterDate($record);
        $start = $this->longDate($record->fecha_inicio);
        $end = $this->longDate($record->fecha_fin);
        $participant = mb_strtoupper(trim((string) $record->solicitante), 'UTF-8');
        $campus = mb_strtoupper(trim((string) $record->plantel_nombre), 'UTF-8');
        $service = str_contains(mb_strtolower((string) $record->servicio_nombre), 'fotocopi')
            ? 'Centro de Fotocopiado' : 'Cafetería';
        $amount = number_format((float) $record->monto, 2, '.', ',');
        $iso = fn (string $text): string => mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, $iso('Toluca de Lerdo, México'), 0, 1, 'R');
        $pdf->Cell(0, 5, $iso($date), 0, 1, 'R');
        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->MultiCell(0, 5, $iso($campus.' Y PERMISIONARIO DESIGNADO'), 0, 'L');
        $pdf->MultiCell(0, 5, $iso('P R E S E N T E S.'), 0, 'L');
        $pdf->Ln(6);
        $pdf->SetFont('Arial', '', 10);

        $paragraphs = [
            'Por instrucciones de los Integrantes de la Comisión para la Evaluación del Servicio de Cafetería y Centro de Fotocopiado se comunica que, de acuerdo con la revisión de la propuesta entregada para participar en la prestación del servicio de '.$service.', el permisionario '.$participant.' ha sido DESIGNADO para cubrir el servicio con fecha de inicio '.$start.' y fecha de término '.$end.', por un monto mensual de MXN $'.$amount.', por lo que se adjunta la lista de los artículos a ofrecer y los precios correspondientes.',
            'El permisionario deberá presentarse a la brevedad posible en las instalaciones del plantel para conocer la ubicación y colocar los insumos correspondientes para brindar el servicio en la fecha asignada.',
            'Antes de ocupar el espacio asignado se deberá adjuntar al presente correo una nota informativa en la que se describan las condiciones internas y externas en las que se encuentra el lugar. Para mayor validez se deberán anexar cinco evidencias fotográficas con buena calidad de imagen y resolución; esta nota deberá estar debidamente firmada y rubricada por el director del plantel y el permisionario designado.',
            'Finalmente, el permisionario deberá cumplir cabalmente con los derechos y obligaciones establecidos en el Reglamento de cafetería y fotocopiado, así como en el contrato. Se sugiere estar atentos a la fecha próxima para la suscripción de este último.',
        ];
        foreach ($paragraphs as $paragraph) {
            $pdf->MultiCell(0, 5, $iso($paragraph), 0, 'J');
            $pdf->Ln(2);
        }
        $pdf->Ln(4);
        $pdf->MultiCell(0, 5, $iso('Sin más por el momento, agradezco su atención.'), 0, 'J');
        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, $iso('ATENTAMENTE'), 0, 1, 'L');
        $pdf->Ln(4);

        $signY = $pdf->GetY();
        $signature = $this->signatures->pathForLegacyId(190);
        if ($signature && ($size = @getimagesize($signature))) {
            $scale = min(74 / max(1, $size[0]), 22 / max(1, $size[1]));
            $width = $size[0] * $scale;
            $height = $size[1] * $scale;
            $pdf->Image($signature, 10 + (80 - $width) / 2, $signY + (28 - $height) / 2, $width, $height);
        }
        $pdf->SetY($signY + 30);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 5, $iso('LIC. MARIA FERNANDA GODOY DÍAZ'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 5, $iso('JEFA DEL DEPARTAMENTO DE RECURSOS MATERIALES Y SERVICIOS GENERALES'), 0, 1, 'L');

        return $pdf->Output('S');
    }

    private function officePdf(): \FPDF
    {
        require_once base_path('app/Support/fpdf/fpdf.php');
        $pdf = new class(resource_path('images/fondo-oficio.png')) extends \FPDF {
            public function __construct(private readonly string $background)
            {
                parent::__construct('P', 'mm', 'Letter');
            }

            public function Header(): void
            {
                $auto = $this->AutoPageBreak;
                $margin = $this->bMargin;
                $this->SetAutoPageBreak(false);
                $this->Image($this->background, 0, 0, $this->GetPageWidth(), $this->GetPageHeight());
                $this->SetAutoPageBreak($auto, $margin);
                $this->SetY(55);
            }

            public function Footer(): void
            {
                $this->SetY(-28);
                $this->SetFont('Arial', 'I', 8);
                $this->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1', 'Página '.$this->PageNo().'/{nb}'), 0, 0, 'C');
            }
        };
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 25);
        return $pdf;
    }

    private function notDesignated(object $record): string
    {
        $pdf = $this->officePdf();
        $iso = fn (string $text): string => mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        $participant = mb_strtoupper(trim((string) $record->solicitante), 'UTF-8');
        $campus = mb_strtoupper(trim((string) $record->plantel_nombre), 'UTF-8');
        $service = str_contains(mb_strtolower((string) $record->servicio_nombre), 'cafeter')
            ? 'Cafetería' : 'Fotocopiado';
        preg_match('/20\d{2}/', (string) $record->convocatoria_nombre, $year);
        $year = $year[0] ?? Carbon::now('America/Mexico_City')->format('Y');

        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 5, $iso('Toluca de Lerdo, México'), 0, 1, 'R');
        $pdf->Cell(0, 5, $iso($this->letterDate($record)), 0, 1, 'R');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 5, $iso('C. '.$participant), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 5, $iso('P R E S E N T E'), 0, 1, 'L');
        $pdf->Ln(10);

        $decision = $record->decision === 'no_aceptado'
            ? 'su propuesta no fue aceptada para la permisión del Servicio de '.$service.' en el '.$campus
            : 'su propuesta no resultó seleccionada para la permisión del Servicio de '.$service.' en el '.$campus;
        $body = 'Sirva este medio para enviarle un cordial saludo, así mismo y en el marco de la "Convocatoria del año '.$year.' para el Servicio de '.$service.' de los Planteles y Centros EMSAD del COBAEM" y por instrucciones de la Lic. María Fernanda Godoy Díaz, Jefa del Departamento de Recursos Materiales y Servicios Generales, me dirijo a Usted para informarle que, '.$decision.'; no obstante, le invitamos estar al pendiente de las próximas convocatorias que publicaremos en nuestra página oficial.';
        $pdf->MultiCell(0, 6, $iso($body), 0, 'J');
        if ($record->decision === 'no_aceptado' && trim((string) $record->respuesta) !== '') {
            $pdf->Ln(5);
            $pdf->MultiCell(0, 6, $iso('Motivo: '.trim((string) $record->respuesta)), 0, 'J');
        }
        $pdf->Ln(8);
        $pdf->MultiCell(0, 6, $iso('Sin otro particular, reciba un cordial saludo.'), 0, 'J');
        $pdf->Ln(18);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 5, $iso('A T E N T A M E N T E'), 0, 1, 'L');
        $pdf->Ln(8);
        $pdf->Cell(0, 5, $iso('LIC. EN P. DIANA MARGOT GARCÍA CONTRERAS'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 5, $iso('ÁREA DE ADQUISICIONES'), 0, 1, 'L');
        $pdf->Cell(0, 5, $iso('DRMYSG'), 0, 1, 'L');

        return $pdf->Output('S');
    }

    private function letterDate(object $record): string
    {
        return ($record->finalizado_at
            ? Carbon::parse($record->finalizado_at, 'UTC')->timezone('America/Mexico_City')
            : Carbon::now('America/Mexico_City'))
            ->locale('es_MX')->translatedFormat('j \d\e F \d\e Y');
    }

    private function longDate(?string $value): string
    {
        return $value ? Carbon::parse($value)->locale('es_MX')->translatedFormat('j \d\e F \d\e Y') : 'fecha pendiente';
    }
}
