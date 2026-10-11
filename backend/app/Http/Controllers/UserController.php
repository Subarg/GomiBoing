<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return User::where('rol', 'vendedor')->orderBy('name')->get(['id', 'name', 'email', 'activo']);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);
        $user = User::create($data + ['rol' => 'vendedor', 'activo' => true]);
        return response()->json($user->only('id', 'name', 'email', 'activo'), 201);
    }

    public function update(Request $r, User $user)
    {
        abort_if($user->rol !== 'vendedor', 403, 'Solo se editan vendedores.');
        $data = $r->validate([
            'name' => 'sometimes|string|max:120',
            'password' => 'sometimes|string|min:6',
            'activo' => 'sometimes|boolean',
        ]);
        $user->update($data);
        if (($data['activo'] ?? true) === false) $user->tokens()->delete();
        return $user->only('id', 'name', 'email', 'activo');
    }
}
