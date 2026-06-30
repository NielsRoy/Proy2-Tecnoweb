<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 24px; color: #1a1a1a; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .subtitulo { color: #555; font-size: 11px; margin: 0 0 16px; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #1f2937; color: #fff; text-align: left;
            padding: 6px 8px; font-size: 11px;
        }
        tbody td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
        tbody tr:nth-child(even) td { background: #f9fafb; }
        .num { text-align: right; }
        tfoot td {
            padding: 7px 8px; font-weight: bold; border-top: 2px solid #1f2937;
            font-size: 12px;
        }
        .vacio { padding: 16px; text-align: center; color: #777; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <p class="subtitulo">{{ $subtitulo }}</p>

    <table>
        <thead>
            <tr>
                @foreach ($columnas as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    @foreach ($fila as $celda)
                        <td>{{ $celda }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="vacio" colspan="{{ count($columnas) }}">
                        No hay registros para los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                @foreach ($filaTotal as $celda)
                    <td>{{ $celda }}</td>
                @endforeach
            </tr>
        </tfoot>
    </table>
</body>
</html>
