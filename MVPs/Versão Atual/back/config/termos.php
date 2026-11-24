<?php

return [
    'hmac_key' => env('TERMOS_HMAC_KEY', 'desenvolvimento-exclusivo-termos-nao-usar-em-producao'),
    'hmac_chave_id' => env('TERMOS_HMAC_KEY_ID', 'v1'),
    'retencao_anos_pos_encerramento' => env('TERMOS_RETENCAO_ANOS', 5),
];
