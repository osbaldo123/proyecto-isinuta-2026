<?php

namespace App\Services;

use App\Models\Afiliado;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class MultasConsultaService
{
    public function puedeGestionar(?User $user): bool
    {
        return $user?->hasPermission('multas.gestionar') ?? false;
    }

    public function puedeGestionarPagos(?User $user): bool
    {
        return $user?->hasPermission('pagos.gestionar') ?? false;
    }

    public function puedeVerListadoPagos(?User $user): bool
    {
        if ($this->puedeGestionarPagos($user)) {
            return true;
        }

        if (! ($user?->hasPermission('pagos.ver') ?? false)) {
            return false;
        }

        return $user->isPresidente() || $user->isAdmin();
    }

    public function puedeVerListadoMultas(?User $user): bool
    {
        if ($this->puedeGestionar($user)) {
            return true;
        }

        if (! ($user?->hasPermission('multas.ver') ?? false)) {
            return false;
        }

        return $user->isPresidente() || $user->isAdmin();
    }

    public function esConsultaVinculada(?User $user): bool
    {
        return $user?->afiliado_id !== null;
    }

    /**
     * @return array{afiliado: ?Afiliado, mensaje: ?string}
     */
    public function resolverAfiliadoConsulta(Request $request): array
    {
        $user = $request->user();

        if ($user?->afiliado_id) {
            $afiliado = Afiliado::query()->find($user->afiliado_id);

            return [
                'afiliado' => $afiliado,
                'mensaje' => $afiliado ? null : 'Su cuenta no está vinculada a un afiliado activo.',
            ];
        }

        $ci = preg_replace('/\D/', '', (string) $request->query('ci', ''));

        if ($ci === '') {
            return ['afiliado' => null, 'mensaje' => null];
        }

        $afiliado = $this->buscarPorCi($ci);

        if (! $afiliado) {
            return [
                'afiliado' => null,
                'mensaje' => "No se pudo encontrar al afiliado con el CI: {$ci}",
            ];
        }

        return ['afiliado' => $afiliado, 'mensaje' => null];
    }

    public function buscarPorCi(string $ci): ?Afiliado
    {
        return Afiliado::query()->where('ci', $ci)->first();
    }

    /**
     * @return LengthAwarePaginator<int, Multa>
     */
    public function multasDeAfiliado(Afiliado $afiliado, Request $request): LengthAwarePaginator
    {
        $estado = trim((string) $request->query('estado', Multa::ESTADO_PENDIENTE));
        $estadosValidos = ['todas', Multa::ESTADO_PENDIENTE, Multa::ESTADO_PAGADA, Multa::ESTADO_ANULADA];
        if (! in_array($estado, $estadosValidos, true)) {
            $estado = Multa::ESTADO_PENDIENTE;
        }

        $motivo = trim((string) $request->query('motivo', ''));
        $motivosValidos = [
            '',
            Multa::MOTIVO_MORA_TRIMESTRAL,
            Multa::MOTIVO_MORA_ANUAL,
            Multa::MOTIVO_FALTA_TRABAJO,
            Multa::MOTIVO_INDIVIDUAL,
        ];
        if (! in_array($motivo, $motivosValidos, true)) {
            $motivo = '';
        }

        $query = Multa::query()
            ->with(['afiliado:id,nombres,apellidos,ci', 'lote.otb:id,nombre'])
            ->where('afiliado_id', $afiliado->id);

        if ($estado === Multa::ESTADO_PENDIENTE) {
            $query->porCobrar();
        } elseif ($estado !== 'todas') {
            $query->where('estado', $estado);
        }

        if ($motivo !== '') {
            $query->where('motivo', $motivo);
        }

        return $query->latest('fecha_generacion')->paginate(20)->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, Pago>
     */
    public function pagosDeAfiliado(Afiliado $afiliado, Request $request): LengthAwarePaginator
    {
        return Pago::query()
            ->with(['afiliado:id,nombres,apellidos,ci', 'cobrador:id,name'])
            ->where('afiliado_id', $afiliado->id)
            ->latest('fecha_pago')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function resumenAfiliado(Afiliado $afiliado): array
    {
        $pendientes = $afiliado->multas()->porCobrar()->get(['monto', 'saldo'])
            ->sum(fn (Multa $multa): float => (float) ($multa->saldo ?? $multa->monto));

        return [
            'id' => $afiliado->id,
            'nombre_completo' => $afiliado->nombre_completo,
            'ci' => $afiliado->ci,
            'estado' => $afiliado->estado,
            'total_multas_pendientes' => (float) $pendientes,
            'lotes' => $afiliado->lotes()
                ->with('otb:id,nombre')
                ->orderBy('id')
                ->get(['id', 'numero_lote', 'otb_id'])
                ->map(fn ($lote): array => [
                    'id' => $lote->id,
                    'numero_lote' => (string) ($lote->numero_lote ?? ''),
                    'otb' => $lote->otb?->nombre,
                ])
                ->all(),
        ];
    }

    public function assertPuedeVerMulta(Request $request, Multa $multa): void
    {
        $user = $request->user();

        if ($this->puedeVerListadoMultas($user)) {
            return;
        }

        if ($this->esConsultaVinculada($user)) {
            if ($multa->afiliado_id !== $user->afiliado_id) {
                abort(403);
            }

            return;
        }

        ['afiliado' => $afiliado] = $this->resolverAfiliadoConsulta($request);

        if (! $afiliado || $multa->afiliado_id !== $afiliado->id) {
            abort(403);
        }
    }

    /**
     * Estado de cuenta: quien gestiona pagos, presidencia y administración ven cualquier lote;
     * una cuenta de consulta, solo los lotes de su afiliado.
     */
    public function assertPuedeVerLote(Request $request, LoteAfiliado $lote): void
    {
        $user = $request->user();

        if ($this->puedeGestionarPagos($user) || $user?->isPresidente() || $user?->isAdmin()) {
            return;
        }

        if ($this->esConsultaVinculada($user)) {
            abort_unless((int) $lote->afiliado_id === (int) $user->afiliado_id, 403);

            return;
        }

        ['afiliado' => $afiliado] = $this->resolverAfiliadoConsulta($request);

        abort_unless($afiliado && (int) $lote->afiliado_id === (int) $afiliado->id, 403);
    }

    public function assertPuedeVerPago(Request $request, Pago $pago): void
    {
        $user = $request->user();

        if ($this->puedeGestionarPagos($user)) {
            return;
        }

        if ($user?->isPresidente() || $user?->isAdmin()) {
            return;
        }

        if ($this->esConsultaVinculada($user)) {
            if ($pago->afiliado_id !== $user->afiliado_id) {
                abort(403);
            }

            return;
        }

        ['afiliado' => $afiliado] = $this->resolverAfiliadoConsulta($request);

        if (! $afiliado || $pago->afiliado_id !== $afiliado->id) {
            abort(403);
        }
    }
}
