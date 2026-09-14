# Skills — API Laravel 13 (pt-MZ)

Dezasseis skills: as de Laravel, extraídas do `CLAUDE.md` do departamento, mais
o fluxo de trabalho inspirado nas skills de Matt Pocock.

## O que está aqui

### Fluxo de trabalho (5)

Encadeiam-se por esta ordem. Cada uma termina sugerindo a seguinte, mas a
passagem de fase é sempre decisão do programador.

| Skill | Faz |
|---|---|
| `grill-me` | Entrevista até a funcionalidade estar percebida. Uma pergunta de cada vez. |
| `to-spec` | Escreve a especificação a partir das decisões tomadas. |
| `to-tickets` | Parte a spec em tickets pequenos, ordenados por dependência. |
| `implement` | Executa **um** ticket, com testes e portas de qualidade. |
| `code-review` | Revê numa sessão nova, sem o contexto de quem escreveu. |

### Laravel (11)

| Skill | Cobre (secções do CLAUDE.md) |
|---|---|
| `laravel-migrations` | 3.1, 4 |
| `laravel-module-scaffold` | 6–12, 21, 24 |
| `laravel-query-optimization` | 5, 16 |
| `laravel-rbac` | 13, 19 |
| `laravel-pest-tests` | 20 |
| `laravel-pt-mz-strings` | 10, 22 — **inclui scaffold pt_PT pronto a instalar** |
| `laravel-queues` | 17 |
| `laravel-logging` | 23 (logs), alargada com contexto por pedido |
| `laravel-media` | 15 |
| `laravel-static-analysis` | 2 (Larastan) |
| `laravel-bootstrap` | 2, 3, 18, 23 |

## O que ficou de fora, e porquê

As secções **0 (Regras de Ouro)**, **22 (Convenções Gerais)** e
**23 (Ambiente)** não viraram skills. Aplicam-se a todas as operações, e uma
skill que precisa de estar sempre carregada é só o `CLAUDE.md` com passos a
mais. **Mantenham-nas no `CLAUDE.md`.**

Regra usada para decidir: se a resposta a "quando é que isto é relevante?" for
"sempre", não é skill.

Os logs são o caso de fronteira: a regra curta (mensagem fixa, contexto em
array, nada sensível) está no `CLAUDE.md`; a configuração, o middleware de
contexto e os pormenores ficam na `laravel-logging`, porque só são relevantes
quando se escreve código que regista.

## Como instalar

**Claude Code** — copiar as pastas para dentro do projecto ou do perfil:

```bash
# por projecto (versionado com o repositório, partilhado pela equipa)
cp -r skills-api-laravel/* .claude/skills/

# ou global, para todos os projectos
cp -r skills-api-laravel/* ~/.claude/skills/
```

Cada pasta tem de ficar directamente dentro de `skills/`, com o `SKILL.md` na
raiz dela. Não criar níveis intermédios (`skills/laravel/laravel-rbac/` não
funciona).

**Claude.ai / Cowork** — carregar cada pasta individualmente pela interface de
skills.

O `CLAUDE.md` continua a ser necessário: as skills assumem-no e remetem para as
suas secções.

## Como se combinam

```
ideia vaga
   ↓ grill-me            (uma pergunta de cada vez)
decisões tomadas
   ↓ to-spec             (docs/specs/NNN-modulo.md)
spec aprovada
   ↓ to-tickets          (docs/tickets/NNN-XX-*.md)
tickets ordenados
   ↓ implement           ← consulta as skills laravel-* conforme o artefacto
código escrito
   ↓ code-review         ← SESSÃO NOVA, obrigatoriamente
```

As skills `laravel-*` descrevem **padrões**; o `implement` descreve o
**processo**. Não competem — o `implement` chama as outras conforme o ticket.

## Sugestão de adopção

Se dezasseis parecer muito para começar, activar quatro:
`laravel-migrations`, `laravel-module-scaffold`, `laravel-query-optimization`,
`laravel-rbac`. São as que cobrem as regras de ouro 1 a 5 e 7. As restantes
entram quando houver aparecido a necessidade.

Depois de um mês de uso, vale a pena rever quais dispararam e quais nunca
dispararam. Uma skill que nunca dispara tem o problema na descrição, não no
conteúdo — o ficheiro `description` do frontmatter é o único mecanismo de
activação.

## Scaffold de traduções

A skill `laravel-pt-mz-strings` não é só regras — traz os ficheiros feitos:

```
laravel-pt-mz-strings/
├── assets/lang/pt_PT/
│   ├── validation.php     ← 111 chaves, alinhadas com o Laravel 13.x
│   ├── auth.php
│   ├── passwords.php
│   ├── pagination.php
│   ├── messages.php       ← mensagens genéricas da API
│   └── attributes.php     ← ~60 campos, incl. NUIT, BI, NUIB
└── scripts/
    ├── install-lang.sh    ← copia para o projecto, sem sobrepor
    └── check-ao90.py      ← detecta ortografia AO90, código de saída 1 para CI
```

```bash
./laravel-pt-mz-strings/scripts/install-lang.sh /caminho/para/o/projecto
```

Locale **`pt_PT`** (não `pt_MZ` — nenhuma biblioteca o reconheceria), fuso
**`Africa/Maputo`**, ortografia **pré-AO90**, vocabulário moçambicano. A
instalação e a configuração do `.env` são um passo do arranque, descrito na
skill `laravel-bootstrap`.

## Antes de usar: ler `CORRECCOES.md`

Oito pontos, dos quais três exigem decisão ou alteração da vossa parte:

- a autorização incoerente nas rotas (secção 2);
- o repositório pt-BR sugerido é **GPL-3.0** — o scaffold não foi derivado dele
  por essa razão (secção 5);
- o `CLAUDE.md` refere `lang/pt/` e `APP_LOCALE=pt`, que passam a `pt_PT`
  (secção 7).
