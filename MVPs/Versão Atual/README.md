# Projeto Anjo Nexus - Escola de TI
# ⚡ Quick Start — Anjo Nexus (Versão Atual)

Guia rápido de inicialização direta para desenvolvimento diário. Para explicações detalhadas de arquitetura e dependências, consulte o [README da pasta MVPs](../README.md).

---

## ⚠️ AVISO IMPORTANTE: Solicitação dos Arquivos `.env` e `.env.docker`

Por segurança, os arquivos de variáveis de ambiente com as chaves reais não ficam públicos no GitHub:
- **`.env` (na pasta `back/.env`):** Usado para rodar o sistema **manualmente pelo terminal + XAMPP**.
- **`.env.docker` (nesta pasta `MVPs/Versão Atual/.env.docker`):** Usado para rodar o sistema **automaticamente via Docker Compose**.

> 💡 **Para Professores, Avaliadores e Colegas de Equipe:** Solicite os arquivos **`.env`** e **`.env.docker`** completos e já configurados diretamente com os membros da equipe (**Ademar, Pedro ou Roberto**) para rodar o projeto de imediato sem precisar criar chaves manualmente.

---

## 🐳 MODO A: Como Rodar pelo Docker (Automático — 1 Comando)

Este modo sobe automaticamente o Banco de Dados MySQL (porta `3307`), roda as Migrations e Seeders, inicia a API Laravel (`8000`), o Agendador (`scheduler`), os Robôs de Editais (`spiders`) e o Front-end Nginx (`5500`) em um único comando.

### 1. Iniciar o Aplicativo do Docker no seu Computador
1. Abra o aplicativo **Docker Desktop** no seu computador.
2. Aguarde até que o motor do Docker finalize a inicialização (o ícone da baleia ficará estável e o status no canto inferior esquerdo mostrará **"Engine running"** em verde).

### 2. Configurar o Arquivo `.env.docker`
1. Caso tenha solicitado o arquivo **`.env.docker`** para a equipe, basta colá-lo dentro desta pasta (`MVPs/Versão Atual/.env.docker`).
2. Caso prefira criar a partir do exemplo, abra o terminal nesta pasta (`MVPs/Versão Atual/`) e faça uma cópia do arquivo `.env.docker.example`:
   ```bash
   # No Windows (PowerShell / CMD):
   copy .env.docker.example .env.docker

   # No Linux / macOS / Git Bash:
   cp .env.docker.example .env.docker
   ```
3. Se copiou do exemplo, abra o `.env.docker` e preencha as chaves do AbacatePay (`ABACATEPAY_API_KEY`, `ABACATEPAY_WEBHOOK_SECRET`), chaves das IAs (`GROQ_API_KEY`, `DEEPSEEK_API_KEY`, `GEMINI_API_KEY`), `MAIL_PASSWORD` (Senha de App de 16 dígitos do Gmail) e `NGROK_AUTHTOKEN`.

### 3. Comandos para Iniciar o Docker
Com o terminal aberto nesta pasta (`MVPs/Versão Atual/`), execute o comando padrão:

```bash
# COMANDO PADRÃO RECOMENDADO: Subida completa (MySQL + Migrations/Seeders + API + Scheduler + Spiders + Front-end)
docker compose --env-file .env.docker up --build
```

> 💡 **Importante sobre AbacatePay vs. Ngrok:**
> Você **NÃO precisa** do `--profile ngrok` (nem de `NGROK_AUTHTOKEN`) para testar o cadastro e o pagamento no AbacatePay!
> - Com o comando padrão acima (`docker compose --env-file .env.docker up --build`), o sistema já cria a cobrança PIX/Cartão normalmente na API do AbacatePay e, quando você clica para verificar/conciliar o pagamento na tela do sistema, o Back-end consulta diretamente o status `PAID` na API do AbacatePay (`GET /api/pagamentos/consultar-anterior`) e ativa a conta na hora sem depender de túnel Ngrok.
> - Só use `--profile ngrok` se quiser testar especificamente o recebimento passivo de Webhook externo no domínio estático `NGROK_DOMAIN` (`esophagus-swifter-cultivate.ngrok-free.dev`) — para isso, mantenha o `NGROK_AUTHTOKEN` preenchido no `.env.docker` e lembre-se de que o plano gratuito do Ngrok permite **apenas 1 túnel ativo por vez no mesmo domínio** (apenas quem estiver testando o webhook automático usa `--profile ngrok`).

- **Acessar o Front-end no navegador:** `http://localhost:5500/paginas/login.html` (ou `http://localhost:5500`)
- **Acessar a API Laravel:** `http://127.0.0.1:8000/api`
- **Comandos adicionais úteis:**
  ```bash
  # OPCIONAL: Subir incluindo também o túnel do Ngrok (exige NGROK_AUTHTOKEN preenchido no .env.docker):
  docker compose --env-file .env.docker --profile ngrok up --build

  # Rodar novamente os robôs extratores de editais quando desejar:
  docker compose --env-file .env.docker run --rm spiders

  # Parar os containers do Docker:
  docker compose down

  # Parar os containers e resetar completamente o volume do banco de dados:
  docker compose down -v
  ```

---

## 💻 MODO B: Como Rodar Manualmente pelo Terminal (XAMPP + Live Server)

## 1. Pré-configuração Rápida

1. **XAMPP:** Inicie o **Apache** e o **MySQL** e garanta que o banco `anjo_nexus` exista no `http://localhost/phpmyadmin`.
2. **Arquivo `.env` (e `.env.docker`):** Solicite o arquivo `.env` completo (e o `.env.docker`, caso vá testar via Docker) com os membros da equipe e cole o `.env` dentro da pasta `/back` (ou copie o `.env.example` e preencha `ABACATEPAY_API_KEY`, `ABACATEPAY_WEBHOOK_SECRET`, `GROQ_API_KEY`, `DEEPSEEK_API_KEY` e `GEMINI_API_KEY`).

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