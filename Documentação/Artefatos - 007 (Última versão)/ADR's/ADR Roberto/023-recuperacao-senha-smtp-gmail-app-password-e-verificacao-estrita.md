# ADR 023: Disparo de E-mail de Recuperação de Senha via SMTP Autenticado (Gmail App Password) e Verificação Estrita em `AuthController@solicitarRecuperacaoSenha`

## Status
Aceito

## Contexto
Para implementar no Back-end o módulo de Recuperação de Senha (`EDTAN-156` — `RF-05`, `RF-06`, `UC-05`), precisávamos substituir o driver padrão de desenvolvimento do Laravel (`MAIL_MAILER=log`, que apenas gravava o texto do e-mail em `storage/logs/laravel.log`) por um disparo SMTP real via internet. Além disso, era requisito de negócio que o Back-end **só disparasse e-mails para endereços efetivamente cadastrados na tabela `users`**, barrando imediatamente qualquer e-mail não registrado.

## Decisão
Decidimos configurar o subsistema de e-mails do Laravel em `back/.env` e `back/.env.example` utilizando o servidor **SMTP do Google (`smtp.gmail.com`, porta `465`, criptografia `ssl`) autenticado via Senha de Aplicativo (`MAIL_PASSWORD`)** da conta oficial `projetoanjonexus@gmail.com`, e implementar o método `solicitarRecuperacaoSenha(Request $requisicao)` em `app/Http/Controllers/AuthController.php` (`POST /api/auth/esqueci-senha`) com verificação estrita de existência do usuário.

## Justificativa
1. **Por que utilizar SMTP do Gmail na porta `465` (`ssl`) com Senha de Aplicativo (`App Password`):** O Google bloqueia conexões SMTP que utilizem a senha convencional da conta quando a autenticação de dois fatores (2FA) está ativa. O uso de uma Senha de Aplicativo de 16 caracteres dedicada ao Back-end permite estabelecer conexão criptografada direta via SSL na porta `465` com alta entregabilidade na caixa de entrada, sem depender de serviços pagos de terceiros.
2. **Por que implementar a verificação estrita (`User::where('email', $email)->first()`) retornando HTTP `404` antes do disparo SMTP:**
   ```php
   $email = strtolower(trim($requisicao->email));
   $usuario = User::where('email', $email)->first();
   if (!$usuario) {
       return response()->json([
           'mensagem' => 'Nenhuma conta foi encontrada com este endereço de e-mail. Verifique o e-mail digitado ou crie uma conta.',
       ], 404);
   }
   ```
   Se o endereço informado não existir na tabela `users`, o Back-end interrompe o processamento imediatamente com HTTP `404`. Isso protege a cota diária de envios do servidor SMTP contra disparos inúteis para e-mails inexistentes e informa com exatidão que não há conta associada àquele endereço.
3. **Por que usar `Mail::html($htmlEmail, ...)` dentro de um bloco `try / catch (\Exception $erro)`:** O método `Mail::html` da facade `Illuminate\Support\Facades\Mail` permite injetar diretamente o template HTML estilizado inline sem precisar criar múltiplas classes *Mailable* e arquivos Blade para um único e-mail transacional, enquanto o bloco `try / catch` captura eventuais falhas de handshake SMTP e devolve uma mensagem JSON estruturada (HTTP `500`) em vez de um stack trace bruto.

## Consequências
- Envio real e instantâneo do link de recuperação de senha para a caixa de entrada do usuário.
- Proteção do servidor SMTP contra envios para contas não cadastradas.

