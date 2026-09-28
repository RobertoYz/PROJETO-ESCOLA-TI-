<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$edital = App\Models\Edital::find(53);
file_put_contents('test_conteudo.txt', $edital->conteudo_completo);
echo "Conteudo salvo\n";
