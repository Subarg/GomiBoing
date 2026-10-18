<?php
namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecetaController extends Controller
{
    private function formato(Producto $p): array
    {
        return [
            'id' => $p->id, 'nombre' => $p->nombre, 'sabor' => $p->sabor, 'tamano' => $p->tamano,
            'activo' => (bool) $p->activo, 'precio_venta' => $p->precio_venta, 'comision' => $p->comision,
            'receta' => $p->insumos->map(fn ($i) => [
                'insumo_id' => $i->id, 'nombre' => $i->nombre, 'unidad_medida' => $i->unidad_medida,
                'cantidad' => (float) $i->pivot->cantidad_requerida,
            ])->sortBy('nombre')->values(),
        ];
    }

    public function index()
    {
        return Producto::with('insumos')->orderBy('tamano')->orderBy('sabor')->get()->map(fn ($p) => $this->formato($p));
    }

    /** Reemplaza la receta completa de un producto. */
    public function update(Request $r, Producto $producto)
    {
        $data = $r->validate([
            'items' => 'required|array|min:1',
            'items.*.insumo_id' => 'required|exists:insumos,id|distinct',
            'items.*.cantidad' => 'required|numeric|min:0.0001',
        ]);

        $producto->insumos()->sync(
            collect($data['items'])->mapWithKeys(fn ($i) => [$i['insumo_id'] => ['cantidad_requerida' => $i['cantidad']]])->all()
        );
        return $this->formato($producto->load('insumos'));
    }

    /**
     * Cambios en bloque para ocasiones especiales:
     *  - agregar: agrega (o actualiza la cantidad de) un insumo en las recetas
     *  - quitar: retira un insumo de las recetas (ej. palitos de tamarindo)
     *  - reemplazar: cambia un insumo por otro (ej. gomitas normales por tiburón)
     */
    public function masivo(Request $r)
    {
        $data = $r->validate([
            'accion' => 'required|in:agregar,quitar,reemplazar',
            'insumo_id' => 'required|exists:insumos,id',
            'nuevo_insumo_id' => 'required_if:accion,reemplazar|nullable|exists:insumos,id|different:insumo_id',
            'cantidad' => 'required_if:accion,agregar|nullable|numeric|min:0.0001',
            'tamano' => 'nullable|in:250,500',
        ]);

        $afectados = DB::transaction(function () use ($data) {
            $productos = Producto::with('insumos')->when($data['tamano'] ?? null, fn ($q, $t) => $q->where('tamano', $t))->get();
            $n = 0;
            foreach ($productos as $p) {
                $actual = $p->insumos->firstWhere('id', $data['insumo_id']);
                switch ($data['accion']) {
                    case 'agregar':
                        $p->insumos()->syncWithoutDetaching([$data['insumo_id'] => ['cantidad_requerida' => $data['cantidad']]]);
                        $n++;
                        break;
                    case 'quitar':
                        if ($actual) { $p->insumos()->detach($data['insumo_id']); $n++; }
                        break;
                    case 'reemplazar':
                        if ($actual) {
                            $cant = $data['cantidad'] ?? $actual->pivot->cantidad_requerida;
                            $p->insumos()->detach($data['insumo_id']);
                            $p->insumos()->syncWithoutDetaching([$data['nuevo_insumo_id'] => ['cantidad_requerida' => $cant]]);
                            $n++;
                        }
                        break;
                }
            }
            return $n;
        });

        return ['afectados' => $afectados];
    }
}
