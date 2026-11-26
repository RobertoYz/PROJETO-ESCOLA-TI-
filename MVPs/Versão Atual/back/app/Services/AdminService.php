<?php

namespace App\Services;

use App\Models\Agencia;
use App\Models\Edital;
use App\Models\Plano;
use App\Models\Startup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AdminService
{
    /**
     * Retorna o consolidado de métricas globais para o dashboard do Administrador.
     */
    public function obterMetricasDashboard(): array
    {
        $totalUsuarios = User::count();
        $usuariosPorStatus = [
            'ativos' => User::where('status', 'ativo')->count(),
            'inativos' => User::where('status', 'inativo')->count(),
            'pendentes' => User::where('status', 'pendente')->count(),
        ];

        $totalAgencias = Agencia::count();
        $agenciasPorStatus = [
            'ativas' => Agencia::where('status_pagamento', 'ativo')->count(),
            'suspensas' => Agencia::where('status_pagamento', 'suspenso')->count(),
            'pendentes' => Agencia::where('status_pagamento', 'pendente')->count(),
            'canceladas' => Agencia::where('status_pagamento', 'cancelado')->count(),
        ];

        $planos = Plano::withCount(['agencias as total_agencias' => function ($query) {
            $query->where('status_pagamento', 'ativo');
        }])->get()->map(function (Plano $plano) {
            $faturamento = (float) $plano->preco * (int) $plano->total_agencias;
            return [
                'id' => $plano->id,
                'nome' => $plano->nome,
                'preco' => (float) $plano->preco,
                'total_agencias' => (int) $plano->total_agencias,
                'faturamento_estimado' => $faturamento,
            ];
        });

        $mrrEstimado = $planos->sum('faturamento_estimado');

        $formasPagamento = [
            'pix' => Agencia::where('status_pagamento', 'ativo')->where('forma_pagamento', 'PIX')->count(),
            'card' => Agencia::where('status_pagamento', 'ativo')->where('forma_pagamento', 'CARD')->count(),
        ];

        $totalEditais = Edital::count();
        $editaisAbertos = Edital::where('status', 'active')->count();

        $editaisPorFonte = Edital::select('fonte', DB::raw('count(*) as total'))
            ->groupBy('fonte')
            ->pluck('total', 'fonte')
            ->toArray();

        $editaisPorRegiao = Edital::select('regiao', DB::raw('count(*) as total'))
            ->whereNotNull('regiao')
            ->groupBy('regiao')
            ->pluck('total', 'regiao')
            ->toArray();

        return [
            'usuarios' => [
                'total' => $totalUsuarios,
                'status' => $usuariosPorStatus,
            ],
            'agencias' => [
                'total' => $totalAgencias,
                'status' => $agenciasPorStatus,
            ],
            'planos' => $planos,
            'financeiro' => [
                'mrr_estimado' => $mrrEstimado,
                'formas_pagamento' => $formasPagamento,
            ],
            'editais' => [
                'total' => $totalEditais,
                'abertos' => $editaisAbertos,
                'por_fonte' => $editaisPorFonte,
                'por_regiao' => $editaisPorRegiao,
            ],
        ];
    }

    /**
     * Lista as consultorias/agências com paginação e filtros avançados.
     */
    public function listarAgencias(array $filtros = []): LengthAwarePaginator
    {
        $query = Agencia::with(['plano:id,nome,preco', 'owner:id,name,email'])
            ->withCount(['startups', 'usuarios']);

        if (!empty($filtros['busca'])) {
            $busca = '%' . trim($filtros['busca']) . '%';
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', $busca)
                    ->orWhereHas('owner', function ($qOwner) use ($busca) {
                        $qOwner->where('name', 'like', $busca)
                            ->orWhere('email', 'like', $busca);
                    });
            });
        }

        if (!empty($filtros['status'])) {
            $query->where('status_pagamento', $filtros['status']);
        }

        if (!empty($filtros['plano_id'])) {
            $query->where('plano_id', $filtros['plano_id']);
        }

        $porPagina = isset($filtros['por_pagina']) ? max(1, min(100, (int) $filtros['por_pagina'])) : 15;

        return $query->latest('id')->paginate($porPagina);
    }

    /**
     * Detalhes completos de uma empresa específica.
     */
    public function obterDetalhesAgencia(int $agenciaId): array
    {
        $agencia = Agencia::with([
            'plano',
            'planoAgendado',
            'upgradePendente',
            'owner:id,name,email,created_at',
            'usuarios:id,agencia_id,name,email,perfil_acesso,status,created_at',
            'startups',
            'termosAceites.documentoLegal',
        ])->findOrFail($agenciaId);

        return [
            'agencia' => $agencia,
            'is_ativa' => $agencia->isPagamentoAtivo(),
            'em_carencia' => $agencia->emPeriodoCarencia(),
            'alerta_vencimento' => $agencia->emJanelaVencimentoProximo(),
        ];
    }

    /**
     * Altera o status da consultoria (ativo, suspenso, cancelado, pendente).
     */
    public function alterarStatusAgencia(int $agenciaId, string $novoStatus): Agencia
    {
        $statusValidos = ['ativo', 'suspenso', 'cancelado', 'pendente'];
        if (!in_array($novoStatus, $statusValidos, true)) {
            throw new \InvalidArgumentException('Status de agência inválido.');
        }

        $agencia = Agencia::findOrFail($agenciaId);
        $agencia->status_pagamento = $novoStatus;

        if ($novoStatus === 'ativo') {
            if (!$agencia->assinatura_expira_em || $agencia->assinatura_expira_em->isPast()) {
                $agencia->assinatura_expira_em = now()->addDays(30);
            }
        }

        $agencia->save();

        return $agencia;
    }

    /**
     * Permite ao administrador alterar o plano da empresa ou conceder dias extras manualmente.
     */
    public function ajustarPlanoAgencia(int $agenciaId, int $planoId, ?int $diasExtras = null): Agencia
    {
        $plano = Plano::findOrFail($planoId);
        $agencia = Agencia::findOrFail($agenciaId);

        $agencia->plano_id = $plano->id;
        $agencia->status_pagamento = 'ativo';

        if ($diasExtras && $diasExtras > 0) {
            $base = ($agencia->assinatura_expira_em && $agencia->assinatura_expira_em->isFuture())
                ? $agencia->assinatura_expira_em
                : now();
            $agencia->assinatura_expira_em = $base->copy()->addDays($diasExtras);
        } elseif (!$agencia->assinatura_expira_em || $agencia->assinatura_expira_em->isPast()) {
            $agencia->assinatura_expira_em = now()->addDays(30);
        }

        $agencia->save();

        return $agencia->load('plano');
    }

    /**
     * Exclui uma consultoria em cascata com limpeza de todos os recursos operacionais.
     */
    public function excluirAgencia(int $agenciaId): void
    {
        $agencia = Agencia::findOrFail($agenciaId);

        DB::transaction(function () use ($agencia) {
            // 1. Excluir dados das startups (kanban cards, colunas, minutas)
            foreach ($agencia->startups as $startup) {
                DB::table('kanban_cards')->where('startup_id', $startup->id)->delete();
                DB::table('minutas')->where('startup_id', $startup->id)->delete();
                $startup->forceDelete();
            }

            // 2. Desvincular e remover usuários operacionais da agência
            $usuarios = User::where('agencia_id', $agencia->id)->get();
            foreach ($usuarios as $usuario) {
                DB::table('personal_access_tokens')->where('tokenable_id', $usuario->id)->delete();
                $usuario->delete();
            }

            // 3. Deletar a agência
            $agencia->delete();
        });
    }

    /**
     * Mapeia e testa o status em tempo real de cada radar/spider suportado na plataforma.
     */
    public function obterStatusRadares(): array
    {
        $definicoesRadares = [
            'FAPDF' => [
                'orgao' => 'Fundação de Apoio à Pesquisa do Distrito Federal (FAPDF)',
                'regiao' => 'Distrito Federal (DF)',
                'url_portal' => 'https://www.fap.df.gov.br',
                'metodo_extracao' => 'Web Scraping (RoachPHP) + Catálogo de Contingência',
                'comando_sync' => 'fapdf:sync',
            ],
            'FAPEG' => [
                'orgao' => 'Fundação de Amparo à Pesquisa do Estado de Goiás (FAPEG)',
                'regiao' => 'Goiás (GO)',
                'url_portal' => 'https://goias.gov.br/fapeg/category/editais/',
                'metodo_extracao' => 'Web Scraping (RoachPHP) + Catálogo de Contingência',
                'comando_sync' => 'fapeg:sync',
            ],
            'FINEP' => [
                'orgao' => 'Financiadora de Estudos e Projetos (FINEP)',
                'regiao' => 'Nacional',
                'url_portal' => 'http://www.finep.gov.br',
                'metodo_extracao' => 'Web Scraping + Chamadas Abertas',
                'comando_sync' => 'editais:sync',
            ],
            'FAPESP' => [
                'orgao' => 'Fundação de Amparo à Pesquisa do Estado de São Paulo (FAPESP)',
                'regiao' => 'São Paulo (SP)',
                'url_portal' => 'https://fapesp.br',
                'metodo_extracao' => 'Web Scraping (PIPE / Chamadas)',
                'comando_sync' => 'editais:sync',
            ],
            'FAPERJ' => [
                'orgao' => 'Fundação Carlos Chagas Filho de Amparo à Pesquisa do Estado do RJ (FAPERJ)',
                'regiao' => 'Rio de Janeiro (RJ)',
                'url_portal' => 'http://www.faperj.br',
                'metodo_extracao' => 'Web Scraping de Editais',
                'comando_sync' => 'editais:sync',
            ],
            'FAPEMIG' => [
                'orgao' => 'Fundação de Amparo à Pesquisa do Estado de Minas Gerais (FAPEMIG)',
                'regiao' => 'Minas Gerais (MG)',
                'url_portal' => 'http://www.fapemig.br',
                'metodo_extracao' => 'Web Scraping de Editais e Chamadas',
                'comando_sync' => 'editais:sync',
            ],
        ];

        $radares = [];

        foreach ($definicoesRadares as $fonte => $meta) {
            $total = Edital::where('fonte', $fonte)->count();
            $abertos = Edital::where('fonte', $fonte)->where('status', 'active')->count();
            $ultimoEdital = Edital::where('fonte', $fonte)->latest('updated_at')->first();

            $statusConexao = $this->testarConexaoPortal($meta['url_portal']);

            $radares[] = [
                'fonte' => $fonte,
                'orgao' => $meta['orgao'],
                'regiao' => $meta['regiao'],
                'url_portal' => $meta['url_portal'],
                'status_servidor' => $statusConexao['status'],
                'codigo_http' => $statusConexao['codigo_http'],
                'latencia_ms' => $statusConexao['latencia_ms'],
                'total_editais' => $total,
                'editais_abertos' => $abertos,
                'ultima_sincronizacao' => $ultimoEdital?->updated_at?->format('d/m/Y H:i:s') ?? 'Nunca',
                'metodo_extracao' => $meta['metodo_extracao'],
                'comando_sync' => $meta['comando_sync'],
            ];
        }

        return $radares;
    }

    /**
     * Executa a sincronização manual de um radar específico ou de todos.
     */
    public function sincronizarRadar(string $fonte): array
    {
        $fonteNormalizada = strtoupper(trim($fonte));

        $comando = match ($fonteNormalizada) {
            'FAPEG' => 'fapeg:sync',
            'FAPDF' => 'fapdf:sync',
            'TODOS', 'ALL' => 'editais:sync',
            default => null,
        };

        if (!$comando) {
            throw new \InvalidArgumentException("Fonte de radar '{$fonte}' não suporta sincronização direta ou comando não mapeado.");
        }

        $inicio = microtime(true);
        $exitCode = Artisan::call($comando);
        $duracaoSegundos = round(microtime(true) - $inicio, 2);
        $saidaLog = Artisan::output();

        $totalAtualizado = ($fonteNormalizada === 'TODOS' || $fonteNormalizada === 'ALL')
            ? Edital::count()
            : Edital::where('fonte', $fonteNormalizada)->count();

        return [
            'fonte' => $fonteNormalizada,
            'comando' => $comando,
            'sucesso' => $exitCode === 0,
            'duracao_segundos' => $duracaoSegundos,
            'total_editais_banco' => $totalAtualizado,
            'log_saida' => trim($saidaLog),
        ];
    }

    /**
     * Realiza um ping HTTP de verificação de disponibilidade do portal.
     */
    protected function testarConexaoPortal(string $url): array
    {
        if (app()->runningUnitTests()) {
            return [
                'status' => 'Online',
                'codigo_http' => 200,
                'latencia_ms' => 45,
            ];
        }

        $inicio = microtime(true);
        try {
            $resposta = Http::timeout(4)->withoutVerifying()->head($url);
            $latencia = round((microtime(true) - $inicio) * 1000);
            $codigo = $resposta->status();

            if ($resposta->successful() || $resposta->redirect()) {
                $status = 'Online';
            } elseif ($codigo === 503 || $codigo === 403) {
                $status = 'Instável / WAF';
            } else {
                $status = 'Offline';
            }

            return [
                'status' => $status,
                'codigo_http' => $codigo,
                'latencia_ms' => $latencia,
            ];
        } catch (\Throwable $e) {
            $latencia = round((microtime(true) - $inicio) * 1000);
            return [
                'status' => 'Offline / Timeout',
                'codigo_http' => null,
                'latencia_ms' => $latencia,
            ];
        }
    }
}
