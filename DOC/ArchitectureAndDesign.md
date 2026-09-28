# 01 — Arquitetura do Sistema

## Visão unificada do sistema

Diagrama que consolida os três níveis do C4 num só lugar: atores externos, o
**servidor de desenvolvimento** (Backend Laravel + Frontend TanStack Start) e o banco **MySQL 8** — com o
Backend "aberto" mostrando as camadas da arquitetura hexagonal por dentro. As setas
internas representam a **direção da dependência** (tudo aponta para o Domain).

```mermaid
flowchart TB
    usuario["Usuário"]
    admin["Admin"]

    subgraph server["Servidor (dev ou Docker)"]
        frontend["Frontend SSR<br/><i>React 19 + TanStack Start · :3000</i>"]

        subgraph backend["Backend API — Laravel 11 · :8000"]
            direction TB
            http["HTTP Layer<br/>Controllers · Middleware"]
            application["Application<br/><i>Use Cases</i>"]
            domain["Domain — núcleo<br/><i>Entities · Repository Ports · Service Ports</i>"]
            infrastructure["Infrastructure<br/><i>Eloquent Repos · Services</i>"]

            http -->|"Chama"| application
            application -->|"Depende de"| domain
            infrastructure -. "Implementa" .-> domain
        end

        mysql[("MySQL 8<br/>docker compose · :3306")]
    end

    usuario -->|"HTTP :3000"| frontend
    admin -->|"HTTP :3000"| frontend
    frontend -. "fetch /api/v1/* via Vite proxy" .-> backend
    infrastructure -->|"Eloquent ORM (PDO mysql)"| mysql

    classDef person fill:#0b3d91,stroke:#072a66,color:#fff;
    classDef app fill:#1168bd,stroke:#0b3d91,color:#fff;
    classDef core fill:#0b3d91,stroke:#072a66,color:#fff;
    classDef db fill:#2f6f4f,stroke:#1d4731,color:#fff;
    class usuario,admin person;
    class frontend,http app;
    class domain core;
    class mysql db;
```

---

## C4 Model — Nível 1: System Context

```mermaid
flowchart TB
    usuario["Usuário<br/><i>Operador de logística</i>"]
    admin["Admin<br/><i>Gestão de transportadoras e usuários</i>"]

    interlinked(["InterlinkedLog<br/><i>Plataforma SaaS de cotação e contratação de fretes</i>"])

    mysql[("MySQL 8<br/><i>Cotações, contratos, rastreamento, auditoria</i>")]

    usuario -->|"Cotações, contratos, rastreamento"| interlinked
    admin -->|"Transportadoras, tabelas, usuários, auditoria"| interlinked
    interlinked -->|"Leitura e escrita"| mysql

    classDef person fill:#0b3d91,stroke:#072a66,color:#fff;
    classDef system fill:#1168bd,stroke:#0b3d91,color:#fff;
    classDef ext fill:#2f6f4f,stroke:#1d4731,color:#fff;
    class usuario,admin person;
    class interlinked system;
    class mysql ext;
```

---

## C4 Model — Nível 2: Containers

```mermaid
flowchart TB
    usuario["Usuário"]

    subgraph server["Servidor"]
        direction TB
        frontend["Frontend SSR<br/><i>React 19 + TanStack Start · :3000</i>"]
        backend["Backend API<br/><i>Laravel 11 + PHP built-in server · :8000</i>"]
        mysql[("MySQL 8<br/><i>banco de dados</i>")]
    end

    usuario -->|"HTTP :3000"| frontend
    frontend -->|"fetch /api/v1/* (Vite proxy)"| backend
    backend -->|"Eloquent ORM (PDO mysql)"| mysql

    classDef person fill:#0b3d91,stroke:#072a66,color:#fff;
    classDef app fill:#1168bd,stroke:#0b3d91,color:#fff;
    classDef db fill:#2f6f4f,stroke:#1d4731,color:#fff;
    class usuario person;
    class frontend,backend app;
    class mysql db;
```

---

## C4 Model — Nível 3: Componentes (Arquitetura Hexagonal)

> As setas representam a **direção da dependência**. O Domain não depende de nenhuma
> outra camada; Infrastructure e HTTP dependem do Domain.

```mermaid
flowchart TB
    subgraph http["HTTP Layer"]
        controllers["Controllers<br/>Auth · User · Company · Carrier · FreightTable<br/>Quotation · Contract · Tracking · Report · AuditLog · SystemLog"]
        middleware["Middleware<br/><i>TokenAuth · Tenant · ForceJson</i>"]
    end

    subgraph application["Application Layer"]
        usecases["Use Cases<br/><i>LoginUserUseCase · RegisterUserUseCase</i>"]
    end

    subgraph domain["Domain Layer — núcleo"]
        entities["Entities (DTOs readonly)<br/><i>User · Company · Carrier · FreightTable · Quotation · Contract · TrackingEvent · AuditLog · SystemLog</i>"]
        repos["Repository Ports<br/><i>UserRepository · CarrierRepository · QuotationRepository · ...</i>"]
        services["Service Ports<br/><i>AuthService · QuotationEngineService · ReportService</i>"]
    end

    subgraph infrastructure["Infrastructure Layer"]
        eloquent["Eloquent Repositories<br/><i>EloquentUserRepository · EloquentCarrierRepository · ...</i>"]
        impl["Services<br/><i>TokenAuthService · QuotationEngine · ReportGenerator</i>"]
    end

    http -->|"Chama"| application
    application -->|"Depende de"| domain
    infrastructure -. "Implementa" .-> domain

    classDef core fill:#0b3d91,stroke:#072a66,color:#fff;
    classDef layer fill:#e8eef7,stroke:#0b3d91,color:#13243a;
    class domain core;
    class http,application,infrastructure layer;
```

### Regra de dependência

| Camada | Pode importar | NÃO pode importar |
|--------|---------------|-------------------|
| **Domain** | stdlib PHP, `Domain/*` | `Application`, `Infrastructure`, `Http` |
| **Application** | `Domain/*` | `Infrastructure`, `Http` |
| **Infrastructure** | `Domain/*`, `Application/*`, Eloquent, frameworks | — |
| **Http (Controllers)** | `Application/*`, `Domain/*`, `Infrastructure/*`, Laravel | — |

---

## Fluxo de uma requisição (ex: `POST /api/v1/quotations`)

```
1. Frontend faz fetch POST /api/v1/quotations (Authorization: Bearer {token})
2. Vite proxy redireciona para Laravel :8000
3. TokenAuthMiddleware valida o token — extrai user_id + company_id
4. TenantMiddleware injeta company_id no request
5. ForceJsonMiddleware garante Content-Type application/json + CORS
6. QuotationController::store() recebe o request validado
7. QuotationEngine::process() calcula frete para cada transportadora ativa
8.   - Converte CEPs em cidades
9.   - Busca FreightTable ativa para rota origem → destino
10.  - Encontra faixa de peso, calcula base + taxas
11.  - Aplica frete mínimo
12.  - Ordena resultados por preço, prazo, custo-benefício
13. EloquentQuotationRepository::save() persiste Quotation + QuotationResults no MySQL (em transação)
14. Response JSON retorna cotação com ranking de transportadoras
```

---

## Estrutura de diretórios

```
InterlinkedLog/
├── backend/                         # Laravel 11 (PHP)
│   ├── app/
│   │   ├── Application/
│   │   │   └── UseCases/Auth/       # LoginUserUseCase, RegisterUserUseCase
│   │   ├── Domain/
│   │   │   ├── Entities/            # DTOs imutáveis (readonly properties)
│   │   │   ├── Repositories/        # Interfaces de repositório (Ports)
│   │   │   └── Services/            # Interfaces de serviço (Ports)
│   │   ├── Http/
│   │   │   ├── Controllers/Api/     # REST controllers (11 controllers)
│   │   │   └── Middleware/          # TokenAuth, Tenant, ForceJson
│   │   ├── Infrastructure/
│   │   │   ├── Repositories/Eloquent/ # 10 implementações Eloquent
│   │   │   └── Services/            # TokenAuthService, QuotationEngine, ReportGenerator
│   │   ├── Models/                  # Eloquent Models (ORM)
│   │   └── Providers/
│   ├── database/
│   │   ├── migrations/              # 19 migrations (criação completa do schema)
│   │   ├── seeders/                 # DatabaseSeeder (seed inicial com admin)
│   │   └── scripts/                 # migrate-sqlite-to-mysql.php (migração legada)
│   ├── resources/views/pdf/         # Template Blade para PDF de Solicitação de Coleta
│   ├── routes/api.php               # Definição das rotas REST
│   ├── docker-entrypoint.sh         # Espera o MySQL e sobe o serve
│   └── Dockerfile                   # Imagem PHP com pdo_mysql/pdo_sqlite
│
├── src/                             # Frontend React (TanStack Start)
│   ├── routes/                      # File-based routing (TanStack Router)
│   ├── components/ui/               # Primitivos shadcn/ui (Radix)
│   ├── shared/components/           # Átomos, Moléculas, Organismos
│   ├── hooks/                       # useAuth
│   └── lib/                         # api.ts, utils, config
│
├── docker-compose.yml               # Orquestra mysql (:3306) + backend (:8080) + frontend (:3000)
├── mysql-init/init.sql              # Cria interlinkedlog_test na 1ª subida
├── scripts/                         # backup-mysql.sh, restore-mysql.sh
├── start.sh                         # Sobe backend + frontend em dev
├── stop.sh                          # Para todos os processos
└── test-all.sh                      # 30 testes automatizados via curl
```
