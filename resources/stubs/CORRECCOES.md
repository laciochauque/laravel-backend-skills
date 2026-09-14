# Correcções e verificações face ao CLAUDE.md de origem

## 1. Referência cruzada errada — corrigida

A secção 4.4 remete para "secção 13 sobre optimização". A secção 13 é RBAC; a
de optimização é a **5**. Corrigido nas skills. Vale a pena corrigir também no
`CLAUDE.md`.

## 2. Autorização incoerente nas rotas — exige decisão da equipa

Em `routes/api/v1/users.php`, o `UserExportController` leva
`permission:user.read` no middleware, mas o `apiResource('users', ...)` não leva
permissão nenhuma.

Isto deixa uma abertura real: o `index` e o `show` do `apiResource` não têm
FormRequest próprio e portanto não têm `authorize()`. Como estão, ficam
acessíveis a **qualquer utilizador autenticado**, independentemente do perfil —
enquanto o `store` e o `update` estão protegidos pelas Policies invocadas nos
Requests.

As skills `laravel-rbac` e `laravel-module-scaffold/references/routes.md`
descrevem as duas formas de fechar isto (middleware por permissão, ou
`authorizeResource` no construtor) e dizem quando usar cada uma. **A escolha é
da equipa** — o que não pode ficar é o estado misto.

## 3. Factos sobre o Laravel 13 — verificados, estão correctos

Confirmados contra a documentação oficial e as notas de lançamento:

- Laravel 13 lançado a **17 de Março de 2026** ✔
- **PHP 8.3** como mínimo (PHP 8.2 descontinuado) ✔
- Upgrade com poucas breaking changes face ao 12 ✔
- **`Queue::route()`** existe mesmo, para declarar fila e conexão por classe de
  job num só sítio ✔

Dois pontos que o `CLAUDE.md` ainda não cobre e podem interessar:

- O Laravel 13 traz também o atributo **`#[WithQueue(connection:, queue:,
  tries:, timeout:)]`** como alternativa às propriedades do job. Coerente com a
  decisão do projecto de manter configuração por propriedades — fica só
  registado.
- A partir do **Laravel 13.26** existe **`Queue::forward()`**, que redirecciona
  filas inteiras a partir do service provider sem tocar nas classes de job. Útil
  se um dia a equipa migrar do driver `database` para outro.

## 4. Nota sobre cobertura de testes

O `CLAUDE.md` define `--coverage --min=80`. Vale registar na equipa que
cobertura alta não implica testes bons: um teste que chama o endpoint sem
assertar nada conta na mesma para a percentagem. Por isso a skill
`laravel-pest-tests` exige **quatro casos por endpoint** (feliz, 401, 403, 422)
e asserção de estrutura, não só de código HTTP. A cobertura é o chão; os quatro
casos são o critério.

---

# Adenda — scaffold de traduções (pt_PT)

## 5. O repositório de referência é GPL-3.0, não MIT

O `lucascudo/laravel-pt-br-localization` está licenciado sob **GNU GPL-3.0**.
A GPL é copyleft: um projecto que incorpore ficheiros derivados dela fica, em
princípio, obrigado a distribuir-se sob os mesmos termos. Para uma API
proprietária de departamento, isso é um problema que ninguém quer descobrir
mais tarde.

**Por isso o scaffold não foi derivado desse repositório.** A base estrutural
saiu dos ficheiros oficiais do `laravel/framework` 13.x
(`src/Illuminate/Translation/lang/en/`), que são **MIT** — a mesma licença do
resto do projecto — e as traduções para português europeu pré-AO90 foram
escritas de raiz.

O repositório pt-BR foi usado apenas para comparar o conjunto de chaves, o que
não cria obra derivada. E a comparação revelou que estava desactualizado: tinha
**96 chaves de validação** contra as **111** do Laravel 13. Faltavam-lhe, entre
outras, `any_of`, `array_keys`, `base64`, `doesnt_contain`, `encoding`,
`in_array_keys`, `prohibited_if_accepted` e `prohibited_if_declined`.

Se ainda assim quiserem usar esse repositório, a decisão é da equipa — mas
convém ser uma decisão, não um `composer require` distraído.

## 6. Verificação do scaffold

| Verificação | Resultado |
|---|---|
| Chaves de validação vs Laravel 13.x | 137/137 mensagens, nenhuma em falta nem a mais |
| Placeholders (`:attribute`, `:min`, `:values`…) | todos coincidentes com o original |
| Chaves duplicadas | nenhuma nos seis ficheiros |
| Ortografia AO90 | nenhuma ocorrência |

## 7. `lang/pt/` passa a `lang/pt_PT/` — actualizar o CLAUDE.md

O `CLAUDE.md` refere `lang/pt/` e `APP_LOCALE=pt` nas secções **10**, **22** e
**23**. Com a mudança para `pt_PT`, essas referências ficam erradas — e o
sintoma é discreto: o Laravel não encontra a pasta, cai no fallback e devolve
tudo em inglês, sem erro nenhum.

Alterações a fazer no `CLAUDE.md`:

| Onde | De | Para |
|---|---|---|
| Secção 10 | `lang/pt/` | `lang/pt_PT/` |
| Secção 22 | "via `lang/pt/`" | "via `lang/pt_PT/`" |
| Secção 23 | `APP_LOCALE=pt` | `APP_LOCALE=pt_PT` |
| Secção 23 | — | acrescentar `APP_TIMEZONE=Africa/Maputo` |
| Secção 23 | — | acrescentar `APP_FAKER_LOCALE=pt_PT` |

As **chaves** de tradução não mudam: `__('messages.deleted')` continua igual.
Muda só a pasta.

## 8. `APP_TIMEZONE=Africa/Maputo` — o que isto implica

Maputo é CAT (UTC+2) e não tem horário de Verão, o que elimina os bugs de
transição de hora. Mas definir `APP_TIMEZONE` muda o que o Eloquent **grava**,
não só o que mostra: os `timestamps` passam a ir para a base de dados em hora
de Maputo.

Três cuidados, detalhados na skill `laravel-pt-mz-strings`:

1. **Não misturar com dados já gravados em UTC** — passam a ser lidos com duas
   horas de desvio, silenciosamente.
2. **O MySQL tem fuso próprio** — `NOW()`, `CURRENT_TIMESTAMP` e colunas com
   `useCurrent()` seguem o servidor de base de dados, não o PHP.
3. **Integrações externas continuam em UTC** — converter explicitamente.

A alternativa continua a ser `APP_TIMEZONE=UTC` com conversão na apresentação.
Como os Resources usam `toIso8601String()`, o cliente recebe o desvio
(`+02:00`) em qualquer dos casos.
