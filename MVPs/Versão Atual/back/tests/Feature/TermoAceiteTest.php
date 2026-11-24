<?php

namespace Tests\Feature;

use App\Models\DocumentoLegal;
use App\Models\EmailVerificacaoCadastro;
use App\Models\Plano;
use App\Models\TermoAceite;
use App\Models\User;
use App\Services\AbacatePayService;
use App\Services\TermoAceiteService;
use Database\Seeders\DocumentoLegalSeeder;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TermoAceiteTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTRO = '/api/auth/registrar-com-plano';
    private const HISTORICO = '/api/termos/meu-aceite';
    private const EXPURGO = 'termos:expurgar-aceites-vencidos';
    private const EMAIL = 'titular@example.test';

    private function cadastro(): array
    {
        $plano = Plano::create(['nome' => 'Teste', 'preco' => 10, 'limite_startups' => 2, 'limite_membros_equipe' => 1]);
        return [
            'name' => 'Titular', 'nome_agencia' => 'Agência de teste', 'email' => self::EMAIL,
            'password' => 'senha-de-teste', 'plano_id' => $plano->id,
            'verification_token' => $this->confirmarEmailCadastro(self::EMAIL),
        ];
    }

    private function publicar(string $versao): DocumentoLegal
    {
        return app(TermoAceiteService::class)->publicarNovaVersao('termos_uso', $versao, 'Termos revisados', [
            ['texto' => 'Texto revisado', 'titulo' => 'Revisão'],
        ]);
    }

    public function test_conjunto_completo_congelado_e_hash_canonico(): void
    {
        $this->aceiteCadastro();
        $url = '/api/termos/vigentes';
        $dados = $this->getJson($url)->assertOk()->json();
        foreach (['termos_uso' => 'termos', 'politica_privacidade' => 'privacidade'] as $tipo => $campo) {
            $documento = $dados[$tipo];
            $secoes = $documento['conteudo_json'];
            foreach ($secoes as &$secao) {
                ksort($secao, SORT_STRING);
            }
            unset($secao);
            $canonico = json_encode([$tipo, '1.0.0', $documento['titulo'], $secoes], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->assertSame(hash('sha256', $canonico), $dados['hash_'.$campo]);
            $this->assertStringContainsString('projetoanjonexus@gmail.com', $canonico);
        }
        config(['mail.from.address' => 'outro@example.test']);
        $this->getJson($url)->assertExactJson($dados);
        DocumentoLegal::where('tipo', 'termos_uso')->sole()->update(['vigente_tipo' => null]);
        $this->getJson($url)->assertServiceUnavailable();
    }

    public function test_publicacao_atomica_e_seeder_preserva_versoes(): void
    {
        $this->aceiteCadastro();
        $nova = $this->publicar('1.1.0');
        $this->seed(DocumentoLegalSeeder::class);
        $this->seed(DocumentoLegalSeeder::class);
        $this->assertDatabaseCount('documentos_legais', 3);
        $this->assertDatabaseHas('documentos_legais', ['tipo' => 'termos_uso', 'versao' => '1.0.0', 'vigente_tipo' => null]);
        try {
            $this->publicar('1.1.0');
            $this->fail('A publicação duplicada deveria falhar.');
        } catch (DomainException) {
            $this->assertSame('termos_uso', $nova->refresh()->vigente_tipo);
        }
        $this->expectException(UniqueConstraintViolationException::class);
        DocumentoLegal::where('tipo', 'termos_uso')->where('versao', '1.0.0')->update(['vigente_tipo' => 'termos_uso']);
    }

    public function test_snapshots_sao_imutaveis_e_validam_tipo(): void
    {
        $this->aceiteCadastro();
        $documento = DocumentoLegal::firstOrFail();
        foreach (['titulo' => 'Alterado', 'versao' => '2', 'tipo' => 'outro', 'vigente_tipo' => 'invalido',
            'hash_sha256' => 'x', 'conteudo_json' => [], 'publicado_em' => now()->addDay()] as $campo => $valor) {
            try {
                $documento->refresh()->forceFill([$campo => $valor])->save();
                $this->fail("Alteração de {$campo} permitida.");
            } catch (LogicException) {
                $this->assertNotEquals($valor, $documento->refresh()->getAttribute($campo));
            }
        }
        $this->expectException(LogicException::class);
        $documento->delete();
    }

    public static function rotasCadastro(): array
    {
        return [[self::REGISTRO], ['/api/auth/registro']];
    }

    #[DataProvider('rotasCadastro')]
    public function test_rejeicoes_preservam_token_e_nao_chamam_gateway(string $rota): void
    {
        $dados = $this->cadastro();
        $aceite = $this->aceiteCadastro();
        $this->mock(AbacatePayService::class)->shouldNotReceive('gerarCobranca');
        foreach ([[], ['termos_aceitos' => false], ['termos_aceitos' => '1'], ['termos_aceitos' => 'yes'], ['termos_aceitos' => 'on']] as $invalido) {
            $this->postJson($rota, $dados + $invalido)->assertUnprocessable()->assertJsonPath('codigo_erro', 'TERMOS_NAO_ACEITOS');
        }
        foreach ([['versao_privacidade' => '0'], ['versao_termos' => '0'], [
            'documento_termos_id' => $aceite['documento_privacidade_id'], 'documento_privacidade_id' => $aceite['documento_termos_id'],
        ]] as $invalido) {
            $this->postJson($rota, $dados + $invalido + $aceite)->assertUnprocessable()->assertJsonPath('codigo_erro', 'VERSAO_TERMOS_DESATUALIZADA');
        }
        $this->assertSame(hash('sha256', $dados['verification_token']), EmailVerificacaoCadastro::sole()->verification_token_hash);
        foreach (['users', 'agencias', 'termos_aceites'] as $tabela) {
            $this->assertDatabaseCount($tabela, 0);
        }
    }

    public function test_aceite_preserva_par_validado_historico_privado_e_encerramento(): void
    {
        $this->freezeSecond();
        $this->getJson(self::HISTORICO)->assertUnauthorized();
        $dados = $this->cadastro() + $this->aceiteCadastro() + ['hash_termos' => 'forjado'];
        $this->mock(AbacatePayService::class)->shouldReceive('gerarCobranca')->once()->andReturnUsing(function () {
            $this->publicar('1.1.0');
            return ['url_checkout' => 'https://abacatepay.com/pay/teste', 'id_transacao' => 'teste'];
        });
        $this->withHeader('User-Agent', 'Navegador de teste')->postJson(self::REGISTRO, $dados)->assertCreated();
        $aceite = TermoAceite::sole();
        $this->assertSame($dados['documento_termos_id'], $aceite->documento_termos_id);
        $this->assertSame($dados['documento_privacidade_id'], $aceite->documento_privacidade_id);
        $this->assertSame($aceite->termosUso->hash_sha256, $aceite->hash_termos);
        $this->assertSame(hash_hmac('sha256', self::EMAIL, config('termos.hmac_key')), $aceite->titular_email_hmac);
        $this->assertSame('teste', $aceite->hmac_chave_id);
        $this->assertTrue($aceite->aceito_em->eq(now('UTC')));
        $this->assertNull($aceite->retencao_ate);
        $this->assertSame('127.0.0.1', $aceite->ip_address);
        $this->assertSame('Navegador de teste', $aceite->user_agent);
        $titular = User::sole();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson(self::HISTORICO)->assertExactJson([]);
        Sanctum::actingAs($titular);
        $this->getJson(self::HISTORICO)->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.termos_uso.versao', '1.0.0')
            ->assertJsonPath('0.politica_privacidade.conteudo_json', $aceite->politicaPrivacidade->conteudo_json)
            ->assertJsonMissingPath('0.titular_email_hmac')->assertJsonMissingPath('0.hmac_chave_id');
        $this->deleteJson('/api/conta/cancelar', ['password' => $dados['password'], 'ciente_lgpd' => true])->assertOk();
        $this->assertTrue($aceite->refresh()->retencao_ate->eq(now()->addYears(5)));
        $this->assertNull($aceite->user_id);
        $this->assertNull($aceite->agencia_id);
        $this->assertSame($dados['documento_termos_id'], $aceite->termosUso->id);
        $this->assertNotEmpty($aceite->titular_email_hmac);
    }

    public function test_falha_do_gateway_reverte_cadastro_e_aceite(): void
    {
        $dados = $this->cadastro() + $this->aceiteCadastro();
        $this->mock(AbacatePayService::class)->shouldReceive('gerarCobranca')->once()->andThrow(new \RuntimeException('Falha simulada'));
        $this->postJson(self::REGISTRO, $dados)->assertStatus(500);
        foreach (['users', 'agencias', 'termos_aceites'] as $tabela) {
            $this->assertDatabaseCount($tabela, 0);
        }
        $this->assertNull(EmailVerificacaoCadastro::sole()->consumido_em);
    }

    public function test_producao_exige_chave_dedicada(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['termos.hmac_key' => null, 'app.key' => str_repeat('a', 32)]);
        $this->expectException(LogicException::class);
        app(TermoAceiteService::class)->validarConfiguracao();
    }

    public function test_expurgo_respeita_vencimento_preservacao_e_imutabilidade(): void
    {
        $this->freezeSecond();
        $this->aceiteCadastro();
        $servico = app(TermoAceiteService::class);
        $documentos = $servico->vigentes();
        $criar = function () use ($servico, $documentos) {
            $usuario = User::factory()->create();
            $servico->registrar($usuario, $documentos, request());
            return TermoAceite::where('user_id', $usuario->id)->sole();
        };
        $ativo = $criar();
        $recente = $criar();
        $vencido = $criar();
        $preservado = $criar();
        $this->travel(-5)->years();
        $servico->iniciarRetencao([$preservado->user_id, $vencido->user_id]);
        $this->travel(5)->years();
        $servico->iniciarRetencao([$recente->user_id]);
        $servico->definirPreservacao($preservado->id, true);
        foreach ([fn () => $ativo->update(['versao_termos' => 'outra']), fn () => $ativo->delete()] as $operacao) {
            try {
                $operacao();
                $this->fail('Era esperado bloqueio da operação.');
            } catch (LogicException) {
                $this->assertSame('1.0.0', $ativo->refresh()->versao_termos);
            }
        }
        $this->artisan(self::EXPURGO, ['--dry-run' => true])->expectsOutput('Aceites elegíveis: 1')->assertSuccessful();
        $this->assertDatabaseCount('termos_aceites', 4);
        $this->artisan(self::EXPURGO)->expectsOutput('Aceites removidos: 1')->assertSuccessful();
        $this->assertDatabaseMissing('termos_aceites', ['id' => $vencido->id]);
        $this->artisan(self::EXPURGO)->expectsOutput('Aceites removidos: 0')->assertSuccessful();
        $this->assertDatabaseCount('termos_aceites', 3);
    }
}
