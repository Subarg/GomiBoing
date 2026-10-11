<?php
namespace App\Http\Controllers;

use App\Models\InventarioVendedor;
use App\Models\Insumo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsignacionController extends Controller
{
    private function validar(Request $r): array
    {
        return $r->validate([
            'user_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.insumo_id' => 'required|exists:insumos,id|distinct',
            'items.*.cantidad' => 'required|numeric|min:0.01',
        ]);
    }

    /** Transfiere insumos del almacén central al inventario virtual del vendedor. */
    public function store(Request $r)
    {
        $data = $this->validar($r);
        abort_unless(User::where('id', $data['user_id'])->where('rol', 'vendedor')->exists(), 422, 'El usuario no es vendedor.');

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $it) {
                $insumo = Insumo::lockForUpdate()->findOrFail($it['insumo_id']);
                abort_if($insumo->stock_almacen < $it['cantidad'], 422, "Stock insuficiente en almacén de {$insumo->nombre}.");
                $insumo->decrement('stock_almacen', $it['cantidad']);

                $inv = InventarioVendedor::firstOrCreate(
                    ['user_id' => $data['user_id'], 'insumo_id' => $insumo->id],
                    ['cantidad_asignada' => 0]
                );
                $inv->cantidad_asignada += $it['cantidad'];
                $inv->fecha_asignacion = now()->toDateString();
                $inv->save();
            }
        });

        return $this->inventario($data['user_id']);
    }

    /** Devuelve insumos del vendedor al almacén central. */
    public function devolver(Request $r)
    {
        $data = $this->validar($r);

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $it) {
                $inv = InventarioVendedor::where('user_id', $data['user_id'])
                    ->where('insumo_id', $it['insumo_id'])->lockForUpdate()->first();
                abort_if(!$inv || $inv->cantidad_asignada < $it['cantidad'], 422, 'El vendedor no tiene esa cantidad.');
                $inv->decrement('cantidad_asignada', $it['cantidad']);
                Insumo::where('id', $it['insumo_id'])->increment('stock_almacen', $it['cantidad']);
            }
        });

        return $this->inventario($data['user_id']);
    }

    public function inventario(int|User $user)
    {
        $id = $user instanceof User ? $user->id : $user;
        return InventarioVendedor::with('insumo:id,nombre,unidad_medida')
            ->where('user_id', $id)->get()->sortBy('insumo.nombre')->values();
    }
}
