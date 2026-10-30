<?php

declare(strict_types=1);

namespace App\Services\Asistente;

/**
 * Contrato del modelo de lenguaje: solo interpreta la pregunta. No recibe datos de
 * inventario ni de pacientes (solo el catálogo de bodegas para reconocerlas) y no redacta
 * cifras, así que no puede inventarlas.
 */
interface ModeloLenguaje
{
    /**
     * @param  list<array{id: int, codigo: string, nombre: string}>  $bodegas
     */
    public function interpretar(string $pregunta, array $bodegas): Interpretacion;

    public function nombre(): string;
}
