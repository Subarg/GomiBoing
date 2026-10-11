<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $guarded = [];
    protected $casts = [
        'total_venta' => 'float', 'comision_generada' => 'float',
        'costo_total' => 'float', 'fecha' => 'datetime',
    ];

    public function producto() { return $this->belongsTo(Producto::class); }
    public function user() { return $this->belongsTo(User::class); }
}
