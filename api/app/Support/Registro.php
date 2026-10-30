<?php

namespace App\Support;

use App\Models\Auditoria;
use App\Models\Turno;

/**
 * D-12: trilha de quem mudou o quê. O texto fica pronto para a tela e vai sem o nome de quem fez
 * (a tela mostra o autor ao lado).
 */
final class Registro
{
    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    public static function feito(
        string $acao,
        string $descricao,
        ?int $ministerioId = null,
        ?int $entidadeId = null,
        ?array $antes = null,
        ?array $depois = null,
    ): void {
        Auditoria::create([
            'pessoa_id' => request()->attributes->get('pessoa')?->id,
            'ministerio_id' => $ministerioId,
            'acao' => $acao,
            'entidade' => explode('.', $acao)[0],
            'entidade_id' => $entidadeId,
            'descricao' => mb_substr($descricao, 0, 255),
            'antes' => $antes,
            'depois' => $depois,
        ]);
    }

    /** "Som · 08/11 às 08:00" */
    public static function turno(Turno $turno): string
    {
        $turno->loadMissing('posto:id,nome');

        return ($turno->posto?->nome ?? 'Turno').' · '.$turno->data->format('d/m').' às '.substr((string) $turno->inicio, 0, 5);
    }
}
