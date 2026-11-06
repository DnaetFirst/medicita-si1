<?php

namespace App\Http\Controllers\P7_Notificaciones;

use App\Http\Controllers\Controller;
use App\Models\P7\Notificacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvisoController extends Controller
{
    public function index(Request $request): View
    {
        return view('p3_pacientes.historial.avisos', ['avisos' => $request->user()->notificaciones()->latest('id')->paginate(15), 'header' => 'Mis avisos']);
    }

    public function leer(Request $request, Notificacion $aviso): RedirectResponse
    {
        abort_unless($aviso->user_id === $request->user()->id, 403);
        $aviso->update(['leido' => true]);

        return back()->with('success', 'Aviso marcado como leído.');
    }
}
