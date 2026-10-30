<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sem escopo de igreja de propósito: quem aceita o convite ainda não está logado.
 * O isolamento vem do token, que é secreto e de uso único.
 */
#[Fillable(['pessoa_id', 'criado_por', 'token_hash', 'expira_em', 'usado_em'])]
class Convite extends Model
{
    protected $table = 'convites';

    protected function casts(): array
    {
        return [
            'expira_em' => 'datetime',
            'usado_em' => 'datetime',
        ];
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class)->withoutGlobalScopes();
    }

    public static function hashDe(string $token): string
    {
        return hash('sha256', $token);
    }

    public function vigente(): bool
    {
        return $this->usado_em === null && $this->expira_em->isFuture();
    }
}
