<?php
namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\User;
use App\Models\Venta;
use App\Services\Arqueo;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Insumos del almacén central en o por debajo de su mínimo. */
    public function alertas()
    {
        return Insumo::whereColumn('stock_almacen', '<=', 'stock_minimo')->orderBy('nombre')
            ->get(['id', 'nombre', 'unidad_medida', 'stock_almacen', 'stock_minimo']);
    }

    /** Monitor en tiempo real: desglose de caja de cada vendedor activo (turno abierto). */
    public function monitor()
    {
        return User::where('rol', 'vendedor')->where('activo', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name] + Arqueo::paraVendedor($u->id))->values();
    }

    /** Analítica del día (en tiempo real) e histórica. */
    public function dashboard()
    {
        $ventas = Venta::with(['producto:id,sabor,tamano', 'user:id,name'])
            ->whereDate('fecha', now()->toDateString())->get();

        $ingresos = (float) $ventas->sum('total_venta');
        $costos = (float) $ventas->sum('costo_total');
        $comisiones = (float) $ventas->sum('comision_generada');

        $contar = fn ($coleccion, $clave) => $coleccion->groupBy($clave)->map(fn ($g) => (int) $g->sum('cantidad'))
            ->sortDesc()->map(fn ($t, $k) => ['etiqueta' => (string) $k, 'total' => $t])->values();

        $porHora = $ventas->groupBy(fn ($v) => $v->fecha->format('H'))->sortKeys()->map(fn ($g, $h) => [
            'hora' => "$h:00",
            'u250' => (int) $g->filter(fn ($v) => $v->producto->tamano == 250)->sum('cantidad'),
            'u500' => (int) $g->filter(fn ($v) => $v->producto->tamano == 500)->sum('cantidad'),
        ])->values();

        $porVendedor = $ventas->groupBy('user_id')->map(fn ($g) => [
            'name' => $g->first()->user->name,
            'u250' => (int) $g->filter(fn ($v) => $v->producto->tamano == 250)->sum('cantidad'),
            'u500' => (int) $g->filter(fn ($v) => $v->producto->tamano == 500)->sum('cantidad'),
            'unidades' => (int) $g->sum('cantidad'),
            'ingresos' => (float) $g->sum('total_venta'),
            'comision' => (float) $g->sum('comision_generada'),
        ])->sortByDesc('unidades')->values();

        $historico = fn (string $col) => DB::table('ventas')->join('productos', 'productos.id', '=', 'ventas.producto_id')
            ->select("productos.$col as etiqueta", DB::raw('SUM(ventas.cantidad) as total'))
            ->groupBy("productos.$col")->orderByDesc('total')->get()
            ->map(fn ($r) => ['etiqueta' => (string) $r->etiqueta, 'total' => (int) $r->total]);

        $eliminadas = DB::table('ventas_eliminadas')->join('users', 'users.id', '=', 'ventas_eliminadas.user_id')
            ->whereDate('ventas_eliminadas.created_at', now()->toDateString())
            ->orderByDesc('ventas_eliminadas.id')->limit(10)
            ->get(['users.name as vendedor', 'ventas_eliminadas.producto', 'ventas_eliminadas.cantidad',
                   'ventas_eliminadas.total_venta', 'ventas_eliminadas.created_at']);

        return [
            'hoy' => [
                'unidades' => (int) $ventas->sum('cantidad'),
                'ingresos' => $ingresos,
                'efectivo' => (float) $ventas->where('metodo_pago', 'efectivo')->sum('total_venta'),
                'transferencia' => (float) $ventas->where('metodo_pago', 'transferencia')->sum('total_venta'),
                'comisiones' => $comisiones,
                'costo_insumos' => round($costos, 2),
                'utilidad' => round($ingresos - $costos - $comisiones, 2),
            ],
            'por_sabor_hoy' => $contar($ventas->map(fn ($v) => (object) ['k' => $v->producto->sabor, 'cantidad' => $v->cantidad]), 'k'),
            'por_tamano_hoy' => $contar($ventas->map(fn ($v) => (object) ['k' => $v->producto->tamano, 'cantidad' => $v->cantidad]), 'k'),
            'por_hora_hoy' => $porHora,
            'por_vendedor_hoy' => $porVendedor,
            'por_sabor' => $historico('sabor'),
            'por_tamano' => $historico('tamano'),
            'eliminadas_hoy' => $eliminadas,
        ];
    }
}
