<?php

namespace App\Console\Commands;

use App\Domain\EstadoEscala;
use App\Domain\GradeDeCultos;
use App\Domain\Rodizio;
use App\Models\Banda;
use App\Models\Culto;
use App\Models\Escala;
use App\Models\Funcao;
use App\Models\Igreja;
use App\Models\Ministerio;
use App\Models\Pessoa;
use App\Models\Turno;
use App\Models\User;
use App\Support\IgrejaAtual;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Monta uma igreja de exemplo para testar o app com tudo preenchido: ministérios,
 * líderes, voluntários, bandas com rodízio, escalas em três estados e um choque de
 * horário pronto para mostrar. As datas são relativas a hoje.
 */
class DadosDeExemplo extends Command
{
    protected $signature = 'escala:dados-de-exemplo
        {--senha=senha-exemplo-123 : Senha de todos os logins de exemplo}
        {--force : Permite rodar em produção}';

    protected $description = 'Cria a "Igreja Exemplo" com ministérios, pessoas, bandas e escalas para testar o app';

    /** @var array<string, Pessoa> */
    private array $p = [];

    private string $senha = '';

    public function handle(IgrejaAtual $atual): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Isto cria logins com senha conhecida. Em produção só com --force.');

            return self::FAILURE;
        }

        if (Igreja::where('nome', 'Igreja Exemplo')->exists()) {
            $this->error('A "Igreja Exemplo" já existe. Para recomeçar: php artisan migrate:fresh.');

            return self::FAILURE;
        }

        $this->senha = (string) $this->option('senha');

        if (mb_strlen($this->senha) < 8) {
            $this->error('A senha precisa ter pelo menos 8 caracteres.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($atual) {
            $igreja = Igreja::create(['nome' => 'Igreja Exemplo']);
            $atual->definir($igreja->id);

            try {
                $this->montar();
            } finally {
                $atual->definir(null);
            }
        });

        $this->info('Igreja Exemplo criada. Logins (todos com a senha informada):');
        $this->table(['Papel', 'E-mail'], [
            ['Adm', 'adm@exemplo.test'],
            ['Líder da Mídia', 'ana@exemplo.test'],
            ['Líder do Louvor', 'marcos@exemplo.test'],
            ['Líder da Recepção', 'juliana@exemplo.test'],
            ['Voluntária (Mídia e Louvor)', 'helena@exemplo.test'],
            ['Voluntário com choque de horário', 'rafael@exemplo.test'],
            ['Voluntário (Mídia e Louvor)', 'bruno@exemplo.test'],
        ]);

        return self::SUCCESS;
    }

    private function pessoa(string $chave, string $nome, ?string $email = null, bool $adm = false): Pessoa
    {
        $user = $email ? User::create(['name' => $nome, 'email' => $email, 'password' => $this->senha]) : null;

        return $this->p[$chave] = Pessoa::create(['user_id' => $user?->id, 'nome' => $nome, 'email' => $email, 'adm' => $adm]);
    }

    private function montar(): void
    {
        // ---- pessoas ----
        $this->pessoa('adm', 'Valter Santos', 'adm@exemplo.test', true);
        $this->pessoa('ana', 'Ana Lima', 'ana@exemplo.test');
        $this->pessoa('marcos', 'Marcos Reis', 'marcos@exemplo.test');
        $this->pessoa('juliana', 'Juliana Prado', 'juliana@exemplo.test');
        $this->pessoa('helena', 'Helena Costa', 'helena@exemplo.test');
        $this->pessoa('rafael', 'Rafael Mota', 'rafael@exemplo.test');
        $this->pessoa('bruno', 'Bruno Souza', 'bruno@exemplo.test');

        foreach ([
            'lucas' => 'Lucas Tavares', 'diego' => 'Diego Almeida', 'ester' => 'Ester Moura', 'igor' => 'Igor Barros',
            'sofia' => 'Sofia Ribeiro', 'felipe' => 'Felipe Carvalho', 'fernanda' => 'Fernanda Luz', 'thiago' => 'Thiago Nunes',
            'camila' => 'Camila Rocha', 'gabriel' => 'Gabriel Freitas', 'bianca' => 'Bianca Lopes',
            'carla' => 'Carla Dias', 'pedro' => 'Pedro Alves', 'renata' => 'Renata Lima',
        ] as $chave => $nome) {
            $this->pessoa($chave, $nome);
        }

        // ---- ministérios, equipes e postos ----
        $midia = Ministerio::create(['nome' => 'Mídia', 'lider_id' => $this->p['ana']->id]);
        $louvor = Ministerio::create(['nome' => 'Louvor', 'lider_id' => $this->p['marcos']->id, 'modulos' => ['bandas']]);
        $recepcao = Ministerio::create(['nome' => 'Recepção', 'lider_id' => $this->p['juliana']->id]);

        $equipes = [
            [$midia, ['ana', 'carla', 'bruno', 'helena', 'rafael']],
            [$louvor, ['marcos', 'lucas', 'bruno', 'diego', 'ester', 'helena', 'rafael', 'igor', 'sofia', 'felipe', 'fernanda', 'thiago', 'camila', 'gabriel', 'bianca']],
            [$recepcao, ['juliana', 'pedro', 'renata']],
        ];

        $camera = $midia->postos()->create(['nome' => 'Câmera', 'vagas' => 2]);
        $som = $louvor->postos()->create(['nome' => 'Som', 'vagas' => 2]);
        $porta = $recepcao->postos()->create(['nome' => 'Porta', 'vagas' => 2]);

        foreach ($equipes as [$ministerio, $chaves]) {
            $ministerio->equipe()->attach(collect($chaves)->map(fn ($c) => $this->p[$c]->id)->all());
        }

        foreach ([[$camera, $equipes[0][1]], [$som, $equipes[1][1]], [$porta, $equipes[2][1]]] as [$posto, $chaves]) {
            $posto->pessoas()->attach(collect($chaves)->map(fn ($c) => $this->p[$c]->id)->all());
        }

        // ---- funções e bandas do Louvor ----
        $funcoes = collect(['voz' => 'Voz', 'guitarra' => 'Guitarra', 'bateria' => 'Bateria', 'baixo' => 'Baixo', 'teclado' => 'Teclado'])
            ->map(fn ($nome) => Funcao::create(['ministerio_id' => $louvor->id, 'nome' => $nome]));

        $sabe = ['marcos' => ['voz'], 'lucas' => ['guitarra', 'baixo'], 'bruno' => ['guitarra'], 'diego' => ['bateria'], 'ester' => ['teclado', 'voz'],
            'helena' => ['voz'], 'rafael' => ['bateria'], 'igor' => ['baixo'], 'sofia' => ['voz'], 'felipe' => ['guitarra'],
            'fernanda' => ['baixo'], 'thiago' => ['bateria'], 'camila' => ['teclado', 'voz'], 'gabriel' => ['guitarra'], 'bianca' => ['voz']];

        foreach ($sabe as $chave => $nomes) {
            $this->p[$chave]->funcoes()->attach(collect($nomes)->map(fn ($n) => $funcoes[$n]->id)->all());
        }

        $bandas = collect(['Banda 1', 'Banda 2', 'Banda 3'])->map(fn ($nome, $i) => Banda::create(['ministerio_id' => $louvor->id, 'nome' => $nome, 'ordem' => $i + 1]));

        // ---- cultos e escalas (publicada, a seguinte aberta e a outra em rascunho) ----
        $manha = Culto::create(['nome' => 'Culto da Manhã', 'tipo' => 'semanal', 'dia_semana' => 0, 'ordem' => 1, 'inicio' => '08:00', 'fim' => '11:30']);
        $noite = Culto::create(['nome' => 'Culto da Noite', 'tipo' => 'semanal', 'dia_semana' => 0, 'ordem' => 1, 'inicio' => '19:00', 'fim' => '21:00']);
        Culto::create(['nome' => 'Culto Fé', 'tipo' => 'semanal', 'dia_semana' => 3, 'ordem' => 1, 'inicio' => '20:00', 'fim' => '21:30']);

        // Depois do dia 20 o mês atual está quase acabando: começa no seguinte, para sempre haver turnos futuros.
        $base = CarbonImmutable::now()->startOfMonth();
        if (CarbonImmutable::now()->day > 20) {
            $base = $base->addMonth();
        }

        $meses = [
            [$base->format('Y-m'), EstadoEscala::Publicada],
            [$base->addMonth()->format('Y-m'), EstadoEscala::Aberta],
            [$base->addMonths(2)->format('Y-m'), EstadoEscala::Rascunho],
        ];

        // A escala aberta dá duas semanas para responder; as outras seguem o padrão (início do mês menos 5 dias).
        $prazoDe = fn (int $i) => $i === 1 ? CarbonImmutable::now()->addDays(14)->toDateString() : $base->addMonths($i)->subDays(5)->toDateString();

        $escalasLouvor = collect($meses)->map(fn ($m, $i) => Escala::create(['ministerio_id' => $louvor->id, 'mes' => $m[0], 'status' => $m[1], 'prazo' => $prazoDe($i)]));

        // Louvor: manhã e noite de cada domingo, rodízio contínuo entre os três meses.
        $horarios = [];
        foreach ($meses as $i => [$mes]) {
            foreach (GradeDeCultos::ocorrencias('semanal', 0, 1, $mes) as $data) {
                $horarios[] = ['mes' => $i, 'data' => $data, 'inicio' => '08:00', 'fim' => '11:30', 'culto' => $manha];
                $horarios[] = ['mes' => $i, 'data' => $data, 'inicio' => '19:00', 'fim' => '21:00', 'culto' => $noite];
            }
        }

        $porHorario = Rodizio::atribuir($bandas->pluck('id')->all(), array_map(fn ($h) => ['data' => $h['data'], 'inicio' => $h['inicio']], $horarios));

        $turnosLouvor = collect($horarios)->map(fn ($h) => Turno::create([
            'escala_id' => $escalasLouvor[$h['mes']]->id, 'posto_id' => $som->id,
            'banda_id' => $porHorario[$h['data'].'|'.$h['inicio']], 'culto_id' => $h['culto']->id, 'culto_nome' => $h['culto']->nome,
            'data' => $h['data'], 'inicio' => $h['inicio'], 'fim' => $h['fim'], 'vagas' => 2,
        ]))->each(fn (Turno $t) => $t->setRelation('escala', $escalasLouvor->firstWhere('id', $t->escala_id)));

        // Membros fixos das bandas. O Rafael fica na banda do primeiro culto da manhã do mês que vem,
        // que é o mesmo horário do turno da Mídia: é o choque pronto para mostrar.
        $primeiraManhaDoProximoMes = $turnosLouvor->first(fn (Turno $t) => $t->escala_id === $escalasLouvor[1]->id && substr((string) $t->inicio, 0, 5) === '08:00');

        $fixos = [
            $bandas[0]->id => ['marcos', 'lucas', 'bruno', 'diego', 'ester'],
            $bandas[1]->id => ['helena', 'igor', 'sofia', 'felipe'],
            $bandas[2]->id => ['fernanda', 'thiago', 'camila', 'gabriel', 'bianca'],
        ];

        foreach ($fixos as $bandaId => $chaves) {
            $bandas->firstWhere('id', $bandaId)->membros()->attach(collect($chaves)->map(fn ($c) => $this->p[$c]->id)->all());
        }

        $bandas->firstWhere('id', $primeiraManhaDoProximoMes->banda_id)->membros()->attach($this->p['rafael']->id);

        $membrosPorBanda = $bandas->mapWithKeys(fn (Banda $b) => [$b->id => $b->membros()->get()]);

        // Mês atual (publicada): os dois primeiros membros de cada banda estão confirmados, com função.
        foreach ($turnosLouvor->where('escala_id', $escalasLouvor[0]->id) as $turno) {
            foreach ($membrosPorBanda[$turno->banda_id]->take(2) as $membro) {
                $confirmacao = $turno->confirmacoes()->create(['pessoa_id' => $membro->id]);
                $confirmacao->funcoes()->sync($membro->funcoes()->limit(1)->pluck('funcoes.id')->all());
            }
        }

        // Mês que vem (aberta): cada banda já marcou disponibilidade nos seus turnos.
        foreach ($turnosLouvor->where('escala_id', $escalasLouvor[1]->id) as $turno) {
            foreach ($membrosPorBanda[$turno->banda_id] as $membro) {
                $turno->disponibilidades()->create(['pessoa_id' => $membro->id]);
            }
        }

        // ---- Mídia e Recepção: manhã de cada domingo ----
        foreach ([[$midia, $camera], [$recepcao, $porta]] as [$ministerio, $posto]) {
            foreach ([0, 1] as $i) {
                $escala = Escala::create(['ministerio_id' => $ministerio->id, 'mes' => $meses[$i][0], 'status' => $meses[$i][1], 'prazo' => $prazoDe($i)]);

                foreach (GradeDeCultos::ocorrencias('semanal', 0, 1, $meses[$i][0]) as $n => $data) {
                    $turno = Turno::create([
                        'escala_id' => $escala->id, 'posto_id' => $posto->id, 'culto_id' => $manha->id, 'culto_nome' => $manha->nome,
                        'data' => $data, 'inicio' => '08:00', 'fim' => '11:30', 'vagas' => 2,
                    ]);

                    if ($i === 0) {
                        $equipe = $ministerio->equipe()->get()->pluck('id')->values();
                        $turno->confirmacoes()->create(['pessoa_id' => $equipe[$n % $equipe->count()]]);
                    }
                }
            }
        }
    }
}
