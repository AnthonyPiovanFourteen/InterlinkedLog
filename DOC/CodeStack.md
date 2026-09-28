# 04 — Frontend

## Stack

- **Framework:** React 19 (RSC + SSR via TanStack Start)
- **Build:** Vite 7
- **Roteamento:** TanStack Router (file-based routing)
- **Dados:** TanStack Query 5
- **HTTP:** Fetch nativo (`src/lib/api.ts`)
- **CSS:** Tailwind CSS 4
- **UI:** shadcn/ui + Radix UI
- **Ícones:** Lucide React
- **Gráficos:** Recharts
- **Mapa:** react-simple-maps
- **Forms:** react-hook-form + zod
- **Notificações:** sonner

---

## Árvore de diretórios

```
src/
├── routes/                          # File-based routing (TanStack Router)
│   ├── __root.tsx                   # Layout raiz: AuthProvider, SidebarProvider, AppLayout
│   ├── index.tsx                    # / → Painel de Controle (dashboard + relatórios)
│   ├── login.tsx                    # /login → Tela de login
│   ├── cotacoes.index.tsx           # /cotacoes → Lista de cotações
│   ├── cotacoes.nova.tsx            # /cotacoes/nova → Upload XML + formulário manual
│   ├── cotacoes.resultado.tsx       # /cotacoes/resultado → Ranking de transportadoras
│   ├── contratacoes.tsx             # /contratacoes → Lista e gestão de contratos
│   ├── rastreamento.tsx             # /rastreamento → Timeline de eventos por contrato
│   ├── transportadoras.tsx          # /transportadoras → CRUD transportadoras + tabelas
│   ├── usuarios.tsx                 # /usuarios → Gestão de usuários (Admin only)
│   ├── auditoria.tsx                # /auditoria → Trilha de auditoria (Admin only)
│   └── logs.tsx                     # /logs → Logs do sistema (Admin only)
│
├── components/ui/                   # Primitivos shadcn/ui (Radix-based)
│   └── accordion, alert, avatar, badge, button, card, dialog,
│       dropdown-menu, form, input, label, select, sidebar,
│       skeleton, sonner, table, tabs, textarea, tooltip...
│
├── shared/components/
│   ├── atoms/
│   │   └── StatusBadge.tsx          # Badge colorido de status (cotação, contrato, rastreamento)
│   ├── molecules/
│   │   ├── MetricCard.tsx           # Card de KPI com valor + label + ícone
│   │   └── PageHeader.tsx           # Cabeçalho de página com título + ação
│   └── organisms/
│       ├── AppSidebar.tsx           # Sidebar colapsível com navegação por role
│       └── AppHeader.tsx            # Header fixo com usuário logado
│
├── hooks/
│   └── use-auth.tsx                 # AuthProvider + useAuth (login, logout, isAdmin)
│
├── lib/
│   ├── api.ts                       # Cliente HTTP (fetch + Bearer token + localStorage)
│   ├── utils.ts                     # cn() helper (clsx + tailwind-merge)
│   ├── config.server.ts             # Configuração do servidor SSR
│   ├── error-capture.ts             # Captura de erros
│   └── error-page.ts                # Componente de página de erro
│
├── router.tsx                       # Instância do TanStack Router
├── server.ts                        # Entrada do servidor SSR (Nitro)
├── start.ts                         # Entry point TanStack Start
└── styles.css                       # Estilos globais + Tailwind CSS 4
```

---

## Rotas TanStack Router

Base URL: `/` (SSR mode)

| Path | Arquivo | Auth | Admin | Descrição |
|------|---------|------|-------|-----------|
| `/login` | `login.tsx` | Público | — | Tela de autenticação; redireciona se logado |
| `/` | `index.tsx` | Requer auth | — | Painel de Controle: KPIs, gráficos, mapa, tabelas |
| `/cotacoes` | `cotacoes.index.tsx` | Requer auth | — | Lista de cotações com filtro por status |
| `/cotacoes/nova` | `cotacoes.nova.tsx` | Requer auth | — | Upload XML da NF-e + formulário manual |
| `/cotacoes/resultado` | `cotacoes.resultado.tsx` | Requer auth | — | Ranking de transportadoras; botão de contratar |
| `/contratacoes` | `contratacoes.tsx` | Requer auth | — | Lista de contratos; PDF, CT-e, cancelar |
| `/rastreamento` | `rastreamento.tsx` | Requer auth | — | Timeline de rastreamento por contrato |
| `/transportadoras` | `transportadoras.tsx` | Requer auth | — | CRUD transportadoras + upload tabela .xlsx |
| `/usuarios` | `usuarios.tsx` | Requer auth | Admin | Gestão de usuários da empresa |
| `/auditoria` | `auditoria.tsx` | Requer auth | Admin | Trilha de auditoria |
| `/logs` | `logs.tsx` | Requer auth | Admin | Logs do sistema |

### Guard de autenticação (`__root.tsx`)

```typescript
// AppLayout verifica autenticação a cada render
useEffect(() => {
  if (!loading && !user && pathname !== "/login") {
    navigate({ to: "/login", replace: true });
  }
}, [loading, user, pathname]);
```

---

## Hook: useAuth

Singleton global via `AuthProvider`. Persiste sessão em `localStorage`.

**Estado:**
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `user` | `AuthUser \| null` | Dados do usuário logado |
| `token` | `string \| null` | Token de sessão atual |
| `loading` | `boolean` | Hidratação inicial em andamento |
| `isAdmin` | `boolean` | `user?.role === "Admin"` |

**Ações:**
| Ação | Descrição |
|------|-----------|
| `login(email, password)` | POST `/login` → armazena token + user em localStorage |
| `logout()` | POST `/logout` → limpa localStorage → redireciona para `/login` |

**Hidratação inicial:**
```typescript
// Ao montar, se há token em localStorage, verifica via GET /me
// Se inválido → limpa estado e redireciona para login
api.get<AuthUser>("/me")
  .then(u => { setUser(u); setStoredUser(u); })
  .catch(() => { setToken(null); setUser(null); })
```

---

## Cliente HTTP (`src/lib/api.ts`)

### Configuração

- **Base URL:** `/api/v1` (via Vite proxy → `http://localhost:8000`)
- **Autenticação:** `Authorization: Bearer {token}` (lido de `localStorage`)
- **Sem cookies** — token armazenado no cliente

### Interface

```typescript
api.get<T>(path)                    // GET
api.post<T>(path, body?)            // POST (JSON ou FormData)
api.put<T>(path, body?)             // PUT
api.patch<T>(path, body?)           // PATCH
api.delete<T>(path)                 // DELETE
```

### Erros

- Lança `Error(body.message || "Erro {status}")` para respostas não-ok
- TanStack Query captura e disponibiliza via `error.message`

---

## Componentes Compartilhados

### StatusBadge
**Props:** `status` (string), `variant?`
**Renderiza:** Badge colorido. Cores mapeadas por status (`VALIDA` → verde, `CANCELADA` → vermelho, `Aguardando Transportadora` → amarelo, `Entregue` → verde, etc.)

### MetricCard
**Props:** `title`, `value`, `label?`, `icon?`
**Renderiza:** Card de KPI do painel de controle.

### PageHeader
**Props:** `title`, `description?`, `action?` (ReactNode)
**Renderiza:** Cabeçalho de seção com título, subtítulo e botão de ação primária.

### AppSidebar

Sidebar colapsível com ícone/texto. Grupos de navegação:

| Grupo | Itens | Visível para |
|-------|-------|-------------|
| Principal | Painel, Cotações, Contratações, Rastreamento | Todos |
| Cadastros | Transportadoras | Todos |
| Administração | Usuários, Auditoria, Logs | Admin only |

Rodapé exibe nome + role do usuário logado e botão de logout.

---

## Fluxo de dados

```mermaid
flowchart TD
    Login[/login] -->|api.post /login| Auth[useAuth]
    Auth -->|armazena token + user| LS[localStorage]
    Route[Qualquer rota autenticada] -->|useQuery| Query[TanStack Query]
    Query -->|api.get/post| API[api.ts fetch]
    API -->|Authorization Bearer| Backend[Laravel :8000]
    Backend -->|JSON| Query
    Query -->|data| Route
    Route -->|useMutation| Mutation[TanStack Query]
    Mutation -->|api.post/patch/delete| Backend
```

---

## Diagrama de Navegação

```mermaid
flowchart TD
    Login[/login] -->|autenticado| Painel[/ - Painel]
    Painel -->|clica Cotações| Cotacoes[/cotacoes]
    Cotacoes -->|Nova Cotação| Nova[/cotacoes/nova - Upload XML]
    Nova -->|Cotar| Resultado[/cotacoes/resultado - Ranking]
    Resultado -->|Contratar| Contratos[/contratacoes]
    Contratos -->|PDF| PDF[Download Solicitação Coleta]
    Contratos -->|Rastrear| Rastreamento[/rastreamento]
    Painel -->|admin| Usuarios[/usuarios]
    Painel -->|admin| Auditoria[/auditoria]
    Painel -->|admin| Logs[/logs]
    Painel -->|cadastros| Transportadoras[/transportadoras]
```
