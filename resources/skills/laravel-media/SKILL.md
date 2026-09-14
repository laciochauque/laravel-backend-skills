---
name: laravel-media
description: Gestão de ficheiros com Spatie Media Library neste projecto — colecções declaradas como constantes de classe, MIME types restritos, nunca strings literais. Usar SEMPRE que envolver upload, anexos, imagens, avatares, documentos, thumbnails, ou qualquer ficheiro associado a um model. Usar também ao criar uma entidade que aceita ficheiros, e quando um upload falha ou aceita tipos indevidos.
---

# Media collections

## Colecções são constantes, não strings

```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

final class Document extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const string ATTACHMENT_MEDIA = 'attachment';
    public const string THUMBNAIL_MEDIA  = 'thumbnail';

    /** @var list<string> */
    private const array ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ATTACHMENT_MEDIA)
            ->acceptsMimeTypes(self::ALLOWED_MIME_TYPES)
            ->useDisk('public');

        $this->addMediaCollection(self::THUMBNAIL_MEDIA)
            ->acceptsMimeTypes(['image/jpeg', 'image/png'])
            ->singleFile()
            ->useDisk('public');
    }
}
```

`public const string` e `private const array` são constantes tipadas — PHP 8.3,
que é o mínimo exigido pelo Laravel 13.

```php
// ✅
$document->addMediaFromRequest('file')->toMediaCollection(Document::ATTACHMENT_MEDIA);

// ❌ nunca
$document->addMediaFromRequest('file')->toMediaCollection('attachment');
```

Uma string literal mal escrita não dá erro — cria silenciosamente uma colecção
nova chamada `attachement` e o ficheiro desaparece da vista sem aviso.

## Validação no Request, não só na colecção

O `acceptsMimeTypes` rejeita no momento da gravação, o que é tarde: o pedido já
subiu o ficheiro. Validar também no FormRequest, para o utilizador receber 422
com mensagem em português:

```php
'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
```

O `max` é em kilobytes. E tem de ser coerente com `upload_max_filesize` e
`post_max_size` do PHP — se o limite do PHP for menor, o pedido morre antes de
chegar à validação e o utilizador vê um erro genérico.

## Nos Resources

Media é relação, por isso segue a mesma regra de todas as relações:

```php
'avatar'      => $this->whenLoaded('media', fn () => $this->getFirstMediaUrl('avatar')),
'attachments' => $this->whenLoaded('media', fn () => $this->getMedia(
    self::ATTACHMENT_MEDIA
)->map(fn ($m) => [
    'id'   => $m->uuid,
    'name' => $m->file_name,
    'size' => $m->size,
    'url'  => $m->getUrl(),
])),
```

Carregar sempre com `->load('media')` no controller. Numa listagem, chamar
`getFirstMediaUrl()` sem eager loading dispara uma query por linha.

## Disco e segurança

- `public` para o que é mesmo público (avatares, thumbnails).
- Ficheiros sensíveis vão para disco privado, servidos por rota autenticada que
  verifica a permissão antes de devolver o ficheiro. Um documento confidencial
  num disco público é acessível por URL a quem a adivinhar.
- `php artisan storage:link` é preciso para o disco público funcionar.

## Eliminação

Com soft delete no model, os ficheiros **não** são removidos — o que é o
comportamento certo, já que o registo pode ser restaurado. Se houver limpeza
definitiva, tem de ser um processo próprio, sobre registos já eliminados em
definitivo. Decidir isto na spec, não a meio da implementação.
