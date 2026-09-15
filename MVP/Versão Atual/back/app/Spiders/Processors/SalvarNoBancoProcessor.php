<?php

namespace App\Spiders\Processors;

use App\Models\Edital;
use RoachPHP\ItemPipeline\ItemInterface;
use RoachPHP\ItemPipeline\Processors\ItemProcessorInterface;
use RoachPHP\Support\Configurable;

use App\Jobs\AnalyzeEditalWithIA;

class SalvarNoBancoProcessor implements ItemProcessorInterface
{
    use Configurable;

    public function processItem(ItemInterface $item): ItemInterface
    {
        $edital = Edital::updateOrCreate(
            ['external_id' => $item->get('external_id')],
            $item->all()
        );

        if (!$edital->ai_analyzed) {
            // Em vez de chamar a IA direto, chamamos o job que extrai o texto gigante
            // E o ScrapeEditalCompletoJob chamará a IA depois
            \App\Jobs\ScrapeEditalCompletoJob::dispatch($edital);
        }

        return $item;
    }
}
