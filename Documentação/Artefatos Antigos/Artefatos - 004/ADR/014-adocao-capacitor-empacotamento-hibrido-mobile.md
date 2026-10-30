# ADR 014: Adoção do Capacitor para Empacotamento Híbrido Mobile e Desacoplamento de Clientes

## Status
Aceito

## Contexto (Jira EDTAN-178)
Na esteira de evolução do ecossistema B2B AnjoNexus, surgiu a demanda pelo desenvolvimento do canal mobile para atender aos seguintes requisitos do projeto:
- **RF-28 (App Mobile para Visualização):** Permitir que proponentes e fundadores de startups acompanhem oportunidades de fomento (Radar de Editais) e o andamento do pipeline Kanban em dispositivos móveis.
- **RF-47 (Notificações e Alertas Mobile):** Enviar alertas em tempo real sobre prazos urgentes, editais recém-publicados e transições de fase de propostas.
- **RNF-06 (Design Responsivo Mobile/Desktop):** Garantir interface ergonômica em smartphones, com suporte a gestos de toque, safe areas e performance nativa.

Surgiram os seguintes dilemas arquiteturais para a equipe:
1. **Reescrita vs Reaproveitamento:** Reescrever toda a aplicação do zero em um framework mobile opinativo (React Native ou Flutter) ou adotar uma abordagem de empacotamento híbrido moderno?
2. **Curva de Aprendizado e Sobrecarga de Dependências:** Como entregar um aplicativo de alta qualidade mantendo a linguagem da equipe (HTML5, CSS3 e Vanilla JS) sem impor pipelines pesados de build ou duplicidade de regras de negócio?
3. **Comunicação com a API Backend:** Como viabilizar a comunicação transparente da WebView do Android com a API RESTful do Laravel, considerando as peculiaridades de rede de emuladores (`10.0.2.2`) e dispositivos físicos via Wi-Fi?

---

## Decisão

Decidimos adotar o **Capacitor 7 (Ionic)** para encapsular a aplicação web em um pacote nativo Android (`.apk` / `.aab`), organizando o projeto em um diretório desacoplado (`MVPs/Versão Atual/mobile`).

### 1. Separação Estrita de Diretórios (`mobile/` vs `front/` vs `back/`)
Em vez de misturar artefatos de compilação mobile dentro da pasta `front`, isolamos o módulo em `MVPs/Versão Atual/mobile/`:
- **`www/`:** Single Page Application (SPA) mobile otimizada com Bottom Navigation Bar, Safe Area insets e cards touch-friendly.
- **`android/`:** Projeto Gradle nativo com suporte a `AndroidManifest.xml`, ícones e configurações do Android SDK.
- **`capacitor.config.json`:** Metadados da aplicação (`appId`: `br.gov.escola.anjonexus`).

### 2. Plugins Nativos Selecionados
Adotamos os plugins oficiais da biblioteca do Capacitor:
- **`@capacitor/app`:** Tratamento inteligente do botão físico "Voltar" (*hardware back button*) do Android, fechando modais e confirmando saída da aplicação.
- **`@capacitor/status-bar`:** Customização da barra de status com o tema dark mode institucional (`#0b0f19`).
- **`@capacitor/splash-screen`:** Exibição suave de tela de carregamento integrada à identidade visual.
- **`@capacitor/preferences`:** Armazenamento local seguro para tokens de autenticação (Sanctum) e seleção de startup ativa.

### 3. Configuração Dinâmica de Host da API
Implementamos no cliente `api.js` um mecanismo de resolução adaptativa de endereço do backend:
- Emulador Android: mapeamento padrão para `http://10.0.2.2:8000/api`.
- Dispositivo Físico / Wi-Fi: seletor de IP em tempo real acessível pelo app (modal de configurações), facilitando apresentações e validações na banca avaliadora.
- Web Browser / Desenvolvimento: fallback automático para `http://127.0.0.1:8000/api`.

---

## Justificativa Acadêmica (TCC / Defesa de Banca)

1. **Aderência ao Desacoplamento RESTful:** A existência de um cliente Web Desktop (`front`) e um cliente Mobile (`mobile`) consumindo a mesma API Laravel (`back`) comprova na prática que a arquitetura do backend é agnóstica e atende plenamente ao princípio da separação de responsabilidades.
2. **Custo Zero e Eficiência de Recursos:** O Capacitor evita a necessidade de reescrever lógica e telas já validadas, mitigando o risco de prazos e reduzindo o débito técnico da equipe.
3. **Padrão PWA/Híbrido Consagrado:** O uso de WebViews aceleradas por hardware com Capacitor é adotado em larga escala na indústria de software por grandes corporações (ex: apps corporativos da AWS, Target, Southwest Airlines).

---

## Consequências

### Positivas:
- Entrega rápida do requisito RF-28 e RF-47 sem desestabilizar o frontend desktop existente.
- Compatibilidade nativa com ecossistema Android e facilidade de geração de builds via Gradle / Android Studio.
- Interface ergonômica pensada exclusivamente para o uso com uma mão (Bottom Navigation).

### Negativas / Limitações Futuras:
- Recursos que demandem processamento gráfico intensivo (ex: jogos 3D ou IA com visão computacional pesada no device) exigem módulos nativos em Kotlin/Java. Para o propósito de dashboards, editais e pipelines, o impacto na performance é desprezível.
