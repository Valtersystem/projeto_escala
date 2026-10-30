<?php

namespace App\Rules;

use App\Domain\Horario;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * RN-46: aceita "08h00", "8:30", "8.30" e "8". Depois da validação, use
 * Horario::formatar() para guardar como HH:MM.
 */
class HorarioValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Horario::minutos(is_scalar($value) ? (string) $value : null) === null) {
            $fail('Informe um horário válido, como 08h00 ou 19:30.');
        }
    }
}
