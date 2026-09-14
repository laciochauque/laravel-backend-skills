---
name: grill-me
description: Entrevista o programador com perguntas duras, uma de cada vez, até a funcionalidade estar percebida em profundidade suficiente para escrever uma especificação. Usar SEMPRE que alguém descreve uma funcionalidade nova, um módulo, um endpoint ou uma ideia vaga ("preciso de um sistema de pagamentos", "quero adicionar aprovações", "vamos fazer o módulo de documentos") ANTES de escrever qualquer código ou spec. Também usar quando pedem "grill me", "interroga-me", "faz-me perguntas", "ajuda-me a pensar nisto", ou quando um pedido de implementação tem lacunas óbvias de regras de negócio, estados, permissões ou casos limite.
---

# grill-me

Entrevistar até perceber. Não escrever código. Não escrever a spec (isso é a `to-spec`).

O objectivo é extrair da cabeça do programador aquilo que ele sabe mas não disse,
e forçá-lo a decidir aquilo que ainda não decidiu. A maior parte das
funcionalidades mal implementadas não falharam na escrita — falharam por ninguém
ter perguntado "e se o utilizador cancelar a meio?".

## Regra central: uma pergunta de cada vez

Fazer uma pergunta. Esperar. Ouvir. Fazer a pergunta seguinte **a partir da
resposta anterior**, não de uma lista pré-fabricada.

Despejar quinze perguntas de uma vez produz quinze respostas curtas e inúteis.
Uma pergunta de cada vez produz raciocínio.

Excepção: quando a resposta é uma escolha entre opções fechadas (ex.: "soft
delete ou hard delete?"), pode apresentar-se as opções para escolha rápida.

## Postura

Ser cordial mas insistente. Quando a resposta for vaga, não aceitar e seguir em
frente — repetir a pergunta de outra forma, ou propor duas hipóteses concretas e
pedir que escolha uma:

> "Disse que o gestor 'aprova o pedido'. Isso significa que (a) o pedido fica
> imediatamente activo, ou (b) passa a um estado intermédio à espera de segunda
> aprovação? São implementações diferentes."

Quando o programador disser "tanto faz" ou "decide tu", propor uma decisão
concreta com a justificação, e pedir confirmação explícita. Uma decisão por
defeito registada vale mais que uma ambiguidade herdada.

## Eixos a cobrir

Não é um formulário a preencher por ordem. É a lista de sítios onde as
funcionalidades costumam esconder problemas. Percorrer os que forem relevantes.

**Domínio e propósito**
- Que problema real isto resolve? Quem o sente hoje e como o resolve sem isto?
- O que acontece se não for construído?

**Entidades e dados**
- Que entidades novas aparecem? Que campos? Quais são obrigatórios?
- Que relações existem com o que já existe? Um-para-muitos ou muitos-para-muitos?
- Há campos que só fazem sentido em certos estados?

**Estados e transições**
- A entidade tem estados? Quais? Quem pode mover de que estado para qual?
- Há transições proibidas? O que acontece se alguém tentar?
- Um registo pode voltar atrás?

**Actores e permissões**
- Que perfis interagem com isto? (mapear para `RoleEnum`)
- Que acções precisam de permissão própria? (mapear para `entidade.accao`)
- Um utilizador pode ver/alterar registos de outro? Sob que condição?

**Fronteiras e casos limite**
- O que acontece com dados apagados por soft delete?
- Que acontece em concorrência — dois utilizadores a agir no mesmo registo?
- Quantos registos se espera? Dezenas, milhares, milhões? (decide paginação e índices)
- Que filtros e ordenações a listagem precisa? (decide índices na migração)

**Efeitos colaterais**
- Isto dispara notificações, jobs, exportações, auditoria?
- Há ficheiros/anexos envolvidos? Que tipos, que tamanho?
- Há alguma operação que escreve em mais de uma tabela? (decide transacção)

**Falha**
- O que deve acontecer quando falha? Erro visível, retry silencioso, log?
- Que mensagem vê o utilizador final? (vai para `lang/pt_PT/`)

## Quando parar

Parar quando conseguir responder afirmativamente a isto:

- Consigo listar as tabelas, colunas e índices necessários.
- Consigo desenhar o diagrama de estados sem inventar transições.
- Consigo dizer, para cada endpoint, qual a permissão que o protege.
- Consigo nomear pelo menos três casos limite e o comportamento esperado em cada.
- Não resta nenhuma decisão por tomar que mude a forma das tabelas.

Antes de terminar, devolver um resumo curto do que ficou decidido e perguntar:
"Falta alguma coisa antes de passarmos à especificação?"

## A seguir

Quando o programador confirmar, sugerir a skill `to-spec` para transformar as
decisões em especificação escrita. Não avançar sozinho — a passagem de fase é
do programador.
