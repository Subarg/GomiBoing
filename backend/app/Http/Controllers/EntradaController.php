<?php
namespace App\Http\Controllers;

use App\Models\EntradaAlmacen;
use App\Models\Insumo;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntradaController extends Controller
{
    public function index()
    {
        return EntradaAlmacen::with('insumo:id,nombre,unidad_medida')->latest('fecha')->latest('id')->limit(100)->get();
    }

    /** Suma al almacén y recalcula el costo unitario promedio ponderado. Debe ir dentro de una transacción. */
    private function registrar(int $insumoId, float $cantidad, float $costo, string $proveedor, string $fecha): EntradaAlmacen
    {
        $insumo = Insumo::lockForUpdate()->findOrFail($insumoId);
        $entrada = EntradaAlmacen::create([
            'insumo_id' => $insumo->id, 'cantidad_ingresada' => $cantidad,
            'costo_total' => $costo, 'proveedor' => $proveedor, 'fecha' => $fecha,
        ]);

        $previo = max($insumo->stock_almacen, 0);
        $nuevo = $previo + $cantidad;
        $insumo->costo_unitario = (($previo * $insumo->costo_unitario) + $costo) / $nuevo;
        $insumo->stock_almacen = $insumo->stock_almacen + $cantidad;
        $insumo->save();

        return $entrada->load('insumo:id,nombre,unidad_medida');
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'insumo_id' => 'required|exists:insumos,id',
            'cantidad_ingresada' => 'required|numeric|min:0.01',
            'costo_total' => 'required|numeric|min:0',
            'proveedor' => 'required|string|max:120',
            'fecha' => 'nullable|date',
        ]);

        Proveedor::firstOrCreate(['nombre' => trim($data['proveedor'])]);
        $entrada = DB::transaction(fn () => $this->registrar(
            $data['insumo_id'], $data['cantidad_ingresada'], $data['costo_total'],
            $data['proveedor'], $data['fecha'] ?? now()->toDateString()
        ));
        return response()->json($entrada, 201);
    }

    /**
     * Compra de Boing surtido: una caja con varios sabores.
     * El costo se reparte entre sabores en proporción a las piezas de cada uno.
     */
    public function surtido(Request $r)
    {
        $data = $r->validate([
            'tamano' => 'required|in:250,500',
            'proveedor' => 'required|string|max:120',
            'costo_total' => 'required|numeric|min:0',
            'fecha' => 'nullable|date',
            'sabores' => 'required|array|min:1',
            'sabores.*.sabor' => 'required|string',
            'sabores.*.cantidad' => 'required|integer|min:0',
        ]);

        $lineas = collect($data['sabores'])->filter(fn ($s) => $s['cantidad'] > 0)->values();
        abort_if($lineas->isEmpty(), 422, 'Captura al menos un sabor con piezas.');

        $ml = (int) $data['tamano'];
        foreach ($lineas as $l) {
            abort_unless(
                Producto::where('activo', true)->where('sabor', $l['sabor'])->where('tamano', $ml)->exists(),
                422, "El sabor {$l['sabor']} no está activo en {$ml} ml."
            );
        }
        Proveedor::firstOrCreate(['nombre' => trim($data['proveedor'])]);
        $totalPzas = $lineas->sum('cantidad');
        $fecha = $data['fecha'] ?? now()->toDateString();

        $entradas = DB::transaction(function () use ($lineas, $ml, $totalPzas, $data, $fecha) {
            $restante = round($data['costo_total'], 2);
            $out = [];
            foreach ($lineas as $idx => $l) {
                $esUltimo = $idx === $lineas->count() - 1;
                $costo = $esUltimo ? $restante : round($data['costo_total'] * $l['cantidad'] / $totalPzas, 2);
                $restante = round($restante - $costo, 2);

                $insumo = Insumo::firstOrCreate(['nombre' => "Boing {$l['sabor']} {$ml}ml"], [
                    'unidad_medida' => 'pza', 'stock_minimo' => 12,
                    'presentacion' => 'Caja', 'presentacion_cantidad' => Insumo::cajaBoing($ml),
                ]);
                $out[] = $this->registrar($insumo->id, $l['cantidad'], $costo, $data['proveedor'], $fecha);
            }
            return $out;
        });

        return response()->json($entradas, 201);
    }
}
