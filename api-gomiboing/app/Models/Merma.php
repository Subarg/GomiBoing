<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Merma extends Model
{
    protected $table = 'mermas';
    protected $guarded = [];
    protected $casts = ['cantidad' => 'float', 'costo' => 'float', 'fecha' => 'datetime'];

    public function insumo() { return $this->belongsTo(Insumo::class); }
    public function user() { return $this->belongsTo(User::class); }
}
