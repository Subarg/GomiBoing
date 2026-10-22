<?php
namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogoController extends Controller
{
    /**
     * Crea un sabor nuevo: un producto por tamaño con SU Boing y la receta (sin Boing) copiada de un sabor base.
     * Si el producto ya existía (p. ej. desactivado), lo reactiva y le asigna su Boing.
     */
    public function storeSabor(Request $r)
    {
        $data = $r->validate([
            'sabor' => 'required|string|max:40',
            'base_sabor' => 'required|string|exists:productos,sabor',
            'tamanos' => 'required|array|min:1',
            'tamanos.*' => 'in:250,500',
            'precio_250' => 'nullable|numeric|min:0', 'comision_250' => 'nullable|numeric|min:0',
            'precio_500' => 'nullable|numeric|min:0', 'comision_500' => 'nullable|numeric|min:0',
        ]);
        $sabor = trim($data['sabor']);

        DB::transaction(function () use ($data, $sabor) {
            foreach ($data['tamanos'] as $ml) {
                $ml = (int) $ml;
                $base = Producto::with('insumos')->where('sabor', $data['base_sabor'])->where('tamano', $ml)->first();
                abort_if(!$base, 422, "El sabor base no tiene presentación de {$ml} ml.");

                $boing = Insumo::firstOrCreate(['nombre' => "Boing $sabor {$ml}ml"], [
                    'unidad_medida' => 'pza', 'stock_minimo' => 12,
                    'presentacion' => 'Caja', 'presentacion_cantidad' => Insumo::cajaBoing($ml),
                ]);

                $producto = Producto::firstOrNew(['nombre' => "Gomiboing $sabor {$ml}ml"]);
                $nuevo = !$producto->exists;
                if ($nuevo) {
                    $producto->sabor = $sabor;
                    $producto->tamano = $ml;
                    $producto->precio_venta = $data["precio_$ml"] ?? $base->precio_venta;
                    $producto->comision = $data["comision_$ml"] ?? $base->comision;
                }
                $producto->activo = true;
                $producto->boing_insumo_id = $boing->id;
                $producto->save();

                if ($nuevo) {
                    foreach ($base->insumos as $i) {
                        if (!Insumo::esBoing($i->nombre)) {
                            $producto->insumos()->attach($i->id, ['cantidad_requerida' => $i->pivot->cantidad_requerida]);
                        }
                    }
                }
            }
        });

        return response()->json(['message' => "Sabor $sabor listo."], 201);
    }

    public function update(Request $r, Producto $producto)
    {
        $data = $r->validate([
            'activo' => 'sometimes|boolean',
            'precio_venta' => 'sometimes|numeric|min:0',
            'comision' => 'sometimes|numeric|min:0',
            'boing_insumo_id' => 'sometimes|nullable|exists:insumos,id',
        ]);
        if (!empty($data['boing_insumo_id'])) {
            abort_unless(Insumo::esBoing(Insumo::findOrFail($data['boing_insumo_id'])->nombre), 422, 'Elige un insumo de tipo Boing.');
        }
        $producto->update($data);
        return $producto->only('id', 'nombre', 'activo', 'precio_venta', 'comision', 'boing_insumo_id');
    }

    public function destroy(Producto $producto)
    {
        abort_if(Venta::where('producto_id', $producto->id)->exists(), 422,
            'Este producto ya tiene ventas registradas. Desactívalo en lugar de eliminarlo.');
        $producto->delete();
        return response()->noContent();
    }

    /**
     * Saneamiento: quita de las recetas cualquier Boing (ya no van ahí) y asigna a cada producto
     * su Boing propio cuando le falta y existe un insumo "Boing {sabor} {tamaño}ml".
     */
    public function reparar()
    {
        $quitados = 0;
        $asignados = 0;
        DB::transaction(function () use (&$quitados, &$asignados) {
            foreach (Producto::with('insumos')->get() as $p) {
                foreach ($p->insumos as $i) {
                    if (Insumo::esBoing($i->nombre)) { $p->insumos()->detach($i->id); $quitados++; }
                }
                if (!$p->boing_insumo_id) {
                    $propio = Insumo::where('nombre', "Boing {$p->sabor} {$p->tamano}ml")->first();
                    if ($propio) { $p->update(['boing_insumo_id' => $propio->id]); $asignados++; }
                }
            }
        });
        return ['quitados' => $quitados, 'asignados' => $asignados];
    }
}
