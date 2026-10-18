<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Fondo de caja (cambio del día) que el admin le entrega al vendedor
        Schema::create('fondos_caja', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('corte_id')->nullable()->constrained('cortes')->nullOnDelete();
            $t->decimal('monto', 10, 2)->default(0);
            $t->timestamps();
        });

        Schema::table('cortes', function (Blueprint $t) {
            $t->decimal('saldo_inicial', 12, 2)->default(0);
            $t->unsignedInteger('unidades_250')->default(0);
            $t->unsignedInteger('unidades_500')->default(0);
            $t->decimal('entregar', 12, 2)->default(0); // con signo: negativo = el admin paga al vendedor
        });

        // Cajas de Boing: 250ml = 27 pzas, 500ml = 24 pzas (también en bases ya sembradas)
        DB::table('insumos')->where('nombre', 'like', 'Boing % 250ml')->update(['presentacion' => 'Caja', 'presentacion_cantidad' => 27]);
        DB::table('insumos')->where('nombre', 'like', 'Boing % 500ml')->update(['presentacion' => 'Caja', 'presentacion_cantidad' => 24]);
    }

    public function down(): void {
        Schema::table('cortes', fn (Blueprint $t) => $t->dropColumn(['saldo_inicial', 'unidades_250', 'unidades_500', 'entregar']));
        Schema::dropIfExists('fondos_caja');
    }
};
