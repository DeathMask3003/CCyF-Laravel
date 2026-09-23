<?php

namespace App\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class FinalEvaluationReport
{
    public function source(string $origin, int $recordId, string $service): ?object
    {
        $local = DB::table('ccyf_prevaluaciones')
            ->where('origen', $origin)->where('registro_id', $recordId)->first();
        if ($local) {
            if (! in_array((int) $local->resultado, [1, 2, 3], true)) {
                return null;
            }
            return (object) [
                'result' => (int) $local->resultado,
                'evaluator_id' => (int) $local->evaluador_id,
                'evaluated_at' => $local->updated_at,
            ];
        }
        if ($origin !== 'historico') {
            return null;
        }

        [$table, $key] = $service === 'cafeteria'
            ? ['tm_preval_cafe_doc', 'id_preval_cafe']
            : ['tm_preval_foto_doc', 'id_preval_foto'];
        $legacy = DB::connection('legacy')->table($table)->where('doc_id', $recordId)
            ->where('est', 1)->whereIn('viable_estado', [1, 2, 3])
            ->orderByDesc($key)->first(['viable_estado', 'usu_preval', 'fecha_registro']);

        return $legacy ? (object) [
            'result' => (int) $legacy->viable_estado,
            'evaluator_id' => (int) $legacy->usu_preval,
            'evaluated_at' => $legacy->fecha_registro,
        ] : null;
    }

    public function pdf(object $record, object $source, array $detail): Response
    {
        $evaluator = DB::table('ccyf_usuarios')->where('usu_id', $source->evaluator_id)
            ->orWhere('legacy_usu_id', $source->evaluator_id)->value('usu_area');
        if (! $evaluator && $record->origen === 'historico') {
            $evaluator = DB::connection('legacy')->table('tm_usuario')
                ->where('usu_id', $source->evaluator_id)->value('usu_area');
        }

        $html = view('revision.final-evaluation', compact('record', 'source', 'detail', 'evaluator'))->render();
        $temp = storage_path('app/mpdf');
        File::ensureDirectoryExists($temp);
        $pdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4', 'tempDir' => $temp,
            'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 15, 'margin_bottom' => 17,
            'default_font' => 'dejavusans',
        ]);
        $pdf->SetTitle('Hoja final de evaluación · '.$record->nombre);
        $pdf->SetAuthor('CCyF CoBaEMex');
        $pdf->SetHTMLFooter('<div style="border-top:1px solid #d8c9ce;padding-top:5px;color:#70656a;font-size:7pt;text-align:right">CCyF · Página {PAGENO} de {nbpg}</div>');
        $pdf->WriteHTML($html);
        $bytes = $pdf->Output('', Destination::STRING_RETURN);

        $response = response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Evaluacion_Final_'.$record->origen.'_'.$record->registro_id.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
