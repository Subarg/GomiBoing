<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class UserController extends Controller
{
    /** Contraseña que el admin asignó (solo se conoce si se capturó desde este panel). */
    private function visible(User $u): ?string
    {
        if (!$u->password_visible) return null;
        try { return Crypt::decryptString($u->password_visible); } catch (\Throwable) { return null; }
    }

    private function formato(User $u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'activo' => (bool) $u->activo, 'password' => $this->visible($u)];
    }

    public function index()
    {
        return User::where('rol', 'vendedor')->orderBy('name')->get()->map(fn ($u) => $this->formato($u));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);
        $user = User::create($data + ['rol' => 'vendedor', 'activo' => true]);
        $user->forceFill(['password_visible' => Crypt::encryptString($data['password'])])->save();
        return response()->json($this->formato($user), 201);
    }

    public function update(Request $r, User $user)
    {
        abort_if($user->rol !== 'vendedor', 403, 'Solo se editan vendedores.');
        $data = $r->validate([
            'name' => 'sometimes|string|max:120',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|nullable|string|min:6',
            'activo' => 'sometimes|boolean',
        ]);

        $nuevaClave = $data['password'] ?? null;
        unset($data['password']);
        $user->update($data);

        if ($nuevaClave) {
            $user->update(['password' => $nuevaClave]);
            $user->forceFill(['password_visible' => Crypt::encryptString($nuevaClave)])->save();
            $user->tokens()->delete(); // cierra sus sesiones: deberá entrar con la clave nueva
        }
        if (array_key_exists('activo', $data) && !$data['activo']) $user->tokens()->delete();

        return $this->formato($user->fresh());
    }
}
