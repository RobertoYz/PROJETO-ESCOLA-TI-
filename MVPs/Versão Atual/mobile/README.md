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
