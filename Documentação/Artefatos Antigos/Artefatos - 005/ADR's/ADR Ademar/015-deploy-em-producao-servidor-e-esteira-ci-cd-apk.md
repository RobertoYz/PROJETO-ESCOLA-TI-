# ADR 015: Arquitetura de Deploy em Produção (Apache/cPanel), Roteamento Unificado e Pipeline CI/CD para APK Mobile

## Status
Aceito

## Contexto (Jira EDTAN-209: Subir a aplicação-deploy servidor)
Com o amadurecimento dos módulos de Backend (Laravel 12), Frontend Desacoplado (Vanilla JS) e Canal Mobile (Capacitor 7), fez-se necessário realizar a transição do ambiente de desenvolvimento local (XAMPP/localhost) para o ambiente de produção em nuvem corporativa (HostGator / cPanel em `http://anjonexus.intalenti.com.br`).

Durante o processo de publicação e validação em homologação e produção, surgiram os seguintes desafios de infraestrutura e engenharia:
1. **Roteamento e Servidor Web Apache:** O Laravel exige que requisições RESTful (`/api/*`) sejam direcionadas ao seu kernel HTTP (`public/index.php`), enquanto o frontend desacoplado possui páginas estáticas e scripts SPA (`paginas/*.html`, `scripts/*.js`). Sem uma reescrita correta de URLs no `.htaccess`, o Apache retornava HTTP 404 em chamadas como `POST /api/auth/login`.
2. **Exceção de Rota de Autenticação (`RouteNotFoundException`):** O middleware `auth:sanctum` do Laravel, quando acionado por clientes externos sem cabeçalho `Accept: application/json`, tentava redirecionar para a rota de login padrão da web (`route('login')`), que não estava formalmente nomeada na API desacoplada, provocando erro HTTP 500 nos logs do servidor.
3. **Automação do Motor de Spiders de Editais:** O ecossistema AnjoNexus depende da captura contínua de oportunidades (FINEP, FAPESP, FAPESC). Era mandatório estabelecer rotinas agendadas (Cron Jobs no Linux) para invocar o agendador do framework sem intervenção manual de operadores.
4. **Compilação e Distribuição do Aplicativo Mobile Nativo:** O desenvolvimento local em estações de trabalho sem o Android Studio ou com restrições de memória inviabilizava a compilação ágil do arquivo binário `.apk`. Era necessário prover um pipeline de Integração Contínua (CI/CD) em nuvem para gerar o pacote executável e disponibilizá-lo para download direto no smartphone dos avaliadores da banca.

---

## Decisão

Implementamos um conjunto arquitetural de deploy unificado, contingência e pipeline automatizado composto por:

### 1. Roteamento Apache Unificado com `.htaccess` Especializado
No diretório raiz da aplicação na hospedagem (`public_html` / `anjonexus.intalenti.com.br`), configuramos regras de reescrita mod_rewrite no `.htaccess`:
- Arquivos estáticos existentes (HTML, CSS, JS, imagens, PDFs) são servidos diretamente pelo servidor web com baixa latência e compressão gzip.
- Todas as rotas de API (`/api/.*`) e requisições dinâmicas são reescritas transparentemente para o `index.php` do Laravel, assegurando a coexistência harmônica entre frontend desacoplado e backend RESTful.

### 2. Resolução da Rota Nomeada de Autenticação na API
No arquivo `routes/api.php`, definimos explicitamente a rota nomeada `login`:
```php
Route::get('/auth/login', function () {
    return response()->json([
        'sucesso' => false,
        'mensagem' => 'Não autenticado. Por favor, efetue o login via POST /api/auth/login.',
        'codigo' => 401
    ], 401);
})->name('login');
```
Isso garante conformidade total com o contrato RESTful e elimina qualquer tentativa do framework de renderizar views HTML Blade inexistentes.

### 3. Agendamento de Spiders via Cron Job do Servidor
Configuramos no painel cPanel da HostGator o acionador automático executado a cada minuto:
```bash
* * * * * cd /home2/intale75/anjonexus.intalenti.com.br && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```
O agendador executa os spiders de FAPESC, FAPESP e FINEP de acordo com as janelas temporais estipuladas no `routes/console.php`.

### 4. Pipeline de CI/CD no GitHub Actions para Build do APK Android
Criamos o workflow automatizado `.github/workflows/build-apk.yml`, operando em container Ubuntu com:
- **Node.js 20:** Instala dependências do Capacitor e sincroniza os assets web compilados (`npx @capacitor/cli sync android`).
- **Java JDK 21 (Zulu):** Alinhado aos padrões oficiais exigidos pelo Capacitor 7 e Android Gradle Plugin 8.7.2.
- **Android SDK 35 & Gradle 8.11:** Compilação do projeto nativo via `./gradlew assembleDebug --no-daemon --stacktrace`.
- **Upload e Disponibilização de Artefatos:** Publicação direta do arquivo `anjonexus-mobile-apk` (`app-debug.apk` de ~3.57 MB) acessível por link permanente e versionado.

### 5. Página de Onboarding e Download Mobile (`baixar-app.html`)
Desenvolvemos uma página pública de distribuição (`/paginas/baixar-app.html`) contendo:
- QR Code dinâmico para acesso instantâneo via câmera do celular.
- Botão direto para instalação e abertura como Progressive Web App (PWA).
- Botão de download direto do binário nativo (`/downloads/anjonexus.apk`).

---

## Justificativa Acadêmica (TCC / Defesa de Banca)

1. **Aderência às Regras Acadêmicas e Autoria Própria:** O pipeline de CI/CD no GitHub Actions compila código 100% autoral escrito pela equipe (Vanilla JS, HTML5 e CSS3 no frontend; PHP 8.2 e Laravel 12 no backend). O Gradle e o GitHub Actions são ferramentas de automação de compilação (*build tool* e *CI/CD*), amplamente adotadas na indústria e permitidas por não gerarem código ou lógica sintética.
2. **Alta Disponibilidade e Custo Zero de Infraestrutura:** A utilização do GitHub Actions (camada gratuita de 2.000 minutos/mês para repositórios públicos) eliminou a necessidade de servidores dedicados de compilação (Jenkins) ou computadores de alta performance locais.
3. **Experiência de Usuário e Apresentação para a Banca:** A disponibilização de um link direto e QR Code para baixar o app nativo no celular permite que a banca examinadora teste a aplicação em tempo real em seus próprios smartphones durante a apresentação.

---

## Consequências

### Positivas:
- Publicação estável em servidor de produção real com HTTPS e subdomínio próprio (`anjonexus.intalenti.com.br`).
- Ciclo de build do aplicativo Android 100% reproduzível, auditável e sem dependência do ambiente local de nenhum desenvolvedor.
- Resolução definitiva de erros de cabeçalho de autenticação e rotas 404 em produção.
- Execução contínua e autônoma dos spiders de editais governamentais.

### Negativas / Mitigações:
- Arquivos `.apk` em modo Debug exigem autorização de "instalação de fontes desconhecidas" no Android. Essa limitação é padrão para builds de desenvolvimento e homologação acadêmica; a versão para Google Play Store no futuro apenas exigirá a assinatura com chave privada (*Keystore Release*).
