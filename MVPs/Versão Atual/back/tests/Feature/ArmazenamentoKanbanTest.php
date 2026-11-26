<?php

namespace Tests\Feature;

use App\Models\Agencia;
use App\Models\KanbanAnexo;
use App\Models\KanbanCard;
use App\Models\Plano;
use App\Models\Startup;
use App\Models\StartupPortalAcesso;
use App\Models\User;
use App\Services\KanbanService;
use Database\Seeders\PlanoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArmazenamentoKanbanTest extends TestCase
{
    use RefreshDatabase;

    private User $titular;
    private Startup $startup;
    private KanbanCard $card;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(PlanoSeeder::class);
        $this->titular = $this->criarAgencia();
        $this->startup = $this->criarStartup('Primeira');
        $this->card = app(KanbanService::class)->createCard(['agencia_id' => $this->titular->agencia_id,
            'startup_id' => $this->startup->id, 'title' => 'Inscrição']);
        $this->actingAs($this->titular, 'sanctum');
    }

    private function criarAgencia(): User
    {
        $agencia = Agencia::create(['nome' => 'Consultoria', 'plano_id' => 2, 'status_pagamento' => 'ativo']);
        $usuario = User::factory()->create(['agencia_id' => $agencia->id, 'perfil_acesso' => 'consultor_admin']);
        $agencia->update(['owner_id' => $usuario->id]);
        return $usuario;
    }

    private function criarStartup(string $nome): Startup
    {
        return Startup::create(['agencia_id' => $this->titular->agencia_id, 'razao_social' => $nome,
            'cnpj' => fake()->unique()->numerify('##############')]);
    }

    private function enviar(UploadedFile $arquivo, ?KanbanCard $card = null)
    {
        $id = ($card ?? $this->card)->id;
        return $this->post("/api/kanban/cards/$id/dados-anexos", ['arquivo' => $arquivo], ['Accept' => 'application/json']);
    }

    private function anexar(string $nome = 'dados.txt', string $texto = 'dados', ?KanbanCard $card = null): KanbanAnexo
    {
        $resposta = $this->enviar(UploadedFile::fake()->createWithContent($nome, $texto), $card)->assertOk();
        return KanbanAnexo::findOrFail(collect($resposta->json('card.anexos'))->firstWhere('nome_arquivo', $nome)['id']);
    }

    public function test_cotas_oficiais_e_seeder_idempotente(): void
    {
        $this->seed(PlanoSeeder::class);
        $this->assertSame([1024, 5120, 25600], Plano::orderBy('id')->pluck('limite_armazenamento_mb')->all());
        $this->assertSame([50, 50, 50], Plano::orderBy('id')->pluck('limite_arquivo_mb')->all());
    }

    public function test_upload_persiste_bytes_e_listagem_ordenada_com_card_e_etapa(): void
    {
        $pequeno = $this->anexar();
        $grande = $this->anexar('maior.txt', 'abcdefghij');
        $this->assertSame('abcdefghij', Storage::disk('local')->get($grande->caminho));
        $this->assertSame(10, $grande->tamanho_bytes);
        $vazia = $this->criarStartup('Vazia');
        $avulso = app(KanbanService::class)->createCard(['agencia_id' => $this->titular->agencia_id]);
        $this->anexar('avulso.txt', 'abc', $avulso);
        $this->getJson("/api/startups/{$this->startup->id}/arquivos")->assertOk()->assertJsonCount(2, 'arquivos')
            ->assertJsonPath('usado_bytes', 15)->assertJsonPath('arquivos.0.id', $grande->id)
            ->assertJsonPath('arquivos.1.id', $pequeno->id)->assertJsonPath('arquivos.0.card_titulo', 'Inscrição')
            ->assertJsonPath('arquivos.0.etapa_nome', 'Backlog');
        $this->getJson('/api/assinatura/consumo')->assertOk()->assertJsonPath('armazenamento.usado_bytes', 18)
            ->assertJsonPath('creditos_minutas.limite', 15)->assertJsonPath('armazenamento.sem_startup.bytes', 3)
            ->assertJsonPath('armazenamento.por_startup.1.startup_id', $vazia->id)
            ->assertJsonPath('armazenamento.por_startup.1.usado_bytes', 0);
    }

    public function test_fronteiras_individual_e_total_sem_gravacao_parcial(): void
    {
        Plano::find(2)->update(['limite_armazenamento_mb' => 50]);
        $this->enviar(UploadedFile::fake()->create('grande.pdf', 51201, 'application/pdf'))
            ->assertUnprocessable()->assertJsonPath('codigo_erro', 'LIMITE_TAMANHO_ARQUIVO_EXCEDIDO');
        $this->assertDatabaseCount('kanban_anexos', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
        $this->enviar(UploadedFile::fake()->create('fronteira.pdf', 51200, 'application/pdf'))->assertOk();
        $this->enviar(UploadedFile::fake()->createWithContent('um.txt', 'x'))->assertUnprocessable()
            ->assertJsonPath('codigo_erro', 'LIMITE_ARMAZENAMENTO_ATINGIDO')->assertJsonPath('disponivel_mb', 0);
        $this->assertDatabaseCount('kanban_anexos', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_consumo_e_arquivos_isolados_por_agencia(): void
    {
        $anexo = $this->anexar();
        $outra = $this->criarAgencia();
        $this->actingAs($outra, 'sanctum')->getJson('/api/assinatura/consumo')->assertJsonPath('armazenamento.usado_bytes', 0);
        $this->getJson("/api/startups/{$this->startup->id}/arquivos")->assertNotFound();
        $this->getJson("/api/kanban/anexos/{$anexo->id}")->assertNotFound();
        $this->deleteJson("/api/kanban/anexos/{$anexo->id}")->assertNotFound();
    }

    public function test_leitura_por_membro_e_exclusao_somente_com_permissao(): void
    {
        $anexo = $this->anexar();
        $rota = "/api/kanban/anexos/{$anexo->id}";
        $leitor = User::factory()->create(['agencia_id' => $this->titular->agencia_id,
            'perfil_acesso' => 'consultor_padrao', 'permissoes' => []]);
        $this->actingAs($leitor, 'sanctum')->get($rota)->assertDownload('dados.txt');
        $this->deleteJson($rota)->assertForbidden();
        Storage::disk('local')->assertExists($anexo->caminho);
        $this->actingAs($this->titular, 'sanctum')->deleteJson($rota)->assertOk()->assertJsonPath('armazenamento.usado_bytes', 0);
        Storage::disk('local')->assertMissing($anexo->caminho);
    }

    public function test_exclusoes_em_cascata_liberam_o_disco(): void
    {
        foreach (['card', 'column', 'board'] as $escopo) {
            $card = app(KanbanService::class)->createCard(['agencia_id' => $this->titular->agencia_id]);
            $anexo = $this->anexar('anexo.txt', 'conteúdo', $card);
            if ($escopo === 'card') {
                $this->deleteJson("/api/kanban/cards/{$card->id}")->assertOk();
            } else {
                $card->$escopo->delete();
            }
            Storage::disk('local')->assertMissing($anexo->caminho);
            $this->assertDatabaseMissing('kanban_anexos', ['id' => $anexo->id]);
        }
    }

    public function test_downgrade_preserva_download_e_bloqueia_ate_liberar_espaco(): void
    {
        $anexo = $this->anexar();
        Plano::find(1)->update(['limite_armazenamento_mb' => 1]);
        $anexo->update(['tamanho_bytes' => 1048577]);
        $this->titular->agencia->update(['plano_id' => 1]);
        $this->getJson('/api/assinatura/consumo')->assertJsonPath('armazenamento.acima_do_limite', true);
        $this->get("/api/kanban/anexos/{$anexo->id}")->assertDownload();
        $this->enviar(UploadedFile::fake()->createWithContent('novo.txt', 'x'))->assertUnprocessable()
            ->assertJsonPath('codigo_erro', 'LIMITE_ARMAZENAMENTO_ATINGIDO');
        $this->deleteJson("/api/kanban/anexos/{$anexo->id}")->assertOk();
        $this->anexar();
    }

    public function test_token_portal_bloqueado_em_todas_as_rotas_de_anexos_e_consumo(): void
    {
        $anexo = $this->anexar();
        Sanctum::actingAs(new StartupPortalAcesso(), ['portal-startup']);
        $this->getJson('/api/assinatura/consumo')->assertForbidden();
        $this->getJson('/api/assinatura/atual')->assertForbidden();
        $this->getJson("/api/startups/{$this->startup->id}/arquivos")->assertForbidden();
        $this->getJson("/api/kanban/anexos/{$anexo->id}")->assertForbidden();
        $this->deleteJson("/api/kanban/anexos/{$anexo->id}")->assertForbidden();
        $this->enviar(UploadedFile::fake()->createWithContent('novo.txt', 'x'))->assertForbidden();
    }

    public function test_falha_ao_persistir_remove_arquivo_recente(): void
    {
        KanbanAnexo::creating(fn () => throw new \RuntimeException('Falha de persistência'));
        $this->enviar(UploadedFile::fake()->createWithContent('novo.txt', 'x'))->assertServerError();
        $this->assertEmpty(Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('kanban_anexos', 0);
    }

    public function test_backfill_usa_tamanho_fisico_e_preserva_ausentes_com_zero(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('kanban_anexos/antigo.txt', 'abc');
        $this->card->update(['metadata' => ['notas' => 'preservar', 'anexos' => [
            ['nome' => 'A', 'caminho' => '/storage/kanban_anexos/antigo.txt', 'tamanho' => 999],
            ['nome' => 'B', 'caminho' => '/storage/kanban_anexos/ausente.txt', 'tamanho' => 999],
        ]]]);
        $migracao = require database_path('migrations/2026_11_26_000002_importar_anexos_kanban.php');
        $migracao->up();
        $migracao->up();
        $this->assertDatabaseCount('kanban_anexos', 2);
        $this->assertDatabaseHas('kanban_anexos', ['nome_arquivo' => 'A', 'tamanho_bytes' => 3]);
        $this->assertDatabaseHas('kanban_anexos', ['nome_arquivo' => 'B', 'tamanho_bytes' => 0]);
        $this->assertSame(['notas' => 'preservar'], $this->card->fresh()->metadata);
        Storage::disk('public')->assertMissing('kanban_anexos/antigo.txt');
        Storage::disk('local')->assertExists('kanban_anexos/antigo.txt');
    }
}
