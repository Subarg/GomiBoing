<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('proveedores', function (Blueprint $t) {
            $t->id();
            $t->string('nombre')->unique();
            $t->timestamps();
        });

        // Lo que consumió cada venta, para devolverlo al inventario si se elimina por error
        Schema::create('venta_consumos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $t->foreignId('insumo_id')->constrained('insumos')->cascadeOnDelete();
            $t->decimal('cantidad', 14, 4);
        });

        // Bitácora de ventas eliminadas por el vendedor (el admin puede revisarla)
        Schema::create('ventas_eliminadas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->string('producto');
            $t->unsignedInteger('cantidad');
            $t->enum('metodo_pago', ['efectivo', 'transferencia']);
            $t->decimal('total_venta', 10, 2);
            $t->decimal('comision_generada', 10, 2);
            $t->timestamp('fecha_venta')->nullable();
            $t->timestamps();
        });

        Schema::table('cortes', function (Blueprint $t) {
            $t->decimal('total_250', 12, 2)->default(0);
            $t->decimal('total_500', 12, 2)->default(0);
            $t->decimal('comision_250', 12, 2)->default(0);
            $t->decimal('comision_500', 12, 2)->default(0);
        });

        // Proveedores que ya aparecen en compras anteriores
        $nombres = DB::table('entradas_almacen')->distinct()->pluck('proveedor')
            ->map(fn ($n) => trim(str_replace('(surtido)', '', $n)))->filter()->unique();
        foreach ($nombres as $n) {
            DB::table('proveedores')->insertOrIgnore(['nombre' => $n, 'created_at' => now(), 'updated_at' => now()]);
        }

        // Productos activos sin ningún Boing en su receta (p. ej. se eliminó el Boing de ese sabor):
        // se desactivan para que el POS no calcule piezas disponibles sin ese insumo.
        $huerfanos = DB::table('productos')->where('activo', true)->whereNotExists(function ($q) {
            $q->select(DB::raw(1))->from('recetas')->join('insumos', 'insumos.id', '=', 'recetas.insumo_id')
                ->whereColumn('recetas.producto_id', 'productos.id')->where('insumos.nombre', 'like', 'Boing %');
        })->pluck('id');
        DB::table('productos')->whereIn('id', $huerfanos)->update(['activo' => false]);
    }

    public function down(): void {
        Schema::table('cortes', fn (Blueprint $t) => $t->dropColumn(['total_250', 'total_500', 'comision_250', 'comision_500']));
        Schema::dropIfExists('ventas_eliminadas');
        Schema::dropIfExists('venta_consumos');
        Schema::dropIfExists('proveedores');
    }
};
