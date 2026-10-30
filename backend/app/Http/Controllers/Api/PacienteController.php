<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AccionBitacora;
use App\Enums\TipoDocumento;
use App\Http\Controllers\Controller;
use App\Http\Resources\PacienteResource;
use App\Http\Resources\PrescripcionResource;
use App\Models\Paciente;
use App\Models\User;
use App\Services\Auditoria\BitacoraAccesos;
use App\Support\FechaNegocio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * RN-10: cada acceso a datos de un paciente queda en la bitácora.
 */
class PacienteController extends Controller
{
    public function __construct(private readonly BitacoraAccesos $bitacora) {}

    /**
     * Búsqueda solo por documento exacto: no hay listados ni búsqueda por nombre que
     * permitan volcar datos de pacientes.
     */
    public function buscar(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Paciente::class);

        $filtro = $request->validate([
            'tipo_documento' => ['required', Rule::enum(TipoDocumento::class)],
            'numero_documento' => ['required', 'string', 'max:20'],
        ]);

        $paciente = Paciente::query()
            ->where('tipo_documento', $filtro['tipo_documento'])
            ->where('numero_documento', $filtro['numero_documento'])
            ->first();

        $this->bitacora->registrar($this->usuario($request), AccionBitacora::Busqueda, $paciente?->id, $request->ip());

        return PacienteResource::collection($paciente !== null ? [$paciente] : []);
    }

    public function show(Request $request, Paciente $paciente): PacienteResource
    {
        Gate::authorize('view', $paciente);

        $this->bitacora->registrar($this->usuario($request), AccionBitacora::ConsultaDetalle, $paciente->id, $request->ip());

        return PacienteResource::make($paciente);
    }

    public function prescripciones(Request $request, Paciente $paciente): AnonymousResourceCollection
    {
        Gate::authorize('view', $paciente);

        $soloVigentes = $request->boolean('vigentes');

        $prescripciones = $paciente->prescripciones()
            ->with('items.producto')
            ->when($soloVigentes, fn ($q) => $q->whereDate('vigente_hasta', '>=', FechaNegocio::hoy()->toDateString())
                ->whereDate('fecha_emision', '<=', FechaNegocio::hoy()->toDateString()))
            ->orderByDesc('fecha_emision')
            ->get();

        $this->bitacora->registrar($this->usuario($request), AccionBitacora::ConsultaPrescripciones, $paciente->id, $request->ip());

        return PrescripcionResource::collection($prescripciones);
    }

    private function usuario(Request $request): User
    {
        $usuario = $request->user();
        assert($usuario instanceof User);

        return $usuario;
    }
}
