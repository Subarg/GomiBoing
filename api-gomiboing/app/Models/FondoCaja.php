<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FondoCaja extends Model
{
    protected $table = 'fondos_caja';
    protected $guarded = [];
    protected $casts = ['monto' => 'float'];
}
