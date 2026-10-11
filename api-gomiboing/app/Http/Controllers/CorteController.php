<?php
namespace App\Http\Controllers;

use App\Models\Corte;
use App\Models\FondoCaja;
use App\Models\InventarioVendedor;
use App\Models\Insumo;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CorteController extends Controller
{
    /** Cálculo del arqueo del turno abierto (ventas sin corte + fondo de caja). */
    private function arqueo(int $uid): array
    {
        $ventas = Venta::where('user_id', $uid)->whereNull('corte_id');

        $porTamano = DB::table('ventas')->join('productos', 'productos.id', '=', 'ventas.producto_id')
            ->where('ventas.user_id', $uid)->whereNull('ventas.corte_id')
            ->select('productos.tamano', DB::raw('SUM(ventas.cantidad) as unidades'), DB::raw('SUM(ventas.total_venta) as total'))
            ->groupBy('productos.tamano')->get()->keyBy('tamano');

        $efectivo = (float) (clone $ventas)->where('metodo_pago', 'efectivo')->sum('total_venta');
        $transferencia = (float) (clone $ventas)->where('metodo_pago', 'transferencia')->sum('total_venta');
        $comision = (float) (clone $ventas)->sum('comision_generada');
        $saldo = (float) (FondoCaja::where('user_id', $uid)->whereNull('corte_id')->value('monto') ?? 0);

        $efectivoEnCaja = $saldo + $efectivo;
        $entregar = $efectivoEnCaja - $comision; // con signo

        return [
            'saldo_inicial' => $saldo,
            'unidades_250' => (int) ($porTamano->get(250)->unidades ?? 0),
            'total_250' => (float) ($porTamano->get(250)->total ?? 0),
            'unidades_500' => (int) ($porTamano->get(500)->unidades ?? 0),
            'total_500' => (float) ($porTamano->get(500)->total ?? 0),
            'unidades' => (int) (clone $ventas)->sum('cantidad'),
            'total_efectivo' => $efectivo,
            'total_transferencia' => $transferencia,
            'total_vendido' => $efectivo + $transferencia,
            'comision' => $comision,
            'efectivo_en_caja' => $efectivoEnCaja,
            'entregar' => $entregar,
            'monto_entregar' => max(0, $entregar),   // el vendedor entrega al admin
            'monto_recibir' => max(0, -$entregar),   // el admin le completa su ganancia
        ];
    }

    public function preview(Request $r)
    {
        $uid = $r->user()->id;
        $res = $this->arqueo($uid);
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
            $a = $this->arqueo($uid);
            abort_if($a['unidades'] === 0 && $a['saldo_inicial'] <= 0, 422, 'No hay ventas ni fondo de caja pendientes de corte.');

            $corte = Corte::create([
                'user_id' => $uid, 'unidades' => $a['unidades'],
                'unidades_250' => $a['unidades_250'], 'unidades_500' => $a['unidades_500'],
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
}
