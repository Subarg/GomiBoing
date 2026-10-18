<?php
namespace App\Http\Controllers;

use App\Models\InventarioVendedor;
use App\Models\Producto;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function index()
    {
        return Producto::where('activo', true)->orderBy('tamano')->orderBy('sabor')
            ->get(['id', 'nombre', 'sabor', 'tamano', 'precio_venta', 'comision']);
    }

    /**
     * Para el POS: todos los productos activos con las piezas que el vendedor puede preparar
     * con su lote (`disponible`) y los insumos que le faltan (`faltan`).
     * El POS solo muestra los que tienen disponible >= 1 y usa `faltan` para explicar por qué no puede vender.
     */
    public function disponibles(Request $r)
    {
        $stock = InventarioVendedor::where('user_id', $r->user()->id)->pluck('cantidad_asignada', 'insumo_id');

        return Producto::with('insumos')->where('activo', true)->orderBy('tamano')->orderBy('sabor')->get()
            ->map(function ($p) use ($stock) {
                $max = PHP_INT_MAX;
                $faltan = [];
                foreach ($p->insumos as $i) {
                    $req = (float) $i->pivot->cantidad_requerida;
                    if ($req <= 0) continue;
                    $cap = (int) floor((($stock[$i->id] ?? 0) + 0.00001) / $req);
                    if ($cap < 1) $faltan[] = $i->nombre;
                    $max = min($max, $cap);
                }
                $tieneBoing = $p->insumos->contains(fn ($i) => str_starts_with($i->nombre, 'Boing '));
                if (!$tieneBoing || $max === PHP_INT_MAX) { $max = 0; $faltan[] = 'Boing'; }

                return [
                    'id' => $p->id, 'nombre' => $p->nombre, 'sabor' => $p->sabor, 'tamano' => $p->tamano,
                    'precio_venta' => $p->precio_venta, 'comision' => $p->comision,
                    'disponible' => $max, 'faltan' => $faltan,
                ];
            })->values();
    }
}
