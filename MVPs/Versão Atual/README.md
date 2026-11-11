# Projeto Anjo Nexus - Escola de TI
# ⚡ Quick Start — Anjo Nexus (Versão Atual)

Guia rápido de inicialização direta para desenvolvimento diário. Para explicações detalhadas de arquitetura e dependências, consulte o [README da pasta MVPs](../README.md).

---

## 1. Pré-configuração Rápida

1. **XAMPP:** Inicie o **Apache** e o **MySQL** e garanta que o banco `anjo_nexus` exista no `http://localhost/phpmyadmin`.
2. **Arquivo `.env`:** Solicite o arquivo `.env` completo com os membros da equipe e cole dentro da pasta `/back` (ou copie o `.env.example` e preencha `ABACATEPAY_API_KEY`, `ABACATEPAY_WEBHOOK_SECRET`, `GROQ_API_KEY`, `DEEPSEEK_API_KEY` e `GEMINI_API_KEY`).

---

## 2. Instalação e Banco de Dados (`/back`)

Abra o terminal na pasta `back` e execute na sequência:

~~~bash
cd back
composer install
npm install puppeteer
php artisan key:generate
php artisan migrate:fresh --seed
~~~

---

## 3. Terminais de Execução

Para rodar o ecossistema completo, utilize os comandos abaixo em terminais separados (dentro de `/back`):

### Terminal 1: API Laravel (`http://127.0.0.1:8000`)
~~~bash
php artisan serve
~~~

### Terminal 2: Fila de Processamento e IA
~~~bash
php artisan queue:work
~~~

### Terminal 3: Túnel Webhook AbacatePay (ngrok)
~~~bash
# Apenas na 1ª vez na máquina:
ngrok config add-authtoken <TOKEN_DA_EQUIPE>

# Iniciar o túnel fixo:
ngrok http --domain=esophagus-swifter-cultivate.ngrok-free.dev 8000
~~~

### Terminal 4: Robôs de Captura de Editais (Opcional)
~~~bash
php artisan spider:fapesc
php artisan spider:fapesp
php artisan roach:run FinepSpider
~~~

---

## 4. Inicialização do Front-end (`/front`)

1. No VS Code, navegue até `front/paginas/cadastro.html` ou `front/paginas/login.html`.
2. Clique com o botão direito no arquivo e selecione **"Open with Live Server"**.

---

## 5. Compilação Mobile com Capacitor (Geração de APK / Android)

O aplicativo móvel do Anjo Nexus utiliza o **Capacitor** para empacotar o mesmo frontend Vanilla JS/CSS dentro de uma **WebView nativa de alto desempenho**, conectando-se diretamente às APIs nativas de notificações do Android/iOS.

### 5.1 Como funciona a compilação na prática:
1. **Ponte Nativa (JavaScript Bridge):** O Capacitor não reescreve seu código; ele cria um projeto nativo Android (`/android`) e hospeda seus arquivos estáticos em `android/app/src/main/assets/public`.
2. **Notificações na Barra do Celular:** Plugins como `@capacitor/local-notifications` convertem chamadas JavaScript em eventos reais do sistema operacional (`NotificationManagerCompat`), fazendo o smartphone vibrar e exibir alertas mesmo com a tela bloqueada.

### 5.2 Passo a passo para gerar o APK:

1. **Instalar dependências do Capacitor (na pasta `/front`):**
   ~~~bash
   cd front
   npm install @capacitor/core @capacitor/cli @capacitor/android @capacitor/local-notifications
   ~~~

2. **Inicializar o projeto Capacitor (apenas na 1ª vez):**
   ~~~bash
   npx cap init "Anjo Nexus" "com.anjonexus.app" --web-dir .
   npx cap add android
   ~~~

3. **Sincronizar arquivos do frontend para o Android:**
   Sempre que alterar códigos HTML, CSS ou JS, rode:
   ~~~bash
   npx cap sync
   ~~~

4. **Compilar e Rodar o APK:**
   - **Opção A (Via Android Studio):**
     ~~~bash
     npx cap open android
     ~~~
     *(No Android Studio, clique em `Build > Build APK(s)` ou aperte `Play` com o celular/emulador conectado).*
   
   - **Opção B (Linha de comando direta via Gradle):**
     ~~~bash
     cd android
     ./gradlew assembleDebug
     ~~~
     *O arquivo instalável será gerado em: `android/app/build/outputs/apk/debug/app-debug.apk`.*
