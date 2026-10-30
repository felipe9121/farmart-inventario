<?php

declare(strict_types=1);

namespace App\Services\Asistente;

use App\Models\Bodega;
use App\Services\Alertas\AlertasInventario;

/**
 * Asistente de inventario. El modelo interpreta la pregunta; los datos salen de la BD
 * (AlertasInventario) y la respuesta se arma con plantillas: el modelo nunca produce
 * cifras. La pregunta no se guarda ni se registra (puede contener datos personales).
 */
class Asistente
{
    public const SUGERENCIAS = [
        '¿Qué lotes vencen en los próximos 90 días en la Bodega Central?',
        '¿Qué productos están bajo el stock mínimo en la Farmacia Sede Norte?',
    ];

    public function __construct(
        private readonly ModeloLenguaje $modelo,
        private readonly AlertasInventario $alertas,
    ) {}

    public function responder(string $pregunta): RespuestaAsistente
    {
        $bodegas = Bodega::query()->where('activa', true)->orderBy('id')->get(['id', 'codigo', 'nombre']);
        $interpretacion = $this->modelo->interpretar($pregunta, $bodegas->map(fn (Bodega $b) => [
            'id' => $b->id, 'codigo' => $b->codigo, 'nombre' => $b->nombre,
        ])->values()->all());

        $lugar = $interpretacion->bodegaId !== null
            ? 'la '.$bodegas->firstWhere('id', $interpretacion->bodegaId)?->nombre
            : 'todas las bodegas';

        return match ($interpretacion->intencion) {
            Intencion::LotesPorVencer => $this->lotesPorVencer($interpretacion, $lugar),
            Intencion::StockBajoMinimo => $this->stockBajo($interpretacion, $lugar),
            Intencion::Desconocida => new RespuestaAsistente(
                Intencion::Desconocida,
                'Por ahora solo puedo responder dos preguntas: qué lotes están por vencer y qué productos están bajo el stock mínimo, en una bodega o en todas.',
                [],
                $interpretacion,
                $this->modelo->nombre(),
                self::SUGERENCIAS,
            ),
        };
    }

    private function lotesPorVencer(Interpretacion $interpretacion, string $lugar): RespuestaAsistente
    {
        $dias = $interpretacion->dias ?? AlertasInventario::DIAS_VENCIMIENTO;
        $lotes = $this->alertas->lotesPorVencer($interpretacion->bodegaId, $dias);

        if ($lotes === []) {
            $texto = "No hay lotes con stock que venzan en los próximos {$dias} días en {$lugar}.";
        } else {
            $vencidos = count(array_filter($lotes, fn (array $l) => $l['dias_para_vencer'] < 0));
            $proximo = $lotes[0];
            $texto = sprintf(
                'En %s hay %d %s con stock que vence%s en los próximos %d días%s. El primero es %s, lote %s, que %s.',
                $lugar,
                count($lotes),
                count($lotes) === 1 ? 'lote' : 'lotes',
                count($lotes) === 1 ? '' : 'n',
                $dias,
                $vencidos > 0 ? " ({$vencidos} ya ".($vencidos === 1 ? 'vencido' : 'vencidos').')' : '',
                $proximo['producto'],
                $proximo['numero_lote'],
                $proximo['dias_para_vencer'] < 0
                    ? "venció el {$proximo['fecha_vencimiento']} y aún tiene {$proximo['cantidad']} unidades"
                    : "vence el {$proximo['fecha_vencimiento']} ({$proximo['cantidad']} unidades)",
            );
        }

        return new RespuestaAsistente(Intencion::LotesPorVencer, $texto, $lotes, $interpretacion, $this->modelo->nombre());
    }

    private function stockBajo(Interpretacion $interpretacion, string $lugar): RespuestaAsistente
    {
        $productos = $this->alertas->stockBajoMinimo($interpretacion->bodegaId);

        if ($productos === []) {
            $texto = "Ningún producto está bajo el stock mínimo en {$lugar}.";
        } else {
            usort($productos, fn (array $a, array $b) => $b['faltante'] <=> $a['faltante']);
            $mayor = $productos[0];
            $texto = sprintf(
                'En %s hay %d %s bajo el stock mínimo. El de mayor faltante es %s en %s: %d de %d unidades (faltan %d).',
                $lugar,
                count($productos),
                count($productos) === 1 ? 'producto' : 'productos',
                $mayor['producto'],
                $mayor['bodega'],
                $mayor['disponible'],
                $mayor['minimo'],
                $mayor['faltante'],
            );
        }

        return new RespuestaAsistente(Intencion::StockBajoMinimo, $texto, $productos, $interpretacion, $this->modelo->nombre());
    }
}
