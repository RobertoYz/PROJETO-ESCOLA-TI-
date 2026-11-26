<?php

namespace App\Http\Controllers;

use App\Models\Startup;
use App\Services\StartupPortalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StartupPortalController extends Controller
{
    public function __construct(private StartupPortalService $service) {}

    public function salvar(Request $request, Startup $startup)
    {
        Gate::authorize('update', $startup);
        $criar = $request->isMethod('post');
        if (is_string($request->login)) {
            $request->merge(['login' => strtolower(trim($request->login))]);
        }
        $unico = Rule::unique('startup_portal_acessos', 'login');
        if (!$criar && $startup->portalAcesso) {
            $unico->ignore($startup->portalAcesso);
        }
        $dados = $request->validate([
            'login' => [$criar ? 'required' : 'sometimes', 'string', 'regex:/^[a-z0-9._-]{3,60}$/D', $unico],
            'password' => [$criar ? 'required' : 'nullable', 'string', 'min:8', 'max:72'],
            'ativo' => [$criar ? 'prohibited' : 'sometimes', 'boolean'],
        ]);

        if ($criar) {
            unset($dados['ativo']);
        }

        return response()->json($this->service->salvar($startup, $dados, $criar), $criar ? 201 : 200);
    }

    public function acompanhamento(Request $request)
    {
        return response()->json($this->service->acompanhamento($request->user()));
    }
}
