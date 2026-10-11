<?php
namespace App\Http\Controllers;

use App\Models\InventarioVendedor;
use App\Models\Insumo;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    /** Ventas del turno en curso (aún sin corte). */
    public function index(Request $r)
    {
        return Venta::with('producto:id,nombre')->where('user_id', $r->user()->id)
            ->whereNull('corte_id')->latest('id')->get();
    }

    /** Registra el carrito completo y descuenta la receta del inventario del vendedor. */
    public function store(Request $r)
    {
        $data = $r->validate([
            'metodo_pago' => 'required|in:efectivo,transferencia',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.cantidad' => 'required|integer|min:1|max:200',
            'items.*.cucharas_extra' => 'nullable|integer|min:0|max:200',
        ]);
        $user = $r->user();

        return DB::transaction(function () use ($data, $user) {
            $cucharaId = Insumo::where('nombre', Insumo::CUCHARA)->value('id');
            $ventas = [];

            foreach ($data['items'] as $it) {
                $producto = Producto::with('insumos')->where('activo', true)->findOrFail($it['producto_id']);
                $qty = (int) $it['cantidad'];
                $extra = (int) ($it['cucharas_extra'] ?? 0);

                $requerido = [];
                foreach ($producto->insumos as $insumo) {
                    $requerido[$insumo->id] = ($requerido[$insumo->id] ?? 0) + $insumo->pivot->cantidad_requerida * $qty;
                }
                if ($extra > 0 && $cucharaId) {
                    $requerido[$cucharaId] = ($requerido[$cucharaId] ?? 0) + $extra;
                }

                $costo = 0;
                foreach ($requerido as $insumoId => $necesario) {
                    $insumo = Insumo::findOrFail($insumoId);
                    $inv = InventarioVendedor::where('user_id', $user->id)->where('insumo_id', $insumoId)
                        ->lockForUpdate()->first();
                    if (!$inv || $inv->cantidad_asignada + 0.00001 < $necesario) {
                        abort(422, "No tienes suficiente {$insumo->nombre} asignado para vender {$producto->nombre}.");
                    }
                    $inv->decrement('cantidad_asignada', $necesario);
                    $costo += $necesario * $insumo->costo_unitario;
                }

                $ventas[] = Venta::create([
                    'user_id' => $user->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $qty,
                    'metodo_pago' => $data['metodo_pago'],
                    'cucharas_extra' => $extra,
                    'total_venta' => $producto->precio_venta * $qty,
                    'comision_generada' => $producto->comision * $qty,
                    'costo_total' => round($costo, 4),
                    'fecha' => now(),
                ])->load('producto:id,nombre');
            }

            return response()->json($ventas, 201);
        });
    }
}
