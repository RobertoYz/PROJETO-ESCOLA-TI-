<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$key = trim(explode(',', env('GROQ_API_KEY'))[0]);
$res = Illuminate\Support\Facades\Http::withToken($key)->get('https://api.groq.com/openai/v1/models');
$data = json_decode($res->body(), true);
if (isset($data['data'])) {
    foreach ($data['data'] as $model) {
        echo $model['id'] . "\n";
    }
} else {
    echo $res->body();
}
