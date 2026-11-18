<?php

namespace App\Http\Controllers;

use App\Models\ImagemGerada;

class ImagensGeradasController extends RecursoDoPromptController
{
    protected function model(): string
    {
        return ImagemGerada::class;
    }

    protected function campoObrigatorio(): array
    {
        return ['url_imagem', 'URL da imagem é obrigatória'];
    }

    protected function mensagens(): array
    {
        return [
            'nao_encontrado' => 'Imagem não encontrada',
            'carregar' => 'Erro ao carregar imagens',
            'criado' => 'Imagem criada com sucesso',
            'criar' => 'Erro ao criar imagem',
            'atualizado' => 'Imagem atualizada com sucesso',
            'atualizar' => 'Erro ao atualizar imagem',
            'excluido' => 'Imagem excluída com sucesso',
            'excluir' => 'Erro ao excluir imagem',
        ];
    }
}
