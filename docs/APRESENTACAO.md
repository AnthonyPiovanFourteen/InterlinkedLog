# InterlinkedLog

## Plataforma SaaS de Cotação e Contratação de Fretes

---

## 1. O Problema

Hoje, uma empresa que precisa transportar carga:

1. Consulta várias transportadoras por telefone / WhatsApp / e-mail
2. Aguarda retorno de cada uma
3. Compara preços manualmente (planilhas, anotações)
4. Entra em contato novamente para contratar
5. Acompanha a entrega por canais externos

**Resultado:** perda de tempo, retrabalho, erros, falta de histórico.

---

## 2. A Solução InterlinkedLog

Centraliza em uma única plataforma:

```
UPLOAD XML → COTAÇÃO → CONTRATAÇÃO → RASTREAMENTO → PAINEL DE CONTROLE
```

O usuário faz upload do XML da NF-e, o sistema extrai automaticamente os dados e cota contra as tabelas de frete das transportadoras cadastradas.

---

## 3. Stack Tecnológica

| Camada | Tecnologia |
|--------|------------|
| Frontend | React 19 + TypeScript + TailwindCSS v4 + TanStack Start |
| Roteamento | TanStack Router (file-based) |
| Dados | TanStack Query |
| UI | shadcn/ui + Radix + Recharts |
| Mapa | react-simple-maps |
| Backend | PHP 8.4 + Laravel 11 |
| Arquitetura | Hexagonal + DDD |
| Persistência | SQLite |
| Autenticação | Tokens UUID + Middleware |
| PDF | barryvdh/laravel-dompdf |
| Testes | 30 cenários com curl |

---

## 4. Perfis de Usuário

| Perfil | Permissões |
|--------|-----------|
| Admin | Gestão completa: dashboard, cotações, contratações, rastreamento, transportadoras, usuários, auditoria, logs |
| Usuário | Operacional: dashboard, cotações, contratações, rastreamento, transportadoras |

### Tenant Isolation

- Cada usuário pertence a **uma única empresa**
- Usuário **nunca** visualiza dados de outra empresa
- Toda query de banco é escopada por `company_id`
- Middleware injeta `company_id` do token em toda requisição

---

## 5. Regras de Negócio

### 5.1 Tabelas de Frete

Cadastradas pelo Admin via upload de planilha `.xlsx`.

**Estrutura (2 abas):**

**Aba 1 — Rotas:** cidade origem × cidade destino × faixa de peso × prazo

| Origem | Destino | 0-30kg | 31-100kg | 101-300kg | Prazo |
|--------|---------|--------|----------|-----------|-------|
| São Paulo | Curitiba | R$ 85,50 | R$ 142,00 | R$ 285,00 | 2d |

**Aba 2 — Taxas:**

| Parâmetro | Exemplo | Tipo |
|-----------|---------|------|
| Ad Valorem | 0,30% | % sobre valor NF |
| GRIS | R$ 18,90 | Taxa fixa |
| Despacho | R$ 25,00 | Taxa fixa |
| Pedágio | 5,00% | % sobre frete |
| Frete Mínimo | R$ 50,00 | Valor mínimo |
| Cubagem | 300 kg/m³ | Fator de conversão |
| TDE | R$ 40,00 | Taxa fixa |

### 5.2 Motor de Cotação

**Entrada — upload de XML da NF-e:**

O sistema extrai automaticamente do XML:
- Nº NF
- CNPJ Remetente / Destinatário
- CEP Origem / Destino
- Peso, caixas, cubagem
- Valor da mercadoria

**Processamento (automático):**

```
1. CEPs → cidades (ex: 01000-000 → São Paulo/SP)
2. Busca transportadoras com tabela ativa para a rota
3. Encontra faixa de peso correspondente
4. Calcula: frete base + ad valorem + gris + despacho + pedágio + tde
5. Aplica frete mínimo
6. Ordena e ranqueia resultados
```

**Saída — ranking comparativo:**

| Transportadora | Prazo | Frete | Taxas | Total |
|---------------|-------|-------|-------|-------|
| 🏆 Braspress | 2d | R$ 85,50 | R$ 73,00 | R$ 158,50 |
| Jamef | 3d | R$ 92,00 | R$ 68,00 | R$ 160,00 |
| Rodonaves | 2d | R$ 90,00 | R$ 72,00 | R$ 162,00 |

Destaques visuais: Melhor Preço, Melhor Prazo, Melhor Custo-Benefício.

**Status de cotação:**

```
VALIDA (7 dias) → CONTRATADA / EXPIRADA / CANCELADA
```

### 5.3 Contratação

- Operador seleciona a transportadora no ranking
- Sistema gera PDF: **Solicitação de Coleta / Ordem de Coleta**
- PDF contém: contratante, transportadora, carga, valores, prazo, assinaturas
- CT-e fica como **"Aguardando Transportadora"** (a transportadora emite depois)
- Operador registra o nº do CT-e quando a transportadora informar

### 5.4 Rastreamento

Controle operacional interno. Status **livre** com sugestões pré-definidas:

```
Coleta Agendada → Coletado → Em Rota → Chegou ao Destino → Saiu para Entrega → Entregue
```

O operador pode digitar qualquer status personalizado.

**Comparativo de prazo:**
- Calcula automaticamente `data_entrega − data_coleta`
- Compara com o prazo contratado
- Exibe ✅ "No prazo" ou ⚠️ "Atraso de X dias"

**Desempenho da transportadora:**
- Backend calcula % de entregas no prazo por transportadora
- Exibido no resultado da cotação para decisão fundamentada

### 5.5 Painel de Controle

Dashboard + Relatórios unificados em uma única tela:

- **4 Cards:** Cotações, Contratações, Valor em frete, Transportadora top
- **4 KPIs:** Taxa de conversão, Ticket médio, Economia, Prazo médio
- **Gráficos:** Donut (status de cotações) + Barras (top transportadoras)
- **Mapa do Brasil:** Rotas ativas com origem → destino
- **Tabelas:** Top transportadoras + Rotas mais utilizadas

---

## 6. Tour pelo Sistema

### 6.1 Login

```
http://localhost:3000
Email: admin@interlinked.io
Senha: admin123
```

### 6.2 Menu (Admin)

| Grupo | Itens |
|-------|-------|
| Principal | Painel, Cotações, Contratações, Rastreamento |
| Cadastros | Transportadoras (+ Tabelas de Frete) |
| Administração | Usuários, Auditoria, Logs |

### 6.3 Fluxo Principal

```
1. Transportadoras → cadastra transportadora + upload tabela .xlsx
2. Cotações → upload XML NF-e → sistema preenche automaticamente → Cotar
3. Resultado → compara transportadoras → Seleciona a melhor
4. Contratações → PDF Solicitação de Coleta → registra CT-e
5. Rastreamento → timeline → atualiza status → monitora prazo
6. Painel → visão consolidada com mapa, gráficos e KPIs
```

---

## 7. API REST

Base: `/api/v1`

### Auth
```
POST /login      GET /me        POST /logout     POST /register
```

### Quotations
```
GET    /quotations              ← listar com filtro
POST   /quotations              ← criar + processar motor
POST   /quotations/parse-xml    ← extrair dados do XML da NF-e
GET    /quotations/{id}         ← detalhe com resultados
POST   /quotations/{id}/cancel  ← cancelar
```

### Contracts
```
GET    /contracts               ← listar
POST   /contracts               ← criar a partir de cotação
GET    /contracts/{id}          ← detalhe
GET    /contracts/{id}/pdf      ← download Solicitação de Coleta
PATCH  /contracts/{id}/cte      ← registrar CT-e
POST   /contracts/{id}/cancel   ← cancelar
```

### Tracking
```
GET    /tracking                ← listar com eventos
GET    /tracking/{contractId}   ← eventos do contrato
POST   /tracking/{contractId}/events ← adicionar evento
```

### Reports
```
GET    /reports/dashboard       ← KPIs + conversão + status
GET    /reports/detailed        ← ranking + rotas + valores
GET    /carriers/{id}/performance ← % entregas no prazo
```

### CRUD
```
/api/users     /api/carriers    /api/freight-tables
/api/companies/{id}
```

### Sistema (Admin)
```
GET/POST /audit-logs    GET/POST /system-logs
```

---

## 8. Arquitetura

```
backend/app/
├── Domain/
│   ├── Entities/          # DTOs imutáveis (readonly)
│   ├── Repositories/      # Interfaces (desacopladas de infra)
│   └── Services/          # Interfaces (Auth, Quotation, Report)
├── Application/
│   └── UseCases/Auth/     # Casos de uso
├── Infrastructure/
│   ├── Repositories/
│   │   └── Eloquent/      # Implementação SQLite via Eloquent
│   └── Services/          # TokenAuth, QuotationEngine, ReportGenerator
├── Http/
│   ├── Controllers/Api/   # REST endpoints
│   └── Middleware/        # TokenAuth, Tenant, ForceJson
└── Models/                # Eloquent Models

src/
├── routes/                # File-based (TanStack Router)
├── components/ui/         # shadcn/ui primitives
├── shared/components/     # Átomos, Moléculas, Organismos
├── hooks/                 # useAuth
└── lib/                   # API client, utils, formatters
```

---

## 9. Como Rodar

```bash
./start.sh     # backend :8000 + frontend :3000
./stop.sh      # para tudo
./test-all.sh  # 30 testes automatizados
```

```
Frontend: http://localhost:3000
Backend:  http://localhost:8000/api/v1
Login:    admin@interlinked.io / admin123
```

---

## 10. Testes

30 cenários cobrindo todo o fluxo:

```
Auth (4)            Login, login inválido, /me, logout
Dashboard (4)       KPIs, conversion_rate, system-logs, audit-logs
Carriers (2)        Listar, performance
Freight Tables (2)  Listar, detalhe
Quotations (5)      XML parse, criar, listar, detalhe, cancelar
Contracts (4)       Criar, listar, CT-e, PDF download
Tracking (4)        Listar, detalhe, evento padrão, evento customizado
Users (2)           Listar, criar
Company (1)         Visualizar empresa
Tenant (2)          Isolamento, token invalidado
```

```bash
./test-all.sh
```
