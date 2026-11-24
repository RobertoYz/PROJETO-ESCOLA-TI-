# ADR 044: Confirmação de e-mail no cadastro com OTP e token descartável (`EDTAN-283`)

## Status

Implementado

## Data

Novembro de 2026

## Contexto e Problema (Jira EDTAN-283)

O cadastro precisa confirmar que o titular tem acesso à caixa postal antes da escolha do plano. Criar usuários e agências nessa etapa deixaria contas abandonadas e ampliaria a retenção de dados pessoais. Web e Mobile compartilham o wizard e a API precisa verificar cada etapa sem depender de uma sessão de navegador no servidor.

### Alternativas Consideradas e Rejeitadas

- Criar o usuário com `email_verified_at = null` anteciparia a persistência de nome, agência e senha.
- Uma flag no cliente poderia ser alterada por quem chama a API diretamente.
- Limitar o domínio a Gmail excluiria caixas corporativas e outros provedores legítimos.

---

## Decisão Arquitetural

1. O serviço normaliza o endereço e rejeita contas existentes. Cada e-mail tem um registro transitório único. O código aleatório de seis dígitos permanece válido por 15 minutos, com hash adaptativo, cinco tentativas e intervalo mínimo de 60 segundos entre envios. O reenvio substitui a verificação anterior.
2. Laravel Mail entrega o código por SMTP configurável. Gmail com STARTTLS na porta 587 é o exemplo de transporte. O destinatário pode pertencer a qualquer domínio válido.
3. A confirmação apaga o OTP e devolve ao cliente um `verification_token` de 32 bytes aleatórios, válido por 60 minutos. O servidor guarda seu SHA-256. O cliente mantém a prova e o endereço verificado em `sessionStorage` durante a escolha do plano e do pagamento.
4. `registrar-com-plano` bloqueia o registro do e-mail e consome a prova dentro da transação de criação da agência, do usuário verificado, da cobrança e do token Sanctum de pendência. A rota legada `auth/registro` usa o mesmo contrato.
5. O scheduler remove diariamente os registros cuja expiração ou consumo ocorreu há mais de 24 horas.

---

## Justificativa

A prova vincula a confirmação da caixa postal ao endereço usado no checkout. A transação permite repetir a tentativa após falha na criação da cobrança. O armazenamento transitório limita os dados pessoais coletados antes da escolha do pagamento.

---

## Consequências

### Positivas

- Um cadastro abandonado antes do pagamento deixa somente o registro transitório.
- O consumo sob bloqueio impede que duas requisições criem contas usando a mesma prova.

### Negativas / Trade-offs

- O avanço depende da entrega do e-mail. Expiração ou bloqueio exige um novo código.
- O envio síncrono mantém o bloqueio do registro durante a comunicação com o transporte. O expurgo depende da execução diária do scheduler.

---

## Rastreabilidade

- Jira `EDTAN-283`.
- ADR 035 (cadastro e pagamento), ADR 041 (verificação em convites) e ADR 042 (bloqueio pessimista).
