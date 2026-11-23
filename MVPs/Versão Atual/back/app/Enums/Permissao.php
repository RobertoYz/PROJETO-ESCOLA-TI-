<?php

namespace App\Enums;

enum Permissao: string
{
    case STARTUPS_CRIAR_EDITAR = 'startups.criar_editar';
    case STARTUPS_EXCLUIR = 'startups.excluir';
    case KANBAN_GERENCIAR = 'kanban.gerenciar';
    case MINUTAS_GERENCIAR = 'minutas.gerenciar';
    case EQUIPE_GERENCIAR = 'equipe.gerenciar';
    case ASSINATURA_GERENCIAR = 'assinatura.gerenciar';

    public function label(): string
    {
        return match ($this) {
            self::STARTUPS_CRIAR_EDITAR => 'Criar e Editar Startups',
            self::STARTUPS_EXCLUIR => 'Excluir Startups da Carteira',
            self::KANBAN_GERENCIAR => 'Gerenciar Pipeline Kanban (Cards e Anexos)',
            self::MINUTAS_GERENCIAR => 'Gerar e Editar Minutas com IA',
            self::EQUIPE_GERENCIAR => 'Gerenciar Equipe (Convidar e Editar Consultores)',
            self::ASSINATURA_GERENCIAR => 'Gerenciar Assinatura e Faturamento (Exclusivo do Titular)',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::STARTUPS_CRIAR_EDITAR => 'Permite cadastrar novas empresas e atualizar dados cadastrais e pitch.',
            self::STARTUPS_EXCLUIR => 'Permite remover empresas da carteira da consultoria (Soft Delete).',
            self::KANBAN_GERENCIAR => 'Permite criar, movimentar, anexar documentos e excluir cards no Kanban.',
            self::MINUTAS_GERENCIAR => 'Permite acionar o gerador de minutas IA e salvar novas versões.',
            self::EQUIPE_GERENCIAR => 'Permite convidar consultores operacionais. Concedível apenas pelo Titular.',
            self::ASSINATURA_GERENCIAR => 'Exclusiva do Titular financeiro da consultoria (nunca delegável).',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    /**
     * @return array<int, self>
     */
    public static function delegaveis(bool $incluirEquipeGerenciar = true): array
    {
        $lista = [
            self::STARTUPS_CRIAR_EDITAR,
            self::STARTUPS_EXCLUIR,
            self::KANBAN_GERENCIAR,
            self::MINUTAS_GERENCIAR,
        ];

        if ($incluirEquipeGerenciar) {
            $lista[] = self::EQUIPE_GERENCIAR;
        }

        return $lista;
    }

    /**
     * @return array<int, string>
     */
    public static function valoresDelegaveis(bool $incluirEquipeGerenciar = true): array
    {
        return array_map(
            fn (self $p) => $p->value,
            self::delegaveis($incluirEquipeGerenciar)
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function catalogo(bool $atorEhOwner = true): array
    {
        return array_map(fn (self $p) => [
            'codigo' => $p->value,
            'label' => $p->label(),
            'descricao' => $p->descricao(),
            'exclusiva_owner' => $p === self::ASSINATURA_GERENCIAR,
            'apenas_owner_concede' => $p === self::EQUIPE_GERENCIAR,
            'disponivel_para_ator' => $p !== self::ASSINATURA_GERENCIAR
                && ($atorEhOwner || $p !== self::EQUIPE_GERENCIAR),
        ], self::delegaveis(true));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function presets(bool $atorEhOwner = true): array
    {
        return [
            'gerente_operacional' => [
                'id' => 'gerente_operacional',
                'nome' => 'Gerente Operacional',
                'descricao' => 'Acesso operacional completo, incluindo gestão de startups, Kanban, minutas e equipe.',
                'apenas_owner' => true,
                'disponivel' => $atorEhOwner,
                'permissoes' => [
                    self::STARTUPS_CRIAR_EDITAR->value,
                    self::STARTUPS_EXCLUIR->value,
                    self::KANBAN_GERENCIAR->value,
                    self::MINUTAS_GERENCIAR->value,
                    self::EQUIPE_GERENCIAR->value,
                ],
            ],
            'consultor_pleno' => [
                'id' => 'consultor_pleno',
                'nome' => 'Consultor Pleno',
                'descricao' => 'Cria e edita startups, opera o Kanban e gera minutas técnicas com IA.',
                'apenas_owner' => false,
                'disponivel' => true,
                'permissoes' => [
                    self::STARTUPS_CRIAR_EDITAR->value,
                    self::KANBAN_GERENCIAR->value,
                    self::MINUTAS_GERENCIAR->value,
                ],
            ],
            'visualizador' => [
                'id' => 'visualizador',
                'nome' => 'Visualizador',
                'descricao' => 'Somente leitura no Radar de Editais, Startups, Pipeline Kanban e Minutas.',
                'apenas_owner' => false,
                'disponivel' => true,
                'permissoes' => [],
            ],
        ];
    }
}
