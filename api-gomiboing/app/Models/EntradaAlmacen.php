<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntradaAlmacen extends Model
{
    protected $table = 'entradas_almacen';
    protected $guarded = [];
    protected $casts = ['cantidad_ingresada' => 'float', 'costo_total' => 'float'];

    public function insumo() { return $this->belongsTo(Insumo::class); }
}
