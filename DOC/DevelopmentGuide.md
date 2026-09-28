# 07 — Desenvolvimento

## Setup Local

### Pré-requisitos

- PHP 8.2+ com extensão `pdo_mysql`
- Composer 2.x
- Node.js 22+
- Bun 1.x (ou npm/yarn)
- MySQL 8 (o mais simples: `docker compose up -d mysql`)

### Passo a passo

```bash
# 1. Clone o repositório
git clone <repo-url>
cd InterlinkedLog

# 2. Suba o MySQL (banco interlinkedlog + interlinkedlog_test)
docker compose up -d mysql

# 3. Instale dependências do backend
cd backend
composer install

# 4. Configure o .env do backend
cp .env.example .env
php artisan key:generate

# 5. Aplique as migrations e popule os dados iniciais
php artisan migrate
php artisan app:seed
# → Email: admin@interlinked.io  Senha: admin123

# 6. Instale dependências do frontend
cd ..
bun install        # ou: npm install

# 7. Suba tudo de uma vez (recomendado)
./start.sh
# Frontend: http://localhost:3000
# Backend:  http://localhost:8000/api/v1
```

---

## Usando Docker

```bash
# Subir com Docker Compose
./docker-start.sh
# ou: docker compose up --build -d

# Parar
./docker-stop.sh
# ou: docker compose down
```

---

## Comandos do dia a dia

```bash
# ===== BACKEND =====

# Subir servidor dev
cd backend
php -S 127.0.0.1:8000 -t public

# Ou via script completo (seed + backend + frontend)
./start.sh

# Parar tudo
./stop.sh

# Rodar migrations
php artisan migrate

# Seed com dados iniciais (apaga tudo e recria)
php artisan app:seed

# Rodar testes PHP (PHPUnit — exige o MySQL do docker de pé)
cd backend
php artisan test

# Ver rotas registradas
php artisan route:list

# Limpar caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# ===== FRONTEND =====

# Dev server com hot reload
bun run dev       # ou: npm run dev

# Build de produção
bun run build     # ou: npm run build

# Linting
bun run lint

# Formatação
bun run format

# ===== TESTES DE API =====

# 30 testes automatizados via curl (requer sistema rodando)
./test-all.sh
```

---

## Convenções de Código

### Backend (PHP / Laravel)

| Elemento | Convenção | Exemplo |
|----------|-----------|---------|
| Arquivos | PascalCase | `QuotationController.php`, `EloquentCarrierRepository.php` |
| Classes | PascalCase | `QuotationEngine`, `TokenAuthService` |
| Métodos | camelCase | `findByCompany()`, `validateToken()` |
| Variáveis | camelCase | `$companyId`, `$freightTable` |
| Constantes | UPPER_SNAKE | `PASSWORD_BCRYPT` |
| Namespaces | PSR-4 | `App\Domain\Repositories\CarrierRepository` |

#### Domain Entities

Todas as entidades são **DTOs imutáveis** com `public readonly`:

```php
class Quotation
{
    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $nfNumber,
        // ...
    ) {}
}
```

- Sem setters
- Sem lógica de negócio
- Sem dependência de framework
- Use `named arguments` nos construtores com muitos parâmetros

#### Repository Interfaces (Domain Ports)

```php
// Domain/Repositories/QuotationRepository.php
interface QuotationRepository
{
    public function findByCompany(string $companyId): array;
    public function findById(string $id): ?Quotation;
    public function save(Quotation $quotation): void;
}
```

#### Implementações Eloquent (Infrastructure)

```php
// Infrastructure/Repositories/Eloquent/EloquentQuotationRepository.php
class EloquentQuotationRepository implements QuotationRepository
{
    public function findByCompany(string $companyId): array
    {
        return QuotationModel::where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($m) => $this->toEntity($m))
            ->all();
    }
}
```

#### Use Cases

```php
class LoginUserUseCase
{
    public function __construct(private AuthService $authService) {}

    public function execute(string $email, string $password): ?array
    {
        return $this->authService->login($email, $password);
    }
}
```

### Frontend (TypeScript / React)

| Elemento | Convenção | Exemplo |
|----------|-----------|---------|
| Arquivos de rota | kebab-case | `cotacoes.nova.tsx`, `__root.tsx` |
| Componentes | PascalCase | `AppSidebar.tsx`, `StatusBadge.tsx` |
| Hooks | camelCase com prefixo `use-` | `use-auth.tsx` |
| Constantes | UPPER_SNAKE | `API_BASE` |
| Tipos/interfaces | PascalCase | `AuthUser`, `QuotationResult` |

#### Componentes de rota (TanStack Router)

```typescript
// src/routes/cotacoes.index.tsx
import { createFileRoute } from "@tanstack/react-router";

export const Route = createFileRoute("/cotacoes/")({
  component: CotacoesPage,
});

function CotacoesPage() {
  const { data } = useQuery({ queryKey: ["quotations"], queryFn: ... });
  return <div>...</div>;
}
```

#### Chamadas de API

```typescript
import { api } from "@/lib/api";

// GET
const data = await api.get<Quotation[]>("/quotations");

// POST JSON
const result = await api.post<Quotation>("/quotations", { nf_number: "001", ... });

// POST FormData (upload)
const fd = new FormData();
fd.append("file", xmlFile);
const parsed = await api.post<ParsedNFe>("/quotations/parse-xml", fd);
```

---

## Como adicionar uma nova funcionalidade

### 1. Criar a entidade (Domain)

```php
// backend/app/Domain/Entities/MinhaEntidade.php
class MinhaEntidade
{
    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        // ...
    ) {}
}
```

### 2. Criar a interface de repositório (Domain Port)

```php
// backend/app/Domain/Repositories/MinhaEntidadeRepository.php
interface MinhaEntidadeRepository
{
    public function findByCompany(string $companyId): array;
    public function save(MinhaEntidade $entity): void;
}
```

### 3. Criar o Eloquent Model

```php
// backend/app/Models/MinhaEntidade.php
class MinhaEntidade extends Model
{
    protected $table = 'minhas_entidades';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'company_id', ...];
}
```

### 4. Criar a migration

```bash
cd backend
php artisan make:migration create_minhas_entidades_table
# Edite a migration em database/migrations/
php artisan migrate
```

### 5. Implementar o repositório (Infrastructure)

```php
// backend/app/Infrastructure/Repositories/Eloquent/EloquentMinhaEntidadeRepository.php
class EloquentMinhaEntidadeRepository implements MinhaEntidadeRepository
{
    public function findByCompany(string $companyId): array { ... }
    public function save(MinhaEntidade $entity): void { ... }
}
```

### 6. Registrar no Service Provider

```php
// backend/app/Providers/AppServiceProvider.php
$this->app->bind(MinhaEntidadeRepository::class, EloquentMinhaEntidadeRepository::class);
```

### 7. Criar o Controller

```php
// backend/app/Http/Controllers/Api/MinhaEntidadeController.php
class MinhaEntidadeController extends Controller
{
    public function __construct(private MinhaEntidadeRepository $repo) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        return response()->json($this->repo->findByCompany($companyId));
    }
}
```

### 8. Registrar a rota

```php
// backend/routes/api.php
Route::apiResource('minhas-entidades', MinhaEntidadeController::class);
```

### 9. Criar a rota no frontend

```typescript
// src/routes/minhas-entidades.tsx
import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";

export const Route = createFileRoute("/minhas-entidades")({
  component: MinhasEntidadesPage,
});

function MinhasEntidadesPage() {
  const { data } = useQuery({
    queryKey: ["minhas-entidades"],
    queryFn: () => api.get("/minhas-entidades"),
  });
  return <div>...</div>;
}
```

---

## Seed de dados iniciais

O seed (`php artisan app:seed`) cria:

| Dado | Quantidade | Credenciais |
|------|-----------|-------------|
| Empresa | 1 (Empresa Modelo Ltda) | — |
| Admin | 1 | `admin@interlinked.io` / `admin123` |
| Usuários | 2 | `marina@interlinked.io`, `rafael@interlinked.io` / `admin123` |
| Transportadoras | 8 | Braspress, Jamef, TNT, Rodonaves, Patrus, Solistica, JSL, Total Express |
| Tabelas de frete | 8 (uma por transportadora) | 4 rotas × 3 faixas de peso cada |
| Cotações | 3 | NF 000001 (CONTRATADA), 000002 e 000003 (VALIDA) |
| Contrato | 1 | OC gerada para a cotação 000001 |
| Eventos de rastreamento | 3 | Coleta Agendada → Coletado → Em Rota |
| Logs do sistema | 10 | Vários níveis (INFO, WARNING, ERROR) |

> O seed é idempotente via `php artisan app:seed` — **recria** os dados do zero a cada execução.
> Em produção, execute o seed apenas no setup inicial.
