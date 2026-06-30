<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fontaneria compartida para generar reportes de LISTADO descargables (la usan Compras y Ventas).
 * Dos formatos: CSV (abre en Excel/LibreOffice, con BOM UTF-8 para que las tildes salgan bien) y
 * PDF (via dompdf, renderizando la Blade generica `reportes.listado`). Es solo presentacion: la
 * consulta y el armado de filas/totales los hace cada controlador.
 */
class Reporte
{
    /**
     * Descarga CSV: BOM UTF-8 + cabecera + filas. Compatible con Excel (lee el BOM y respeta UTF-8).
     *
     * @param  array<int, string>  $columnas  cabeceras
     * @param  array<int, array<int, string|int|float|null>>  $filas
     */
    public static function csv(array $columnas, array $filas, string $nombreArchivo): StreamedResponse
    {
        return Response::streamDownload(function () use ($columnas, $filas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel
            // Parametros explicitos (PHP 8.4 deprecia omitir $escape); escape '' = CSV estandar.
            fputcsv($salida, $columnas, ',', '"', '');
            foreach ($filas as $fila) {
                fputcsv($salida, $fila, ',', '"', '');
            }
            fclose($salida);
        }, $nombreArchivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Descarga PDF renderizando la Blade `reportes.listado`.
     *
     * @param  array{titulo: string, subtitulo: string, columnas: array<int, string>, filas: array<int, array<int, string|int|float|null>>, filaTotal: array<int, string|int|float|null>}  $datos
     */
    public static function pdf(array $datos, string $nombreArchivo): \Illuminate\Http\Response
    {
        $html = View::make('reportes.listado', $datos)->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4');
        $dompdf->render();

        return Response::make($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
        ]);
    }
}
