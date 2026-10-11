<?php
namespace App\Http\Controllers;

use App\Models\FondoCaja;
use App\Models\Insumo;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Insumos del almacén central en o por debajo de su mínimo. */
    public function alertas()
    {
        return Insumo::whereColumn('stock_almacen', '<=', 'stock_minimo')->orderBy('nombre')
            ->get(['id', 'nombre', 'unidad_medida', 'stock_almacen', 'stock_minimo']);
    }

    /** Monitor en tiempo real: lo que cada vendedor lleva vendido sin corte. */
    public function monitor()
    {
        return User::where('rol', 'vendedor')->where('activo', true)->orderBy('name')->get(['id', 'name'])
            ->map(function ($u) {
                $v = Venta::where('user_id', $u->id)->whereNull('corte_id');
                $efectivo = (float) (clone $v)->where('metodo_pago', 'efectivo')->sum('total_venta');
                $fondo = (float) (FondoCaja::where('user_id', $u->id)->whereNull('corte_id')->value('monto') ?? 0);
                $comision = (float) (clone $v)->sum('comision_generada');
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'unidades' => (int) (clone $v)->sum('cantidad'),
                    'fondo' => $fondo,
                    'efectivo' => $efectivo,
                    'transferencia' => (float) (clone $v)->where('metodo_pago', 'transferencia')->sum('total_venta'),
                    'comision' => $comision,
                    'efectivo_en_caja' => $fondo + $efectivo,
                    'entregar' => $fondo + $efectivo - $comision,
                ];
            })->values();
    }

    /** Analítica: más vendidos por sabor y tamaño + utilidad. */
    public function dashboard()
    {
        $hoy = Venta::whereDate('fecha', now()->toDateString());
        $ingresos = (float) (clone $hoy)->sum('total_venta');
        $costos = (float) (clone $hoy)->sum('costo_total');
        $comisiones = (float) (clone $hoy)->sum('comision_generada');

        $agrupar = fn (string $col) => DB::table('ventas')->join('productos', 'productos.id', '=', 'ventas.producto_id')
            ->select("productos.$col as etiqueta", DB::raw('SUM(ventas.cantidad) as total'))
            ->groupBy("productos.$col")->orderByDesc('total')->get()
            ->map(fn ($r) => ['etiqueta' => (string) $r->etiqueta, 'total' => (int) $r->total]);

        return [
            'hoy' => [
                'unidades' => (int) (clone $hoy)->sum('cantidad'),
                'ingresos' => $ingresos,
                'efectivo' => (float) (clone $hoy)->where('metodo_pago', 'efectivo')->sum('total_venta'),
                'transferencia' => (float) (clone $hoy)->where('metodo_pago', 'transferencia')->sum('total_venta'),
                'comisiones' => $comisiones,
                'costo_insumos' => round($costos, 2),
                'utilidad' => round($ingresos - $costos - $comisiones, 2),
            ],
            'por_sabor' => $agrupar('sabor'),
            'por_tamano' => $agrupar('tamano'),
        ];
    }
}
