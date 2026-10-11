<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioVendedor extends Model
{
    protected $table = 'inventario_vendedor';
    protected $guarded = [];
    protected $casts = ['cantidad_asignada' => 'float'];

    public function insumo() { return $this->belongsTo(Insumo::class); }
    public function user() { return $this->belongsTo(User::class); }
}
