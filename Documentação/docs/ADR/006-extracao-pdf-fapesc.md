# ADR 006: Extração de Dados de PDFs para Editais da FAPESC (EDTAN-82)

## Status
Aceito

## Contexto
O ecossistema Anjo Nexus precisa mapear editais de múltiplas fundações de fomento (como a FAPESC), os quais frequentemente não expõem detalhes técnicos em suas páginas web, concentrando as informações críticas em documentos PDF anexados. Para viabilizar a análise via IA, o conteúdo estruturado precisa ser extraído do arquivo PDF.

## Decisão
Decidimos adotar a biblioteca `smalot/pdfparser` nativa do PHP. 
- O Spider agora clica na página de detalhes do edital, localiza o link do arquivo PDF, faz o download do binário para a memória e extrai o texto utilizando a biblioteca.
- Em vez de enviar o PDF inteiro para a Inteligência Artificial (o que causaria altíssimos custos operacionais), adotamos um roteamento de conteúdo: utilizamos expressões regulares (`Regex`) aplicadas ao texto extraído do PDF para identificar e separar seções chaves (ex: "DOS OBJETIVOS", "DOS CRITÉRIOS DE ADMISSIBILIDADE"). 
- Apenas as informações seccionadas e relevantes são encaminhadas ao serviço da IA, simulando um comportamento *Retrieval-Augmented Generation* (RAG) estático e enxuto.

## Consequências
- **Positivas:** 
  - Redução massiva do uso de tokens nas APIs de Inteligência Artificial.
  - Elimina a dependência de binários externos no servidor (como `pdftotext`), operando estritamente em PHP.
  - Menor latência para o *prompt*, pois o texto já é pré-filtrado via heurística.
- **Negativas:** 
  - Expressões Regulares dependem fortemente da estruturação do PDF (títulos específicos). Caso a FAPESC altere substancialmente o formato visual ou nomes das seções de seus editais, a heurística de regex deverá ser atualizada manualmente.
