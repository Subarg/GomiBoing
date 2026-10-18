<?php
namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index() { return Proveedor::orderBy('nombre')->get(['id', 'nombre']); }

    public function store(Request $r)
    {
        $data = $r->validate(['nombre' => 'required|string|max:120|unique:proveedores,nombre']);
        $data['nombre'] = trim($data['nombre']);
        return response()->json(Proveedor::create($data)->only('id', 'nombre'), 201);
    }

    public function destroy(Proveedor $proveedor)
    {
        $proveedor->delete(); // las compras ya registradas conservan el nombre escrito
        return response()->noContent();
    }
}
