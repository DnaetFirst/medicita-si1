<?php

namespace App\Http\Controllers\P3_Pacientes;

use App\Http\Controllers\Controller;
use App\Models\P2\Especialidad;
use App\Models\P3\AtencionMedica;
use App\Models\P3\Derivacion;
use App\Models\P3\HistoriaClinica;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\P5\OrdenLaboratorio;
use App\Services\AccesoPacienteService;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HistoriaController extends Controller
{
    public function show(Request $request, Paciente $paciente, AccesoPacienteService $acceso): View
    {
        $acceso->autorizarClinica($request->user(), $paciente);
        $historia = HistoriaClinica::where('paciente_id', $paciente->id)->first();
        $atenciones = AtencionMedica::with(['autor', 'derivaciones.especialidad'])->where('historia_clinica_id', $historia?->id ?? 0)->latest('id')->paginate(10);
        $citas = $paciente->citas()->where('medico_id', $request->user()->medico->id)->where('estado', 'en_atencion')->get();
        $ordenes = OrdenLaboratorio::with(['examenes', 'resultados'])->where('paciente_id', $paciente->id)->latest()->get();
        $ordenes = OrdenLaboratorio::with(['examenes', 'resultados'])->where('paciente_id', $paciente->id)->latest()->get();
        BitacoraService::registrar('CONSULTAR_HISTORIA_CLINICA', 'P3_Pacientes', ['paciente_id' => $paciente->id]);

        return view('p3_pacientes.historia.show', [
            'paciente' => $paciente, 'historia' => $historia, 'atenciones' => $atenciones, 'citas' => $citas,
            'ordenes' => $ordenes,
            'especialidades' => Especialidad::where('activo', true)->orderBy('nombre')->get(), 'header' => 'Historia clínica',
        ]);
    }

    public function store(Request $request, Paciente $paciente, AccesoPacienteService $acceso): RedirectResponse
    {
        $acceso->autorizarClinica($request->user(), $paciente);
        $datos = $request->validate([
            'cita_id' => 'required|integer', 'version' => 'required|integer|min:0', 'operacion_id' => 'required|uuid',
            'motivo_consulta' => 'required|string|max:5000', 'hallazgos' => 'nullable|string|max:10000',
            'diagnostico' => 'required|string|max:10000', 'tratamiento' => 'nullable|string|max:10000',
            'indicaciones' => 'nullable|string|max:10000', 'antecedentes' => 'nullable|string|max:10000',
            'medicacion_habitual' => 'nullable|string|max:5000', 'corrige_id' => 'nullable|integer',
            'motivo_correccion' => 'required_with:corrige_id|nullable|string|min:5|max:1000',
        ]);
        DB::transaction(function () use ($request, $paciente, $datos, $acceso): void {
            Paciente::whereKey($paciente->id)->lockForUpdate()->firstOrFail();
            $cita = Cita::whereKey($datos['cita_id'])->where('paciente_id', $paciente->id)->lockForUpdate()->firstOrFail();
            $acceso->autorizarClinica($request->user(), $paciente);
            abort_unless($cita->medico_id === $request->user()->medico->id, 403);
            if ($previa = AtencionMedica::where('operacion_id', $datos['operacion_id'])->first()) {
                abort_unless($previa->cita_id === $cita->id && $previa->user_id === $request->user()->id, 409);

                return;
            }
            $corrige = ! empty($datos['corrige_id']);
            abort_unless($corrige ? in_array($cita->estado, ['en_atencion', 'atendida']) : $cita->estado === 'en_atencion', 422, 'La atención debe estar iniciada.');
            $historia = HistoriaClinica::where('paciente_id', $paciente->id)->lockForUpdate()->first();
            abort_unless((int) $datos['version'] === ($historia?->version ?? 0), 409, 'Existe una versión más reciente. Revisa el expediente antes de guardar.');
            if ($corrige) {
                $original = AtencionMedica::whereKey($datos['corrige_id'])->where('cita_id', $cita->id)->firstOrFail();
                abort_if(AtencionMedica::where('corrige_id', $original->id)->exists(), 409, 'Esta nota ya tiene una corrección.');
            } else {
                abort_if(AtencionMedica::where('cita_id', $cita->id)->where('es_correccion', false)->exists(), 409, 'La nota ya existe. Usa una corrección.');
            }
            $historia ??= HistoriaClinica::create(['paciente_id' => $paciente->id, 'nro_expediente' => 'HC-'.$paciente->id, 'fecha_apertura' => today(), 'version' => 0]);
            $historia->update(['antecedentes' => $datos['antecedentes'] ?? $historia->antecedentes,
                'medicacion_habitual' => $datos['medicacion_habitual'] ?? $historia->medicacion_habitual, 'version' => $historia->version + 1]);
            unset($datos['version']);
            $nota = AtencionMedica::create(array_merge($datos, [
                'historia_clinica_id' => $historia->id, 'user_id' => $request->user()->id, 'es_correccion' => $corrige,
                'antecedentes' => $historia->antecedentes, 'medicacion_habitual' => $historia->medicacion_habitual,
            ]));
            BitacoraService::registrar($corrige ? 'CORREGIR_ATENCION' : 'REGISTRAR_ATENCION', 'P3_Pacientes', ['atencion_id' => $nota->id, 'paciente_id' => $paciente->id]);
        }, 3);

        return redirect()->route('historia.show', $paciente)->with('success', 'Nota guardada. El cierre de la cita se registra por separado.');
    }

    public function derivar(Request $request, AtencionMedica $atencion, AccesoPacienteService $acceso): RedirectResponse
    {
        $paciente = $atencion->historia->paciente;
        $acceso->autorizarClinica($request->user(), $paciente);
        abort_unless($atencion->cita->medico_id === $request->user()->medico->id, 403);
        $datos = $request->validate(['especialidad_id' => 'required|integer', 'motivo' => 'required|string|max:5000',
            'indicaciones' => 'nullable|string|max:5000', 'operacion_id' => 'required|uuid']);
        DB::transaction(function () use ($request, $atencion, $datos): void {
            $especialidad = Especialidad::whereKey($datos['especialidad_id'])->lockForUpdate()->firstOrFail();
            abort_unless($especialidad->activo, 422, 'La especialidad destino no está habilitada.');
            $derivacion = Derivacion::firstOrCreate(['operacion_id' => $datos['operacion_id']], array_merge($datos, [
                'atencion_medica_id' => $atencion->id, 'user_id' => $request->user()->id,
            ]));
            abort_unless($derivacion->atencion_medica_id === $atencion->id && $derivacion->user_id === $request->user()->id, 409);
            if ($derivacion->wasRecentlyCreated) {
                BitacoraService::registrar('EMITIR_DERIVACION', 'P3_Pacientes', ['derivacion_id' => $derivacion->id]);
            }
        });

        return redirect()->route('historia.show', $paciente)->with('success', 'Derivación emitida. No se ha reservado una nueva cita.');
    }
}
