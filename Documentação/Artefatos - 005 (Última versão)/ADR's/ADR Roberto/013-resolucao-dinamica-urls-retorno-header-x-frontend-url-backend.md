# ADR 013: Parser Dinâmico de URLs de Retorno no Back-end (`AbacatePayService` e `AuthController`) via Cabeçalho `X-Frontend-Url`

## Status
Aceito

## Contexto
No Back-end, dois serviços precisam construir URLs completas apontando de volta para páginas do cliente:
1. O `AbacatePayService@gerarCobranca` precisa enviar ao AbacatePay os campos obrigatórios `returnUrl` e `completionUrl` apontando para `login.html`;
2. O `AuthController@solicitarRecuperacaoSenha` precisa montar a variável `$linkRedefinicao` no corpo do e-mail apontando para `redefinir-senha.html?token=...&email=...`.
O problema (`EDTAN-208` e `EDTAN-156`) era que depender apenas de `env('FRONT_URL')` ou do cabeçalho HTTP nativo `Referer` (`request()->header('referer')`) falhava no Back-end: os navegadores modernos aplicam a política `Referrer-Policy: strict-origin-when-cross-origin`, que trunca o caminho completo das subpastas da requisição e envia apenas a origem raiz (ex: `http://127.0.0.1:5500/`), causando erro `404` quando o gateway redirecionava o usuário de volta.

## Decisão
Decidimos implementar no Back-end (`AbacatePayService.php` e `AuthController.php`) um algoritmo de resolução dinâmica de URL que lê prioritariamente o cabeçalho customizado `request()->header('X-Frontend-Url')` (com fallback em cascata para `X-Referer`, `Referer` e `FRONT_URL`), analisa a estrutura via `parse_url()` / `strpos(..., '/paginas/')` e reconstrói o caminho exato até o diretório `/paginas/`.

## Justificativa
1. **Por que priorizar `request()->header('X-Frontend-Url')` sobre `Referer` no PHP:** Cabeçalhos customizados (`X-*`) não sofrem sanitização nem truncamento de `path` pela política de `Referrer-Policy` dos navegadores. Assim, o PHP recebe a string exata da URL onde o usuário estava (incluindo subpastas com espaços codificados como `%20` ou acentos como `%C3%A3`).
2. **Por que usar `parse_url($origemRequisicao)` e `strpos($caminho, '/paginas/')` em `AbacatePayService.php`:**
   ```php
   $urlAnalisada = parse_url($origemRequisicao);
   $host = ($urlAnalisada['scheme'] ?? 'http') . '://' . ($urlAnalisada['host'] ?? 'localhost') . (isset($urlAnalisada['port']) ? ':' . $urlAnalisada['port'] : '');
   $caminho = $urlAnalisada['path'] ?? '';
   if (str_contains($caminho, '/paginas/')) {
       $prefixo = substr($caminho, 0, strpos($caminho, '/paginas/'));
       $urlBaseFront = rtrim($host . $prefixo, '/');
   }
   $urlLogin = rtrim($urlBaseFront, '/') . '/paginas/login.html';
   ```
   Ao localizar a posição da substring `'/paginas/'` e recortar o prefixo anterior (`substr`), o Back-end descobre dinamicamente a raiz do cliente em qualquer servidor (Apache/XAMPP, Live Server ou domínio de produção) sem depender de variáveis fixas no `.env`.
3. **Por que aplicar a mesma lógica em `AuthController@solicitarRecuperacaoSenha`:** Ao extrair `$urlBasePaginas = substr($urlOrigemFront, 0, strpos($urlOrigemFront, '/paginas/') + 9)` e concatenar `'redefinir-senha.html?token=' . urlencode($tokenPuro) . '&email=' . urlencode($usuario->email)`, o Back-end garante que o link enviado por SMTP para o Gmail do usuário sempre abra a página correta sem erro `404`.

## Consequências
- Independência total de configuração manual de caminhos de pastas no `.env` do Back-end.
- Redirecionamento pós-pagamento (`returnUrl` / `completionUrl`) e links de e-mail de recuperação de senha funcionando sem erro `404` em qualquer ambiente.

