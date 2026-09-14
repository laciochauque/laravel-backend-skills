# Formato das respostas e handler de excepções

## Formatos

```json
// Detalhe (show / store / update)
{ "data": { "id": "ulid", "name": "..." } }
```

```json
// Listagem paginada (index) — GET /api/v1/users?page=2, 45 registos, 15 por página
{
  "data": [ { "id": "ulid", "name": "..." } ],
  "links": {
    "first": "https://api.exemplo.co.mz/api/v1/users?page=1",
    "last":  "https://api.exemplo.co.mz/api/v1/users?page=3",
    "prev":  "https://api.exemplo.co.mz/api/v1/users?page=1",
    "next":  "https://api.exemplo.co.mz/api/v1/users?page=3"
  },
  "meta": {
    "current_page": 2,
    "from": 16,
    "last_page": 3,
    "links": [
      { "url": "https://api.exemplo.co.mz/api/v1/users?page=1", "label": "&laquo; Anterior", "page": 1, "active": false },
      { "url": "https://api.exemplo.co.mz/api/v1/users?page=1", "label": "1", "page": 1, "active": false },
      { "url": "https://api.exemplo.co.mz/api/v1/users?page=2", "label": "2", "page": 2, "active": true },
      { "url": "https://api.exemplo.co.mz/api/v1/users?page=3", "label": "3", "page": 3, "active": false },
      { "url": "https://api.exemplo.co.mz/api/v1/users?page=3", "label": "Seguinte &raquo;", "page": 3, "active": false }
    ],
    "path": "https://api.exemplo.co.mz/api/v1/users",
    "per_page": 15,
    "to": 30,
    "total": 45
  }
}
```

```json
// Opções de select (options) — sem paginação, no máximo 100
{ "data": [ { "id": "ulid", "label": "Carlos do Rosário Mateus" } ] }
```

```json
// Mensagem simples (delete / acção)
{ "message": "Registo eliminado com sucesso." }
```

```json
// Erro de validação (422)
{ "message": "Os dados fornecidos são inválidos.", "errors": { "email": ["..."] } }
```

```json
// Erro de negócio (403 / 404)
{ "message": "O recurso solicitado não foi encontrado.", "error_code": "RESOURCE_NOT_FOUND" }
```

## Paginação: o que o frontend usa

Todas as listagens usam `paginate()` — o mesmo formato em todos os endpoints.

- **`meta.total` e `meta.last_page`** — "página 2 de 3", "45 registos".
- **`meta.links`** — os botões numerados. O primeiro elemento é *anterior*, o
  último é *seguinte*, e o do meio com `active: true` é a página actual.
- **Com muitas páginas** (a partir de 14), o Laravel mostra as primeiras, uma
  janela à volta da actual e as últimas, separadas por
  `{ "url": null, "label": "...", "active": false }` — sem a chave `page`.
- **Navegar por `page`**, ou pelo `url`, que já leva os filtros e a ordenação.
  *Anterior* na primeira página e *seguinte* na última vêm com `url` e `page`
  a `null`.
- **Os rótulos de anterior e seguinte** vêm de `lang/pt_PT/pagination.php` e
  trazem entidades HTML (`&laquo;`, `&raquo;`). Se o frontend os mostrar, tem de
  os interpretar como HTML; o mais simples é desenhar as setas e ignorar o
  `label` desses dois.

## Handler global

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions): void {

    $exceptions->render(function (ModelNotFoundException $e, Request $request): JsonResponse {
        return response()->json([
            'message'    => __('messages.not_found'),
            'error_code' => 'RESOURCE_NOT_FOUND',
        ], 404);
    });

    $exceptions->render(function (AuthorizationException $e, Request $request): JsonResponse {
        return response()->json([
            'message'    => __('messages.forbidden'),
            'error_code' => 'FORBIDDEN',
        ], 403);
    });

})
```

## Códigos `error_code`

O `error_code` é para o cliente da API decidir comportamento sem ler texto em
português. Manter a lista curta e estável — uma vez publicado, um código é
contrato.

| Código | HTTP |
|---|---|
| `RESOURCE_NOT_FOUND` | 404 |
| `FORBIDDEN` | 403 |
| `UNAUTHENTICATED` | 401 |
| `VALIDATION_FAILED` | 422 |

## O que nunca sai na resposta

Mensagens de excepção internas, nomes de classes, caminhos de ficheiros,
consultas SQL. Com `APP_DEBUG=false` o Laravel já esconde a maior parte, mas um
`catch` que devolva `$e->getMessage()` ao cliente fura essa protecção. Essa
informação vai para o log (skill `laravel-logging`), não para a resposta — e o
cliente recebe o `X-Request-Id` para a encontrar lá.
