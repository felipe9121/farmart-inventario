<?php

declare(strict_types=1);

namespace App\Actions\Dispensaciones;

use SensitiveParameter;

/**
 * Co-firma del regente que autoriza productos de control especial (RN-05).
 */
final readonly class CredencialesAutorizacion
{
    public function __construct(
        public string $email,
        // Oculta la clave en trazas de excepciones (RN-10: nada sensible en logs).
        #[SensitiveParameter] public string $password,
    ) {}

    public function __debugInfo(): array
    {
        return ['email' => $this->email, 'password' => '***'];
    }
}
