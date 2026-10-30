<?php

namespace App\Exceptions;

use DomainException;

/**
 * Violação de uma regra de negócio (ver docs/regras-de-negocio.md).
 * A camada HTTP converte em resposta 422.
 */
class RegraDeNegocio extends DomainException
{
}
