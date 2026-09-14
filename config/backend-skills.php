<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Locale do projecto
    |--------------------------------------------------------------------------
    |
    | pt_PT e não pt_MZ: Carbon, Faker, Number:: e os pacotes Spatie reconhecem
    | pt_PT. O registo moçambicano vive no vocabulário das mensagens.
    |
    */

    'locale'          => 'pt_PT',
    'fallback_locale' => 'en',
    'timezone'        => 'Africa/Maputo',
    'currency'        => 'MZN',

    /*
    |--------------------------------------------------------------------------
    | Logs
    |--------------------------------------------------------------------------
    |
    | Canal daily: um ficheiro por dia em storage/logs, guardando os mais
    | recentes. Escritos no .env por `php artisan backend:install`.
    |
    */

    'log_channel'    => 'daily',
    'log_daily_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Destino das skills
    |--------------------------------------------------------------------------
    |
    | Relativo à raiz do projecto. Versionar esta pasta faz com que toda a
    | equipa partilhe as mesmas convenções.
    |
    */

    'skills_path' => '.claude/skills',

    /*
    |--------------------------------------------------------------------------
    | Skills a instalar
    |--------------------------------------------------------------------------
    |
    | Vazio instala todas. Para começar com menos, listar apenas as desejadas:
    | ['laravel-migrations', 'laravel-module-scaffold', 'laravel-rbac']
    |
    */

    'skills' => [],

    /*
    |--------------------------------------------------------------------------
    | Verificação ortográfica
    |--------------------------------------------------------------------------
    |
    | Caminhos analisados por `php artisan backend:check-ao90` quando nenhum
    | argumento é passado.
    |
    */

    'ao90_paths' => ['lang', 'app', 'database'],

];
