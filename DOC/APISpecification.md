# 02 — API REST

## Autenticação de endpoints

Todos os endpoints (exceto `POST /login` e `POST /register`) exigem o header
`Authorization: Bearer {token}`. O token é um UUID de 64 caracteres hexadecimais gerado
no login e armazenado em `localStorage` pelo frontend.

Base URL: `/api/v1`

---

## Auth

| Método | Path | Auth | Descrição |
|--------|------|------|-----------|
| POST | `/login` | Público | Autentica e retorna token + dados do usuário |
| POST | `/register` | Público | Cria novo usuário e empresa |
| POST | `/logout` | Requer token | Invalida o token atual |
| GET | `/me` | Requer token | Retorna dados do usuário autenticado |

### DTOs

**POST `/login`** (JSON):
```json
{
  "email": "admin@interlinked.io",
  "password": "string"
}
```

**Resposta de login:**
```json
{
  "token": "64 chars hex",
  "user": {
    "id": "uuid",
    "name": "string",
    "email": "string",
    "role": "Admin | Usuário",
    "company_id": "uuid",
    "status": "Ativo | Inativo"
  }
}
```

**POST `/register`** (JSON):
```json
{
  "name": "string",
  "email": "string",
  "password": "string",
  "company_name": "string",
  "company_cnpj": "string"
}
```

---

## Users

Prefixo: `/users` — `apiResource` completo (index, show, store, update, destroy)

| Método | Path | Descrição |
|--------|------|-----------|
| GET | `/users` | Lista usuários da empresa (tenant-scoped) |
| GET | `/users/{id}` | Detalha usuário |
| POST | `/users` | Cria usuário |
| PUT | `/users/{id}` | Atualiza usuário |
| DELETE | `/users/{id}` | Remove usuário |

---

## Companies

| Método | Path | Descrição |
|--------|------|-----------|
| GET | `/companies/{id}` | Retorna dados da empresa do usuário autenticado |

---

## Carriers (Transportadoras)

Prefixo: `/carriers` — `apiResource` completo

| Método | Path | Descrição |
|--------|------|-----------|
| GET | `/carriers` | Lista o catálogo de transportadoras |
| GET | `/carriers/{id}` | Detalha transportadora |
| POST | `/carriers` | Cadastra transportadora |
| PUT | `/carriers/{id}` | Atualiza transportadora |
| DELETE | `/carriers/{id}` | Remove transportadora |

> Transportadora é **catálogo global**, compartilhado entre tenants — a
> "Transportadora XYZ" é a mesma empresa para todos. Escrita restrita a `Admin`.
> Quem é privado por tenant é a **tabela de frete** e a **credencial de API**,
> porque cada empresa negocia o próprio preço.

Cada item traz o estado de integração:

```json
{
  "id": "uuid", "name": "Rodonaves", "cnpj": "...",
  "gateway": "rodonaves",   // adaptador que a atende, ou null
  "integrated": false       // se este tenant já configurou credencial
}
```
| GET | `/carriers/{carrierId}/performance` | % entregas no prazo da transportadora |

**POST/PUT `/carriers`** (JSON):
```json
{
  "name": "string",
  "cnpj": "string (18 chars)",
  "origin_city": "string",
  "origin_uf": "string (2 chars)",
  "contact_name": "string | null",
  "contact_phone": "string | null",
  "contact_email": "string | null",
  "status": "Ativo | Inativo"
}
```

---

## Carrier Credentials (Credenciais de API)

Credenciais das transportadoras, **por tenant**. Escrita restrita a `Admin`.

| Método | Path | Descrição |
|--------|------|-----------|
| GET | `/carrier-credentials/gateways` | Gateways disponíveis e o que cada um exige |
| GET | `/carrier-credentials` | Credenciais do tenant |
| POST | `/carrier-credentials` | Configura integração |
| PATCH | `/carrier-credentials/{id}` | Atualiza segredos ou ativa/desativa |
| DELETE | `/carrier-credentials/{id}` | Remove integração |

**Os segredos NUNCA retornam numa resposta.** A listagem informa apenas quais
chaves estão preenchidas:

```json
{
  "id": "uuid", "carrier_id": "uuid", "gateway": "jamef", "active": true,
  "configured_secrets": ["username", "password", "documento_devedor"]
}
```

**GET `/carrier-credentials/gateways`** — o que cada adaptador declara. É o que
permite à interface montar o formulário sozinha:

```json
{
  "name": "jamef",
  "required_secrets": ["username", "password", "documento_devedor"],
  "secret_hints": {
    "documento_devedor": "CNPJ de quem paga o frete — normalmente o da sua própria empresa."
  },
  "documentation_url": "https://developers.jamef.com.br/documentacao"
}
```

**POST `/carrier-credentials`** (JSON):
```json
{
  "carrier_id": "uuid",
  "gateway": "jamef",
  "secrets": { "username": "...", "password": "...", "documento_devedor": "..." },
  "active": true
}
```

Segredo obrigatório ausente ou vazio → `422` com a lista do que falta.
Credencial já existente para a transportadora → `422`.

No `PATCH`, quando `secrets` vem, **substitui o conjunto inteiro** — mesclagem
parcial deixaria ambíguo se chave omitida significa manter ou apagar. Para só
desativar, envie apenas `active`.

---

## Freight Tables (Tabelas de Frete)

Prefixo: `/freight-tables` — `apiResource` completo

| Método | Path | Descrição |
|--------|------|-----------|
| GET | `/freight-tables` | Lista tabelas de frete |
| GET | `/freight-tables/{id}` | Detalha tabela com rotas, faixas de peso e taxas |
| POST | `/freight-tables` | Cria tabela de frete (upload `.xlsx` com 2 abas) |
| PUT | `/freight-tables/{id}` | Atualiza tabela |
| DELETE | `/freight-tables/{id}` | Remove tabela |

**POST `/freight-tables`** (`multipart/form-data`):
```
carrier_id:   uuid
name:         string
valid_from:   date (YYYY-MM-DD)
valid_until:  date (YYYY-MM-DD)
file:         .xlsx (2 abas: Rotas + Taxas)
```

---

## Quotations (Cotações)

> **A cotação chega em duas etapas.** O `POST` responde de imediato com os
> resultados das tabelas locais e despacha um job por transportadora integrada.
> O `GET /quotations/{id}` mostra os resultados crescendo, com `carriers.pending`
> dizendo quem ainda falta.

Cada resultado traz `source` (`tabela` ou `api`) e, quando vem de API, o
`gateway`, o `service` escolhido e o `protocol` da transportadora — sem isso não
há como auditar de onde saiu o preço de um contrato.

O `GET /quotations/{id}` acrescenta:

```json
{
  "results": [ /* reordenados a cada leitura: os de API chegam depois */ ],
  "carriers": {
    "pending": ["loggi"],
    "attempts": [
      { "gateway": "braspress", "status": "cotada", "message": null },
      { "gateway": "jadlog", "status": "nao_atende", "message": null },
      { "gateway": "rodonaves", "status": "indisponivel", "message": "Rodonaves (cotação) respondeu 503" }
    ]
  },
  "benchmark": { "sample": 4, "median_value": 412.5, "median_deadline": 3, "months": 6 }
}
```

| `status` | Significado |
|---|---|
| `pendente` | job despachado, ainda sem resposta |
| `cotada` | devolveu preço |
| `nao_atende` | resposta legítima de negócio — **não é erro** |
| `indisponivel` | falha da transportadora (timeout, 5xx, limite de requisições) |
| `erro` | falha nossa ou de configuração |

O `benchmark` é a mediana do que a empresa **efetivamente contratou** em rota
igual, com peso dentro de ±30%, nos últimos 6 meses. Mediana e não média porque
um frete atípico distorce.


| Método | Path | Auth | Descrição |
|--------|------|------|-----------|
| GET | `/quotations` | Requer token | Lista cotações da empresa com filtros |
| POST | `/quotations` | Requer token | Cria cotação e processa motor de preços |
| GET | `/quotations/{id}` | Requer token | Detalha cotação com resultados por transportadora |
| POST | `/quotations/{id}/cancel` | Requer token | Cancela cotação |
| POST | `/quotations/parse-xml` | Requer token | Extrai dados do XML da NF-e |

**POST `/quotations/parse-xml`** (`multipart/form-data`):
```
file: XML da NF-e
```

**Resposta de parse-xml:**
```json
{
  "nf_number": "string",
  "sender_cnpj": "string",
  "receiver_cnpj": "string",
  "origin_cep": "string",
  "destination_cep": "string",
  "weight": "float",
  "boxes": "int",
  "volume": "float",
  "cargo_value": "float"
}
```

**POST `/quotations`** (JSON):
```json
{
  "nf_number": "string",
  "sender_cnpj": "string",
  "receiver_cnpj": "string",
  "origin_cep": "string",
  "destination_cep": "string",
  "weight": "float",
  "boxes": "int",
  "volume": "float",
  "cargo_value": "float"
}
```

**Resposta de cotação (GET `/quotations/{id}`):**
```json
{
  "id": "uuid",
  "nf_number": "string",
  "origin_city": "string",
  "destination_city": "string",
  "destination_state": "string",
  "weight": "float",
  "cargo_value": "float",
  "status": "VALIDA | CONTRATADA | EXPIRADA | CANCELADA",
  "valid_until": "date",
  "results": [
    {
      "id": "uuid",
      "carrier_id": "uuid",
      "carrier_name": "string",
      "freight_value": "float",
      "fees": "float",
      "final_value": "float",
      "deadline": "int (dias)",
      "fees_breakdown": {
        "ad_valorem": "float",
        "gris": "float",
        "despacho": "float",
        "pedagio": "float",
        "tde": "float"
      }
    }
  ]
}
```

**Filtros GET `/quotations`:**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `status` | string | `VALIDA`, `CONTRATADA`, `EXPIRADA`, `CANCELADA` |
| `page` | int | Paginação |

---

## Contracts (Contratações)

| Método | Path | Descrição |
|--------|------|-----------|
| GET | `/contracts` | Lista contratos da empresa |
| POST | `/contracts` | Cria contrato a partir de cotação |
| GET | `/contracts/{id}` | Detalha contrato |
| GET | `/contracts/{id}/pdf` | Download da Solicitação de Coleta (PDF) |
| PATCH | `/contracts/{id}/cte` | Registra número do CT-e |
| POST | `/contracts/{id}/cancel` | Cancela contrato |

**POST `/contracts`** (JSON):
```json
{
  "quotation_id": "uuid",
  "quotation_result_id": "uuid"
}
```

**PATCH `/contracts/{id}/cte`** (JSON):
```json
{
  "cte_number": "string"
}
```

**Resposta de contrato:**
```json
{
  "id": "uuid",
  "carrier_name": "string",
  "nf_number": "string",
  "origin_city": "string",
  "destination_city": "string",
  "destination_state": "string",
  "freight_value": "float",
  "fees": "float",
  "final_value": "float",
  "deadline": "int",
  "status": "Aguardando Transportadora | Em Andamento | Entregue | Cancelado",
  "document_number": "string",
  "cte_number": "string | null",
  "cancelled_at": "datetime | null",
  "cancel_reason": "string | null"
}
```

---

## Tracking (Rastreamento)

| Método | Path | Descrição |
|--------|------|-----------|
| GET | `/tracking` | Lista contratos com eventos de rastreamento |
| GET | `/tracking/{contractId}` | Eventos de um contrato específico |
| POST | `/tracking/{contractId}/events` | Adiciona evento — **só em contrato manual** |
| PATCH | `/tracking/{contractId}/events/{eventId}` | Registra observação — vale nos dois modos |

> **Automático e manual não se misturam.** O modo é definido na contratação e
> não muda: `automatico` quando a transportadora tem API com credencial ativa,
> `manual` caso contrário.
>
> Em contrato automático, `POST .../events` devolve **422** — quem alimenta é o
> sincronismo (`php artisan tracking:sync`, agendado a cada 30 min). O único
> input manual permitido ali é a observação.

**POST `/tracking/{contractId}/events`** (JSON):
```json
{
  "title": "string (ex: 'Coletado', 'Em Rota', qualquer texto livre)",
  "date": "date (YYYY-MM-DD)",
  "time": "string (HH:MM)",
  "observation": "string | null"
}
```

**Status sugeridos (não obrigatórios):**
```
Coleta Agendada → Coletado → Em Rota → Chegou ao Destino → Saiu para Entrega → Entregue
```

---

## Reports (Relatórios)

| Método | Path | Descrição |
|--------|------|-----------|
| GET | `/reports/dashboard` | KPIs + taxa de conversão + gráficos + mapa de rotas |
| GET | `/reports/detailed` | Ranking de transportadoras + rotas + valores detalhados |
| GET | `/carriers/{carrierId}/performance` | % de entregas no prazo da transportadora |

**Resposta `/reports/dashboard`:**
```json
{
  "total_quotations": "int",
  "total_contracts": "int",
  "total_freight_value": "float",
  "top_carrier": "string",
  "conversion_rate": "float (%)",
  "avg_ticket": "float",
  "savings": "float",
  "avg_deadline": "float",
  "quotation_status": { "VALIDA": "int", "CONTRATADA": "int", ... },
  "top_carriers": [{ "name": "string", "count": "int" }],
  "routes": [{ "origin": "string", "destination": "string", "count": "int" }]
}
```

---

## Audit Logs

| Método | Path | Auth | Descrição |
|--------|------|------|-----------|
| GET | `/audit-logs` | Requer token | Lista registros de auditoria da empresa |
| POST | `/audit-logs` | Requer token | Registra entrada de auditoria |

---

## System Logs

| Método | Path | Auth | Descrição |
|--------|------|------|-----------|
| GET | `/system-logs` | Requer token | Lista logs do sistema da empresa |
| POST | `/system-logs` | Requer token | Registra log de sistema |

---

## Tabela Resumo Rápida

| Prefixo | Controller | Endpoints | Auth |
|---------|-----------|-----------|------|
| `/login`, `/register` | AuthController | 2 | Público |
| `/logout`, `/me` | AuthController | 2 | Requer token |
| `/users` | UserController | 5 (CRUD) | Requer token |
| `/companies` | CompanyController | 1 (show) | Requer token |
| `/carriers` | CarrierController | 5 + performance | Requer token |
| `/freight-tables` | FreightTableController | 5 (CRUD) | Requer token |
| `/quotations` | QuotationController | 5 | Requer token |
| `/contracts` | ContractController | 6 | Requer token |
| `/tracking` | TrackingController | 3 | Requer token |
| `/reports` | ReportController | 2 | Requer token |
| `/audit-logs` | AuditLogController | 2 | Requer token |
| `/system-logs` | SystemLogController | 2 | Requer token |
