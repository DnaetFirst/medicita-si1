<?php

namespace App\Http\Controllers\P5_Laboratorio;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarAviso;
use App\Models\P3\Paciente;
use App\Models\P5\DetalleResultado;
use App\Models\P5\ExamenLaboratorio;
use App\Models\P5\OrdenExamen;
use App\Models\P5\OrdenLaboratorio;
use App\Models\P5\ResultadoLaboratorio;
use App\Models\P7\Notificacion;
use App\Services\AccesoPacienteService;
use App\Services\BitacoraService;
use App\Services\NotificacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaboratorioController extends Controller
{
    public function __construct(public AccesoPacienteService $acceso) {}

    /**
     * Bandeja de órdenes de laboratorio (CU15 — Rol: lab / admin).
     */
    public function indexOrdenes(Request $request): View
    {
        abort_unless($request->user()->rol?->slug === 'lab' || $request->user()->isAdmin(), 403);

        $query = OrdenLaboratorio::with(['paciente', 'medico.user', 'examenes', 'resultados'])->latest();

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search): void {
                $q->where('codigo_orden', 'like', "%{$search}%")
                    ->orWhereHas('paciente', fn ($qp) => $qp->where('nombres', 'like', "%{$search}%")->orWhere('apellidos', 'like', "%{$search}%")->orWhere('ci', 'like', "%{$search}%"));
            });
        }

        $ordenes = $query->paginate(15)->withQueryString();

        return view('p5_laboratorio.ordenes.index', [
            'ordenes' => $ordenes,
            'filters' => $request->all(),
            'title' => 'Bandeja de Órdenes de Laboratorio (CU15) — MediCita Pro',
            'header' => 'Gestión de Laboratorio Clínico',
        ]);
    }

    /**
     * Formulario para emitir orden médica de laboratorio (CU15 — Rol: medico).
     */
    public function crearOrden(Request $request, Paciente $paciente): View
    {
        $this->acceso->autorizarClinica($request->user(), $paciente);
        $examenes = ExamenLaboratorio::where('activo', true)->orderBy('categoria')->orderBy('nombre')->get();

        return view('p5_laboratorio.ordenes.create', [
            'paciente' => $paciente,
            'examenes' => $examenes,
            'cita_id' => $request->query('cita_id'),
            'atencion_id' => $request->query('atencion_id'),
            'title' => 'Emitir Orden de Laboratorio — MediCita Pro',
            'header' => 'Nueva Orden de Análisis Clínicos',
        ]);
    }

    /**
     * Guarda la orden de laboratorio con los exámenes seleccionados (CU15).
     */
    public function storeOrden(Request $request, Paciente $paciente): RedirectResponse
    {
        $this->acceso->autorizarClinica($request->user(), $paciente);

        $validated = $request->validate([
            'examenes' => 'required|array|min:1',
            'examenes.*' => 'exists:examenes_laboratorio,id',
            'observaciones' => 'nullable|string|max:1000',
            'cita_id' => 'nullable|integer|exists:citas,id',
            'atencion_medica_id' => 'nullable|integer|exists:atenciones_medicas,id',
            'operacion_id' => 'nullable|uuid',
        ]);

        $orden = DB::transaction(function () use ($request, $paciente, $validated): OrdenLaboratorio {
            $codigoOrden = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(8));

            $orden = OrdenLaboratorio::create([
                'codigo_orden' => $codigoOrden,
                'paciente_id' => $paciente->id,
                'medico_id' => $request->user()->medico->id,
                'cita_id' => $validated['cita_id'] ?? null,
                'atencion_medica_id' => $validated['atencion_medica_id'] ?? null,
                'estado' => 'emitida',
                'observaciones' => $validated['observaciones'] ?? null,
                'operacion_id' => $validated['operacion_id'] ?? (string) Str::uuid(),
            ]);

            foreach ($validated['examenes'] as $examenId) {
                OrdenExamen::create([
                    'orden_laboratorio_id' => $orden->id,
                    'examen_laboratorio_id' => $examenId,
                    'estado' => 'pendiente',
                ]);
            }

            BitacoraService::registrar('EMITIR_ORDEN_LABORATORIO', 'P5_Laboratorio', [
                'orden_id' => $orden->id,
                'codigo_orden' => $codigoOrden,
                'paciente_id' => $paciente->id,
                'medico_id' => $request->user()->medico->id,
            ]);

            return $orden;
        }, 3);

        NotificacionService::notificarOrdenLaboratorio($orden);

        return redirect()->route('laboratorio.ordenes.show', $orden)->with('success', "Orden de laboratorio {$orden->codigo_orden} emitida con éxito.");
    }

    /**
     * Detalle de una orden de laboratorio y sus resultados (CU15).
     */
    public function showOrden(Request $request, OrdenLaboratorio $orden): View
    {
        if ($request->user()->rol?->slug !== 'lab') {
            abort_unless($request->user()->isMedico() || $request->user()->isPaciente(), 403);
            abort_unless($this->acceso->pacientesResultados($request->user())->whereKey($orden->paciente_id)->exists(), 403);
        }
        $orden->load(['paciente', 'medico.user', 'examenes',
            'resultados' => fn ($query) => $query->when($request->user()->rol?->slug !== 'lab', fn ($resultados) => $resultados->where('estado', 'publicado')),
            'resultados.detalles.examen', 'resultados.autor']);

        return view('p5_laboratorio.ordenes.show', [
            'orden' => $orden,
            'title' => "Orden {$orden->codigo_orden} — MediCita Pro",
            'header' => 'Detalle de Orden de Laboratorio',
        ]);
    }

    /**
     * Formulario para registrar un resultado de laboratorio (CU15 — Rol: lab).
     */
    public function createResultado(Request $request, OrdenLaboratorio $orden): View
    {
        abort_unless($request->user()->rol?->slug === 'lab', 403);
        $orden->load(['paciente', 'examenes']);

        return view('p5_laboratorio.resultados.create', [
            'orden' => $orden,
            'title' => 'Captura de Resultados — MediCita Pro',
            'header' => 'Registro de Resultados de Laboratorio',
        ]);
    }

    /**
     * Guarda el resultado en borrador con detalles y archivo PDF opcional (CU15).
     */
    public function storeResultado(Request $request, OrdenLaboratorio $orden): RedirectResponse
    {
        abort_unless($request->user()->rol?->slug === 'lab', 403);

        $validated = $request->validate([
            'detalles' => 'required|array|min:1',
            'detalles.*.examen_laboratorio_id' => 'required|exists:examenes_laboratorio,id',
            'detalles.*.parametro' => 'required|string|max:100',
            'detalles.*.valor' => 'required|string|max:255',
            'detalles.*.unidad_medida' => 'nullable|string|max:50',
            'detalles.*.rango_referencia' => 'nullable|string|max:100',
            'detalles.*.interpretacion' => 'nullable|string|max:50',
            'archivo_pdf' => 'nullable|file|mimes:pdf|max:10240', // máx 10 MB
            'observaciones' => 'nullable|string|max:1000',
            'corrige_id' => 'nullable|exists:resultados_laboratorio,id',
            'motivo_correccion' => 'required_with:corrige_id|nullable|string|max:500',
            'operacion_id' => 'nullable|uuid',
        ]);

        $resultado = DB::transaction(function () use ($request, $orden, $validated): ResultadoLaboratorio {
            $orden = OrdenLaboratorio::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            if (! empty($validated['operacion_id']) && ($previo = ResultadoLaboratorio::where('operacion_id', $validated['operacion_id'])->first())) {
                abort_unless($previo->orden_laboratorio_id === $orden->id && $previo->user_id === $request->user()->id, 409);

                return $previo;
            }
            abort_if($orden->estado === 'cancelada' || ($orden->estado === 'finalizada' && empty($validated['corrige_id'])), 422, 'La orden no admite resultados nuevos.');
            $permitidos = $orden->examenes()->pluck('examenes_laboratorio.id')->all();
            foreach ($validated['detalles'] as $detalle) {
                abort_unless(in_array((int) $detalle['examen_laboratorio_id'], $permitidos), 422, 'El examen no pertenece a esta orden.');
            }
            if (! empty($validated['corrige_id'])) {
                $original = ResultadoLaboratorio::whereKey($validated['corrige_id'])->where('orden_laboratorio_id', $orden->id)->lockForUpdate()->firstOrFail();
                abort_unless($original->isPublicado(), 422, 'Solo se corrigen resultados publicados.');
                abort_if(ResultadoLaboratorio::where('corrige_id', $original->id)->exists(), 409, 'Este resultado ya tiene una corrección.');
            }
            $pdfPath = null;
            if ($request->hasFile('archivo_pdf')) {
                $pdfPath = $request->file('archivo_pdf')->store('laboratorio', 'local');
            }

            $version = 1;
            if (! empty($validated['corrige_id'])) {
                $original = ResultadoLaboratorio::whereKey($validated['corrige_id'])->where('orden_laboratorio_id', $orden->id)->firstOrFail();
                $version = $original->version + 1;
            }

            $codigoResultado = 'RES-'.date('Ymd').'-'.strtoupper(Str::random(8));

            $resultado = ResultadoLaboratorio::create([
                'codigo_resultado' => $codigoResultado,
                'orden_laboratorio_id' => $orden->id,
                'user_id' => $request->user()->id,
                'version' => $version,
                'corrige_id' => $validated['corrige_id'] ?? null,
                'motivo_correccion' => $validated['motivo_correccion'] ?? null,
                'estado' => 'borrador',
                'archivo_pdf_path' => $pdfPath,
                'archivo_pdf_contenido' => $request->hasFile('archivo_pdf') ? base64_encode($request->file('archivo_pdf')->getContent()) : null,
                'observaciones' => $validated['observaciones'] ?? null,
                'operacion_id' => $validated['operacion_id'] ?? (string) Str::uuid(),
            ]);

            foreach ($validated['detalles'] as $detalle) {
                DetalleResultado::create([
                    'resultado_laboratorio_id' => $resultado->id,
                    'examen_laboratorio_id' => $detalle['examen_laboratorio_id'],
                    'parametro' => $detalle['parametro'],
                    'valor' => $detalle['valor'],
                    'unidad_medida' => $detalle['unidad_medida'] ?? null,
                    'rango_referencia' => $detalle['rango_referencia'] ?? null,
                    'interpretacion' => $detalle['interpretacion'] ?? null,
                ]);

                // Actualizar estado del examen en la orden a en_proceso
                OrdenExamen::where('orden_laboratorio_id', $orden->id)
                    ->where('examen_laboratorio_id', $detalle['examen_laboratorio_id'])
                    ->update(['estado' => 'en_proceso']);
            }

            if ($orden->estado === 'emitida') {
                $orden->update(['estado' => 'en_proceso']);
            }

            BitacoraService::registrar('REGISTRAR_RESULTADO_BORRADOR', 'P5_Laboratorio', [
                'resultado_id' => $resultado->id,
                'orden_id' => $orden->id,
                'version' => $version,
            ]);

            return $resultado;
        }, 3);

        return redirect()->route('laboratorio.ordenes.show', $orden)->with('success', 'Resultado guardado en borrador. Pendiente de validación.');
    }

    /**
     * Valida un resultado en borrador (CU15 — Rol: lab).
     */
    public function validarResultado(Request $request, ResultadoLaboratorio $resultado): RedirectResponse
    {
        abort_unless($request->user()->rol?->slug === 'lab', 403);
        DB::transaction(function () use ($request, $resultado): void {
            $orden = OrdenLaboratorio::whereKey($resultado->orden_laboratorio_id)->lockForUpdate()->firstOrFail();
            $resultado = ResultadoLaboratorio::whereKey($resultado->id)->lockForUpdate()->firstOrFail();
            if ($resultado->isValidado()) {
                return;
            }
            abort_unless($resultado->isBorrador(), 422, 'Solo resultados en borrador pueden ser validados.');
            abort_if($orden->estado === 'cancelada', 422, 'La orden está cancelada.');
            abort_unless($resultado->detalles()->exists(), 422, 'Registra los detalles antes de validar.');
            $resultado->update(['estado' => 'validado', 'fecha_validacion' => now(), 'validado_por_user_id' => $request->user()->id]);
            BitacoraService::registrar('VALIDAR_RESULTADO_LABORATORIO', 'P5_Laboratorio', ['resultado_id' => $resultado->id, 'orden_id' => $resultado->orden_laboratorio_id]);
        }, 3);

        return back()->with('success', 'Resultado validado correctamente. Listo para publicación.');
    }

    /**
     * Publica un resultado validado (CU15 — Rol: lab).
     */
    public function publicarResultado(Request $request, ResultadoLaboratorio $resultado): RedirectResponse
    {
        abort_unless($request->user()->rol?->slug === 'lab', 403);

        DB::transaction(function () use ($request, $resultado): void {
            $orden = OrdenLaboratorio::whereKey($resultado->orden_laboratorio_id)->lockForUpdate()->firstOrFail();
            $resultado = ResultadoLaboratorio::whereKey($resultado->id)->lockForUpdate()->firstOrFail();
            if ($resultado->isPublicado()) {
                return;
            }
            abort_unless($resultado->isValidado(), 422, 'Solo resultados previamente validados pueden ser publicados.');
            abort_if($orden->estado === 'cancelada', 422, 'La orden está cancelada.');

            $resultado->update([
                'estado' => 'publicado',
                'fecha_publicacion' => now(),
                'publicado_por_user_id' => $request->user()->id,
            ]);

            // Marcar los exámenes informados
            $examenesInformados = $resultado->detalles->pluck('examen_laboratorio_id')->unique();
            OrdenExamen::where('orden_laboratorio_id', $orden->id)
                ->whereIn('examen_laboratorio_id', $examenesInformados)
                ->update(['estado' => 'informado']);

            // Verificar si todos los exámenes de la orden ya tienen estado 'informado'
            $todosInformados = ! OrdenExamen::where('orden_laboratorio_id', $orden->id)
                ->where('estado', '!=', 'informado')
                ->exists();

            if ($todosInformados) {
                $orden->update(['estado' => 'finalizada']);
            } else {
                $orden->update(['estado' => 'en_proceso']);
            }

            // Notificación al paciente y tutores autorizados
            $this->notificarPublicacion($resultado);

            BitacoraService::registrar('PUBLICAR_RESULTADO_LABORATORIO', 'P5_Laboratorio', [
                'resultado_id' => $resultado->id,
                'orden_id' => $orden->id,
                'orden_finalizada' => $todosInformados,
            ]);
        }, 3);

        return back()->with('success', 'Resultado publicado exitosamente. Notificación despachada a paciente y médico.');
    }

    /**
     * Muestra el resultado de laboratorio publicado a pacientes o médicos autorizados (CU15).
     */
    public function showResultado(Request $request, ResultadoLaboratorio $resultado): View
    {
        $this->acceso->autorizarResultado($request->user(), $resultado);
        $resultado->load(['orden.paciente', 'orden.medico.user', 'detalles.examen', 'autor', 'publicadoPor']);

        return view('p5_laboratorio.resultados.show', [
            'resultado' => $resultado,
            'title' => "Informe de Laboratorio: {$resultado->codigo_resultado} — MediCita Pro",
            'header' => 'Informe de Análisis Clínico',
        ]);
    }

    /**
     * Descarga autenticada del PDF privado de laboratorio (CU15).
     */
    public function descargarPdf(Request $request, ResultadoLaboratorio $resultado): StreamedResponse
    {
        $this->acceso->autorizarResultado($request->user(), $resultado);
        abort_unless($resultado->archivo_pdf_contenido || ($resultado->archivo_pdf_path && Storage::disk('local')->exists($resultado->archivo_pdf_path)), 404, 'El archivo PDF no está disponible.');

        BitacoraService::registrar('DESCARGAR_PDF_LABORATORIO', 'P5_Laboratorio', [
            'resultado_id' => $resultado->id,
            'archivo' => $resultado->archivo_pdf_path,
        ]);

        if ($resultado->archivo_pdf_contenido) {
            $contenido = base64_decode($resultado->archivo_pdf_contenido, true);
            abort_if($contenido === false, 500, 'No se pudo recuperar el documento.');

            return response()->streamDownload(function () use ($contenido): void {
                echo $contenido;
            }, "Resultado-{$resultado->codigo_resultado}.pdf", ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
        }

        return Storage::disk('local')->download($resultado->archivo_pdf_path, "Resultado-{$resultado->codigo_resultado}.pdf");
    }

    /**
     * Listado de resultados de laboratorio del paciente (CU15 — Rol: paciente).
     */
    public function misResultados(Request $request): View
    {
        $pacientes = $this->acceso->pacientesResultados($request->user())->get();
        $paciente = $request->filled('paciente_id') ? $pacientes->firstWhere('id', (int) $request->paciente_id) : $pacientes->first();

        $resultados = collect();
        if ($paciente) {
            $resultados = ResultadoLaboratorio::with(['orden.medico.user', 'detalles.examen', 'publicadoPor'])
                ->where('estado', 'publicado')
                ->whereHas('orden', fn ($q) => $q->where('paciente_id', $paciente->id))
                ->latest('fecha_publicacion')
                ->paginate(10);
        }

        return view('p5_laboratorio.resultados.mis_resultados', [
            'pacientes' => $pacientes,
            'paciente' => $paciente,
            'resultados' => $resultados,
            'title' => 'Mis Resultados de Laboratorio (CU15) — MediCita Pro',
            'header' => 'Resultados de Análisis Clínicos',
        ]);
    }

    /**
     * Despacha aviso de publicación sin exponer contenido clínico.
     */
    protected function notificarPublicacion(ResultadoLaboratorio $resultado): void
    {
        $paciente = $resultado->orden->paciente;
        $destinatarios = collect([$paciente->user_id]);
        $destinatarios = $destinatarios->merge($paciente->responsables()
            ->where('activo', true)
            ->where('puede_ver_resultados', true)
            ->whereDate('fecha_limite_vigencia', '>=', today())
            ->where(fn ($q) => $q->whereNull('vigencia_inicio')->orWhereDate('vigencia_inicio', '<=', today()))
            ->pluck('user_id'));

        foreach ($destinatarios->filter()->unique() as $userId) {
            $aviso = Notificacion::firstOrCreate([
                'evento_id' => 'lab:pub:'.$resultado->id.':'.$userId,
            ], [
                'user_id' => $userId,
                'tipo' => 'laboratorio',
                'titulo' => 'Resultados de laboratorio disponibles',
                'mensaje' => "El informe de laboratorio {$resultado->codigo_resultado} ha sido publicado y está disponible en su portal clínico para descarga segura.",
                'leido' => false,
            ]);

            if ($aviso->wasRecentlyCreated) {
                EnviarAviso::dispatch($aviso->id)->afterCommit();
            }
        }

        $medicoUser = $resultado->orden->medico?->user;
        if ($medicoUser) {
            $avisoDoc = Notificacion::firstOrCreate([
                'evento_id' => 'lab:pub:doc:'.$resultado->id.':'.$medicoUser->id,
            ], [
                'user_id' => $medicoUser->id,
                'tipo' => 'laboratorio',
                'titulo' => 'Resultados de laboratorio disponibles',
                'mensaje' => "El informe de laboratorio {$resultado->codigo_resultado} del paciente {$paciente->nombre_completo} ha sido publicado y está disponible en su historial.",
                'leido' => false,
            ]);

            if ($avisoDoc->wasRecentlyCreated) {
                EnviarAviso::dispatch($avisoDoc->id)->afterCommit();
            }
        }
    }
}
