<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Cada producto apunta a SU Boing (1 pza por vaso). Ya no vive como renglón de la receta.
        Schema::table('productos', function (Blueprint $t) {
            $t->foreignId('boing_insumo_id')->nullable()->constrained('insumos')->nullOnDelete();
        });

        foreach (DB::table('productos')->get() as $p) {
            $id = DB::table('insumos')->where('nombre', "Boing {$p->sabor} {$p->tamano}ml")->value('id');
            if ($id) DB::table('productos')->where('id', $p->id)->update(['boing_insumo_id' => $id]);
        }

        // Limpia cualquier Boing que haya quedado como renglón de receta (incluidos los de otros sabores)
        $boings = DB::table('insumos')->where('nombre', 'like', 'Boing %')->pluck('id');
        DB::table('recetas')->whereIn('insumo_id', $boings)->delete();
    }

    public function down(): void {
        Schema::table('productos', fn (Blueprint $t) => $t->dropConstrainedForeignId('boing_insumo_id'));
    }
};
