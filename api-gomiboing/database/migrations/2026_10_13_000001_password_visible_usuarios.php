<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Copia CIFRADA (con APP_KEY) de la contraseña que el admin le asigna a cada empleado
        Schema::table('users', fn (Blueprint $t) => $t->text('password_visible')->nullable());
    }
    public function down(): void {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('password_visible'));
    }
};
