# Diagrama de Classes (Sistema Único)

Este documento contém o Diagrama de Classes consolidado para todo o sistema **Anjo Nexus**, cobrindo o painel Administrativo Global, Consultores, Startups, Web Scraping, IA e Gestão Kanban.

O diagrama segue a notação **PlantUML**, mapeando as Models (Entidades) primárias e as classes de Serviço (Services) e Controladores (Controllers) que orquestram os 44 Casos de Uso.

```plantuml
@startuml
title Diagrama de Classes - Anjo Nexus (UML Consolidado)

' ----------------------------------------------------
' 1. CLASSES DE DOMÍNIO (MODELS)
' ----------------------------------------------------

class User {
  - id : BigInt
  - name : String
  - email : String
  - password_hash : String
  - role : Enum
  + login()
  + resetPassword()
  + hasPermission(permission: String) : Boolean
}

class Subscription {
  - id : BigInt
  - plan_name : Enum
  - status : Enum
  - next_billing_date : Date
  + processWebhook(payload: Json)
  + isQuotaExceeded(quota_type: String) : Boolean
}

class Startup {
  - id : BigInt
  - cnpj : String
  - name : String
  - cnae_primary : String
  - trl_level : Int
  - pitch_default : Text
  + updateProfile(data: Array)
  + matchWithEdital(edital: Edital) : Float
}

class Tag {
  - id : BigInt
  - category : Enum
  - name : String
}

class Edital {
  - id : BigInt
  - title : String
  - deadline : DateTime
  - source_url : String
  - original_file_path : String
  + extractRequirementsViaOcr()
}

class EditalRequirement {
  - description : Text
  - is_mandatory : Boolean
}

class KanbanTicket {
  - title : String
  - due_date : DateTime
  - status : String
  + moveToColumn(columnId: BigInt)
  + attachFile(file: File)
}

class Minuta {
  - id : BigInt
  - ai_prompt_used : Text
  - html_content : LongText
  + exportToWord() : File
  + exportToPdf() : File
}

class Notification {
  - type : Enum
  - message : Text
  - is_read : Boolean
  + markAsRead()
}

' ----------------------------------------------------
' 2. CLASSES DE SERVIÇO (SERVICES & JOBS)
' ----------------------------------------------------

class AuthService {
  + authenticateUser(email, password) : Token
  + generateRecoveryLink(email) : Boolean
}

class CnpjScraperService {
  + fetchCompanyData(cnpj: String) : Array
}

class EditalSpiderJob {
  + runRoutine()
  - cleanDescriptions(rawHtml: String)
  - avoidDuplicates(uid: String) : Boolean
}

class OcrPdfService {
  + extractTextFromPdf(filePath: String) : String
  + identifyKeyTerms(text: String) : Array
}

class GeminiAiService {
  + generateProposal(pitch: String, editalText: String) : String
  + formatToHtml(rawResponse: String) : String
}

class MatchEngineService {
  + calculateScore(startup: Startup, edital: Edital) : Float
  + dispatchMatchNotifications()
}

class BillingService {
  + handleDowngrade(user: User, newPlan: String) : Boolean
  + softDeleteAccountCascading(user: User)
}

' ----------------------------------------------------
' 3. RELACIONAMENTOS UML
' ----------------------------------------------------

' Herança (Generalização) lógica baseada na Regra de Negócio
User <|-- "ConsultorAdmin"
User <|-- "ConsultorPadrao"
User <|-- "StartupClient"

' Composições e Agregações de Entidades
User "1" *-- "0..*" Subscription : "Assina"
User "1" o-- "0..*" Startup : "Gerencia"
Startup "1" *-- "0..*" Tag : "Possui Preferências"
Edital "1" *-- "0..*" Tag : "Classificado por"
Edital "1" *-- "0..*" EditalRequirement : "Contém Tarefas"

Startup "1" o-- "0..*" Edital : "Favorita (Lista)"
Startup "1" *-- "1" KanbanTicket : "Controla Pipeline via"
KanbanTicket "1" o-- "0..1" Edital : "Refere-se ao Edital"
KanbanTicket "1" *-- "0..1" Minuta : "Possui Rascunho"

User "1" *-- "0..*" Notification : "Recebe"

' Dependências de Serviços (Uso)
AuthService ..> User : "Gerencia Autenticação"
BillingService ..> Subscription : "Controla Cotas"
CnpjScraperService ..> Startup : "Auto-preenche UC-11"
EditalSpiderJob ..> Edital : "Cria novos via Scraping UC-39"
OcrPdfService ..> EditalRequirement : "Gera checklists UC-26"
GeminiAiService ..> Minuta : "Redige texto UC-21"
MatchEngineService ..> Edital : "Compara Tags UC-27"
MatchEngineService ..> Startup : "Compara Tags UC-27"
MatchEngineService ..> Notification : "Dispara Alerta"

@enduml
```

### Como Visualizar:
Copie o bloco de código acima e cole em um renderizador online como o [PlantText](https://www.planttext.com/) ou utilize uma extensão de visualização PlantUML no VS Code.
