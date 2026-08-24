# Diagrama Entidade-Relacionamento (DER) Físico e Lógico

Este documento contém o modelo de banco de dados relacional para a plataforma **Anjo Nexus**, extraído rigorosamente a partir dos 44 Casos de Uso detalhados.

O diagrama abaixo está no formato **PlantUML** e descreve as tabelas físicas, tipos de dados (lógicos), Chaves Primárias (PK) e Chaves Estrangeiras (FK).

```plantuml
@startuml
!define Table(name,desc) entity name as "desc" << (T,#FFAAAA) >>
!define primary_key(x) <b><color:#b8861b>[PK]</color> x</b>
!define foreign_key(x) <color:#aaaaaa>[FK]</color> x
!define column(x) x

title DER: Anjo Nexus (Físico / Lógico)

' Entidades de Acesso e Gestão Financeira
entity "users" {
  primary_key(id) : BIGINT
  column(name) : VARCHAR(255)
  column(email) : VARCHAR(255) [UNIQUE]
  column(password_hash) : VARCHAR(255)
  column(role) : ENUM('adm', 'consultor_admin', 'consultor_padrao', 'startup')
  column(created_at) : TIMESTAMP
}

entity "subscriptions" {
  primary_key(id) : BIGINT
  foreign_key(user_id) : BIGINT
  column(plan_name) : ENUM('starter', 'basic', 'pro')
  column(status) : ENUM('pending', 'active', 'canceled')
  column(mp_payment_id) : VARCHAR(100)
  column(next_billing_date) : DATE
}

' Entidades do Core Business (Empresas e Preferências)
entity "startups" {
  primary_key(id) : BIGINT
  foreign_key(admin_id) : BIGINT
  column(cnpj) : VARCHAR(14) [UNIQUE]
  column(name) : VARCHAR(255)
  column(cnae_primary) : VARCHAR(50)
  column(tax_regime) : VARCHAR(100)
  column(social_capital) : DECIMAL(15,2)
  column(porte) : VARCHAR(50)
  column(trl_level) : INT
  column(pitch_default) : TEXT
}

entity "tags" {
  primary_key(id) : BIGINT
  column(category) : ENUM('nicho', 'regiao', 'regime')
  column(name) : VARCHAR(100)
}

entity "startup_tag" {
  foreign_key(startup_id) : BIGINT
  foreign_key(tag_id) : BIGINT
}

' Entidades do Processo de Editais e Scraping
entity "editais" {
  primary_key(id) : BIGINT
  column(title) : VARCHAR(255)
  column(source_url) : TEXT
  column(original_file_path) : TEXT
  column(min_budget) : DECIMAL(15,2)
  column(max_budget) : DECIMAL(15,2)
  column(deadline) : DATETIME
  column(published_at) : DATETIME
  column(status) : ENUM('active', 'closed')
}

entity "edital_tag" {
  foreign_key(edital_id) : BIGINT
  foreign_key(tag_id) : BIGINT
}

entity "edital_requisitos" {
  primary_key(id) : BIGINT
  foreign_key(edital_id) : BIGINT
  column(description) : TEXT
  column(is_mandatory) : BOOLEAN
}

entity "startup_edital_favorito" {
  foreign_key(startup_id) : BIGINT
  foreign_key(edital_id) : BIGINT
  column(favorited_at) : TIMESTAMP
}

' Entidades de Pipeline e Processamento Visual (Kanban e Minutas)
entity "kanban_boards" {
  primary_key(id) : BIGINT
  foreign_key(startup_id) : BIGINT
  column(name) : VARCHAR(255)
}

entity "kanban_columns" {
  primary_key(id) : BIGINT
  foreign_key(board_id) : BIGINT
  column(title) : VARCHAR(100)
  column(order_index) : INT
}

entity "kanban_tickets" {
  primary_key(id) : BIGINT
  foreign_key(column_id) : BIGINT
  foreign_key(edital_id) : BIGINT [NULL]
  column(title) : VARCHAR(255)
  column(due_date) : DATETIME
}

entity "kanban_attachments" {
  primary_key(id) : BIGINT
  foreign_key(ticket_id) : BIGINT
  column(file_name) : VARCHAR(255)
  column(file_path) : TEXT
}

entity "minutas" {
  primary_key(id) : BIGINT
  foreign_key(startup_id) : BIGINT
  foreign_key(edital_id) : BIGINT
  foreign_key(ticket_id) : BIGINT
  column(ai_prompt_used) : TEXT
  column(html_content) : LONGTEXT
  column(created_at) : TIMESTAMP
  column(updated_at) : TIMESTAMP
}

' Entidade de Notificações Internas
entity "notificacoes" {
  primary_key(id) : BIGINT
  foreign_key(user_id) : BIGINT
  column(type) : ENUM('match', 'deadline', 'cota_excedida')
  column(message) : TEXT
  column(is_read) : BOOLEAN
  column(created_at) : TIMESTAMP
}

' ---------------------------------------
' RELACIONAMENTOS (Chaves Estrangeiras)
' ---------------------------------------
users ||--o{ subscriptions : "1:N"
users ||--o{ startups : "1:N (admin_id)"
startups ||--o{ startup_tag : "1:N"
tags ||--o{ startup_tag : "1:N"

editais ||--o{ edital_tag : "1:N"
tags ||--o{ edital_tag : "1:N"
editais ||--o{ edital_requisitos : "1:N"

startups ||--o{ startup_edital_favorito : "1:N"
editais ||--o{ startup_edital_favorito : "1:N"

startups ||--|| kanban_boards : "1:1"
kanban_boards ||--o{ kanban_columns : "1:N"
kanban_columns ||--o{ kanban_tickets : "1:N"
editais |o--o{ kanban_tickets : "0:N (Referência opcional)"
kanban_tickets ||--o{ kanban_attachments : "1:N"

startups ||--o{ minutas : "1:N"
editais ||--o{ minutas : "1:N"
kanban_tickets |o--o| minutas : "0:1"

users ||--o{ notificacoes : "1:N"

@enduml
```

### Como Visualizar:
Copie o bloco de código acima e cole em um renderizador online como o [PlantText](https://www.planttext.com/) ou utilize uma extensão de visualização PlantUML no VS Code.

