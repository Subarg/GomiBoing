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
     * Crea un sabor nuevo: un producto por tamaño, con receta copiada de un sabor base
     * (cambiando el Boing por el del sabor nuevo). Si el producto existía desactivado, lo reactiva.
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
                $producto->save();

                if ($nuevo) {
                    foreach ($base->insumos as $i) {
                        if (!str_starts_with($i->nombre, 'Boing ')) {
                            $producto->insumos()->attach($i->id, ['cantidad_requerida' => $i->pivot->cantidad_requerida]);
                        }
                    }
                }
                $cantBoing = $base->insumos->first(fn ($i) => str_starts_with($i->nombre, 'Boing '))?->pivot->cantidad_requerida ?? 1;
                $producto->insumos()->syncWithoutDetaching([$boing->id => ['cantidad_requerida' => $cantBoing]]);
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
        ]);
        $producto->update($data);
        return $producto->only('id', 'nombre', 'activo', 'precio_venta', 'comision');
    }

    public function destroy(Producto $producto)
    {
        abort_if(Venta::where('producto_id', $producto->id)->exists(), 422,
            'Este producto ya tiene ventas registradas. Desactívalo en lugar de eliminarlo.');
        $producto->delete();
        return response()->noContent();
    }

    /**
     * Corrige recetas con Boing equivocado: cada producto debe llevar solo el Boing de su sabor y tamaño.
     * Quita los Boing ajenos y agrega el propio si el insumo existe. No cambia qué productos están activos.
     */
    public function reparar()
    {
        $quitados = 0;
        $agregados = 0;
        DB::transaction(function () use (&$quitados, &$agregados) {
            foreach (Producto::with('insumos')->get() as $p) {
                $propio = Insumo::where('nombre', "Boing {$p->sabor} {$p->tamano}ml")->first();
                foreach ($p->insumos as $i) {
                    if (str_starts_with($i->nombre, 'Boing ') && (!$propio || $i->id !== $propio->id)) {
                        $p->insumos()->detach($i->id);
                        $quitados++;
                    }
                }
                if ($propio && !$p->insumos->contains('id', $propio->id)) {
                    $p->insumos()->syncWithoutDetaching([$propio->id => ['cantidad_requerida' => 1]]);
                    $agregados++;
                }
            }
        });
        return ['quitados' => $quitados, 'agregados' => $agregados];
    }
}
