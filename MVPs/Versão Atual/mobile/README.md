# 📱 AnjoNexus Mobile — Aplicativo Híbrido (Capacitor)
## Módulo de Visualização de Editais e Notificações (Jira EDTAN-178)

> **Requisitos Vinculados:**  
> - **RF-28 (App Mobile para Visualização):** Foco em consulta de editais, radar inteligente e acompanhamento do status no pipeline Kanban.  
> - **RF-47 (Notificações e Alertas):** Recebimento de alertas de novos editais, prazos de submissão e andamentos processuais.  
> - **RNF-06 (Design Responsivo Mobile/Desktop):** Interface ergonômica com Bottom Navigation, Safe Area para entalhes/notches e suporte a toque nativo.  
> - **ADR 014:** Adoção do ecossistema Capacitor para empacotamento híbrido com desacoplamento RESTful.

---

### 🏛️ 1. Arquitetura do Módulo

O aplicativo mobile foi desenvolvido utilizando a tecnologia **Capacitor 7** da Ionic, empacotando uma Single Page Application (SPA) em Vanilla JS + HTML5 + CSS3 moderna:

```
mobile/
├── android/                   # Projeto nativo gerado pelo Capacitor (Gradle/Android Studio)
├── www/                       # Código-fonte da aplicação mobile (Assets Web)
│   ├── index.html             # Shell da SPA mobile com Bottom Navigation e Safe Area
│   ├── css/
│   │   └── mobile.css         # Design System Dark Mode, animações touch e sheets
│   └── js/
│       ├── api.js             # Cliente HTTP com detecção de IP (10.0.2.2 vs Localhost)
│       └── app.js             # Controlador de abas (Radar, Pipeline, Alertas, Perfil)
├── capacitor.config.json      # Configurações do Capacitor (App ID, Splash, StatusBar)
├── package.json               # Dependências do Capacitor (@capacitor/core, @capacitor/android)
└── README.md                  # Este manual operacional
```

---

### 🚀 2. Como Executar e Testar

#### A. No Navegador Web (Modo Rápido / Live Server):
1. Abra o arquivo `mobile/www/index.html` com o **Live Server** do VS Code ou execute:
   ```bash
   cd "MVPs/Versão Atual/mobile"
   npx serve www -p 3000
   ```
2. Abra `http://localhost:3000` e ative o modo responsivo do navegador (`F12` -> Alternar barra de ferramentas do dispositivo).

#### B. No Emulador Android ou Dispositivo Físico:
1. Certifique-se de que as dependências estão sincronizadas:
   ```bash
   cd "MVPs/Versão Atual/mobile"
   npm run cap:sync
   ```
2. Abra o projeto nativo no Android Studio:
   ```bash
   npm run cap:open
   ```
3. No Android Studio, clique em **Run 'app'** para instalar no seu emulador ou celular conectado via USB.

---

### 🌐 3. Configuração de IP da API para a Banca Avaliadora

Para permitir que a banca avaliadora teste o app no celular físico conectado no mesmo Wi-Fi ou no emulador Android:
- **No Emulador Android:** Aponte para `http://10.0.2.2:8000/api` (o emulador mapeia o `127.0.0.1` do PC host para `10.0.2.2`).
- **No Celular Físico (Wi-Fi):** Aponte para `http://<IP_DO_SEU_PC>:8000/api` (ex: `http://192.168.1.15:8000/api`).
- **Pelo próprio App:** Clique no ícone de **Engrenagem** no topo da tela do app ou na aba **Perfil** -> *Alterar IP do Servidor API*. O app permite testar e salvar o IP em tempo real sem precisar recompilar o código!

---

### 📦 4. Guia Passo a Passo: Gerando e Distribuindo o APK em Produção

Quando a aplicação for promovida para o ambiente de produção, siga o roteiro abaixo para gerar o arquivo `.apk` final e distribuí-lo aos usuários:

#### Etapa 1 — Ajustar a URL Base da API para o Servidor de Produção
No arquivo `mobile/www/js/api.js`, certifique-se de que a URL de produção aponta para o domínio oficial em HTTPS:
```javascript
function getDefaultApiUrl() {
  const isCapacitorAndroid = window.Capacitor && window.Capacitor.getPlatform() === 'android';
  if (isCapacitorAndroid) {
    // URL do servidor de produção na nuvem:
    return 'https://api.anjonexus.com.br/api';
  }
  return 'https://api.anjonexus.com.br/api';
}
```
Em seguida, sincronize as alterações para a pasta nativa do Android:
```bash
cd "MVPs/Versão Atual/mobile"
npm run cap:sync
```

#### Etapa 2 — Gerar o APK Assinado (Signed APK) no Android Studio
1. Abra o projeto no Android Studio:
   ```bash
   npm run cap:open
   ```
2. No menu superior, navegue em:  
   👉 **Build** > **Generate Signed Bundle / APK...**
3. Escolha a opção **APK** (ou **Android App Bundle** se for publicar na Play Store) e clique em **Next**.
4. **Chave de Assinatura Digital (*Keystore*):**
   - Se for o primeiro build, clique em **Create new...** e preencha:
     - *Key store path:* `caminho/anjonexus-release-key.jks`
     - *Password:* defina uma senha forte para a chave.
     - *Alias:* `anjonexus`
     - *Validity:* `25` anos.
     - *Certificate:* Nome da equipe/empresa (ex: AnjoNexus Tecnologia B2B).
5. Selecione a variante de compilação **`release`**.
6. Marque as versões de assinatura:
   - ✅ **V1 (Jar Signature)**
   - ✅ **V2 (Full APK Signature)**
7. Clique em **Finish**. O Gradle compilará os arquivos otimizados e minificados.

📍 **Localização do APK gerado:**  
O arquivo final assinado estará em:  
`mobile/android/app/release/app-release.apk`

---

#### Etapa 3 — Alternativa Via Linha de Comando (Gradle CLI / CI-CD)
Se preferir gerar o APK sem abrir a interface do Android Studio:
```bash
cd "MVPs/Versão Atual/mobile/android"

# No Windows (PowerShell):
.\gradlew.bat assembleRelease

# No Linux/macOS:
./gradlew assembleRelease
```
O arquivo será gerado em: `android/app/build/outputs/apk/release/`.

---

#### Etapa 4 — Como o Usuário Final Baixa o APK em Produção?

1. **Canal Direto B2B (Download via Plataforma Web / Landing Page):**
   - Renomeie o arquivo `app-release.apk` para `anjonexus-radar-editais.apk`.
   - Armazene o arquivo na pasta de downloads públicos do servidor (ex: `/var/www/public/downloads/anjonexus-radar-editais.apk`) ou crie uma **Release no repositório GitHub** anexando o `.apk`.
   - No portal web ou no e-mail de boas-vindas do SaaS, adicione o botão:
     ```html
     <a href="https://anjonexus.com.br/downloads/anjonexus-radar-editais.apk" class="btn-download-app">
       📲 Baixar App Android (.APK)
     </a>
     ```
   - O usuário acessa o link pelo celular, clica em "Baixar", confirma a instalação de fontes da organização e o app é instalado imediatamente.

2. **Canal Oficial (Google Play Store):**
   - Na Etapa 2, selecione **Android App Bundle (`.aab`)**.
   - Acesse o console de desenvolvedor do Google Play ([play.google.com/console](https://play.google.com/console)).
   - Faça o upload do arquivo `.aab` na esteira de Produção ou Teste Fechado.
   - O aplicativo fica acessível publicamente via link da Google Play Store para qualquer usuário Android.

