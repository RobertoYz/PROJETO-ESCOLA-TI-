<?php

namespace App\Services;

use App\Models\KanbanCard;
use App\Models\Startup;
use App\Models\StartupPortalAcesso;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StartupPortalService
{
    public function autenticar(string $login, string $senha): array
    {
        return DB::transaction(function () use ($login, $senha) {
            $perfil = StartupPortalAcesso::where('login', strtolower(trim($login)))->lockForUpdate()->first();
            abort_unless($perfil && Hash::check($senha, $perfil->password), 401, 'Credenciais incorretas.');
            if (!$perfil->disponivel()) {
                abort(response()->json(['codigo_erro' => 'PORTAL_DESATIVADO', 'message' => 'Acesso ao portal desativado.'], 403));
            }

            $perfil->ultimo_acesso_em = now();
            $perfil->save();

            return [
                'token' => $perfil->createToken('portal-startup', ['portal-startup'])->plainTextToken,
                'tipo_acesso' => 'portal_startup',
                'startup_id' => $perfil->startup_id,
                'startup_nome' => $perfil->startup->nome_fantasia ?: $perfil->startup->razao_social,
                'agencia_nome' => $perfil->agencia->nome,
                'login' => $perfil->login,
            ];
        });
    }

    public function salvar(Startup $startup, array $dados, bool $criar): array
    {
        try {
            return DB::transaction(function () use ($startup, $dados, $criar) {
                $startup = Startup::whereKey($startup->id)->lockForUpdate()->firstOrFail();
                $perfil = $startup->portalAcesso()->lockForUpdate()->first();
                if ($criar && $perfil) {
                    throw ValidationException::withMessages(['startup' => 'Esta startup já possui acesso.']);
                }
                abort_if(!$criar && !$perfil, 404);
                $perfil ??= $startup->portalAcesso()->make(['ativo' => true]);
                $perfil->agencia_id = $startup->agencia_id;
                if (isset($dados['password'])) {
                    $dados['password'] = Hash::make($dados['password']);
                } else {
                    unset($dados['password']);
                }
                $perfil->fill($dados);
                if (!$criar && ($perfil->isDirty('password') || !$perfil->ativo)) {
                    $perfil->tokens()->delete();
                }
                $perfil->save();

                return StartupPortalAcesso::resumo($perfil);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['login' => 'Login ou perfil já cadastrado.']);
        }
    }

    public function acompanhamento(StartupPortalAcesso $perfil): array
    {
        $processos = KanbanCard::query()
            ->join('kanban_boards as quadro', 'quadro.id', '=', 'kanban_cards.board_id')
            ->join('kanban_columns as etapa', function ($join) {
                $join->on('etapa.id', '=', 'kanban_cards.column_id')->on('etapa.board_id', '=', 'quadro.id');
            })
            ->leftJoin('editals as edital', 'edital.id', '=', 'kanban_cards.edital_id')
            ->where('quadro.agencia_id', $perfil->agencia_id)->where('kanban_cards.startup_id', $perfil->startup_id)
            ->orderBy('etapa.position')->orderBy('kanban_cards.position')
            ->toBase()->get(['kanban_cards.id', 'kanban_cards.title as titulo', 'etapa.title as etapa',
                'etapa.position as ordem', 'edital.title as edital', 'edital.deadline as prazo',
                'edital.objetivo as descricao', 'kanban_cards.updated_at as atualizado_em']);

        return [
            'startup' => ['id' => $perfil->startup_id, 'nome' => $perfil->startup->nome_fantasia ?: $perfil->startup->razao_social],
            'agencia' => ['nome' => $perfil->agencia->nome],
            'resumo' => ['total_processos' => $processos->count(), 'por_etapa' => (object) $processos->countBy('etapa')->all()],
            'processos' => $processos,
        ];
    }
}
