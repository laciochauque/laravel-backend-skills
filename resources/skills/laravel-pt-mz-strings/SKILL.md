---
name: laravel-pt-mz-strings
description: Língua e traduções deste projecto — locale pt_PT, fuso Africa/Maputo, e um scaffold completo de ficheiros de língua pronto a copiar (validation, auth, passwords, pagination, messages, attributes) em Português europeu pré-AO90 com registo moçambicano. Usar SEMPRE que escrever mensagens de validação ou de API, labels de enums, quando um FormRequest parecer precisar de messages(), ao criar uma entidade nova (que obriga a acrescentar campos em attributes.php), ou quando houver dúvida de ortografia pré-AO90. Usar também ao instalar os ficheiros de língua num projecto.
---

# Língua e traduções

## A regra em duas linhas

**Identificadores em inglês. Texto visível em Português europeu, ortografia
pré-AO90, registo de Moçambique. Nenhuma string de utilizador escrita dentro do
código.**

| Contexto | Língua |
|---|---|
| Classes, métodos, variáveis, rotas, tabelas, colunas | Inglês |
| Jobs, Events, Listeners | Inglês |
| Comentários PHPDoc e inline | pt pré-AO90, frases curtas |
| Strings JSON (`message`, `errors`) | pt, via `lang/pt_PT/` |
| Labels de enums (`label()`) | pt |
| Nomes de testes (`it('...')`) | pt |

## Locale: `pt_PT`, não `pt_MZ`

O código de locale do projecto é **`pt_PT`**. Moçambique não tem um conjunto de
ficheiros de língua próprio no ecossistema Laravel, e o português escrito
formal moçambicano segue a norma europeia. Usar `pt_MZ` só criaria um locale que
nenhuma biblioteca de terceiros reconhece — Carbon, Faker, `Number::` e os
pacotes Spatie sabem todos o que é `pt_PT`.

O registo moçambicano entra no **vocabulário** das mensagens, não no código do
locale.

```env
APP_LOCALE=pt_PT
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=pt_PT
APP_TIMEZONE=Africa/Maputo
```

```
lang/
├── pt_PT/
│   ├── validation.php   ← 111 chaves, alinhadas com o Laravel 13
│   ├── auth.php
│   ├── passwords.php
│   ├── pagination.php
│   ├── messages.php     ← mensagens genéricas da API
│   └── attributes.php   ← nomes legíveis dos campos
└── en/                  ← opcional, só se quiserem personalizar o fallback
```

## Scaffold pronto

Os ficheiros já existem. **Copiar, não reescrever.**

Se o projecto tem o pacote `laciochauque/laravel-backend-skills` instalado:

```bash
php artisan backend:install --lang-only
```

Sem o pacote, a partir da pasta desta skill:

```bash
./scripts/install-lang.sh /caminho/para/o/projecto
```

Ou à mão:

```bash
mkdir -p lang/pt_PT
cp assets/lang/pt_PT/*.php lang/pt_PT/
php artisan config:clear
```

Em qualquer dos casos, ficheiros já existentes não são sobrepostos — só com
`--force`. Isso protege o `attributes.php`, que cresce a cada entidade nova.

O `validation.php` cobre as 111 chaves do Laravel 13, com os placeholders
(`:attribute`, `:min`, `:values`, `:date`…) verificados um a um contra o
ficheiro oficial. Ao actualizar o Laravel, comparar com
`vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php`
para ver se apareceram chaves novas.

## Nunca escrever `messages()`

```php
final class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }

    // Sem messages() — ver lang/pt_PT/validation.php e lang/pt_PT/attributes.php
}
```

Um `messages()` inline significa que a mesma frase vai ser reescrita em cinco
Requests e que uma delas vai divergir. Em `validation.php`, a chave
`'attributes'` fica vazia — os nomes dos campos vivem no seu próprio ficheiro.

Para o caso raro em que um campo precisa mesmo de mensagem própria, existe a
secção `custom` do `validation.php`:

```php
'custom' => [
    'email' => [
        'unique' => 'Já existe uma conta registada com este endereço.',
    ],
],
```

## Entidade nova: acrescentar a `attributes.php`

O ficheiro já traz cerca de 60 campos comuns, incluindo os documentos
moçambicanos (`nuit`, `bi_number`, `nuib`). Toda a entidade nova acrescenta os
seus.

Um campo em falta aparece ao utilizador com o nome técnico em inglês no meio de
uma frase em português:

> "O campo due_date é obrigatório."

É o sintoma mais visível de um módulo entregue a meio.

## Fuso horário: `Africa/Maputo`

```env
APP_TIMEZONE=Africa/Maputo
```

Maputo é CAT (UTC+2) e **não tem horário de Verão**, o que elimina a categoria
inteira de bugs de transição de hora.

**A decisão tem uma consequência que vale a pena saber:** `APP_TIMEZONE` não
muda só o que o utilizador vê — muda o que o Eloquent **escreve** na base de
dados. Os `timestamps` passam a ser gravados em hora de Maputo, não em UTC.

Isso é perfeitamente viável num sistema que serve um só país, e é mais simples
de depurar. Mas traz três cuidados:

1. **Não misturar.** Se alguma tabela já tem dados gravados em UTC, mudar o
   `APP_TIMEZONE` faz esses registos passarem a ser lidos como se fossem hora
   local — duas horas de diferença, sem erro nenhum a assinalar.
2. **O MySQL tem fuso próprio.** Funções como `NOW()` e `CURRENT_TIMESTAMP`
   usam o fuso do servidor de base de dados, não o do PHP. Colunas com
   `useCurrent()` no `default` seguem o MySQL. Alinhar os dois, ou deixar o
   Eloquent escrever todos os timestamps.
3. **Integrações externas continuam em UTC.** Ao consumir ou enviar datas para
   APIs de terceiros, converter explicitamente.

Se a API vier a servir clientes noutros fusos, a alternativa é manter
`APP_TIMEZONE=UTC` e converter só na apresentação. O `toIso8601String()` dos
Resources já inclui o desvio (`+02:00`), por isso o cliente recebe a informação
correcta em qualquer dos casos.

## Carbon e Number no service provider

```php
// app/Providers/AppServiceProvider.php
use Carbon\CarbonImmutable;
use Illuminate\Support\Number;

public function boot(): void
{
    Number::useLocale('pt_PT');
    Number::useCurrency('MZN');
    CarbonImmutable::setLocale('pt');
}
```

Isto faz com que `Number::currency(1500)` devolva o valor em meticais formatado
à portuguesa, e que `$date->diffForHumans()` devolva "há 3 dias" em vez de
"3 days ago".

## Ortografia pré-AO90

O Acordo Ortográfico de 1990 não é seguido. As consoantes mudas mantêm-se.

| ✅ pré-AO90 | ❌ AO90 |
|---|---|
| acção, acções | ação, ações |
| actualizar, actual | atualizar, atual |
| activo, activar | ativo, ativar |
| correcto, correcção | correto, correção |
| optimizar, óptimo | otimizar, ótimo |
| objectivo | objetivo |
| directório, directriz | diretório, diretriz |
| excepção | exceção |
| selecção, seleccionar | seleção, selecionar |
| colecção | coleção |
| arquitectura | arquitetura |
| projecto | projeto |
| electrónico | eletrónico |
| facto | fato |
| adopção | adoção |

`facto` (acontecimento) e `fato` (vestuário) são palavras diferentes em
pré-AO90, e ambas existem.

### Verificação automática

Com o pacote instalado:

```bash
php artisan backend:check-ao90
php artisan backend:check-ao90 app lang database
php artisan backend:check-ao90 --strict   # inclui "fato"
```

Sem o pacote:

```bash
python3 scripts/check-ao90.py lang app
```

Ambos devolvem código de saída 1 se encontrarem ocorrências — dá para pôr no CI
a par do PHPStan. A tabela acima faz a verificação disparar de propósito; ao
correr sobre o projecto, apontar para `lang/` e `app/`, não para as skills.

## Registo moçambicano

Norma europeia formal. Em particular:

| Usar | Em vez de |
|---|---|
| correio electrónico | e-mail |
| senha | palavra-passe, password |
| utilizador | usuário |
| eliminar | deletar, apagar |
| ficheiro | arquivo |
| registo | registro |
| telemóvel | celular |
| autocarro | ônibus |
| casa de banho | banheiro |

## Tom das mensagens

Frases curtas, terceira pessoa formal, sem culpar o utilizador. "Não tem
permissão para realizar esta operação", não "Você não pode fazer isso".

As mensagens de erro não expõem detalhes internos — nada de nomes de tabelas,
de classes ou de excepções na resposta. Esses vão para o log.
