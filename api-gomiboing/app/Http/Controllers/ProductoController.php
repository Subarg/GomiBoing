<?php
namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\InventarioVendedor;
use App\Models\Producto;
use App\Models\User;
use App\Services\Disponibilidad;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function index()
    {
        return Producto::where('activo', true)->orderBy('tamano')->orderBy('sabor')
            ->get(['id', 'nombre', 'sabor', 'tamano', 'precio_venta', 'comision']);
    }

    /**
     * Datos para el POS: productos con las líneas de su receta (insumos compartidos + su Boing),
     * el stock del vendedor y los nombres de los insumos. El POS calcula en vivo lo que alcanza
     * descontando lo que ya está en la cuenta.
     */
    public function disponibles(Request $r)
    {
        $uid = $r->user()->id;
        $stock = Disponibilidad::stockDe($uid);
        $nombres = Insumo::pluck('nombre', 'id')->all();

        $productos = Producto::with('insumos')->where('activo', true)->orderBy('tamano')->orderBy('sabor')->get()
            ->map(function ($p) use ($stock, $nombres) {
                $l = Disponibilidad::lineas($p);
                return [
                    'id' => $p->id, 'nombre' => $p->nombre, 'sabor' => $p->sabor, 'tamano' => $p->tamano,
                    'precio_venta' => $p->precio_venta, 'comision' => $p->comision,
                    'boing_insumo_id' => $p->boing_insumo_id ? (int) $p->boing_insumo_id : null,
                    'lineas' => collect($l)->map(fn ($c, $id) => ['insumo_id' => (int) $id, 'cantidad' => $c])->values(),
                    'disponible' => Disponibilidad::capacidad($l, $stock),
                    'faltan' => Disponibilidad::faltan($l, $stock, $nombres),
                ];
            })->values();

        return [
            'productos' => $productos,
            'cuchara_insumo_id' => Insumo::where('nombre', Insumo::CUCHARA)->value('id'),
            'stock' => collect($stock)->map(fn ($c, $id) => ['insumo_id' => (int) $id, 'cantidad' => $c])->values(),
            'insumos' => Insumo::get(['id', 'nombre', 'unidad_medida']),
        ];
    }

    /** Capacidad del lote del propio vendedor. */
    public function capacidad(Request $r)
    {
        return Disponibilidad::resumen($r->user()->id);
    }

    /** Capacidad del lote de un vendedor (admin). */
    public function capacidadVendedor(User $user)
    {
        return Disponibilidad::resumen($user->id);
    }
}
