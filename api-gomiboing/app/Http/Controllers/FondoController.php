<?php
namespace App\Http\Controllers;

use App\Models\FondoCaja;
use App\Models\User;
use Illuminate\Http\Request;

class FondoController extends Controller
{
    /** Fondo de caja vigente (del turno abierto) de un vendedor. */
    public function show(User $user)
    {
        return ['monto' => (float) (FondoCaja::where('user_id', $user->id)->whereNull('corte_id')->value('monto') ?? 0)];
    }

    /** Define cuánto dinero (cambio) habrá en la caja del vendedor este turno. */
    public function store(Request $r)
    {
        $data = $r->validate(['user_id' => 'required|exists:users,id', 'monto' => 'required|numeric|min:0']);
        abort_unless(User::where('id', $data['user_id'])->where('rol', 'vendedor')->exists(), 422, 'El usuario no es vendedor.');

        $fondo = FondoCaja::updateOrCreate(['user_id' => $data['user_id'], 'corte_id' => null], ['monto' => $data['monto']]);
        return ['monto' => (float) $fondo->monto];
    }
}
