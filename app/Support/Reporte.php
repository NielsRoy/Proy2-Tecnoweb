<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fontaneria compartida para generar reportes de LISTADO descargables (la usan todos los modulos
 * con reporte: compras, ventas, inventarios, bitacora, productos, promociones, usuarios). Tres
 * formatos: PDF (dompdf + Blade `reportes.listado`), Excel .xlsx real (PhpSpreadsheet; el servidor
 * tiene ext-zip/gd/xml) y CSV (abre en Excel, con BOM UTF-8). Es solo presentacion: la consulta y
 * el armado de columnas/filas/total los hace cada controlador.
 *
 * @phpstan-type DatosReporte array{titulo: string, subtitulo: string, columnas: array<int, string>, filas: array<int, array<int, string|int|float|null>>, filaTotal: array<int, string|int|float|null>}
 */
class Reporte
{
    /**
     * Punto de entrada unico: despacha al formato pedido (pdf|xlsx|csv; por defecto csv). Centraliza
     * el nombre de archivo (base + timestamp) y como se agrega la fila de TOTAL en cada formato.
     *
     * @param  DatosReporte  $datos
     */
    public static function generar(string $formato, array $datos, string $nombreBase): mixed
    {
        $formato = in_array($formato, ['pdf', 'xlsx', 'csv'], true) ? $formato : 'csv';
        $nombre = $nombreBase.'_'.now()->format('Ymd_His');

        return match ($formato) {
            'pdf' => self::pdf($datos, "{$nombre}.pdf"),
            'xlsx' => self::xlsx($datos, "{$nombre}.xlsx"),
            default => self::csv(
                $datos['columnas'],
                [...$datos['filas'], $datos['filaTotal']],
                "{$nombre}.csv",
            ),
        };
    }

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
     * Descarga Excel .xlsx real (PhpSpreadsheet): titulo + subtitulo + cabecera con estilo + filas +
     * fila de TOTAL en negrita. Se escribe directo a la salida via streamDownload.
     *
     * @param  DatosReporte  $datos
     */
    public static function xlsx(array $datos, string $nombreArchivo): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Reporte');

        $nCols = count($datos['columnas']);
        $ultimaCol = Coordinate::stringFromColumnIndex($nCols);

        // Escribe una fila completa de valores (array de celdas) en la fila $row.
        $escribirFila = function (array $valores, int $row) use ($hoja): void {
            foreach (array_values($valores) as $i => $celda) {
                $hoja->setCellValue(Coordinate::stringFromColumnIndex($i + 1).$row, $celda);
            }
        };

        // Fila 1: titulo (combinado). Fila 2: subtitulo (combinado). Fila 4: cabecera.
        $hoja->setCellValue('A1', $datos['titulo']);
        $hoja->mergeCells("A1:{$ultimaCol}1");
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $hoja->setCellValue('A2', $datos['subtitulo']);
        $hoja->mergeCells("A2:{$ultimaCol}2");
        $hoja->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('666666');

        $filaCabecera = 4;
        $escribirFila($datos['columnas'], $filaCabecera);
        $hoja->getStyle("A{$filaCabecera}:{$ultimaCol}{$filaCabecera}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        $fila = $filaCabecera + 1;
        foreach ($datos['filas'] as $registro) {
            $escribirFila($registro, $fila);
            $fila++;
        }

        // Fila de TOTAL (negrita, borde superior).
        $escribirFila($datos['filaTotal'], $fila);
        $hoja->getStyle("A{$fila}:{$ultimaCol}{$fila}")->getFont()->setBold(true);
        $hoja->getStyle("A{$fila}:{$ultimaCol}{$fila}")->getBorders()->getTop()
            ->setBorderStyle(Border::BORDER_THIN);

        // Autoajustar ancho de columnas.
        for ($c = 1; $c <= $nCols; $c++) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return Response::streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Descarga PDF renderizando la Blade `reportes.listado`.
     *
     * @param  DatosReporte  $datos
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
