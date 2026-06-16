# InterlinkedLog — Especificação Técnica

## Arquitetura

### Hexagonal Architecture (Ports & Adapters)

```
                    ┌─────────────────────────────────┐
                    │         DOMAIN LAYER             │
                    │  Entities (DTOs)                 │
                    │  Repository Interfaces (Ports)   │
                    │  Service Interfaces (Ports)      │
                    └──────────┬──────────────────────┘
                               │ implements
              ┌────────────────┼────────────────┐
              ▼                ▼                ▼
     ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
     │  Application │  │Infrastructure│  │     HTTP     │
     │   UseCases   │  │  Eloquent    │  │  Controllers │
     │              │  │  Services    │  │  Middleware  │
     └──────────────┘  └──────────────┘  └──────────────┘
```

O domínio não depende de framework, banco de dados ou infraestrutura.  
Trocar SQLite por PostgreSQL requer apenas trocar as implementações de repositório.

---

## Domain Layer

### Entities (Value Objects imutáveis)

Todas as entidades são DTOs com `public readonly` — sem setters, sem lógica de negócio, sem dependências de framework.

| Entidade | Descrição |
|----------|-----------|
| User | id, name, email, passwordHash, role, status, companyId |
| Company | id, name, cnpj, type, phone, email, city, uf |
| Carrier | id, companyId, name, cnpj, originCity, originState, status |
| FreightTable | id, carrierId, name, validFrom, validUntil, status, routes, weightRanges, fees |
| Quotation | id, companyId, userId, nfNumber, senderCnpj, receiverCnpj, originCep, destinationCep, originCity, destinationCity, destinationState, weight, boxes, volume, cargoValue, status, results, validUntil |
| Contract | id, companyId, quotationId, carrierId, carrierName, nfNumber, originCity, destinationCity, destinationState, freightValue, fees, finalValue, deadline, status, documentNumber, cteNumber |
| TrackingEvent | id, contractId, title, date, time, observation |
| AuditLog | id, companyId, userId, userName, module, action, entityType, entityId, oldValues, newValues |
| SystemLog | id, companyId, userId, userName, level, event, message |

### Repository Interfaces (Ports)

| Interface | Métodos |
|-----------|---------|
| UserRepository | findById, findByEmail, findByCompany, save, delete |
| CompanyRepository | findById, save |
| CarrierRepository | findById, findAll, findByOrigin, save, delete |
| FreightTableRepository | findById, findByCarrier, findActiveByCarrierAndRoute, findAll, save, delete |
| QuotationRepository | findByCompany, findById, save |
| ContractRepository | findByCompany, findById, save |
| TrackingEventRepository | findByContract, findById, save |
| AuditLogRepository | findByCompany, save |
| SystemLogRepository | findByCompany, save |

### Service Interfaces (Ports)

| Interface | Métodos |
|-----------|---------|
| AuthService | login, validateToken, logout |
| QuotationEngineService | cepToCity, process |
| ReportService | dashboard, detailed, carrierPerformance |

---

## Application Layer

### Use Cases

```
LoginUserUseCase    → orquestra autenticação
RegisterUserUseCase → orquestra criação de usuário
```

Use cases são stateless e injetam apenas interfaces do domínio.

---

## Infrastructure Layer

### Implementações Eloquent

10 repositórios em `Infrastructure/Repositories/Eloquent/` implementam as interfaces do domínio usando Eloquent Models sobre SQLite.

### Serviços

| Serviço | Implementa | Descrição |
|---------|-----------|-----------|
| TokenAuthService | AuthService | Login/logout com tokens UUID, validação via hash |
| QuotationEngine | QuotationEngineService | CEP → cidade, cálculo de frete, ranking |
| ReportGenerator | ReportService | Agregações para dashboard e relatórios detalhados |

### Cálculo de Frete (QuotationEngine)

```
1. CEP → cidade (mapa de prefixos)
2. Para cada transportadora ativa:
   a. Busca tabela ativa com rota origem → destino
   b. Encontra faixa de peso (start ≤ weight ≤ end)
   c. Frete base = valor da faixa
   d. Taxas = ad_valorem + gris + despacho + pedágio + tde
   e. Total = frete_base + taxas
   f. Se total < frete_mínimo → total = frete_mínimo
3. Ordena por preço, prazo, custo-benefício
4. Retorna ranking com flags de destaque
```

---

## HTTP Layer

### Controllers

| Controller | Endpoints |
|-----------|-----------|
| AuthController | login, register, logout, me |
| UserController | full REST (CRUD) |
| CompanyController | show |
| CarrierController | full REST (CRUD) |
| FreightTableController | full REST (CRUD) |
| QuotationController | index, store, show, cancel, parseXml |
| ContractController | index, store, show, cancel, updateCte, pdf |
| TrackingController | index, show, store |
| ReportController | dashboard, detailed, carrierPerformance |
| AuditLogController | index, store |
| SystemLogController | index, store |

### Middleware (ordem de execução)

| Middleware | Função |
|-----------|--------|
| TokenAuthMiddleware | Valida `Authorization: Bearer {token}`, injeta user_id + company_id |
| TenantMiddleware | Bloqueia acesso a registros de outras empresas |
| ForceJsonResponse | Força Content-Type application/json + CORS |

---

## Frontend

### Rotas (TanStack Router)

```
/                          Painel de Controle
/login                     Login (público)
/cotacoes                  Lista de cotações
/cotacoes/nova             Nova cotação (upload XML + manual)
/cotacoes/resultado        Resultado da cotação (ranking)
/contratacoes              Lista de contratações
/rastreamento              Controle de rastreamento
/transportadoras           Cadastro de transportadoras + tabelas
/usuarios                  Gestão de usuários (Admin only)
/auditoria                 Trilha de auditoria (Admin only)
/logs                      Logs do sistema (Admin only)
```

### Componentes Compartilhados

```
atoms/          StatusBadge
molecules/      MetricCard, PageHeader, HighlightCard
organisms/      AppSidebar, AppHeader
```

### Fluxo de Dados

```
Route → useQuery → api.get/post → fetch(/api/v1/...) → Laravel → SQLite
                                        ↕
                              Vite proxy /api → backend:8000
```

---

## Banco de Dados (SQLite)

14 tabelas com UUID como chave primária:

```
companies          users             carriers
freight_tables     freight_table_routes    freight_table_weight_ranges
freight_table_fees quotations        quotation_results
contracts          tracking_events   audit_logs
system_logs
```

Migrations em `database/migrations/`. Seed em `database/seeders/DatabaseSeeder.php`.

---

## Segurança

- Tokens UUID com 64 caracteres hexadecimais
- Senhas bcrypt (PASSWORD_BCRYPT)
- Tenant isolation via middleware
- CORS configurável
- Rate limiting via Laravel throttle (a implementar)
- `public $incrementing = false` + `protected $keyType = 'string'` — UUIDs não sequenciais

---

## Decisões de Design

| Decisão | Justificativa |
|---------|---------------|
| UUID como PK | Evita enumeração, compatível com sistemas distribuídos |
| DTOs imutáveis | Thread-safe, sem efeitos colaterais, fácil de testar |
| Repository pattern | Desacopla domínio da persistência |
| Service interfaces | Desacopla domínio da implementação |
| Token-based auth | Stateless, escalável |
| Factory methods nas entidades | Centraliza regras de criação |
| Readonly properties | Imutabilidade garantida em runtime (PHP 8.2+) |
| Named arguments | Clareza nos construtores com 10+ parâmetros |
