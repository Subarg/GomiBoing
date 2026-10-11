<?php
namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\InventarioVendedor;
use App\Models\Merma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InsumoController extends Controller
{
    private function rules(?int $id = null): array
    {
        return [
            'nombre' => 'required|string|max:120|unique:insumos,nombre' . ($id ? ",$id" : ''),
            'unidad_medida' => 'required|in:gr,ml,pza',
            'stock_minimo' => 'required|numeric|min:0',
            'stock_almacen' => 'nullable|numeric|min:0', // ajuste manual de existencia
            'presentacion' => 'nullable|string|max:60',
            'presentacion_cantidad' => 'required|numeric|min:0.01',
        ];
    }

    public function index() { return Insumo::orderBy('nombre')->get(); }

    public function store(Request $r)
    {
        $data = $r->validate($this->rules());
        $data['stock_almacen'] = $data['stock_almacen'] ?? 0;
        return response()->json(Insumo::create($data), 201);
    }

    public function update(Request $r, Insumo $insumo)
    {
        $data = $r->validate($this->rules($insumo->id));
        // El nombre "Cuchara" lo usa el POS para las cucharas extra: no se renombra.
        if ($insumo->nombre === Insumo::CUCHARA) $data['nombre'] = Insumo::CUCHARA;
        if (!array_key_exists('stock_almacen', $data) || $data['stock_almacen'] === null) unset($data['stock_almacen']);
        $insumo->update($data);
        return $insumo->fresh();
    }

    /** Elimina el insumo y lo quita de todas las recetas. Se bloquea si algún vendedor lo tiene asignado. */
    public function destroy(Insumo $insumo)
    {
        abort_if(
            InventarioVendedor::where('insumo_id', $insumo->id)->where('cantidad_asignada', '>', 0)->exists(),
            422, 'Algún vendedor todavía tiene este insumo asignado. Pide el corte o devuélvelo al almacén primero.'
        );
        DB::transaction(function () use ($insumo) {
            Merma::where('insumo_id', $insumo->id)->delete();
            $insumo->delete(); // recetas, compras e inventario vacío se eliminan en cascada
        });
        return response()->noContent();
    }
}
