# ADR 047 — Controle de armazenamento do Kanban por plano e gestão de anexos (EDTAN-290)

## Status

Implementado

## Data

26 de novembro de 2026

## Contexto e problema

Os limites históricos de 10 MB por arquivo e 25 MB por card permitiam aumentar indefinidamente o consumo criando novos cards. Em hospedagem compartilhada com cPanel, o consumo agregado de uma consultoria afeta o disco disponível para todas as agências. Os anexos eram entradas JSON em `kanban_cards.metadata` e o download utilizava um caminho público.

## Decisão

Cada plano oferece 512 MB por startup prevista na sua capacidade contratada. A franquia é compartilhada entre os cards da agência. Standard tem 1.024 MB, Pro tem 5.120 MB e Enterprise tem 25.600 MB. Cada arquivo pode ocupar até 50 MB. Um MB equivale a 1.048.576 bytes.

O serviço `ArmazenamentoKanbanService` valida em bytes sob bloqueio pessimista da agência. A tabela `kanban_anexos` registra o card, nome original, tamanho físico, tipo MIME, disco e caminho. A migração importa os registros JSON, transfere os arquivos históricos para o disco privado e atribui zero bytes aos ausentes. A atualização de anotações preserva os anexos. Um erro ao persistir o upload aciona a remoção do novo arquivo.

O consumo pertence à agência do quadro. A distribuição utiliza `kanban_cards.startup_id` e inclui startups atuais com zero bytes. Cards sem uma startup atual entram no grupo residual, inclusive durante a exclusão lógica da startup. O downgrade continua sujeito às regras da EDTAN-161. Após a efetivação de uma cota menor, a agência mantém o acesso aos documentos e pode liberar espaço ou ampliar o plano para voltar a enviar arquivos.

A exclusão de anexo, card, coluna ou quadro registra o caminho na mesma transação da remoção do registro de anexo no banco. Após o commit, o serviço remove o objeto físico. O scheduler repete exclusões pendentes em caso de falha de disco ou interrupção do processo.

Minha Assinatura apresenta os créditos do mês civil e o armazenamento total e por startup. Em Minhas Startups, a engrenagem reúne edição, perfil do portal e arquivos anexados. A agência pode baixar um arquivo autenticado e a permissão `kanban.gerenciar` autoriza a exclusão. O middleware do portal mantém o isolamento dos perfis externos.

## Alternativas descartadas

- Manter a cota por card permitiria contornar a franquia distribuindo arquivos entre cards.
- Confiar nos tamanhos e caminhos recebidos em JSON permitiria inconsistências de consumo e de autorização.
- Apagar arquivos durante o downgrade resultaria em perda de documentos de trabalho da agência.

## Consequências

A implantação deve executar as migrations antes de servir a nova interface. Durante o backfill, usar janela de manutenção para impedir gravações pelo código antigo e reservar espaço temporário para copiar o maior anexo. O PHP da imagem Docker configura `upload_max_filesize=55M` e `post_max_size=64M`. Em cPanel, aplicar esses valores ao PHP do domínio e verificar o limite de corpo do servidor HTTP.

Novos arquivos usam o disco privado `local`. A futura adoção de Cloudflare R2 ou AWS S3 requer o adaptador Flysystem correspondente, um bucket privado e a configuração das credenciais e de `KANBAN_FILESYSTEM_DISK` no ambiente. O disco registrado em cada linha mantém os arquivos anteriores acessíveis durante uma migração gradual.
