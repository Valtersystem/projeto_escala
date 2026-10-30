# Regras de negócio e casos de teste — App de escalas (v1)

Extraído do protótipo `index.html` (commit `be9c347`) e atualizado em 30/10/2026 com as decisões da seção 12. Onde uma decisão difere do protótipo, vale a decisão. Cada regra tem um código `RN-xx` e, quando verificável, um caso de teste `T-xx` no formato Dado / Quando / Então. Os testes servem de base para a API Laravel.

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
**RN-11** Tirar a pessoa da equipe de um ministério remove também os postos, as funções e as bandas dela daquele ministério (D-08).
**RN-12** Excluir pessoa remove a liderança dela, suas disponibilidades e confirmações.
**RN-13** Posto tem nome (obrigatório), descrição opcional e vagas padrão (mínimo 1). Excluir posto apaga seus turnos e tira o posto das pessoas.
**RN-14** Função tem nome e pertence a um ministério. Excluir função a remove das pessoas e dos turnos.
**RN-15** Banda pertence a um ministério. Cada pessoa pode ser membro fixo de zero, uma ou várias bandas. O líder edita os membros da banda.

- **T-08** Dado ministério com 2 postos e 3 escalas, quando é excluído, então postos, escalas e turnos somem e as pessoas continuam existindo sem o vínculo.
- **T-09** Dado voluntário em Mídia e Louvor, quando sai da equipe do Louvor, então perde os postos, as funções e as bandas do Louvor e mantém os da Mídia.
- **T-10** Dado posto com turnos e pessoas vinculadas, quando excluído, então os turnos somem e as pessoas ficam sem esse posto.
- **T-11** Dado nome vazio ao criar ministério, posto ou pessoa, então a API rejeita com erro de validação.
- **T-12** Dado posto com vagas 0 ou negativas, então o valor é ajustado para 1.

---

## 5. Escalas e estados

**RN-16** Cada ministério tem no máximo uma escala por mês. Criar outra para o mesmo mês é recusado.
**RN-17** A escala nasce em `rascunho`, com prazo de resposta (data) definido pelo líder. O valor sugerido é o início do mês menos o "prazo padrão em dias" da igreja (padrão 5); o líder pode mudar ao criar (D-02, D-03).
**RN-18** Em `rascunho` ninguém além do líder vê a escala. O líder cria, edita e exclui turnos.
**RN-19** Só dá para liberar a escala (`rascunho` → `aberta`) se ela tiver ao menos um turno.
**RN-20** Em `aberta`, os voluntários do ministério marcam disponibilidade e o líder confirma quem fica em cada vaga.
**RN-21** Publicar (`aberta` → `publicada`) é permitido mesmo com turnos incompletos, pessoas sem função ou choques. O app só alerta antes.
**RN-22** O líder pode reabrir uma escala publicada (`publicada` → `aberta`).
**RN-23** O voluntário só vê seus turnos em "Minha escala" quando a escala está `publicada` e ele está confirmado.

- **T-13** Dado escala de novembro já criada para o ministério, quando se cria outra de novembro, então é recusada.
- **T-56** Dado "prazo padrão" de 5 dias e escala de novembro/2026, quando o líder abre o formulário de criação, então o prazo sugerido é 27/10/2026 e ele pode alterá-lo.
- **T-14** Dado escala em rascunho sem turnos, quando o líder tenta liberar, então é recusado com "adicione pelo menos um turno".
- **T-15** Dado escala em rascunho, quando um voluntário consulta as escalas abertas, então ela não aparece.
- **T-16** Dado escala publicada com turnos incompletos, quando o líder publica, então a publicação funciona e os turnos incompletos aparecem como "precisa de gente".
- **T-17** Dado voluntário confirmado em turno de escala `aberta`, quando consulta "Minha escala", então o turno não aparece; aparece só depois de `publicada`.
- **T-18** Dado escala publicada, quando reaberta, então volta para `aberta` e o líder pode alterar confirmações.

---

## 6. Turnos

**RN-24** Turno tem posto, data, início, fim, vagas (mínimo 1), banda opcional, culto opcional.
**RN-25** Data e horário só se editam, e o turno só se exclui, enquanto a escala está em `rascunho`. Excluir turno remove disponibilidades e confirmações dele. As vagas podem ser aumentadas até a escala ser publicada (D-01).
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
**RN-31** Os turnos são ordenados por data e horário de início antes de receber banda. Turnos do mesmo horário (de postos diferentes) recebem a mesma banda: o rodízio avança por horário, não por turno (D-05).
**RN-32** O líder pode escolher a banda à mão ao criar um turno avulso. Banda vazia é permitida.
**RN-33** "Fora da banda fixa": se a pessoa não é membro fixo da banda do turno, o líder vê o aviso, mas pode confirmar.

- **T-24** Dado 3 bandas e novembro/2026 com Manhã e Noite aos domingos, quando se gera, então a sequência é B1,B2,B3,B1,B2,B3,B1,B2,B3,B1 (confere com a planilha da líder).
- **T-25** Dado novembro terminando na Banda 1, quando se gera dezembro, então o primeiro turno de dezembro é da Banda 2.
- **T-26** Dado ministério sem bandas, quando se gera, então os turnos ficam sem banda.
- **T-62** Dado dois postos com turnos no mesmo dia e horário, quando se gera o rodízio, então os dois turnos recebem a mesma banda.
- **T-27** Dado pessoa da Banda 3, quando o líder a confirma num turno da Banda 1, então a confirmação funciona e o app marca "fora da banda fixa".

---

## 8. Disponibilidade e confirmação

**RN-34** O voluntário só marca turnos de escalas `aberta` dos ministérios em que está na equipe.
**RN-35** Marcar é alternar: tocar de novo desmarca.
**RN-36** O voluntário vê primeiro os turnos dos postos dele. Os demais postos do ministério aparecem como "fora do seu perfil" e também podem ser marcados. Se ele não tem nenhum posto marcado, vê todos.
**RN-37** Enviar disponibilidade exige ao menos um turno marcado.
**RN-38** Choque não impede marcar nem enviar disponibilidade. Depois do prazo definido pelo líder, a marcação é bloqueada, e o líder pode estender o prazo (D-02).
**RN-39** O líder vê, por turno, "Confirmados (n/vagas)" e "Marcaram disponibilidade" (marcaram e ainda não foram confirmados).
**RN-40** Confirmar é bloqueado quando o turno já está lotado; o líder pode aumentar as vagas. Choque não bloqueia a confirmação (D-01).
**RN-41** Confirmar alguém com choque gera aviso para a pessoa.
**RN-42** Tirar alguém do turno remove a confirmação e as funções dela nesse turno.
**RN-43** Se o ministério tem funções, ao confirmar o líder escolhe uma ou mais funções (ou "sem função"). Funções que a pessoa já faz aparecem primeiro.
**RN-44** "Carga" é um alerta quando a pessoa tem, no mês do turno, mais turnos confirmados que o limite configurado (padrão 3). Só alerta, não bloqueia (D-04).
**RN-64** O voluntário não vê a quantidade de vagas dos turnos nem quantos faltam; essa informação é só do líder (D-01).
**RN-65** O líder e o Adm não veem pessoas inativas na lista "Marcaram disponibilidade" (D-09).

- **T-28** Dado escala publicada, quando o voluntário tenta marcar disponibilidade, então é recusado.
- **T-29** Dado voluntário que não é da equipe do ministério, quando tenta marcar turno dele, então é recusado.
- **T-30** Dado nenhum turno marcado, quando envia disponibilidade, então é recusado com "marque pelo menos um turno".
- **T-31** Dado turno marcado, quando toca de novo, então a marcação é removida.
- **T-57** Dado escala com prazo ontem, quando o voluntário tenta marcar disponibilidade, então é recusado.
- **T-58** Dado escala com prazo vencido, quando o líder estende o prazo, então o voluntário volta a poder marcar.
- **T-32** Dado turno com 2 vagas e 2 confirmados, quando o líder confirma o terceiro, então é recusado com "turno lotado"; depois que o líder aumenta as vagas para 3, a confirmação é aceita.
- **T-59** Dado voluntário, quando consulta turnos da escala aberta, então a resposta da API não traz a quantidade de vagas nem quantos faltam.
- **T-33** Dado confirmação com choque, então a pessoa recebe aviso "você ficou escalado em dois lugares".
- **T-34** Dado confirmação, quando o líder tira a pessoa, então ela e suas funções saem do turno.
- **T-35** Dado pessoa com 4 turnos confirmados no mês e limite 3, quando o líder abre o turno desse mês, então aparece o alerta de carga.
- **T-60** Dado pessoa com 4 turnos confirmados em meses diferentes (2 por mês) e limite 3, então não aparece alerta de carga.
- **T-61** Dado pessoa inativa que marcou disponibilidade, quando o líder abre o turno, então ela não aparece em "Marcaram disponibilidade".

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

## 11b. Acesso, login e isolamento por igreja (v1)

**RN-66** O login é por e-mail e senha. A mensagem de erro é a mesma para e-mail inexistente, senha errada e conta que só entra com Google, para não revelar quais e-mails existem.
**RN-67** Pessoa inativa ou sem vínculo com um login não entra. Se for desativada depois de logada, o token deixa de funcionar (403).
**RN-68** No máximo 5 tentativas de login por minuto para cada e-mail e IP; depois responde 429.
**RN-69** O token de acesso expira em 30 dias. O logout invalida o token.
**RN-70** Toda consulta é filtrada pela igreja da pessoa logada. Sem igreja definida, a consulta volta vazia (nunca mostra dados de outra igreja). Ao criar um registro, a igreja atual é preenchida sozinha.
**RN-71** Violação de regra de negócio responde 422 com a mensagem para a tela.
**RN-72** A primeira igreja e o primeiro Adm são criados por comando (`php artisan escala:criar-igreja`). O Adm cadastra o resto.

- **T-64** Dado login válido, quando entra, então recebe o token e os dados da pessoa, e o token abre `/api/eu`.
- **T-65** Dado e-mail inexistente ou senha errada, então a mensagem de erro é a mesma.
- **T-66** Dado pessoa inativa, quando tenta entrar, então é recusada.
- **T-67** Dado token emitido antes da desativação, quando a pessoa é desativada, então o token passa a responder 403.
- **T-68** Dado logout, então o mesmo token responde 401 na requisição seguinte.
- **T-69** Dado requisição sem token, então as rotas protegidas respondem 401.
- **T-70** Dadas 5 tentativas de login seguidas, então a sexta responde 429, mesmo com a senha certa.
- **T-71** Dadas duas igrejas, então cada uma vê só os próprios ministérios.
- **T-72** Dada consulta sem igreja definida, então o resultado é vazio.
- **T-73** Dado o comando de criar igreja com dados válidos, então nascem a igreja e o Adm, que consegue entrar; com senha curta ou e-mail repetido, nada é criado.

---

## 11c. Cadastros por papel e convite (v1)

**RN-73** O líder (ou o Adm) gera um link de convite para a pessoa. Exige e-mail cadastrado e pessoa ativa.
**RN-74** O convite vale 7 dias e só pode ser usado uma vez. Só o hash do token é guardado. Um convite novo invalida os anteriores ainda não usados.
**RN-75** Ao aceitar o convite, a pessoa escolhe a senha (mínimo 8 caracteres). Se ainda não tem login, o login é criado com o e-mail cadastrado. Se já tem, a senha é trocada e as sessões anteriores são encerradas.
**RN-76** Na v1 não há recuperação de senha por e-mail. Quem esquece a senha recebe um novo convite do líder. A recuperação por e-mail fica para o fim.
**RN-77** Convite inexistente, expirado ou já usado recebe a mesma mensagem.
**RN-78** Quem pode o quê: o **Adm** cria, renomeia e exclui ministérios, vincula o líder, cadastra e exclui pessoas (qualquer uma) e define quem é Adm. O **líder** cadastra pessoas só nos ministérios que lidera, inclui e tira da equipe, edita e inativa pessoas da equipe, e gere os postos do ministério. O **voluntário** não cadastra nada. O Adm não gere postos de ministério alheio.
**RN-79** O e-mail é único por igreja. Quem já tem login não pode trocar de e-mail. Ninguém exclui a si mesmo.
**RN-80** Registro de outra igreja responde 404, como se não existisse.

- **T-74** Dado Adm, líder e voluntária, então só o Adm cria ministério.
- **T-75** Dado nome vazio ou repetido (ministério, pessoa ou posto), então a API recusa com erro de validação.
- **T-76** Dado Adm, quando vincula um líder, então o ministério passa a ter esse líder; o líder não faz isso.
- **T-77** Dado líder da Mídia, quando consulta ou cria postos do Louvor, então recebe 403; a voluntária também não cria posto.
- **T-78** Dadas vagas 0, negativas ou vazias, então o posto fica com 1 vaga.
- **T-79** Dado posto com turnos, quando o líder do ministério o exclui, então os turnos somem e as pessoas continuam.
- **T-80** Dado ministério com postos, escalas e turnos, quando o Adm o exclui, então tudo isso some e as pessoas continuam.
- **T-81** Dado ministério de outra igreja, então editar ou excluir responde 404 e nada muda.
- **T-82** Dada pessoa em Louvor e Mídia, quando sai da equipe do Louvor, então perde postos, funções e bandas do Louvor e mantém as da Mídia.
- **T-83** Dado líder, quando cadastra pessoa, então só vale para os ministérios que ele lidera, sem marcar Adm.
- **T-84** Dado líder, quando mexe em pessoa de outro ministério ou tenta definir Adm, então recebe 403.
- **T-85** Dada pessoa com login, quando se troca o e-mail, então a API recusa; repetir o mesmo e-mail é aceito.
- **T-86** Dado Adm, quando exclui uma pessoa, então a pessoa e o login dela saem; excluir a si mesmo é recusado.
- **T-87** Dados Adm, líder e voluntária, então cada um lista só o que o papel permite.
- **T-88** Dado convite gerado pelo líder, quando a pessoa aceita com uma senha, então o login é criado e ela entra.
- **T-89** Dada pessoa sem e-mail, então não dá para convidar.
- **T-90** Dado convite expirado, usado ou inexistente, então é recusado.
- **T-91** Dado um segundo convite, então o primeiro deixa de valer.
- **T-92** Dada pessoa que já tem login, quando aceita um convite, então a senha é trocada, a antiga deixa de valer e as sessões antigas caem.
- **T-93** Dado voluntária ou líder de outro ministério, então não gera convite; o Adm gera.
- **T-94** Dada senha com menos de 8 caracteres, então a API recusa e o convite continua valendo.
- **T-95** Dado convite gerado, então o banco guarda só o hash do token.

---

## 11d. Bandas, funções, cultos, escalas e turnos pela API (v1)

**RN-81** Banda tem nome e `ordem` (a posição no rodízio). Sem ordem informada, entra no fim da fila. Só o líder do ministério cria, edita e exclui. Os membros fixos precisam estar na equipe do ministério. Excluir a banda mantém os turnos, que ficam sem banda.
**RN-82** Função é criada pelo líder. O que a pessoa "já faz" aceita só funções do ministério e só de quem está na equipe; as funções de outros ministérios não mudam.
**RN-83** Todos veem a grade de cultos; só o Adm altera. O horário aceita `8h30`, `19:00` etc. e é guardado como `HH:MM`. Culto mensal usa ordem 1 a 5 ou -1 (última).
**RN-84** Só o líder cria a escala. As ações de estado valem cada uma para um estado: liberar só em rascunho, publicar só em aberta, reabrir só em publicada. Rascunho é invisível para quem não é o líder.
**RN-85** Turno avulso pode ser criado em escala em rascunho ou aberta (não em publicada). Posto e banda precisam ser do mesmo ministério. As vagas padrão vêm do posto (mínimo 1). Edição: em rascunho muda tudo; em aberta só as vagas, e só para mais; em publicada nada (reabra antes). Só se exclui turno em rascunho.
**RN-86** "Gerar dos cultos" cria um turno por culto, data do mês e posto escolhidos, sem duplicar os que já existem. Recusa quando o ministério não tem posto ("Crie um posto antes."), quando nenhum culto cai no mês e quando todos os turnos já existem. Se o ministério tem bandas, o rodízio é aplicado (RN-29 a RN-31).
**RN-87** Editar o prazo da escala ou um turno exige mandar a `versao` que a tela viu. Se o registro já mudou, a API responde 409 e nada é gravado (D-14).
**RN-88** O voluntário não recebe a quantidade de vagas nem a versão dos turnos (RN-64). Em escala publicada, só recebe os turnos em que está confirmado (RN-23).

- **T-96** Dado líder, quando cria bandas, então a ordem do rodízio segue a criação.
- **T-97** Dado Adm, voluntária ou líder de outro ministério, então não mexem nas bandas; quem serve no ministério vê.
- **T-98** Dados membros fixos, então só a equipe do ministério entra, e lista vazia esvazia a banda.
- **T-99** Dada banda com turno, quando excluída, então o turno continua sem banda.
- **T-100** Dado líder, quando cria funções e define o que a pessoa faz, então só as do ministério mudam.
- **T-101** Dado líder e Adm, então só o Adm altera a grade de cultos e o horário é padronizado (`9h` vira `09:00`).
- **T-102** Dados cultos semanal e mensal, então a API devolve as datas do mês.
- **T-103** Dado turno com culto, quando o culto é excluído, então o turno guarda o nome do culto.
- **T-104** Dada nova escala, então nasce em rascunho, versão 1, com o prazo sugerido (início do mês menos 5 dias); mês inválido é recusado.
- **T-105** Dado mês que já tem escala, então a segunda é recusada; outro ministério pode ter a sua.
- **T-106** Dados Adm, voluntária e líder de outro ministério, então não criam escala; escala de outra igreja responde 404.
- **T-107** Dada escala em rascunho, então o voluntário não a vê na lista nem pelo id.
- **T-108** Dada escala sem turnos, então não libera; com turno libera e a versão sobe; liberar de novo é recusado.
- **T-109** Dada escala aberta com turnos incompletos, então publica; publicar rascunho é recusado.
- **T-110** Dada escala publicada, então reabre; reabrir escala não publicada é recusado.
- **T-111** Dado voluntário, então não vê vagas nem versão, e em escala publicada só vê o turno em que está confirmado.
- **T-112** Dado culto de domingo e posto de 2 vagas, quando gera novembro, então nascem 5 turnos com 2 vagas e rodízio.
- **T-113** Dado turnos já gerados, quando gera de novo, então não duplica; um culto novo entra sem repetir os existentes.
- **T-114** Dado ministério sem posto, ou posto de outro ministério, então a geração é recusada.
- **T-115** Dados Manhã e Noite, então a sequência de novembro confere com a planilha da líder.
- **T-116** Dado novembro terminando na Banda 1, quando gera dezembro, então começa na Banda 2.
- **T-117** Dado ministério sem bandas, então os turnos nascem sem banda.
- **T-118** Dados dois postos no mesmo horário, então recebem a mesma banda.
- **T-119** Dado culto que não cai no mês, então a geração é recusada.
- **T-120** Dado turno avulso, então usa as vagas do posto, padroniza o horário e guarda o nome do culto; vagas 0 viram 1.
- **T-121** Dado turno, então a edição depende do estado: rascunho muda tudo, aberta só aumenta vagas, publicada recusa.
- **T-122** Dado turno em rascunho, quando excluído, então leva disponibilidades e confirmações; em escala aberta é recusado.
- **T-123** Dado turno alterado por outra pessoa, quando se edita com a versão antiga, então a API responde 409 e nada é gravado.
- **T-124** Dado prazo da escala, então a edição também exige versão; o líder pode estender o prazo, e quem não é líder não pode.

---

## 12. Decisões tomadas (v1)

Decididas em 30/10/2026. A única ainda aberta é a D-18.

| # | Ponto | Protótipo | Decisão | Status |
|---|---|---|---|---|
| D-01 | Turno cheio | Aceita confirmar acima das vagas | Bloquear a confirmação acima das vagas. O líder pode aumentar as vagas até publicar. O voluntário não vê a quantidade de vagas (RN-64) | Decidido (leitura a confirmar) |
| D-02 | Prazo da escala | Só informa; a marcação continua aberta depois dele | O líder define o prazo de cada escala. Depois dele a marcação é bloqueada; o líder pode estender | Decidido |
| D-03 | Prazo padrão em dias | Configurável, mas sem efeito | O prazo sugerido ao criar a escala é o início do mês menos esse número de dias (padrão 5). O líder pode mudar | Decidido |
| D-04 | Limite de turnos | Texto diz "no mesmo mês", mas conta todos os confirmados | Contar por mês; só alerta | Decidido |
| D-05 | Rodízio de bandas com mais de um posto | Cada posto recebe uma banda diferente no mesmo horário | O rodízio avança por horário, não por turno (RN-31) | Decidido |
| D-06 | Aviso de choque ao publicar | O texto diz "outro ministério", mas a condição também inclui o próprio ministério | Alertar choques com qualquer outro turno confirmado e dizer isso no texto | Decidido |
| D-07 | Exclusões em cascata | Ficam órfãos: confirmações, disponibilidades, funções, trocas e bandas | Chaves estrangeiras com cascata explícita | Decidido |
| D-08 | Tirar da equipe | Remove os postos, mas não funções nem bandas do ministério | Remover também funções e bandas (RN-11) | Decidido |
| D-09 | Pessoa inativa em "Marcaram disponibilidade" | Aparece | Esconder inativos (RN-65) | Decidido |
| D-10 | Horário | Texto livre (`08h00`) | Tipo `time` no banco; aceitar `08h00` só na entrada | Decidido |
| D-11 | Datas e fuso | Datas sem fuso | Fuso por igreja; guardar data e hora locais | Decidido |
| D-12 | Edição depois de publicada | Só reabre, sem trilha | Registrar quem mudou o quê (auditoria) | Decidido |
| D-13 | Pedido de troca | Só aparece em "Minha escala" (turno confirmado em escala `publicada`); nada valida isso no servidor | Manter a regra e validar na API | Decidido |
| D-14 | Concorrência | Dois líderes editando ao mesmo tempo, sem controle | Controle por versão: se o registro mudou, a API responde 409 e a tela avisa | Decidido |
| D-15 | Aprovação de novo voluntário | Não existe | Pedido de entrada pendente que o líder aprova ou recusa; o Google só vincula pelo e-mail verificado | Decidido |
| D-16 | Papéis e login | Não existem | Seção 2 + login por e-mail e senha, e Google | Decidido |
| D-17 | Cultos de uma igreja | Valem para todos os ministérios | Manter, com `igreja_id` | Decidido |
| D-18 | Quais cultos o Louvor serve | Planilha da líder tem Manhã e Noite; a grade tem 3 cultos no domingo | Confirmar com a líder | **Aberta** |

- **T-63** Dado registro que outro líder já alterou depois que a tela foi aberta, quando o primeiro tenta salvar, então a API responde 409 e nenhuma alteração é gravada.

---

## 13. Entidades sugeridas para a API

`igrejas`, `usuarios` (login), `pessoas`, `ministerios`, `ministerio_pessoa` (equipe), `postos`, `posto_pessoa`, `bandas`, `banda_pessoa`, `funcoes`, `funcao_pessoa` (o que já faz), `cultos`, `escalas` (único `ministerio_id + mes`), `turnos`, `disponibilidades` (`turno_id + pessoa_id`), `confirmacoes` (`turno_id + pessoa_id`), `confirmacao_funcao`, `trocas`, `avisos`, `pedidos_entrada` (futuro, D-15). Todas as tabelas de negócio levam `igreja_id`.

Índices e restrições únicas essenciais: `escalas (ministerio_id, mes)`, `disponibilidades (turno_id, pessoa_id)`, `confirmacoes (turno_id, pessoa_id)`, `trocas` com um aberto por `(turno_id, pessoa_id)`.
