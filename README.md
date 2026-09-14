# laravel-backend-skills

Skills de Claude Code, scaffold de traduções `pt_PT` e convenções para APIs
Laravel 13 com arquitectura Actions + Services + DTOs.

Um `composer require --dev` e um `artisan backend:install` deixam o projecto
novo com as convenções da equipa já no sítio.

## Instalação

```bash
composer create-project laravel/laravel minha-api
cd minha-api

composer require --dev laciochauque/laravel-backend-skills
php artisan backend:install
```

O comando:

1. copia as 16 skills para `.claude/skills/`;
2. copia os ficheiros de língua para `lang/pt_PT/`;
3. propõe criar o `CLAUDE.md` com as regras sempre activas;
4. configura `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`,
   `APP_TIMEZONE` e os logs (`LOG_CHANNEL=daily`, `LOG_DAILY_DAYS=30`) no
   `.env`, mostrando as alterações antes de as aplicar.

Nada é sobreposto sem `--force`. Um segundo `backend:install` num projecto já
configurado instala apenas o que falta.

### Porquê `--dev`

O pacote não faz nada em runtime: publica ficheiros e regista comandos de
consola. Os ficheiros publicados são **cópias** que ficam versionadas no
projecto, por isso produção não precisa do pacote.

Isto é deliberado, e a razão principal é o `attributes.php`: cresce a cada
entidade nova. Se as traduções fossem carregadas do `vendor/`, cada campo novo
obrigaria a uma release do pacote. Publicadas, são da equipa.

## Comandos

```bash
php artisan backend:install               # instalação completa
php artisan backend:install --force       # sobrepõe o que já existe
php artisan backend:install --skills-only # só as skills
php artisan backend:install --lang-only   # só as traduções
php artisan backend:install --no-env      # não toca no .env

php artisan backend:skills                # estado das skills instaladas
php artisan backend:skills --available    # skills disponíveis no pacote

php artisan backend:check-ao90            # ortografia AO90 em lang/ e app/
php artisan backend:check-ao90 app lang   # caminhos explícitos
php artisan backend:check-ao90 --strict   # inclui "fato"
```

O `check-ao90` devolve código de saída 1 quando encontra ocorrências — serve
para o CI, a par do PHPStan.

## Publicação selectiva

```bash
php artisan vendor:publish --tag=backend-skills        # → .claude/skills/
php artisan vendor:publish --tag=backend-lang          # → lang/pt_PT/
php artisan vendor:publish --tag=backend-claude-md     # → CLAUDE.md
php artisan vendor:publish --tag=backend-skills-config # → config/backend-skills.php
```

## Configuração

```php
// config/backend-skills.php
return [
    'locale'          => 'pt_PT',
    'fallback_locale' => 'en',
    'timezone'        => 'Africa/Maputo',
    'currency'        => 'MZN',
    'log_channel'     => 'daily',
    'log_daily_days'  => 30,
    'skills_path'     => '.claude/skills',
    'skills'          => [],   // vazio = todas
    'ao90_paths'      => ['lang', 'app', 'database'],
];
```

Para começar com menos skills:

```php
'skills' => [
    'laravel-migrations',
    'laravel-module-scaffold',
    'laravel-query-optimization',
    'laravel-rbac',
],
```

## O que vai dentro

### Skills de fluxo de trabalho (5)

| Skill | Faz |
|---|---|
| `grill-me` | Entrevista até a funcionalidade estar percebida. Uma pergunta de cada vez. |
| `to-spec` | Escreve a especificação a partir das decisões tomadas. |
| `to-tickets` | Parte a spec em tickets pequenos, ordenados por dependência. |
| `implement` | Executa **um** ticket, com testes e portas de qualidade. |
| `code-review` | Revê numa sessão nova, sem o contexto de quem escreveu. |

### Skills de Laravel (11)

`laravel-migrations`, `laravel-module-scaffold`, `laravel-query-optimization`,
`laravel-rbac`, `laravel-pest-tests`, `laravel-pt-mz-strings`,
`laravel-queues`, `laravel-logging`, `laravel-media`,
`laravel-static-analysis`, `laravel-bootstrap`.

### Traduções `pt_PT`

| Ficheiro | Conteúdo |
|---|---|
| `validation.php` | 111 chaves, alinhadas com o Laravel 13.x |
| `auth.php` | mensagens de autenticação |
| `passwords.php` | reposição de senha |
| `pagination.php` | anterior / seguinte |
| `messages.php` | mensagens genéricas da API |
| `attributes.php` | ~60 campos, incluindo NUIT, BI e NUIB |

Português europeu, ortografia **pré-AO90**, vocabulário moçambicano
(correio electrónico, senha, utilizador, ficheiro, registo, telemóvel).

## Notas sobre as decisões

### Locale `pt_PT`, não `pt_MZ`

`pt_MZ` não é reconhecido pelo Carbon, Faker, `Number::` nem pelos pacotes
Spatie. O português escrito formal moçambicano segue a norma europeia; o registo
moçambicano entra no vocabulário das mensagens, não no código do locale.

### `Africa/Maputo`

CAT (UTC+2), sem horário de Verão — o que elimina os bugs de transição de hora.

Definir `APP_TIMEZONE` muda o que o Eloquent **grava**, não só o que mostra: os
`timestamps` passam a ir para a base de dados em hora de Maputo. Três cuidados:

1. **Não misturar com dados já gravados em UTC** — passam a ser lidos com duas
   horas de desvio, silenciosamente.
2. **O MySQL tem fuso próprio** — `NOW()`, `CURRENT_TIMESTAMP` e colunas com
   `useCurrent()` seguem o servidor de base de dados, não o PHP.
3. **Integrações externas continuam em UTC** — converter explicitamente.

O `backend:install` avisa disto ao alterar o `APP_TIMEZONE`. A alternativa é
manter `APP_TIMEZONE=UTC` e converter só na apresentação.

### Logs `daily`, 30 dias

Um ficheiro por dia em `storage/logs/`, com os 30 mais recentes guardados. A
skill `laravel-logging` acrescenta o middleware que junta `request_id`,
utilizador, IP e caminho a cada linha — e aos jobs que o pedido despachar.

### Traduções escritas de raiz

A base estrutural saiu dos ficheiros oficiais do `laravel/framework` 13.x
(`src/Illuminate/Translation/lang/en/`), que são **MIT** — a mesma licença deste
pacote. As traduções para português pré-AO90 são originais.

Não foram derivadas de nenhum pacote de localização existente. O mais conhecido
para português é **GPL-3.0**, e a GPL é copyleft: um projecto que incorpore
ficheiros derivados dela fica obrigado a distribuir-se nos mesmos termos. Para
uma API proprietária, é um problema que ninguém quer descobrir tarde.

### Verificação

| Verificação | Resultado |
|---|---|
| Chaves de validação vs Laravel 13.x | 137/137 mensagens |
| Placeholders (`:attribute`, `:min`, `:values`…) | todos coincidentes |
| Chaves duplicadas | nenhuma |
| Ortografia AO90 | nenhuma ocorrência |

## Requisitos

PHP 8.3+, Laravel 13.x.

## Licença

MIT.
