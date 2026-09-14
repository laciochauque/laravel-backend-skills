<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Nomes legíveis dos campos
|--------------------------------------------------------------------------
|
| Substituem o :attribute nas mensagens de validação. Toda a entidade nova
| acrescenta aqui os seus campos — um campo em falta aparece ao utilizador
| com o nome técnico em inglês no meio de uma frase em português.
|
*/

return [

    // Identificação
    'name'         => 'nome',
    'first_name'   => 'primeiro nome',
    'last_name'    => 'apelido',
    'full_name'    => 'nome completo',
    'title'        => 'título',
    'description'  => 'descrição',
    'slug'         => 'identificador',
    'code'         => 'código',
    'reference'    => 'referência',

    // Contacto
    'email'        => 'endereço de correio electrónico',
    'phone'        => 'número de telefone',
    'mobile'       => 'número de telemóvel',
    'address'      => 'endereço',
    'city'         => 'cidade',
    'province'     => 'província',
    'district'     => 'distrito',
    'country'      => 'país',
    'postal_code'  => 'código postal',

    // Autenticação
    'password'              => 'senha',
    'password_confirmation' => 'confirmação de senha',
    'current_password'      => 'senha actual',
    'new_password'          => 'nova senha',
    'token'                 => 'código',
    'remember_me'           => 'manter sessão iniciada',

    // Documentos de identificação (Moçambique)
    'nuit'          => 'NUIT',
    'bi_number'     => 'número do bilhete de identidade',
    'passport'      => 'número de passaporte',
    'nuib'          => 'NUIB',

    // Estado e classificação
    'active'       => 'estado activo',
    'status'       => 'estado',
    'type'         => 'tipo',
    'category'     => 'categoria',
    'role'         => 'perfil',
    'permission'   => 'permissão',
    'priority'     => 'prioridade',
    'notes'        => 'observações',
    'reason'       => 'motivo',

    // Datas
    'date'         => 'data',
    'start_date'   => 'data de início',
    'end_date'     => 'data de fim',
    'due_date'     => 'data limite',
    'birth_date'   => 'data de nascimento',
    'created_at'   => 'data de criação',
    'updated_at'   => 'data de actualização',

    // Valores
    'amount'       => 'montante',
    'price'        => 'preço',
    'quantity'     => 'quantidade',
    'total'        => 'total',
    'currency'     => 'moeda',
    'percentage'   => 'percentagem',

    // Ficheiros
    'file'         => 'ficheiro',
    'files'        => 'ficheiros',
    'attachment'   => 'anexo',
    'avatar'       => 'fotografia de perfil',
    'thumbnail'    => 'miniatura',
    'document'     => 'documento',

    // Relações
    'user_id'      => 'utilizador',
    'parent_id'    => 'registo superior',

    // Paginação e filtros
    'per_page'     => 'registos por página',
    'page'         => 'página',
    'sort'         => 'ordenação',
    'filter'       => 'filtro',
    'search'       => 'pesquisa',

    // Acrescentar aqui os campos de cada nova entidade

];
