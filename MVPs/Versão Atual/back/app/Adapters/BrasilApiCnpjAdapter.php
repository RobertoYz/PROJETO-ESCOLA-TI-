<?php

namespace App\Adapters;

use App\Rules\CnpjValidationRule;

class BrasilApiCnpjAdapter
{
    /**
     * Traduz a resposta da BrasilAPI para o formato padronizado do domínio interno.
     */
    public function deBrasilApi(array $raw, string $digits): array
    {
        $cnaeDescricao = $raw['cnae_fiscal_descricao'] ?? null;
        $porte = $this->padronizarPorte($raw['porte'] ?? null);

        $regime = null;
        if (isset($raw['opcao_pelo_simples'])) {
            $regime = $raw['opcao_pelo_simples'] ? 'Simples Nacional' : 'Lucro Presumido / Real';
        }

        $telefone = null;
        if (!empty($raw['ddd_telefone_1'])) {
            $telefone = trim($raw['ddd_telefone_1']);
        }

        $socios = [];
        if (!empty($raw['qsa']) && is_array($raw['qsa'])) {
            foreach ($raw['qsa'] as $socio) {
                $socios[] = [
                    'nome' => $socio['nome_socio'] ?? ($socio['nome'] ?? ''),
                    'qualificacao' => $socio['qualificacao_socio'] ?? ($socio['qualificacao'] ?? ''),
                ];
            }
        }

        return [
            'provedor' => 'brasilapi',
            'cnpj' => $digits,
            'cnpj_formatado' => CnpjValidationRule::format($digits),
            'razao_social' => $raw['razao_social'] ?? '',
            'nome_fantasia' => !empty($raw['nome_fantasia']) ? $raw['nome_fantasia'] : ($raw['razao_social'] ?? ''),
            'data_abertura' => $raw['data_inicio_atividade'] ?? null,
            'situacao_cadastral' => $raw['descricao_situacao_cadastral'] ?? 'ATIVA',
            'porte' => $porte,
            'regime_tributario' => $regime,
            'cnae_codigo' => $raw['cnae_fiscal'] ?? null,
            'cnae_descricao' => $cnaeDescricao,
            'nicho_atuacao' => $cnaeDescricao ?: 'Tecnologia / Inovação',
            'natureza_juridica' => $raw['natureza_juridica'] ?? null,
            'endereco' => [
                'cep' => $raw['cep'] ?? null,
                'logradouro' => $raw['logradouro'] ?? null,
                'numero' => $raw['numero'] ?? null,
                'complemento' => $raw['complemento'] ?? null,
                'bairro' => $raw['bairro'] ?? null,
                'cidade' => $raw['municipio'] ?? null,
                'estado' => $raw['uf'] ?? null,
            ],
            'contato' => [
                'email' => $raw['email'] ?? null,
                'telefone' => $telefone,
            ],
            'socios' => $socios,
            'startup_payload' => [
                'cnpj' => CnpjValidationRule::format($digits),
                'razao_social' => $raw['razao_social'] ?? '',
                'nome_fantasia' => !empty($raw['nome_fantasia']) ? $raw['nome_fantasia'] : ($raw['razao_social'] ?? ''),
                'data_abertura' => $raw['data_inicio_atividade'] ?? null,
                'email' => $raw['email'] ?? null,
                'telefone' => $telefone,
                'cep' => $raw['cep'] ?? null,
                'logradouro' => $raw['logradouro'] ?? null,
                'numero' => $raw['numero'] ?? null,
                'bairro' => $raw['bairro'] ?? null,
                'cidade' => $raw['municipio'] ?? null,
                'estado' => $raw['uf'] ?? null,
                'porte' => $porte,
                'regime_tributario' => $regime,
                'nicho_atuacao' => $cnaeDescricao ?: 'Tecnologia / Inovação',
            ],
        ];
    }

    /**
     * Traduz a resposta da ReceitaWS para o formato padronizado do domínio interno.
     */
    public function deReceitaWs(array $raw, string $digits): array
    {
        $cnaeDescricao = null;
        $cnaeCodigo = null;
        if (!empty($raw['atividade_principal']) && is_array($raw['atividade_principal'])) {
            $primeira = $raw['atividade_principal'][0] ?? [];
            $cnaeDescricao = $primeira['text'] ?? null;
            $cnaeCodigo = $primeira['code'] ?? null;
        }

        $dataAbertura = null;
        if (!empty($raw['abertura'])) {
            $partes = explode('/', $raw['abertura']);
            if (count($partes) === 3) {
                $dataAbertura = "{$partes[2]}-{$partes[1]}-{$partes[0]}";
            }
        }

        $porte = $this->padronizarPorte($raw['porte'] ?? null);

        $regime = null;
        if (isset($raw['simples']['optante'])) {
            $regime = $raw['simples']['optante'] ? 'Simples Nacional' : 'Lucro Presumido / Real';
        }

        $socios = [];
        if (!empty($raw['qsa']) && is_array($raw['qsa'])) {
            foreach ($raw['qsa'] as $socio) {
                $socios[] = [
                    'nome' => $socio['nome'] ?? '',
                    'qualificacao' => $socio['qual'] ?? '',
                ];
            }
        }

        return [
            'provedor' => 'receitaws',
            'cnpj' => $digits,
            'cnpj_formatado' => CnpjValidationRule::format($digits),
            'razao_social' => $raw['nome'] ?? '',
            'nome_fantasia' => !empty($raw['fantasia']) ? $raw['fantasia'] : ($raw['nome'] ?? ''),
            'data_abertura' => $dataAbertura,
            'situacao_cadastral' => $raw['situacao'] ?? 'ATIVA',
            'porte' => $porte,
            'regime_tributario' => $regime,
            'cnae_codigo' => $cnaeCodigo,
            'cnae_descricao' => $cnaeDescricao,
            'nicho_atuacao' => $cnaeDescricao ?: 'Tecnologia / Inovação',
            'natureza_juridica' => $raw['natureza_juridica'] ?? null,
            'endereco' => [
                'cep' => $raw['cep'] ?? null,
                'logradouro' => $raw['logradouro'] ?? null,
                'numero' => $raw['numero'] ?? null,
                'complemento' => $raw['complemento'] ?? null,
                'bairro' => $raw['bairro'] ?? null,
                'cidade' => $raw['municipio'] ?? null,
                'estado' => $raw['uf'] ?? null,
            ],
            'contato' => [
                'email' => $raw['email'] ?? null,
                'telefone' => $raw['telefone'] ?? null,
            ],
            'socios' => $socios,
            'startup_payload' => [
                'cnpj' => CnpjValidationRule::format($digits),
                'razao_social' => $raw['nome'] ?? '',
                'nome_fantasia' => !empty($raw['fantasia']) ? $raw['fantasia'] : ($raw['nome'] ?? ''),
                'data_abertura' => $dataAbertura,
                'email' => $raw['email'] ?? null,
                'telefone' => $raw['telefone'] ?? null,
                'cep' => $raw['cep'] ?? null,
                'logradouro' => $raw['logradouro'] ?? null,
                'numero' => $raw['numero'] ?? null,
                'bairro' => $raw['bairro'] ?? null,
                'cidade' => $raw['municipio'] ?? null,
                'estado' => $raw['uf'] ?? null,
                'porte' => $porte,
                'regime_tributario' => $regime,
                'nicho_atuacao' => $cnaeDescricao ?: 'Tecnologia / Inovação',
            ],
        ];
    }

    /**
     * Formata a resposta HTTP de sucesso da consulta de CNPJ.
     */
    public function paraRespostaApi(array $dados): array
    {
        return [
            'status' => 'sucesso',
            'message' => 'Dados da empresa obtidos com sucesso na Receita Federal.',
            'dados' => $dados,
        ];
    }

    /**
     * Padroniza a string de porte da empresa (ME, EPP, Médio, Grande).
     */
    public function padronizarPorte(?string $porte): string
    {
        if (empty($porte)) {
            return 'Demais';
        }

        $p = mb_strtoupper(trim($porte));

        if (str_contains($p, 'MICRO') || $p === 'ME') {
            return 'Microempresa (ME)';
        }

        if (str_contains($p, 'PEQUENO') || $p === 'EPP') {
            return 'Empresa de Pequeno Porte (EPP)';
        }

        if (str_contains($p, 'MÉDIO') || str_contains($p, 'MEDIO')) {
            return 'Médio Porte';
        }

        if (str_contains($p, 'GRANDE')) {
            return 'Grande Porte';
        }

        return 'Demais';
    }
}
