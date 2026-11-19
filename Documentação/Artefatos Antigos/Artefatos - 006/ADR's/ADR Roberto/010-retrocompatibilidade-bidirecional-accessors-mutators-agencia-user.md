# ADR 010: Retrocompatibilidade Bidirecional via Accessors e Mutators nos Modelos `Agencia` e `User`

## Status
Aceito

## Contexto
Durante a evolução do Back-end entre as Sprints 004 e 008 (`EDTAN-104`, `EDTAN-148` e `EDTAN-208`), o código passou por uma padronização semântica para o português (ex: `url_checkout`, `id_cobranca`, `pagamento_ativo`, `nome`, `perfil_acesso`), enquanto partes do banco de dados e módulos construídos paralelamente por outros membros da equipe referenciavam nomenclaturas anteriores (`checkout_url`, `billing_id`, `id_plataforma_pagamento`, `status_pagamento`, `nome_fantasia`, `role`). Renomear ou remover colunas de forma destrutiva quebraria a integração entre os módulos no momento do merge.

## Decisão
Decidimos implementar **Accessors (`get...Attribute`) e Mutators (`set...Attribute`) bidirecionais** nos modelos `app/Models/Agencia.php` e `app/Models/User.php`, sincronizando automaticamente no array interno `$this->attributes` do Eloquent tanto as nomenclaturas em português quanto os nomes originais.

## Justificativa
1. **Por que implementar `getPagamentoAtivoAttribute()` e `setPagamentoAtivoAttribute($valor)` em `Agencia.php`:**
   - O Accessor retorna `!empty($this->attributes['pagamento_ativo']) || ($this->attributes['status_pagamento'] ?? '') === 'ativo'`, garantindo que uma agência seja reconhecida como ativa mesmo que apenas uma das duas colunas tenha sido atualizada no banco.
   - O Mutator grava `$this->attributes['pagamento_ativo'] = (bool) $valor` e, quando `$valor` é verdadeiro, define automaticamente `$this->attributes['status_pagamento'] = 'ativo'`.
2. **Por que unificar `billing_id`, `id_cobranca` e `id_plataforma_pagamento` nos métodos `getBillingIdAttribute` / `setBillingIdAttribute` e `getIdCobrancaAttribute` / `setIdCobrancaAttribute`:** Ao atribuir um ID de transação a qualquer uma dessas propriedades, o Mutator preenche simultaneamente as 3 chaves em `$this->attributes`. Da mesma forma, o Accessor busca em cascata (`$this->attributes['billing_id'] ?? $this->attributes['id_cobranca'] ?? $this->attributes['id_plataforma_pagamento'] ?? null`). O mesmo padrão espelhado foi aplicado em `getCheckoutUrlAttribute` / `setCheckoutUrlAttribute` e `getUrlCheckoutAttribute` / `setUrlCheckoutAttribute`.
3. **Por que implementar `setNomeFantasiaAttribute` / `getNomeFantasiaAttribute` em `Agencia.php` e `setRoleAttribute` / `getRoleAttribute` em `User.php` (`EDTAN-148`):** Em `Agencia.php`, ler ou gravar `nome_fantasia` mapeia transparentemente para `$this->attributes['nome']`. Em `User.php`, ler ou gravar `$usuario->role` (usado no `RegistroService`) mapeia diretamente para `$this->attributes['perfil_acesso']` (com fallback para `'consultor_admin'`), evitando erros de *Column not found* no MySQL.

## Consequências
- Zero quebras de integração no Back-end durante os merges das branches da equipe.
- Sincronia automática entre colunas equivalentes sem duplicar lógica nos Controllers.

