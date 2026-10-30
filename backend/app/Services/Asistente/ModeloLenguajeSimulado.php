<?php

declare(strict_types=1);

namespace App\Services\Asistente;

use Illuminate\Support\Str;

/**
 * Simulador determinista del modelo (mock): reglas por palabras clave. Permite probar y
 * demostrar el asistente sin depender de un proveedor externo ni de credenciales.
 */
final class ModeloLenguajeSimulado implements ModeloLenguaje
{
    private const VENCIMIENTO = '/\b(venc\w*|caduc\w*|expir\w*)\b/';

    private const STOCK_BAJO = '/\b(minimo|minima|reponer|reposicion|agotad\w*|faltan\w*|bajo\w*|debajo|escas\w*)\b/';

    public function interpretar(string $pregunta, array $bodegas): Interpretacion
    {
        $texto = $this->normalizar($pregunta);

        $vencimiento = preg_match(self::VENCIMIENTO, $texto) === 1;
        $stockBajo = preg_match(self::STOCK_BAJO, $texto) === 1;

        // Ninguna o ambas: no se adivina, se pide reformular.
        $intencion = match (true) {
            $vencimiento && ! $stockBajo => Intencion::LotesPorVencer,
            $stockBajo && ! $vencimiento => Intencion::StockBajoMinimo,
            default => Intencion::Desconocida,
        };

        return new Interpretacion(
            $intencion,
            $this->bodega($texto, $bodegas),
            $intencion === Intencion::LotesPorVencer ? $this->dias($texto) : null,
        );
    }

    public function nombre(): string
    {
        return 'simulado';
    }

    /**
     * @param  list<array{id: int, codigo: string, nombre: string}>  $bodegas
     */
    private function bodega(string $texto, array $bodegas): ?int
    {
        $mejor = null;
        $largoMejor = 0;

        foreach ($bodegas as $bodega) {
            $nombre = $this->normalizar($bodega['nombre']);
            // "Farmacia Sede Norte" también se reconoce como "sede norte".
            $candidatos = [$this->normalizar($bodega['codigo']), $nombre, (string) preg_replace('/^(bodega|farmacia)\s+/', '', $nombre)];

            foreach ($candidatos as $candidato) {
                $largo = mb_strlen($candidato);
                if ($largo >= 3 && $largo > $largoMejor && preg_match('/\b'.preg_quote($candidato, '/').'\b/', $texto) === 1) {
                    [$mejor, $largoMejor] = [$bodega['id'], $largo];
                }
            }
        }

        return $mejor;
    }

    private function dias(string $texto): ?int
    {
        if (preg_match('/\b(\d{1,3})\s*dias?\b/', $texto, $m) === 1) {
            return max(1, min(365, (int) $m[1]));
        }

        return match (true) {
            preg_match('/\bsemana\b/', $texto) === 1 => 7,
            preg_match('/\bmes\b/', $texto) === 1 => 30,
            default => null,
        };
    }

    private function normalizar(string $texto): string
    {
        return (string) preg_replace('/\s+/', ' ', mb_strtolower(Str::ascii($texto)));
    }
}
