<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $rol)
    {
        $user = $request->user();
        if (!$user || !$user->activo || $user->rol !== $rol) {
            return response()->json(['message' => 'No tienes permiso para esta acción.'], 403);
        }
        return $next($request);
    }
}
