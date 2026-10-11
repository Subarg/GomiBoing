<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Corte extends Model
{
    protected $table = 'cortes';
    protected $guarded = [];
    protected $casts = [
        'total_efectivo' => 'float', 'total_transferencia' => 'float', 'comision' => 'float',
        'saldo_inicial' => 'float', 'entregar' => 'float', 'sobrante_devuelto' => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
