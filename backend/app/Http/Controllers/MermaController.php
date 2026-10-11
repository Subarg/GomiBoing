<?php
namespace App\Http\Controllers;

use App\Models\InventarioVendedor;
use App\Models\Merma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MermaController extends Controller
{
    public function index(Request $r)
    {
        return Merma::with('insumo:id,nombre,unidad_medida')->where('user_id', $r->user()->id)
            ->latest('id')->limit(50)->get();
    }

    /** Da de baja un insumo dañado: se descuenta del vendedor, costo $0, sin comisión. */
    public function store(Request $r)
    {
        $data = $r->validate([
            'insumo_id' => 'required|exists:insumos,id',
            'cantidad' => 'required|numeric|min:0.01',
            'motivo' => 'required|string|max:200',
        ]);

        return DB::transaction(function () use ($data, $r) {
            $inv = InventarioVendedor::where('user_id', $r->user()->id)
                ->where('insumo_id', $data['insumo_id'])->lockForUpdate()->first();
            abort_if(!$inv || $inv->cantidad_asignada < $data['cantidad'], 422, 'No tienes esa cantidad en tu inventario.');

            $inv->decrement('cantidad_asignada', $data['cantidad']);
            $merma = Merma::create($data + ['user_id' => $r->user()->id, 'costo' => 0, 'fecha' => now()]);

            return response()->json($merma->load('insumo:id,nombre,unidad_medida'), 201);
        });
    }
}
