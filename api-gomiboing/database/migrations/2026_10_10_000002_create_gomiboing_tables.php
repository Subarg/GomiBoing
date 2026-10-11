<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('insumos', function (Blueprint $t) {
            $t->id();
            $t->string('nombre')->unique();
            $t->enum('unidad_medida', ['gr', 'ml', 'pza']);
            $t->decimal('stock_almacen', 14, 2)->default(0);
            $t->decimal('stock_minimo', 14, 2)->default(0);
            $t->decimal('costo_unitario', 14, 4)->default(0); // costo promedio por unidad base
            $t->string('presentacion')->nullable();            // Ej. Kilo, Bote, Botella
            $t->decimal('presentacion_cantidad', 14, 2)->default(1); // Ej. 1000 gr por kilo
            $t->timestamps();
        });

        Schema::create('entradas_almacen', function (Blueprint $t) {
            $t->id();
            $t->foreignId('insumo_id')->constrained('insumos')->cascadeOnDelete();
            $t->decimal('cantidad_ingresada', 14, 2);
            $t->decimal('costo_total', 14, 2);
            $t->string('proveedor');
            $t->date('fecha');
            $t->timestamps();
        });

        Schema::create('productos', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('sabor');
            $t->unsignedInteger('tamano'); // ml
            $t->decimal('precio_venta', 10, 2);
            $t->decimal('comision', 10, 2);
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        Schema::create('recetas', function (Blueprint $t) {
            $t->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $t->foreignId('insumo_id')->constrained('insumos')->cascadeOnDelete();
            $t->decimal('cantidad_requerida', 14, 4);
            $t->primary(['producto_id', 'insumo_id']);
        });

        Schema::create('inventario_vendedor', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('insumo_id')->constrained('insumos')->cascadeOnDelete();
            $t->decimal('cantidad_asignada', 14, 2)->default(0); // saldo vigente
            $t->date('fecha_asignacion')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'insumo_id']);
        });

        Schema::create('cortes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->unsignedInteger('unidades')->default(0);
            $t->decimal('total_efectivo', 12, 2)->default(0);
            $t->decimal('total_transferencia', 12, 2)->default(0);
            $t->decimal('comision', 12, 2)->default(0);
            $t->boolean('sobrante_devuelto')->default(false);
            $t->timestamps();
        });

        Schema::create('ventas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('producto_id')->constrained('productos');
            $t->foreignId('corte_id')->nullable()->constrained('cortes')->nullOnDelete();
            $t->unsignedInteger('cantidad');
            $t->enum('metodo_pago', ['efectivo', 'transferencia']);
            $t->unsignedInteger('cucharas_extra')->default(0);
            $t->decimal('total_venta', 10, 2);
            $t->decimal('comision_generada', 10, 2);
            $t->decimal('costo_total', 12, 4)->default(0);
            $t->timestamp('fecha')->useCurrent();
            $t->timestamps();
            $t->index(['user_id', 'corte_id']);
        });

        Schema::create('mermas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('insumo_id')->constrained('insumos');
            $t->decimal('cantidad', 14, 2);
            $t->string('motivo');
            $t->decimal('costo', 10, 2)->default(0); // siempre $0 para el vendedor
            $t->timestamp('fecha')->useCurrent();
            $t->timestamps();
        });
    }

    public function down(): void {
        foreach (['mermas', 'ventas', 'cortes', 'inventario_vendedor', 'recetas', 'productos', 'entradas_almacen', 'insumos'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
