<?php
namespace Database\Seeders;

use App\Models\Insumo;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Solo siembra una base vacía: así un reinicio no revierte recetas, contraseñas ni insumos editados.
        if (Producto::exists()) {
            return;
        }

        User::updateOrCreate(['email' => 'admin@gomiboing.test'], [
            'name' => 'Administrador', 'password' => env('SEED_ADMIN_PASSWORD', 'admin1234'), 'rol' => 'admin', 'activo' => true,
        ]);
        User::updateOrCreate(['email' => 'vendedor@gomiboing.test'], [
            'name' => 'Vendedor Demo', 'password' => env('SEED_VENDEDOR_PASSWORD', 'vendedor1234'), 'rol' => 'vendedor', 'activo' => true,
        ]);

        // nombre => [unidad, mínimo, presentación, cantidad por presentación]
        $base = [
            'Gomitas'            => ['gr',  2000, 'Kilo',    1000],
            'Gomita de Mango'    => ['pza', 100,  'Bolsa',   100],
            'Tubo de Tamarindo'  => ['pza', 50,   'Bote',    100],
            'Chamoy'             => ['ml',  3000, 'Botella 1.5L', 1500],
            'Tajín'              => ['gr',  400,  'Bote 400g', 400],
            Insumo::CUCHARA      => ['pza', 100,  'Paquete',  100],
        ];
        $insumos = [];
        foreach ($base as $nombre => [$u, $min, $pres, $cant]) {
            $insumos[$nombre] = Insumo::firstOrCreate(['nombre' => $nombre], [
                'unidad_medida' => $u, 'stock_minimo' => $min, 'presentacion' => $pres, 'presentacion_cantidad' => $cant,
            ]);
        }

        $tamanos = [250 => ['precio' => 30, 'comision' => 6, 'mango' => 1], 500 => ['precio' => 45, 'comision' => 7, 'mango' => 2]];

        foreach (['Manzana', 'Mango', 'Uva', 'Guayaba'] as $sabor) {
            foreach ($tamanos as $ml => $cfg) {
                // Caja de Boing: 250ml trae 27 pzas y 500ml trae 24 pzas
                $boing = Insumo::firstOrCreate(['nombre' => "Boing $sabor {$ml}ml"], [
                    'unidad_medida' => 'pza', 'stock_minimo' => 12,
                    'presentacion' => 'Caja', 'presentacion_cantidad' => Insumo::cajaBoing($ml),
                ]);

                $producto = Producto::firstOrCreate(['nombre' => "Gomiboing $sabor {$ml}ml"], [
                    'sabor' => $sabor, 'tamano' => $ml, 'precio_venta' => $cfg['precio'], 'comision' => $cfg['comision'], 'activo' => true,
                ]);

                $receta = [
                    $insumos['Gomitas']->id => 20,
                    $insumos['Tubo de Tamarindo']->id => 1,
                    $insumos['Chamoy']->id => 30,
                    $insumos['Tajín']->id => 1.6,
                    $insumos[Insumo::CUCHARA]->id => 1,
                    $boing->id => 1,
                ];
                if ($sabor === 'Mango') {
                    $receta[$insumos['Gomita de Mango']->id] = $cfg['mango'];
                }
                $producto->insumos()->syncWithoutDetaching(
                    collect($receta)->map(fn ($c) => ['cantidad_requerida' => $c])->all()
                );
            }
        }
    }
}
