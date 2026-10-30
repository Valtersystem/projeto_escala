# Regras de negócio e casos de teste — App de escalas (v1)

Extraído do protótipo `index.html` (commit `be9c347`). Cada regra tem um código `RN-xx` e, quando verificável, um caso de teste `T-xx` no formato Dado / Quando / Então. Os testes servem de base para a API Laravel.

Decisões já tomadas para a v1: Laravel (API) + Vue PWA; líder ou Adm cadastra as pessoas; login com e-mail e senha, com opção de Google; cada filial é uma igreja individual (tenant `igreja_id` em todas as tabelas).

> Convenções: "protótipo" = o que o `index.html` faz hoje. "Decisão" = ponto em que o protótipo é omisso ou contraditório e a v1 precisa escolher (seção 12).

---

## 1. Glossário

| Termo | Significado |
|---|---|
| Igreja | Tenant. Tudo pertence a uma igreja. |
| Ministério | Grupo de serviço (Mídia, Louvor, Recepção). Tem no máximo um líder. |
| Posto | Função ou lugar onde se serve dentro de um ministério (Câmera, Som, Porta). Tem vagas padrão. |
| Banda | Grupo fixo dentro de um ministério (ex.: Banda 1, 2, 3 do Louvor). |
| Função | O que a pessoa faz no turno (voz, guitarra, bateria). Pertence a um ministério. |
| Culto | Item da grade da igreja (nome, dia da semana, horário). Vale para todos os ministérios. |
| Escala | Uma por ministério e por mês. Estados: rascunho → aberta → publicada. |
| Turno | Um dia de serviço dentro da escala: posto, data, horário, vagas, banda (opcional), culto (opcional). |
| Disponibilidade | O voluntário marca os turnos em que pode servir. Não é escala. |
| Confirmação | O líder confirma quem fica em cada vaga. É o que vira escala. |
| Troca | Pedido de substituição feito por quem está confirmado. |

---

## 2. Papéis e permissões

O protótipo troca de perfil por um botão, sem login (`Adm`, `Líder`, `Voluntário`). As permissões abaixo são a proposta para a v1, deduzida do que cada perfil vê e faz.

| Ação | Adm | Líder (do próprio ministério) | Voluntário |
|---|---|---|---|
| Criar, renomear e excluir ministério | sim | não | não |
| Vincular líder a ministério | sim | não | não |
| Cadastrar, editar e excluir pessoa (qualquer) | sim | não | não |
| Cadastrar pessoa na equipe do ministério | sim | sim | não |
| Grade de cultos | sim | só leitura | não |
| Postos, bandas, funções, equipe | não | sim | não |
| Escalas e turnos | não | sim | não |
| Confirmar, tirar da escala, publicar | não | sim | não |
| Resolver pedido de troca | não | sim | não |
| Marcar disponibilidade | não | não | sim |
| Pedir e cancelar substituição | não | não | sim |
| Ver escala própria (publicada) | não | não | sim |
| Relatório geral | sim | não | não |
| Relatório do ministério | não | sim | não |

**RN-01** Uma pessoa pode ser Adm, líder de um ou mais ministérios e voluntária em outros ao mesmo tempo.
**RN-02** O líder só enxerga e altera dados do ministério que lidera. (v1: exigir isso na API, não só na tela.)

- **T-01** Dado um líder do Mídia, quando ele pede os turnos do Louvor, então a API responde 403.
- **T-02** Dado um voluntário, quando ele tenta criar um posto, então a API responde 403.

---

## 3. Grade de cultos

**RN-03** Culto tem nome, tipo (`semanal` ou `mensal`), dia da semana (0 = domingo … 6 = sábado), horário de início e fim. Se for `mensal`, também tem `ordem` (1 a 5, ou -1 = última).
**RN-04** Culto semanal ocorre em todas as datas do mês com aquele dia da semana.
**RN-05** Culto mensal ocorre numa única data: a N-ésima ocorrência do dia no mês, ou a última se `ordem = -1`. Se a N-ésima não existir no mês (ex.: 5ª quarta), o culto não gera data.
**RN-06** Excluir um culto só remove da grade. Turnos já gerados guardam o nome do culto e continuam existindo.

- **T-03** Dado culto semanal de domingo, quando se pedem as datas de novembro/2026, então retorna 01, 08, 15, 22 e 29.
- **T-04** Dado culto mensal "2ª sexta", quando se pedem as datas de novembro/2026, então retorna só 13/11.
- **T-05** Dado culto mensal "5ª quarta" num mês com quatro quartas, então não retorna data.
- **T-06** Dado culto mensal com `ordem = -1`, então retorna a última ocorrência do dia no mês.
- **T-07** Dado turno gerado de um culto, quando o culto é excluído, então o turno continua e mantém o nome do culto.

---

## 4. Ministérios, pessoas, postos, bandas e funções

**RN-07** Nome do ministério é obrigatório. Um ministério tem no máximo um líder (`lider_id`, pode ser vazio). Quem assume a liderança passa a gerir o ministério.
**RN-08** Excluir ministério apaga seus postos, escalas e turnos, e tira o vínculo das pessoas. As pessoas continuam cadastradas.
**RN-09** Pessoa tem nome (obrigatório), telefone (opcional), indicador de Adm, ministérios, postos, funções que já faz, bandas fixas e indicador de ativo.
**RN-10** Pessoa inativa não recebe mais chamadas: não aparece como candidata a substituição.
**RN-11** Tirar a pessoa da equipe de um ministério remove também os postos dela daquele ministério.
**RN-12** Excluir pessoa remove a liderança dela, suas disponibilidades e confirmações.
**RN-13** Posto tem nome (obrigatório), descrição opcional e vagas padrão (mínimo 1). Excluir posto apaga seus turnos e tira o posto das pessoas.
**RN-14** Função tem nome e pertence a um ministério. Excluir função a remove das pessoas e dos turnos.
**RN-15** Banda pertence a um ministério. Cada pessoa pode ser membro fixo de zero, uma ou várias bandas. O líder edita os membros da banda.

- **T-08** Dado ministério com 2 postos e 3 escalas, quando é excluído, então postos, escalas e turnos somem e as pessoas continuam existindo sem o vínculo.
- **T-09** Dado voluntário em Mídia e Louvor, quando sai da equipe do Louvor, então perde os postos do Louvor e mantém os da Mídia.
- **T-10** Dado posto com turnos e pessoas vinculadas, quando excluído, então os turnos somem e as pessoas ficam sem esse posto.
- **T-11** Dado nome vazio ao criar ministério, posto ou pessoa, então a API rejeita com erro de validação.
- **T-12** Dado posto com vagas 0 ou negativas, então o valor é ajustado para 1.

---

## 5. Escalas e estados

**RN-16** Cada ministério tem no máximo uma escala por mês. Criar outra para o mesmo mês é recusado.
**RN-17** A escala nasce em `rascunho`, com prazo de resposta (data). Padrão sugerido: hoje + 7 dias.
**RN-18** Em `rascunho` ninguém além do líder vê a escala. O líder cria, edita e exclui turnos.
**RN-19** Só dá para liberar a escala (`rascunho` → `aberta`) se ela tiver ao menos um turno.
**RN-20** Em `aberta`, os voluntários do ministério marcam disponibilidade e o líder confirma quem fica em cada vaga.
**RN-21** Publicar (`aberta` → `publicada`) é permitido mesmo com turnos incompletos, pessoas sem função ou choques. O app só alerta antes.
**RN-22** O líder pode reabrir uma escala publicada (`publicada` → `aberta`).
**RN-23** O voluntário só vê seus turnos em "Minha escala" quando a escala está `publicada` e ele está confirmado.

- **T-13** Dado escala de novembro já criada para o ministério, quando se cria outra de novembro, então é recusada.
- **T-14** Dado escala em rascunho sem turnos, quando o líder tenta liberar, então é recusado com "adicione pelo menos um turno".
- **T-15** Dado escala em rascunho, quando um voluntário consulta as escalas abertas, então ela não aparece.
- **T-16** Dado escala publicada com turnos incompletos, quando o líder publica, então a publicação funciona e os turnos incompletos aparecem como "precisa de gente".
- **T-17** Dado voluntário confirmado em turno de escala `aberta`, quando consulta "Minha escala", então o turno não aparece; aparece só depois de `publicada`.
- **T-18** Dado escala publicada, quando reaberta, então volta para `aberta` e o líder pode alterar confirmações.

---

## 6. Turnos

**RN-24** Turno tem posto, data, início, fim, vagas (mínimo 1), banda opcional, culto opcional.
**RN-25** Só se edita data, horário e vagas, ou se exclui o turno, enquanto a escala está em `rascunho`. Excluir turno remove disponibilidades e confirmações dele.
**RN-26** Gerar turnos a partir da grade: o líder escolhe cultos e postos; para cada culto × data do mês × posto escolhido nasce um turno com as vagas padrão do posto. Só entram cultos que têm data no mês. Exige ao menos um posto cadastrado.
**RN-27** A geração não duplica: se já existe turno do mesmo posto, data e horário de início, ele é ignorado.
**RN-28** Turno avulso (fora da grade) pode ser criado à mão, com ou sem culto e com ou sem banda.

- **T-19** Dado culto de domingo e um posto com 2 vagas, quando se geram os turnos de novembro, então nascem 5 turnos com 2 vagas cada.
- **T-20** Dado turnos já gerados, quando se gera de novo com o mesmo culto e posto, então nada é duplicado.
- **T-21** Dado ministério sem postos, quando se tenta gerar, então é recusado com "crie um posto antes".
- **T-22** Dado turno com disponibilidades e confirmações, quando excluído, então elas também somem.
- **T-23** Dado turno em escala `aberta`, quando o líder tenta mudar a data, então é recusado.

---

## 7. Rodízio de bandas

**RN-29** Se o ministério tem bandas, ao gerar turnos cada turno recebe uma banda, em sequência: banda 1, 2, 3, 1, 2, 3…
**RN-30** A sequência é contínua entre meses. O primeiro turno do mês novo recebe a banda seguinte à do último turno anterior do mesmo ministério. Se não houver turno anterior com banda, começa pela primeira banda da lista.
**RN-31** Os turnos são ordenados por data e horário de início antes de receber banda.
**RN-32** O líder pode escolher a banda à mão ao criar um turno avulso. Banda vazia é permitida.
**RN-33** "Fora da banda fixa": se a pessoa não é membro fixo da banda do turno, o líder vê o aviso, mas pode confirmar.

- **T-24** Dado 3 bandas e novembro/2026 com Manhã e Noite aos domingos, quando se gera, então a sequência é B1,B2,B3,B1,B2,B3,B1,B2,B3,B1 (confere com a planilha da líder).
- **T-25** Dado novembro terminando na Banda 1, quando se gera dezembro, então o primeiro turno de dezembro é da Banda 2.
- **T-26** Dado ministério sem bandas, quando se gera, então os turnos ficam sem banda.
- **T-27** Dado pessoa da Banda 3, quando o líder a confirma num turno da Banda 1, então a confirmação funciona e o app marca "fora da banda fixa".

---

## 8. Disponibilidade e confirmação

**RN-34** O voluntário só marca turnos de escalas `aberta` dos ministérios em que está na equipe.
**RN-35** Marcar é alternar: tocar de novo desmarca.
**RN-36** O voluntário vê primeiro os turnos dos postos dele. Os demais postos do ministério aparecem como "fora do seu perfil" e também podem ser marcados. Se ele não tem nenhum posto marcado, vê todos.
**RN-37** Enviar disponibilidade exige ao menos um turno marcado.
**RN-38** Disponibilidade não bloqueia nada: pode ser marcada e enviada mesmo com choque, e mesmo depois do prazo (o prazo só informa).
**RN-39** O líder vê, por turno, "Confirmados (n/vagas)" e "Marcaram disponibilidade" (marcaram e ainda não foram confirmados).
**RN-40** Confirmar alguém é permitido mesmo com o turno lotado e mesmo com choque; o app não bloqueia (ver seção 12).
**RN-41** Confirmar alguém com choque gera aviso para a pessoa.
**RN-42** Tirar alguém do turno remove a confirmação e as funções dela nesse turno.
**RN-43** Se o ministério tem funções, ao confirmar o líder escolhe uma ou mais funções (ou "sem função"). Funções que a pessoa já faz aparecem primeiro.
**RN-44** "Carga" é um alerta quando a pessoa tem mais turnos confirmados que o limite configurado (padrão 3).

- **T-28** Dado escala publicada, quando o voluntário tenta marcar disponibilidade, então é recusado.
- **T-29** Dado voluntário que não é da equipe do ministério, quando tenta marcar turno dele, então é recusado.
- **T-30** Dado nenhum turno marcado, quando envia disponibilidade, então é recusado com "marque pelo menos um turno".
- **T-31** Dado turno marcado, quando toca de novo, então a marcação é removida.
- **T-32** Dado turno com 2 vagas e 2 confirmados, quando o líder confirma o terceiro, então a confirmação é aceita (decisão da seção 12).
- **T-33** Dado confirmação com choque, então a pessoa recebe aviso "você ficou escalado em dois lugares".
- **T-34** Dado confirmação, quando o líder tira a pessoa, então ela e suas funções saem do turno.
- **T-35** Dado pessoa com 4 turnos confirmados e limite 3, quando o líder abre o turno, então aparece o alerta de carga.

---

## 9. Choque de horário

**RN-45** Dois turnos chocam quando são do mesmo dia e os intervalos se sobrepõem: `ini1 < fim2` e `ini2 < fim1`.
**RN-46** Horários aceitam texto como `08h00`, `8:30`, `8.30` e `8`. Se o fim não existe ou é anterior ou igual ao início, assume-se duração de 60 minutos. Se o horário de algum turno não puder ser lido, os dois do mesmo dia são tratados como choque.
**RN-47** Turnos confirmados só contam como "firmes" se a escala deles não está em `rascunho`.
**RN-48** O choque vale entre ministérios diferentes e também entre bandas do mesmo ministério.
**RN-49** Para o voluntário marcando disponibilidade, o choque considera os turnos confirmados e os que ele já marcou em escalas abertas. O aviso aparece na hora ao marcar.
**RN-50** "Mesmo dia" é aviso separado: a pessoa tem outro turno confirmado no mesmo dia, sem sobreposição.

- **T-36** Dado turno A 08h00–11h30 e turno B 10h00–12h00 no mesmo dia, então chocam.
- **T-37** Dado A 08h00–11h30 e B 11h30–13h00, então não chocam (fim de um = início do outro).
- **T-38** Dado A 08h00–11h30 e B 19h00–21h00 no mesmo dia, então não chocam, mas B mostra "mesmo dia".
- **T-39** Dado horários `8:30` e `08h30`, então são lidos como o mesmo valor.
- **T-40** Dado fim anterior ao início, então o turno dura 60 minutos.
- **T-41** Dado confirmação em turno de escala em rascunho, então ela não conta como choque.
- **T-42** Dado Mídia 08h–11h30 e Louvor 08h–11h30 no mesmo dia, quando o voluntário marca os dois, então o app avisa choque nos dois.
- **T-43** Dado dois turnos que chocam em bandas diferentes do Louvor, quando a mesma pessoa é confirmada nos dois, então há choque.

---

## 10. Pedido de troca (substituição)

**RN-51** O voluntário confirmado num turno de escala `publicada` pode pedir substituição (botão "Não vou poder"), escolhendo um motivo (Viagem, Trabalho, Saúde, Compromisso de família, Imprevisto, Outro motivo) e, se quiser, uma observação.
**RN-52** Só pode haver um pedido aberto por pessoa e turno.
**RN-53** Quem pediu continua na escala até o líder resolver. O líder recebe aviso.
**RN-54** O voluntário pode cancelar o pedido; o líder é avisado e a pessoa segue no turno.
**RN-55** O líder resolve de uma de três formas: **manter** a pessoa (ela é avisada), **substituir** por outra, ou **deixar a vaga em aberto** (a pessoa é liberada e avisada).
**RN-56** Candidatos a substituir: pessoas ativas do ministério, não confirmadas nesse turno e que atendem o posto (ou não têm posto marcado). Ordem: dentro da banda do turno primeiro, depois quem marcou disponibilidade, depois a que tem menos turnos.
**RN-57** Substituir passa as funções da pessoa que saiu para a que entrou. Os dois são avisados. O líder pode escolher alguém com choque ("escolher mesmo assim").
**RN-58** A aba "Escalas" do líder mostra a quantidade de pedidos abertos.

- **T-44** Dado pedido aberto, quando a mesma pessoa pede de novo no mesmo turno, então é recusado.
- **T-45** Dado pedido aberto, então a pessoa continua confirmada no turno.
- **T-46** Dado pedido aberto, quando o voluntário cancela, então o estado vira `cancelado` e o líder é avisado.
- **T-47** Dado pedido aberto, quando o líder mantém, então estado `resolvido` e a pessoa é avisada.
- **T-48** Dado pedido aberto, quando o líder escolhe Carlos, então Carlos entra, a pessoa sai, Carlos herda as funções e os dois recebem aviso.
- **T-49** Dado pedido aberto, quando o líder deixa a vaga em aberto, então a pessoa sai do turno e é avisada.
- **T-50** Dado dois candidatos, um da banda do turno e outro de fora, então o da banda aparece primeiro.
- **T-51** Dado pessoa inativa, então ela não aparece como candidata.

---

## 11. Avisos, relatórios e ajustes

**RN-59** Avisos são mensagens internas para uma pessoa (lido ou não). São gerados em: confirmação com choque, publicação da escala, pedido de troca, cancelamento e cada resolução de troca. A publicação avisa cada confirmado com data, horário e função ("função a combinar" se não houver).
**RN-60** O voluntário pode marcar todos os avisos como lidos. A aba "Início" mostra o número de não lidos.
**RN-61** Relatório do líder: % de vagas preenchidas (`confirmados limitados às vagas / vagas`), turnos sem ninguém (sem nenhuma disponibilidade marcada), voluntários ativos, turnos confirmados por pessoa, e lista "onde aperta" (turnos sem disponibilidade).
**RN-62** Ajustes: máximo de turnos por pessoa (mín. 1, padrão 3), prazo padrão em dias (mín. 0, padrão 5) e orientações do ministério (texto mostrado ao voluntário).
**RN-63** Exportar e importar dados em JSON existem só no protótipo; a v1 usa o banco.

- **T-52** Dado escala publicada, então cada pessoa confirmada recebe um aviso com data, horário e função.
- **T-53** Dado 10 vagas e 7 confirmados, então o relatório mostra 70%.
- **T-54** Dado turno lotado além das vagas (ex.: 3 confirmados em 2 vagas), então o relatório conta só 2.
- **T-55** Dado turno sem nenhuma disponibilidade, então aparece em "onde aperta".

---

## 12. Decisões pendentes e inconsistências do protótipo

Pontos que a v1 precisa resolver. Cada um está marcado como `D-xx`.

| # | Ponto | O protótipo faz | Proposta |
|---|---|---|---|
| D-01 | Turno cheio | Aceita confirmar acima das vagas | Bloquear, com opção de o líder aumentar as vagas |
| D-02 | Prazo da escala | Só informa; marcação continua aberta depois dele | Bloquear marcação depois do prazo, líder pode estender |
| D-03 | Prazo padrão em dias | Configurável, mas sem efeito | Usar para calcular o prazo ao criar a escala, ou remover |
| D-04 | Limite de turnos | Texto diz "no mesmo mês", mas conta todos os confirmados | Contar por mês e só alertar |
| D-05 | Rodízio de bandas por posto | Se houver 2 postos no mesmo horário, cada posto recebe uma banda diferente | O rodízio deve ser por horário (slot), não por turno |
| D-06 | Aviso de choque ao publicar | O alerta diz "já estão escaladas em outro ministério", mas a condição (`escalaId` comparado com id do turno) é sempre verdadeira, então inclui também choques dentro do próprio ministério | Alertar choques com qualquer outro turno e dizer isso no texto |
| D-07 | Exclusões em cascata | Ao excluir ministério ou posto, ficam órfãos: confirmações, disponibilidades, funções, trocas e bandas | Usar chaves estrangeiras com cascata explícita |
| D-08 | Tirar da equipe | Remove os postos, mas não funções nem bandas do ministério | Remover também |
| D-09 | Bandas e membro inativo | Pessoa inativa ainda aparece em "Marcaram disponibilidade" | Esconder inativos |
| D-10 | Horário | Texto livre (`08h00`) | Tipo `time` no banco; aceitar `08h00` só na entrada |
| D-11 | Datas e fuso | Datas sem fuso | Definir fuso por igreja e guardar data e hora locais |
| D-12 | Edição depois de publicada | Só reabre, sem trilha de quem alterou | Registrar quem mudou o quê (auditoria) |
| D-13 | Pedido de troca | Só aparece em "Minha escala", ou seja, para turno confirmado em escala `publicada`; nada valida isso no servidor | Manter a regra e validar na API |
| D-14 | Concorrência | Dois líderes editando ao mesmo tempo | Controle por versão ou última alteração vence, com aviso |
| D-15 | Aprovação de novo voluntário | Não existe | Pedido de entrada pendente que o líder aprova ou recusa; Google só vincula pelo e-mail verificado |
| D-16 | Papéis e login | Não existem | Seção 2 + autenticação por e-mail e senha, e Google |
| D-17 | Cultos de uma igreja | Valem para todos os ministérios | Manter, com `igreja_id` |
| D-18 | Quantidade de cultos do Louvor | Planilha da líder tem Manhã e Noite; a grade tem 3 cultos no domingo | Confirmar com a líder quais cultos o Louvor serve |

---

## 13. Entidades sugeridas para a API

`igrejas`, `usuarios` (login), `pessoas`, `ministerios`, `ministerio_pessoa` (equipe), `postos`, `posto_pessoa`, `bandas`, `banda_pessoa`, `funcoes`, `funcao_pessoa` (o que já faz), `cultos`, `escalas` (único `ministerio_id + mes`), `turnos`, `disponibilidades` (`turno_id + pessoa_id`), `confirmacoes` (`turno_id + pessoa_id`), `confirmacao_funcao`, `trocas`, `avisos`, `pedidos_entrada` (futuro, D-15). Todas as tabelas de negócio levam `igreja_id`.

Índices e restrições únicas essenciais: `escalas (ministerio_id, mes)`, `disponibilidades (turno_id, pessoa_id)`, `confirmacoes (turno_id, pessoa_id)`, `trocas` com um aberto por `(turno_id, pessoa_id)`.
