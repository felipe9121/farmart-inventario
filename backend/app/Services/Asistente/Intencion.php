<?php

declare(strict_types=1);

namespace App\Services\Asistente;

/**
 * Las únicas preguntas que responde el asistente (alcance acordado: 2 preguntas).
 */
enum Intencion: string
{
    case LotesPorVencer = 'LOTES_POR_VENCER';
    case StockBajoMinimo = 'STOCK_BAJO_MINIMO';
    case Desconocida = 'DESCONOCIDA';
}
