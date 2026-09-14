<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('passa quando a ortografia está em pré-AO90', function () {
    File::ensureDirectoryExists(base_path('lang/pt_PT'));
    File::put(base_path('lang/pt_PT/teste.php'), "<?php return ['a' => 'Acção correcta e activa.'];");

    $this->artisan('backend:check-ao90', ['paths' => ['lang']])->assertSuccessful();
});

it('falha e assinala quando encontra ortografia AO90', function () {
    File::ensureDirectoryExists(base_path('lang/pt_PT'));
    File::put(base_path('lang/pt_PT/teste.php'), "<?php return ['a' => 'Ação correta e ativa.'];");

    $this->artisan('backend:check-ao90', ['paths' => ['lang']])->assertFailed();
});

it('ignora "fato" fora do modo --strict', function () {
    File::ensureDirectoryExists(base_path('lang/pt_PT'));
    File::put(base_path('lang/pt_PT/teste.php'), "<?php return ['a' => 'O fato do senhor.'];");

    $this->artisan('backend:check-ao90', ['paths' => ['lang']])->assertSuccessful();
    $this->artisan('backend:check-ao90', ['paths' => ['lang'], '--strict' => true])->assertFailed();
});
