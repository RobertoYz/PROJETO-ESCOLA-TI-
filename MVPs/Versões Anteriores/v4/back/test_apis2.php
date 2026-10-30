<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$keys = explode(',', env('GROQ_API_KEY'));
$apiKey = trim($keys[0]);

$response = \Illuminate\Support\Facades\Http::withToken($apiKey)
    ->post('https://api.groq.com/openai/v1/chat/completions', [
        'model' => 'llama3-8b-8192',
        'messages' => [
            ['role' => 'system', 'content' => 'Return json object'],
            ['role' => 'user', 'content' => 'Hello']
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.1
    ]);

echo "GROQ STATUS: " . $response->status() . "\n";
echo "GROQ BODY: " . $response->body() . "\n";

$keysGemini = explode(',', env('GEMINI_API_KEY'));
$apiKeyGemini = trim($keysGemini[0]);

$responseGemini = \Illuminate\Support\Facades\Http::withHeaders([
    'Content-Type' => 'application/json'
])
->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key={$apiKeyGemini}", [
    'system_instruction' => [
        'parts' => [
            ['text' => 'Return json object']
        ]
    ],
    'contents' => [
        [
            'parts' => [
                ['text' => 'Hello']
            ]
        ]
    ],
    'generationConfig' => [
        'response_mime_type' => 'application/json',
        'temperature' => 0.1
    ]
]);

echo "GEMINI STATUS: " . $responseGemini->status() . "\n";
echo "GEMINI BODY: " . $responseGemini->body() . "\n";
