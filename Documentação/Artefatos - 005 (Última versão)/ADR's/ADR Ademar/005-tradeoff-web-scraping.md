# ADR 005: Trade-off na Adoção de Web Scraping e Consumo de API Interna

## Status
Aceito

## Contexto
Para alimentar o sistema Anjo Nexus com editais de fomento e inovação, precisávamos de uma fonte de dados confiável. No entanto, instituições governamentais (como a FINEP) muitas vezes não disponibilizam APIs públicas, abertas e documentadas (Open Data REST APIs) para consumo de terceiros. 

As opções disponíveis eram:
1. Cadastrar os editais manualmente (inviável para escala).
2. Aguardar ou solicitar a criação de uma API pública oficial (tempo indeterminado, inviável para o MVP).
3. Utilizar **Web Scraping** (raspar o HTML da página) e engenharia reversa para consumir a API interna não-documentada do site da FINEP (Liferay).

## Decisão
Decidimos adotar a **Opção 3 (Web Scraping e Consumo de API Interna)** como o motor principal de aquisição de dados do projeto.

## Justificativa (Trade-off)
Essa decisão carrega um risco técnico alto e muito conhecido no mercado: **se a estrutura do site fonte (HTML, classes CSS ou endpoints da API interna) mudar, o nosso robô quebra  e para de funcionar até que seja feita uma manutenção.**

Ainda assim, a escolha se justifica pelos seguintes motivos:
1. **Time-to-Market (Agilidade):** Precisávamos validar o MVP (Minimum Viable Product) e demonstrar valor imediatamente. O Web Scraping nos permite contornar a burocracia e obter os dados em tempo real, hoje, sem depender da boa vontade ou do cronograma de TI de órgãos governamentais.
2. **Custo-Benefício:** O custo de manutenção (ajustar os seletores CSS no robô caso a FINEP atualize o site no futuro) é muito menor do que o custo de não ter o produto funcionando ou depender de inserção manual de dados. Geralmente, portais de editais públicos demoram anos para passar por reformulações estruturais severas.
3. **Resiliência Parcial:** Utilizar a API interna do Liferay (mesmo precisando burlar o CORS/Cookies com o Browsershot) é ligeiramente mais estável do que raspar apenas o DOM visual, pois as respostas em JSON da API interna tendem a mudar com menos frequência do que a estrutura visual das divs HTML.

## Consequências
- **Positivas:** O sistema está autônomo, populando a base de dados automaticamente com editais reais e atualizados, permitindo que a IA trabalhe e que o valor do software seja comprovado.
- **Negativas (Risco Adquirido):** Assumimos uma Dívida Técnica (Technical Debt) intencional. A equipe tem ciência de que será necessário monitorar ativamente a execução do robô (ex: alertas de falha no Laravel Horizon ou logs) para agir rapidamente com manutenções preventivas caso a FINEP altere a estrutura do portal de oportunidades.
