<?php

namespace App\Http\Controllers\P1_Usuarios;

use App\Http\Controllers\Controller;
use App\Models\P1\Bitacora;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BitacoraController extends Controller
{
    /**
     * Módulo de auditoría y bitácora del sistema (CU19).
     */
    public function index(Request $request)
    {
        $query = Bitacora::with('user')->latest('created_at');

        if ($request->filled('modulo')) {
            $query->where('modulo', $request->modulo);
        }

        if ($request->filled('accion')) {
            $query->where('accion', 'like', "%{$request->accion}%");
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->hasta);
        }

        $logs = $query->paginate(20)->withQueryString();
        $usuarios = User::select('id', 'name')->orderBy('name')->get();
        $modulos = Bitacora::select('modulo')->distinct()->pluck('modulo');

        return view('p1_auth.bitacora.index', [
            'logs' => $logs,
            'usuarios' => $usuarios,
            'modulos' => $modulos,
            'filters' => $request->all(),
            'title' => 'Bitácora de Auditoría (CU19) — MediCita Pro',
            'header' => 'Registro de Auditoría y Trazabilidad',
        ]);
    }

    /**
     * Exporta el registro de auditoría a formato CSV (CU19).
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $fileName = 'bitacora_auditoria_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $callback = function () use ($request) {
            $file = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['ID', 'Fecha / Hora', 'Usuario', 'Rol', 'Módulo', 'Acción', 'Dirección IP', 'Detalles']);

            $query = Bitacora::latest('created_at');

            if ($request->filled('modulo')) $query->where('modulo', $request->modulo);
            if ($request->filled('user_id')) $query->where('user_id', $request->user_id);

            $query->chunk(200, function ($logs) use ($file) {
                foreach ($logs as $log) {
                    fputcsv($file, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->usuario_nombre,
                        $log->rol,
                        $log->modulo,
                        $log->accion,
                        $log->ip_address,
                        $log->detalles,
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
