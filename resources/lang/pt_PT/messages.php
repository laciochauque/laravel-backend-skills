<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mensagens genéricas da API
|--------------------------------------------------------------------------
|
| Usar sempre via __('messages.chave'). Nenhuma destas frases é escrita
| dentro do código.
|
*/

return [

    // Operações
    'created'   => 'Registo criado com sucesso.',
    'updated'   => 'Registo actualizado com sucesso.',
    'deleted'   => 'Registo eliminado com sucesso.',
    'restored'  => 'Registo restaurado com sucesso.',

    // Erros
    'forbidden'       => 'Não tem permissão para realizar esta operação.',
    'unauthenticated' => 'É necessário autenticar-se para aceder a este recurso.',
    'not_found'       => 'O recurso solicitado não foi encontrado.',
    'invalid_data'    => 'Os dados fornecidos são inválidos.',
    'error'           => 'Ocorreu um erro inesperado.',
    'too_many'        => 'Demasiados pedidos. Tente novamente mais tarde.',
    'conflict'        => 'A operação não pode ser concluída no estado actual do registo.',

    // Autenticação
    'logged_in'       => 'Sessão iniciada com sucesso.',
    'logged_out'      => 'Sessão terminada com sucesso.',

    // Ficheiros
    'file_uploaded'   => 'Ficheiro carregado com sucesso.',
    'file_removed'    => 'Ficheiro removido com sucesso.',
    'file_too_large'  => 'O ficheiro excede o tamanho máximo permitido.',

];
