<?php
namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\InventarioVendedor;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    /** Ventas del turno en curso (aún sin corte). */
    public function index(Request $r)
    {
        return Venta::with('producto:id,nombre,tamano')->where('user_id', $r->user()->id)
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
                abort_unless(
                    $producto->insumos->contains(fn ($i) => str_starts_with($i->nombre, 'Boing ')),
                    422, "{$producto->nombre} no tiene su Boing en la receta. Avisa al administrador."
                );
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

                $venta = Venta::create([
                    'user_id' => $user->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $qty,
                    'metodo_pago' => $data['metodo_pago'],
                    'cucharas_extra' => $extra,
                    'total_venta' => $producto->precio_venta * $qty,
                    'comision_generada' => $producto->comision * $qty,
                    'costo_total' => round($costo, 4),
                    'fecha' => now(),
                ]);

                foreach ($requerido as $insumoId => $necesario) {
                    DB::table('venta_consumos')->insert(['venta_id' => $venta->id, 'insumo_id' => $insumoId, 'cantidad' => $necesario]);
                }
                $ventas[] = $venta->load('producto:id,nombre,tamano');
            }

            return response()->json($ventas, 201);
        });
    }

    /** El vendedor elimina una venta hecha por error: los insumos regresan a su inventario. */
    public function destroy(Request $r, Venta $venta)
    {
        abort_unless($venta->user_id === $r->user()->id, 403, 'Esta venta no es tuya.');
        abort_if($venta->corte_id !== null, 422, 'Esta venta ya está dentro de un corte y no se puede eliminar.');

        DB::transaction(function () use ($venta) {
            $devolver = DB::table('venta_consumos')->where('venta_id', $venta->id)->pluck('cantidad', 'insumo_id')->all();

            if (!$devolver) { // ventas anteriores sin registro: se reconstruye con la receta actual
                $venta->load('producto.insumos');
                foreach ($venta->producto->insumos as $i) {
                    $devolver[$i->id] = $i->pivot->cantidad_requerida * $venta->cantidad;
                }
                if ($venta->cucharas_extra > 0 && ($c = Insumo::where('nombre', Insumo::CUCHARA)->value('id'))) {
                    $devolver[$c] = ($devolver[$c] ?? 0) + $venta->cucharas_extra;
                }
            }

            foreach ($devolver as $insumoId => $cantidad) {
                $inv = InventarioVendedor::firstOrCreate(
                    ['user_id' => $venta->user_id, 'insumo_id' => $insumoId], ['cantidad_asignada' => 0]
                );
                $inv->increment('cantidad_asignada', $cantidad);
            }

            DB::table('ventas_eliminadas')->insert([
                'user_id' => $venta->user_id,
                'producto' => Producto::where('id', $venta->producto_id)->value('nombre') ?? 'Producto',
                'cantidad' => $venta->cantidad, 'metodo_pago' => $venta->metodo_pago,
                'total_venta' => $venta->total_venta, 'comision_generada' => $venta->comision_generada,
                'fecha_venta' => $venta->fecha, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $venta->delete();
        });

        return response()->noContent();
    }
}
