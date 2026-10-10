<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = new \App\Services\DeepSeekService();
$titulo = "EDITAL DE CHAMADA PÚBLICA FAPESC/SED N.º 54/2026 PROGRAMA DE FOMENTO À PESQUISA E INOVAÇÃO PARA O DESENVOLVIMENTO DA EDUCAÇÃO CATARINENSE";
$objetivo = "A FUNDAÇÃO DE AMPARO À PESQUISA E INOVAÇÃO DO ESTADO DE SANTA CATARINA (FAPESC)...";
$publico = "programas de pós-graduação (PPGs) stricto sensu e cursos de licenciatura";

$resultado = $service->analyzeEdital($titulo, $objetivo, $publico);
print_r($resultado);
