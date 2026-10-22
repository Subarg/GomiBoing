<?php
namespace App\Services;

use App\Models\Insumo;
use App\Models\InventarioVendedor;
use App\Models\Producto;

/**
 * Única fuente de verdad de "qué se puede preparar".
 * Un vaso consume: los insumos de la receta (compartidos entre sabores) + 1 pza de SU Boing.
 */
class Disponibilidad
{
    /** [insumo_id => cantidad por vaso]. Ignora cualquier Boing que aparezca en la receta y usa el propio del producto. */
    public static function lineas(Producto $p): array
    {
        $l = [];
        foreach ($p->insumos as $i) {
            if (Insumo::esBoing($i->nombre)) continue;
            $l[$i->id] = (float) $i->pivot->cantidad_requerida;
        }
        if ($p->boing_insumo_id) $l[(int) $p->boing_insumo_id] = 1.0;
        return $l;
    }

    public static function stockDe(int $uid): array
    {
        return InventarioVendedor::where('user_id', $uid)->pluck('cantidad_asignada', 'insumo_id')
            ->map(fn ($v) => (float) $v)->all();
    }

    /** Cuántos vasos completos alcanzan con ese stock. */
    public static function capacidad(array $lineas, array $stock): int
    {
        if (!$lineas) return 0;
        $max = PHP_INT_MAX;
        foreach ($lineas as $id => $req) {
            if ($req <= 0) continue;
            $max = min($max, (int) floor((($stock[$id] ?? 0) + 0.00001) / $req));
        }
        return $max === PHP_INT_MAX ? 0 : $max;
    }

    /** Nombres de los insumos que no alcanzan para un vaso. */
    public static function faltan(array $lineas, array $stock, array $nombres): array
    {
        $f = [];
        foreach ($lineas as $id => $req) {
            if (($stock[$id] ?? 0) + 0.00001 < $req) $f[] = $nombres[$id] ?? "Insumo $id";
        }
        return $f;
    }

    /** Resumen del lote de un vendedor: vasos por producto y cuánto alcanza cada insumo compartido. */
    public static function resumen(int $uid): array
    {
        $stock = self::stockDe($uid);
        $nombres = Insumo::pluck('nombre', 'id')->all();
        $unidades = Insumo::pluck('unidad_medida', 'id')->all();

        $productos = [];
        $uso = [];
        $boings = 0.0;

        foreach (Producto::with('insumos')->where('activo', true)->orderBy('tamano')->orderBy('sabor')->get() as $p) {
            $l = self::lineas($p);
            $productos[] = [
                'id' => $p->id, 'nombre' => $p->nombre, 'sabor' => $p->sabor, 'tamano' => $p->tamano,
                'disponible' => self::capacidad($l, $stock), 'faltan' => self::faltan($l, $stock, $nombres),
            ];
            foreach ($l as $id => $req) {
                if ((int) $id === (int) $p->boing_insumo_id) { $boings += $stock[$id] ?? 0; continue; }
                $uso[$id]['n'] = ($uso[$id]['n'] ?? 0) + 1;
                $uso[$id]['max'] = max($uso[$id]['max'] ?? 0, $req);
            }
        }

        $compartidos = [];
        foreach ($uso as $id => $u) {
            if ($u['n'] < 2) continue; // los exclusivos de un sabor (ej. gomita de mango) se ven en su producto
            $s = $stock[$id] ?? 0;
            $compartidos[] = [
                'insumo_id' => $id, 'nombre' => $nombres[$id] ?? '', 'unidad_medida' => $unidades[$id] ?? '',
                'stock' => $s, 'vasos' => (int) floor(($s + 0.00001) / $u['max']),
            ];
        }
        usort($compartidos, fn ($a, $b) => $a['vasos'] <=> $b['vasos']);

        $minCompartido = $compartidos ? $compartidos[0]['vasos'] : PHP_INT_MAX;
        $vasosTotal = (int) min($minCompartido, floor($boings));
        $limitan = [];
        if ($compartidos && $minCompartido <= floor($boings)) {
            $limitan = array_values(array_map(fn ($c) => $c['nombre'], array_filter($compartidos, fn ($c) => $c['vasos'] === $minCompartido)));
        } elseif ($boings >= 0) {
            $limitan = ['Boing'];
        }

        return [
            'productos' => $productos, 'compartidos' => $compartidos,
            'boings' => $boings, 'vasos_total' => $vasosTotal, 'limitan' => $limitan,
        ];
    }
}
