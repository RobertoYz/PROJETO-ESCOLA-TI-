# Projeto Anjo Nexus - Escola de TI
# ⚡ Quick Start — Anjo Nexus (Versão Atual)

Guia rápido de inicialização direta para desenvolvimento diário. Para explicações detalhadas de arquitetura e dependências, consulte o [README da pasta MVPs](../README.md).

---

## 1. Pré-configuração Rápida

1. **XAMPP:** Inicie o **Apache** e o **MySQL** e garanta que o banco `anjo_nexus` exista no `http://localhost/phpmyadmin`.
2. **Arquivo `.env`:** Solicite o arquivo `.env` completo com os membros da equipe e cole dentro da pasta `/back` (ou copie o `.env.example` e preencha `ABACATEPAY_API_KEY`, `ABACATEPAY_WEBHOOK_SECRET`, `GROQ_API_KEY`, `DEEPSEEK_API_KEY` e `GEMINI_API_KEY`).

1.1. **Arquivo .env:** Solicite o arquivo .env completo com os membros da equipe e cole dentro da pasta /back (ou copie o .env.example e preencha as chaves do AbacatePay, chaves das IAs e o bloco SMTP do Gmail com uma Senha de App de 16 dígitos em MAIL_PASSWORD para a recuperação de senha)

---

## 2. Instalação e Banco de Dados (`/back`)

Abra o terminal na pasta `back` e execute na sequência:

```bash
cd back
composer install
npm install puppeteer
php artisan key:generate
php artisan migrate:fresh --seed
```

---

## 3. Terminais de Execução

Para rodar o ecossistema completo, utilize os comandos abaixo em terminais separados (dentro de `/back`):

### Terminal 1: API Laravel (`[http://127.0.0.1:8000](http://127.0.0.1:8000)`)
```bash
php artisan serve
```

### Terminal 2: Fila de Processamento e IA
```bash
php artisan queue:work
```

### Terminal 3: Túnel Webhook AbacatePay (ngrok)
```bash
# Apenas na 1ª vez na máquina:
ngrok config add-authtoken <TOKEN_DA_EQUIPE>

# Iniciar o túnel fixo:
ngrok http --domain=esophagus-swifter-cultivate.ngrok-free.dev 8000
```

### Terminal 4: Robôs de Captura de Editais (Opcional)
```bash
php artisan spider:fapesc
php artisan spider:fapesp
php artisan roach:run FinepSpider
```

---

## 4. Inicialização do Front-end (`/front`)

> ⚠️ **IMPORTANTE (VS Code):** Para o Live Server funcionar sem dar *reload* infinito na página, você **deve abrir a pasta raiz exata** no VS Code. Vá em *File > Open Folder* e selecione a pasta **`PROJETO-ESCOLA-TI-`** (isso ativa as configurações que ignoram o backend).

1. Com a pasta correta aberta no VS Code, navegue até `front/paginas/cadastro.html` ou `front/paginas/login.html`.
2. Clique com o botão direito no arquivo e selecione **"Open with Live Server"**.