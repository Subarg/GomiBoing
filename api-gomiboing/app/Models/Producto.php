<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $guarded = [];
    protected $casts = ['precio_venta' => 'float', 'comision' => 'float', 'activo' => 'boolean'];

    public function insumos()
    {
        return $this->belongsToMany(Insumo::class, 'recetas')->withPivot('cantidad_requerida');
    }
}
