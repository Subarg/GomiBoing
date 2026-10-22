<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Insumo extends Model
{
    public const CUCHARA = 'Cuchara';

    protected $guarded = [];
    protected $casts = [
        'stock_almacen' => 'float', 'stock_minimo' => 'float',
        'costo_unitario' => 'float', 'presentacion_cantidad' => 'float',
    ];

    public static function esBoing(string $nombre): bool
    {
        return str_starts_with($nombre, 'Boing ');
    }

    /** Piezas por caja de Boing: 250ml trae 27, 500ml trae 24. */
    public static function cajaBoing(int $ml): int
    {
        return $ml === 500 ? 24 : 27;
    }

    public function entradas() { return $this->hasMany(EntradaAlmacen::class); }
}
