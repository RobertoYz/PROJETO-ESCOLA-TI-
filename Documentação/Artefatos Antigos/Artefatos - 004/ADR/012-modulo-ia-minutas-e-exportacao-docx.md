# ADR 012: Arquitetura de Geração de Minutas de Propostas via IA e Exportação Nativa OpenXML

## Status
Aceito

## Contexto
O requisito **EDTAN-122 (V3)** estabelece a necessidade de um módulo gerador de minutas de inovação (Plano de Trabalho / Pitch de Proposta) com base nas informações do edital (objetivo, regras, orçamento) e no perfil da startup (CNPJ, razão social, nicho, porte), atendendo aos requisitos funcionais **RF-29 a RF-34** e **RF-47**.
Além da síntese documental via LLM, o sistema precisa fornecer exportação física nos formatos Microsoft Word (`.docx`) e PDF, permitindo edição interativa, auditoria e versionamento histórico.

Para viabilizar este módulo, deparamo-nos com três decisões arquiteturais fundamentais:
1. Como estruturar a geração via IA garantindo conformidade com editais oficiais brasileiros (ex: FINEP, FAPESC, SEIA Anjo Inovador) sem falhas em caso de indisponibilidade de APIs externas?
2. Como realizar a exportação em `.docx` sem sobrecarregar o ecossistema PHP com dependências pesadas e dependências de extensões complexas?
3. Como garantir rastreabilidade, auditoria e versionamento das alterações manuais feitas pelo consultor?

## Decisão

### 1. Estruturação em 11 Seções Institucionais com Fallback em Três Níveis
Adotamos o padrão oficial de submissão do edital *Anjo Inovador (Paraná / FAPESC)* contendo exatamente 11 seções temáticas padronizadas.
A camada de IA (`MinutaAiService`) opera com uma política em cascata:
- **Nível 1 (Primário):** Google Gemini 2.5 Flash via REST API (alta taxa de geração e suporte a contexto longo).
- **Nível 2 (Secundário):** DeepSeek / Groq (para contingência caso o Gemini atinja limite de requisições).
- **Nível 3 (Resiliência Local):** Motor Determinístico Local, que sintetiza as 11 seções a partir dos dados persistidos no banco de dados, garantindo que o usuário nunca receba tela de erro fatal mesmo se estiver sem conexão com a internet ou sem créditos em APIs externas.

### 2. Exportador OpenXML Nativo para `.docx` (Sem Dependências Externas)
Optamos por implementar um gerador nativo OpenXML em `DocumentExportService.php` utilizando apenas a extensão padrão `ZipArchive` do PHP.
Em vez de instalar bibliotecas pesadas (como `phpoffice/phpword` com centenas de subdependências no Composer), montamos diretamente a árvore OpenXML padrão ISO/IEC 29500 (`word/document.xml`, `[Content_Types].xml`, `_rels/.rels`), garantindo arquivos compactos, leves e 100% legíveis no Microsoft Word e LibreOffice.

### 3. Modelo de Versionamento Imutável e Auditoria (RF-30, RF-33, RF-34)
Decidimos que cada alteração efetuada pelo consultor no editor em tela gera um novo registro na tabela `minutas` com `versao = versao_anterior + 1` e referência `parent_minuta_id`. Nenhuma versão histórica é sobrescrita ou perdida, registrando o nome do autor, data/hora e o modelo de IA que originou a proposta inicial.

### 4. Isolamento Estrito de Minutas por Edital
O histórico e o editor são atrelados estritamente ao `edital_id`. O frontend consulta minutas filtrando por edital (`/api/minutas?edital_id=X`), impedindo que propostas técnicas elaboradas para um edital apareçam inadvertidamente em editais concorrentes.

## Justificativa Acadêmica (TCC)
- **Princípio da Responsabilidade Única (SRP):** Desacoplamento claro entre a inteligência de prompt (`MinutaAiService`), o controlador HTTP (`MinutaController`) e a geração de formatos físicos (`DocumentExportService`).
- **Tolerância a Falhas (Fault Tolerance):** A estratégia de contingência multinível protege o sistema contra indisponibilidade de serviços de terceiros, preservando a continuidade operacional da aplicação.
- **Auditoria e Governança:** O versionamento imutável atende aos padrões de governança corporativa e integridade de dados (LGPD / conformidade com editais de fomento público).

## Consequências

### Positivas:
- Custo operacional zero para geração inicial de minutas e testes acadêmicos.
- Independência total de pacotes de terceiros pesados no backend para geração de Word.
- Rastreabilidade integral de versões de minutas e autores.
- Aderência total ao padrão institucional de editais de inovação brasileiros.

### Negativas / Limitações Futuras:
- O gerador OpenXML nativo atualmente suporta formatação de texto, cabeçalhos, títulos e tabelas básicas. Caso surja necessidade futura de inserir gráficos estatísticos complexos dentro do `.docx`, uma biblioteca especializada ou microserviço em Node/Python poderá ser avaliado.
