<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->api(prepend: [\App\Http\Middleware\IsolarPortalStartup::class]);
        $middleware->alias([
            'check.pagamento' => \App\Http\Middleware\CheckPagamentoAtivo::class,
            'startup.ativa' => \App\Http\Middleware\VerificarStartupAtiva::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $erro, \Illuminate\Http\Request $request) {
            if ($request->is('api/kanban/cards/*/dados-anexos')) {
                return response()->json(['status' => 'erro', 'codigo_erro' => 'LIMITE_TAMANHO_ARQUIVO_EXCEDIDO',
                    'message' => 'O envio excedeu o tamanho máximo permitido.'], 422);
            }
        });
        $exceptions->render(function (\App\Exceptions\PlanLimitExceededException $e, \Illuminate\Http\Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getErrorCode(),
                'codigo' => $e->getErrorCode(),
                'codigo_erro' => $e->getErrorCode(),
                'atual' => $e->getAtual(),
                'usados' => $e->getAtual(),
                'limite' => $e->getLimite(),
                'restantes' => $e->getRestantes(),
                'plano_atual' => $e->getPlanoAtual(),
                'plano_nome' => $e->getPlanoAtual(),
            ], 403);
        });
    })->create();
