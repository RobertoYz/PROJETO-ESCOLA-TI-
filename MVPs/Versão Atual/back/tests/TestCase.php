<?php

namespace Tests;

use App\Mail\CodigoVerificacaoCadastroMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function aceiteCadastro(): array
    {
        $this->seed(\Database\Seeders\DocumentoLegalSeeder::class);
        $publicacao = $this->getJson('/api/termos/vigentes')->assertOk()->json();
        return ['termos_aceitos' => true] + array_intersect_key($publicacao, array_flip([
            'documento_termos_id', 'documento_privacidade_id', 'versao_termos', 'versao_privacidade',
        ]));
    }

    protected function confirmarEmailCadastro(string $email): string
    {
        Mail::fake();
        $this->postJson('/api/auth/enviar-codigo-verificacao', ['email' => $email])->assertOk();
        $codigo = Mail::sent(CodigoVerificacaoCadastroMail::class)->sole()->codigo;

        return $this->postJson('/api/auth/verificar-codigo-email', compact('email', 'codigo'))
            ->assertOk()->json('verification_token');
    }
}
