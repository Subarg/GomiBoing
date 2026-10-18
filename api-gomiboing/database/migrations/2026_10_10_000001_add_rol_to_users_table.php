<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->enum('rol', ['admin', 'vendedor'])->default('vendedor')->after('password');
            $t->boolean('activo')->default(true)->after('rol');
        });
    }
    public function down(): void {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['rol', 'activo']));
    }
};
