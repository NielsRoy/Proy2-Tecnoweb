<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Reglas de credito de las ventas (NEGOCIO.md §4): cuotas maximas segun monto base, interes segun
 * nº de cuotas, monto total con interes y cronograma de cuotas. Funciones puras (sin BD) para
 * reutilizarlas en el server (VentaController) y exponerlas al front (preview en vivo del formulario).
 */
class PlanPago
{
    /** Monto base minimo para poder vender a credito. */
    public const MONTO_MINIMO_CREDITO = 100;

    /** Tope global de cuotas. */
    public const CUOTAS_TOPE = 12;

    /**
     * Cuotas maximas permitidas segun el monto base. 0 = no califica para credito (base < 100).
     */
    public static function cuotasMaximas(float $base): int
    {
        return match (true) {
            $base < self::MONTO_MINIMO_CREDITO => 0,
            $base < 300 => 3,
            $base < 600 => 6,
            $base < 1000 => 9,
            default => 12,
        };
    }

    /** Interes (fraccion: 0.05 = 5%) segun el nº de cuotas. */
    public static function interes(int $cuotas): float
    {
        return match (true) {
            $cuotas <= 3 => 0.05,
            $cuotas <= 6 => 0.10,
            $cuotas <= 9 => 0.15,
            default => 0.20,
        };
    }

    /** Monto total a credito = base * (1 + interes), redondeado a 2 decimales. */
    public static function montoTotalCredito(float $base, int $cuotas): float
    {
        return round($base * (1 + self::interes($cuotas)), 2);
    }

    /**
     * Cronograma de cuotas: N filas con monto igual (montoTotal/N); la ULTIMA cuota absorbe el
     * redondeo para que la suma sea exacta. Vencimiento mensual: la cuota k vence a k-1 meses de
     * la fecha de venta (cuota 1 = misma fecha).
     *
     * @return array<int, array{numero_cuota:int, monto:float, fecha_vencimiento:string}>
     */
    public static function cronograma(float $montoTotal, int $cuotas, string $fechaVenta): array
    {
        $base = Carbon::parse($fechaVenta);
        $montoCuota = floor($montoTotal / $cuotas * 100) / 100; // trunca a 2 decimales
        $acumulado = 0;

        $filas = [];
        for ($k = 1; $k <= $cuotas; $k++) {
            // La ultima cuota toma el resto para que la suma cuadre exactamente con montoTotal.
            $monto = $k < $cuotas ? $montoCuota : round($montoTotal - $acumulado, 2);
            $acumulado = round($acumulado + $monto, 2);

            $filas[] = [
                'numero_cuota' => $k,
                'monto' => $monto,
                'fecha_vencimiento' => $base->copy()->addMonths($k - 1)->toDateString(),
            ];
        }

        return $filas;
    }
}
