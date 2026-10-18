<?php
namespace App\Http\Controllers;

use App\Models\Corte;
use App\Models\FondoCaja;
use App\Models\Insumo;
use App\Models\InventarioVendedor;
use App\Models\Venta;
use App\Services\Arqueo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CorteController extends Controller
{
    /** Vista previa del arqueo: ventas sin cortar + sobrante en inventario. */
    public function preview(Request $r)
    {
        $uid = $r->user()->id;
        $res = Arqueo::paraVendedor($uid);
        $res['sobrante'] = InventarioVendedor::with('insumo:id,nombre,unidad_medida')
            ->where('user_id', $uid)->where('cantidad_asignada', '>', 0)->get();
        return $res;
    }

    /** Cierra el turno. El sobrante se conserva para el siguiente turno o se devuelve al almacén. */
    public function store(Request $r)
    {
        $data = $r->validate(['devolver_sobrante' => 'boolean']);
        $uid = $r->user()->id;
        $devolver = (bool) ($data['devolver_sobrante'] ?? false);

        return DB::transaction(function () use ($uid, $devolver) {
            $a = Arqueo::paraVendedor($uid);
            abort_if($a['unidades'] === 0 && $a['saldo_inicial'] <= 0, 422, 'No hay ventas ni fondo de caja pendientes de corte.');

            $corte = Corte::create([
                'user_id' => $uid, 'unidades' => $a['unidades'],
                'unidades_250' => $a['unidades_250'], 'unidades_500' => $a['unidades_500'],
                'total_250' => $a['total_250'], 'total_500' => $a['total_500'],
                'comision_250' => $a['comision_250'], 'comision_500' => $a['comision_500'],
                'saldo_inicial' => $a['saldo_inicial'], 'total_efectivo' => $a['total_efectivo'],
                'total_transferencia' => $a['total_transferencia'], 'comision' => $a['comision'],
                'entregar' => $a['entregar'], 'sobrante_devuelto' => $devolver,
            ]);
            Venta::where('user_id', $uid)->whereNull('corte_id')->update(['corte_id' => $corte->id]);
            FondoCaja::where('user_id', $uid)->whereNull('corte_id')->update(['corte_id' => $corte->id]);

            if ($devolver) {
                $filas = InventarioVendedor::where('user_id', $uid)->where('cantidad_asignada', '>', 0)->lockForUpdate()->get();
                foreach ($filas as $f) {
                    Insumo::where('id', $f->insumo_id)->increment('stock_almacen', $f->cantidad_asignada);
                    $f->update(['cantidad_asignada' => 0]);
                }
            }
            return response()->json($corte, 201);
        });
    }

    /** Cortes cerrados del propio vendedor. */
    public function historial(Request $r)
    {
        return Corte::where('user_id', $r->user()->id)->latest('id')->limit(30)->get()->map(fn ($c) => Arqueo::deCorte($c));
    }

    /** Cortes cerrados de todos los vendedores (admin). */
    public function adminIndex()
    {
        return Corte::with('user:id,name')->latest('id')->limit(60)->get()
            ->map(fn ($c) => Arqueo::deCorte($c) + ['vendedor' => $c->user->name]);
    }
}
