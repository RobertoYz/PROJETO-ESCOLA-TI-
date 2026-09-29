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
        // Salva ou atualiza o edital no banco de dados com os dados extraídos pelo Spider (Custo Zero de IA)
        Edital::updateOrCreate(
            ['external_id' => $item->get('external_id')],
            $item->all()
        );

        return $item;
    }
}
