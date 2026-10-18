<?php
namespace App\Services;

use App\Models\Corte;
use App\Models\FondoCaja;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

/** Cálculo del desglose de caja de un vendedor (turno abierto o corte ya cerrado). */
class Arqueo
{
    public static function paraVendedor(int $uid): array
    {
        $filas = DB::table('ventas')->join('productos', 'productos.id', '=', 'ventas.producto_id')
            ->where('ventas.user_id', $uid)->whereNull('ventas.corte_id')
            ->select(
                'productos.tamano',
                DB::raw('SUM(ventas.cantidad) as unidades'),
                DB::raw('SUM(ventas.total_venta) as total'),
                DB::raw('SUM(ventas.comision_generada) as comision')
            )->groupBy('productos.tamano')->get()->keyBy('tamano');

        $tam = fn (int $ml) => [
            'unidades' => (int) ($filas->get($ml)->unidades ?? 0),
            'total' => (float) ($filas->get($ml)->total ?? 0),
            'comision' => (float) ($filas->get($ml)->comision ?? 0),
        ];

        $base = Venta::where('user_id', $uid)->whereNull('corte_id');
        $efectivo = (float) (clone $base)->where('metodo_pago', 'efectivo')->sum('total_venta');
        $transf = (float) (clone $base)->where('metodo_pago', 'transferencia')->sum('total_venta');
        $saldo = (float) (FondoCaja::where('user_id', $uid)->whereNull('corte_id')->value('monto') ?? 0);

        return self::armar($saldo, $efectivo, $transf, $tam(250), $tam(500));
    }

    public static function deCorte(Corte $c): array
    {
        $r = self::armar(
            $c->saldo_inicial, $c->total_efectivo, $c->total_transferencia,
            ['unidades' => $c->unidades_250, 'total' => $c->total_250, 'comision' => $c->comision_250],
            ['unidades' => $c->unidades_500, 'total' => $c->total_500, 'comision' => $c->comision_500],
        );
        // Cortes anteriores no guardaban la ganancia por tamaño: se respetan los totales guardados
        $r['comision'] = (float) $c->comision;
        $r['entregar'] = (float) $c->entregar;
        $r['monto_entregar'] = max(0, $r['entregar']);
        $r['monto_recibir'] = max(0, -$r['entregar']);
        $r['unidades'] = (int) $c->unidades;
        return $r + ['id' => $c->id, 'fecha' => $c->created_at];
    }

    public static function armar(float $saldo, float $efectivo, float $transf, array $s250, array $s500): array
    {
        $comision = $s250['comision'] + $s500['comision'];
        $enCaja = $saldo + $efectivo;
        $entregar = $enCaja - $comision; // con signo: negativo = el admin completa la ganancia

        return [
            'saldo_inicial' => $saldo,
            'total_efectivo' => $efectivo,
            'total_transferencia' => $transf,
            'unidades_250' => $s250['unidades'], 'total_250' => $s250['total'], 'comision_250' => $s250['comision'],
            'unidades_500' => $s500['unidades'], 'total_500' => $s500['total'], 'comision_500' => $s500['comision'],
            'unidades' => $s250['unidades'] + $s500['unidades'],
            'total_vendido' => $efectivo + $transf,
            'comision' => $comision,
            'efectivo_en_caja' => $enCaja,
            'dinero_total' => $saldo + $efectivo + $transf, // caja + efectivo + transferencias
            'entregar' => $entregar,
            'monto_entregar' => max(0, $entregar),
            'monto_recibir' => max(0, -$entregar),
        ];
    }
}
//Para render