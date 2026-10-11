<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required']);
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password) || !$user->activo) {
            return response()->json(['message' => 'Correo o contraseña incorrectos.'], 422);
        }

        return [
            'token' => $user->createToken('pos')->plainTextToken,
            'user' => $this->payload($user),
        ];
    }

    public function me(Request $r) { return $this->payload($r->user()); }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();
        return ['message' => 'Sesión cerrada.'];
    }

    private function payload(User $u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'rol' => $u->rol];
    }
}
