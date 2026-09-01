<?php

namespace App\Spiders;

use Generator;
use RoachPHP\Downloader\Middleware\RequestDeduplicationMiddleware;
use RoachPHP\Extensions\LoggerExtension;
use RoachPHP\Extensions\StatsCollectorExtension;
use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;
use RoachPHP\Spider\ParseResult;
use RoachPHP\Downloader\Middleware\ExecuteJavascriptMiddleware;
class FinepSpider extends BasicSpider
{
    public array $startUrls = [
        'https://www.finep.gov.br/oportunidades'
    ];

    public array $downloaderMiddleware = [
        RequestDeduplicationMiddleware::class,
        ExecuteJavascriptMiddleware::class,
    ];

    public array $spiderMiddleware = [
        //
    ];

    public array $itemProcessors = [
        //
    ];

    public array $extensions = [
        LoggerExtension::class,
        StatsCollectorExtension::class,
    ];

    public int $concurrency = 2;

    public int $requestDelay = 1;

    /**
     * @return Generator<ParseResult>
     */
    public function parse(Response $response): Generator
    {
        //Debug: inspeciona o html da pagina
        var_dump($response->html());

        die();
    }
}
