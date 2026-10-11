<?php

use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CorteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntradaController;
use App\Http\Controllers\FondoController;
use App\Http\Controllers\InsumoController;
use App\Http\Controllers\MermaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/productos', [ProductoController::class, 'index']);

    // ---------- Administrador ----------
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::apiResource('insumos', InsumoController::class)->except(['show']);
        Route::get('entradas', [EntradaController::class, 'index']);
        Route::post('entradas', [EntradaController::class, 'store']);
        Route::post('entradas/surtido', [EntradaController::class, 'surtido']);

        Route::get('recetas', [RecetaController::class, 'index']);
        Route::post('recetas/masivo', [RecetaController::class, 'masivo']);
        Route::put('recetas/{producto}', [RecetaController::class, 'update']);

        Route::get('vendedores', [UserController::class, 'index']);
        Route::post('vendedores', [UserController::class, 'store']);
        Route::patch('vendedores/{user}', [UserController::class, 'update']);
        Route::get('vendedores/{user}/inventario', [AsignacionController::class, 'inventario']);
        Route::get('vendedores/{user}/fondo', [FondoController::class, 'show']);
        Route::post('fondos', [FondoController::class, 'store']);

        Route::post('asignaciones', [AsignacionController::class, 'store']);
        Route::post('asignaciones/devolver', [AsignacionController::class, 'devolver']);

        Route::get('alertas', [DashboardController::class, 'alertas']);
        Route::get('monitor', [DashboardController::class, 'monitor']);
        Route::get('dashboard', [DashboardController::class, 'dashboard']);
    });

    // ---------- Vendedor ----------
    Route::middleware('role:vendedor')->prefix('vendedor')->group(function () {
        Route::get('productos', [ProductoController::class, 'disponibles']);
        Route::get('inventario', fn (\Illuminate\Http\Request $r) => app(AsignacionController::class)->inventario($r->user()));
        Route::get('ventas', [VentaController::class, 'index']);
        Route::post('ventas', [VentaController::class, 'store']);
        Route::get('mermas', [MermaController::class, 'index']);
        Route::post('mermas', [MermaController::class, 'store']);
        Route::get('corte', [CorteController::class, 'preview']);
        Route::post('corte', [CorteController::class, 'store']);
    });
});
