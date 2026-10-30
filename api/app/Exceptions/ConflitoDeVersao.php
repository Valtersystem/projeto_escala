<?php

namespace App\Exceptions;

use DomainException;

/**
 * D-14: o registro foi alterado por outra pessoa depois que a tela foi aberta.
 * A camada HTTP responde 409.
 */
class ConflitoDeVersao extends DomainException
{
    public function __construct()
    {
        parent::__construct('Este registro foi alterado por outra pessoa. Recarregue a tela e tente de novo.');
    }
}
