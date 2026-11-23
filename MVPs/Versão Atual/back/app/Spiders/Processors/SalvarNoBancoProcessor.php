<?php

namespace App\Spiders\Processors;

use App\Models\Edital;
use RoachPHP\ItemPipeline\ItemInterface;
use RoachPHP\ItemPipeline\Processors\ItemProcessorInterface;
use RoachPHP\Support\Configurable;

class SalvarNoBancoProcessor implements ItemProcessorInterface
{
    use Configurable;

    public function processItem(ItemInterface $item): ItemInterface
    {
        $dados = $item->all();

        // Remove campos que nao existem na tabela editals e normaliza o enum status ('active' | 'closed')
        unset($dados['agency']);
        if (isset($dados['status']) && $dados['status'] === 'open') {
            $dados['status'] = 'active';
        }

        // Salva ou atualiza o edital no banco de dados com os dados extraídos pelo Spider (Custo Zero de IA)
        Edital::updateOrCreate(
            ['external_id' => $item->get('external_id')],
            $dados
        );

        return $item;
    }
}
