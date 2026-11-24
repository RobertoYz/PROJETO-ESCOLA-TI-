<!doctype html>
<html lang="pt-BR">
<body style="background:#eef5fc;color:#173854;font:16px/1.5 Arial,sans-serif;padding:24px">
    <main style="max-width:480px;margin:auto;background:white;padding:24px;border-radius:12px">
        <p>Anjo Nexus</p>
        <h1 style="font-size:24px">Confirme seu e-mail</h1>
        <p>Olá{{ $nome ? ', '.$nome : '' }}! Digite este código na tela de cadastro.</p>
        <p style="color:#205790;font-size:32px;font-weight:bold;letter-spacing:5px">{{ $codigo }}</p>
        <p>O código é válido por 15 minutos. Se você não solicitou este cadastro, ignore esta mensagem.</p>
    </main>
</body>
</html>
