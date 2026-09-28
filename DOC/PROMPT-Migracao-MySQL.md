# Prompt de Implementação — Preparação e Migração para MySQL

> **Como usar:** entregue este documento inteiro a um agente de codificação com
> acesso ao repositório. Ele foi escrito para ser autocontido. Ao final de cada
> fase o agente deve PARAR e reportar — não execute tudo de uma vez.

---

## PAPEL

Você é um engenheiro backend sênior especialista em PHP/Laravel e MySQL.
Vai trabalhar num repositório existente, em produção futura, mantido por **uma
única pessoa**. Priorize correção e reversibilidade sobre elegância. Não
reescreva o que não foi pedido.

---

## CONTEXTO DO SISTEMA

**InterlinkedLog** — plataforma SaaS multi-tenant de cotação, contratação e
rastreamento de fretes. Recebe NF-e, calcula o melhor frete entre transportadoras
cadastradas, gera contrato em PDF e acompanha o status da carga.

**Stack:**
- Backend: Laravel 11, PHP 8.4, SQLite (a migrar para MySQL 8), dompdf
- Frontend: React 19 + TanStack Start (não será alterado neste trabalho)
- Empacotamento: Docker Compose (`backend` + `frontend`)

**Arquitetura — respeite rigorosamente:**

O backend usa **arquitetura hexagonal**. As dependências apontam para dentro.

```
backend/app/
├── Domain/                    # NÚCLEO — não pode conhecer Laravel/Eloquent
│   ├── Entities/              # objetos imutáveis (Carrier, Contract, Quotation…)
│   ├── Repositories/          # PORTAS (interfaces)
│   └── Services/              # PORTAS (interfaces)
├── Application/UseCases/      # orquestração (hoje só existe para Auth)
├── Infrastructure/
│   ├── Repositories/Eloquent/ # ADAPTADORES — implementam as portas
│   └── Services/              # QuotationEngine, TokenAuthService, ReportGenerator
├── Http/Controllers/Api/      # 11 controllers
├── Http/Middleware/           # TokenAuthMiddleware, TenantMiddleware, ForceJsonResponse
└── Models/                    # Eloquent — usados SOMENTE dentro de Infrastructure/
```

Ligações porta→adaptador ficam em `app/Providers/AppServiceProvider.php`.

**Convenções existentes que você deve seguir:**
- Entidades de domínio são imutáveis, com construtor de argumentos nomeados
- Repositórios convertem Model↔Entity por método privado `toEntity()`
- Repositórios expõem `save()` que faz `updateOrCreate`
- Autenticação: `TokenAuthMiddleware` popula `request->attributes` com
  `auth_user`, `user_id`, `company_id`, `user_role`
- Idioma: mensagens de API em português; código e nomes em inglês

**Estado atual relevante:**
- `QUEUE_CONNECTION=sync`, `CACHE_STORE=file`, `SESSION_DRIVER=file`
- `tests/` contém apenas os dois `ExampleTest` do skeleton — não há cobertura
- `backend/test-all.sh` é smoke test em shell contra a API HTTP
- Nenhuma transação de banco em todo o projeto
- Nenhum índice, exceto `users.email` unique

---

## OBJETIVO GERAL

Preparar o sistema para migrar de SQLite para MySQL 8, corrigindo antes os
defeitos que o SQLite hoje mascara, e então executar a migração.

**Premissa central que justifica a ordem das fases:** o SQLite tem escritor
único e serializa todas as escritas. O código não usa transações. Hoje o banco
mascara acidentalmente condições de corrida. Sob MySQL/InnoDB, com concorrência
real, esses defeitos ficam alcançáveis. **Migrar sem corrigir isso é uma
regressão, não um avanço.**

---

## REGRAS DE EXECUÇÃO — LEIA ANTES DE COMEÇAR

1. **Pare ao fim de cada fase** e reporte: arquivos alterados, decisões tomadas,
   testes rodados e resultado. Aguarde aprovação antes da fase seguinte.
2. **Não faça scope creep.** Não corrija bugs que não estão listados aqui. Se
   encontrar algum, **anote no relatório** e siga em frente.
3. **Não reformate código** que você não estava alterando. Nada de mudar aspas,
   indentação ou ordem de imports em bloco.
4. **Não altere o frontend** (`src/`, `package.json`, `vite.config.ts`).
5. **Não toque nestes itens** — estão fora do escopo, já foram avaliados:
   `APP_DEBUG`, `APP_KEY`, `.env` versionado, rate limiting de login, token em
   `localStorage`, `POST /register`, `php artisan serve`, `cepMap` hardcoded.
6. **Um commit por fase**, mensagem descritiva em português. Ver a seção
   "CONVENÇÕES DE COMMIT E PULL REQUEST" no fim deste documento — ela é
   obrigatória, não sugestão.
7. **Rode os testes ao fim de cada fase.** Se algo ficar vermelho, corrija antes
   de reportar. Não reporte fase concluída com teste falhando.
8. Se uma instrução daqui conflitar com o que você encontrar no código, **pare e
   pergunte**. Não improvise.

---

# FASE 0 — Rede de segurança e correção de concorrência

**Bloqueia todas as demais. Nada aqui é opcional.**

## 0.1 — Testes de caracterização (FAÇA PRIMEIRO)

Antes de alterar qualquer linha de lógica, escreva testes que capturem o
comportamento **atual** do fluxo crítico. Eles são a rede que protege as
mudanças seguintes.

Crie `backend/tests/Feature/` com cobertura de:

- `POST /api/v1/quotations` — cotação retorna resultados para uma rota semeada,
  com valores de frete, prazo e taxas conferidos numericamente
- `POST /api/v1/contracts` — contratação a partir de cotação válida cria
  contrato, muda status da cotação para `CONTRATADA` e gera evento de rastreio
- `POST /api/v1/contracts` com cotação de **outro tenant** deve retornar 404
- `GET /api/v1/contracts` lista apenas contratos do tenant autenticado
- `POST /api/v1/login` com credencial válida e inválida

Use `RefreshDatabase` e o seeder existente (`php artisan app:seed` /
`DatabaseSeeder`). Se o seeder não permitir criar um segundo tenant para o teste
de isolamento, crie os dados diretamente no teste.

**Critério de aceite:** `php artisan test` verde, com no mínimo 5 testes de
feature além dos `ExampleTest`.

## 0.2 — Transações e trava de concorrência

Há três fluxos que fazem múltiplas escritas sem atomicidade.

**(a) `app/Http/Controllers/Api/ContractController.php::store`**

Hoje faz três escritas independentes: salva contrato, regrava a cotação com
status `CONTRATADA`, cria evento de rastreio. Falha no meio deixa contrato órfão
ou cotação contratada sem contrato.

Pior: entre a leitura da cotação e a escrita do contrato não há trava. Sob MySQL,
duas requisições concorrentes podem ambas ler a cotação como `VALIDA` e ambas
criar contrato — **dois contratos e dois CT-e para a mesma cotação**.

Exigido:
- Envolver as três escritas em `DB::transaction`
- Reler a cotação **dentro** da transação com trava pessimista
  (`lockForUpdate`) e só então validar `status === STATUS_VALID`
- Adicionar ao contrato `ContractRepository` (a porta em `Domain/Repositories/`)
  um método de leitura com trava, ex.: `findByIdForUpdate(string $id)`.
  A porta permanece agnóstica de Eloquent; o `lockForUpdate` fica no adaptador.

**(b) `app/Infrastructure/Repositories/Eloquent/EloquentQuotationRepository.php::save`**

Faz `QuotationResult::where('quotation_id',$id)->delete()` seguido de N inserts.
Falha no meio **apaga permanentemente** os resultados da cotação. Note que este
método é chamado também pelo `ContractController::store`, então a contratação
hoje apaga e recria todos os resultados da cotação.

Exigido: envolver o `delete` + inserts em transação.

**(c) `app/Infrastructure/Repositories/Eloquent/EloquentFreightTableRepository.php::save`**

Mesmo padrão, com rotas, faixas de peso e taxas aninhadas. Envolver em transação.

**Critério de aceite:**
- `grep -rn "DB::transaction" backend/app/` retorna ao menos 3 ocorrências
- Existe teste que prova que duas contratações concorrentes da mesma cotação
  resultam em **exatamente um** contrato (simule sequencialmente se não puder
  paralelizar — o essencial é que a segunda tentativa receba 422)
- Testes da 0.1 continuam verdes

## 0.3 — Unificar geração de UUID

Existem duas estratégias na mesma coluna de chave primária:

| Origem | Função | Resultado |
|---|---|---|
| 9 controllers + `RegisterUserUseCase` + 2 repositórios | `uuid_create()` | UUID v4 **aleatório** |
| `save()` dos repositórios (`?? Str::orderedUuid()`) | `Str::orderedUuid()` | v4 **monotônico** |

Dois problemas:

1. `uuid_create()` vem de `symfony/polyfill-uuid`, que **não está declarado em
   `backend/composer.json`** — é dependência transitiva. Funciona por acidente.
2. No SQLite a PK é só um índice comum. **No InnoDB a chave primária é o índice
   clusterizado**: inserir UUID aleatório espalha escritas pelo B-tree, causando
   page splits e fragmentação, e cada índice secundário carrega os 36 bytes da PK.

Exigido:
- Substituir **todas** as ocorrências de `uuid_create()` por
  `Str::orderedUuid()->toString()` (use `Illuminate\Support\Str`)
- Confirmar que nenhuma ocorrência de `uuid_create` resta:
  `grep -rn "uuid_create" backend/app/ backend/database/` deve retornar vazio
- **Não** migrar os UUIDs já existentes no banco. Dados antigos permanecem com
  ID aleatório; isso é aceitável e esperado.

**Critério de aceite:** grep vazio; testes verdes.

### ⛔ PARE. Reporte a Fase 0 e aguarde aprovação.

---

# FASE 1 — Consistência de dados e modelo de tenancy

A migração para MySQL é o momento mais barato para corrigir schema. Depois,
com dados de clientes reais, custa janela de manutenção e risco.

## 1.1 — Unificar `'Ativo'` / `'Ativa'`

Há duas grafias do mesmo status em uso, e elas se contradizem — **transportadora
criada pela API fica invisível ao motor de cotação**:

| Local | Valor atual |
|---|---|
| `Domain/Entities/Carrier.php:37` (`create()`) | `'Ativa'` |
| `CarrierController::update` (validação) | `in:Ativa,Inativa` |
| migration `create_carriers_table` (default) | `'Ativo'` |
| `DatabaseSeeder.php:80` (transportadoras) | `'Ativo'` |
| `Infrastructure/Services/QuotationEngine.php:45` | pula se `!== 'Ativo'` |
| `EloquentCarrierRepository::findByOrigin` | filtra `where('status','Ativa')` |

Exigido:
- Criar constantes no domínio (ex.: `Domain/Entities/CarrierStatus.php` com
  `ATIVA` / `INATIVA`), seguindo o estilo de `Domain/Entities/Role.php`
- **Padronizar em `'Ativa'`** (concorda com a validação da API e com o gênero de
  "transportadora"). Note que `users` usa `'Ativo'` e **deve permanecer assim** —
  são domínios distintos, não unifique os dois
- Substituir todos os literais por referência à constante
- Escrever migration de dados: `UPDATE carriers SET status='Ativa' WHERE status='Ativo'`
- Ajustar o `DatabaseSeeder` para usar a constante
- Corrigir o default da coluna na migration de schema

**Critério de aceite:** existe teste que cria transportadora **via API**, cria
tabela de frete para ela e prova que ela **aparece** no resultado de uma cotação.
Esse teste falha antes da correção e passa depois.

## 1.2 — Modelo de tenancy: catálogo global + tabela privada

**Decisão de negócio já tomada — implemente exatamente assim:**

- **`carriers` é catálogo GLOBAL**, compartilhado entre todos os tenants. A
  "Transportadora XYZ" é a mesma empresa para todo mundo.
- **`freight_tables` é PRIVADA por tenant**, porque cada empresa negocia seu
  próprio preço com a transportadora.

Estado atual, que está errado nos dois sentidos:
- `carriers` **tem** `company_id` nullable, mas `EloquentCarrierRepository`
  ignora a coluna em `findAll`, `findById`, `save` e `delete` — e
  `CarrierController` não lê `company_id` em nenhum método
- `freight_tables` **não tem** `company_id` e `findAll()` é irrestrito → tabelas
  de preço negociado vazam entre tenants concorrentes

Exigido:

**(a) `carriers` — remover `company_id`**
- Migration removendo a coluna `company_id` de `carriers`
- Leitura permanece livre para qualquer usuário autenticado
- **Escrita (`store`, `update`, `destroy`) restrita a `Role::ADMIN`** — leia
  `user_role` de `request->attributes`. Retorne 403 para não-admin
- Ajustar `Domain/Entities/Carrier.php` e o repositório se referenciarem company

**(b) `freight_tables` — adicionar `company_id` obrigatório**
- Migration adicionando `foreignUuid('company_id')->constrained()->cascadeOnDelete()`
- Índice único: `unique(['carrier_id','company_id','valid_from'])`
- Backfill: atribuir as tabelas existentes à empresa do seeder. Se houver mais de
  uma empresa no banco, **pare e pergunte** — não escolha por conta própria
- `FreightTableController` deve ler `company_id` de `request->attributes` e
  passá-lo adiante em `index`, `store`, `show`, `update`, `destroy`
- `FreightTable` (entidade de domínio) ganha `companyId`
- `EloquentFreightTableRepository`: **todos** os métodos passam a filtrar por
  `company_id`, incluindo `findById`, `findAll` e
  `findActiveByCarrierAndRoute` (este é chamado pelo `QuotationEngine` — a
  assinatura da porta precisará receber o `companyId`)
- `QuotationEngine::process` precisa do `companyId`; ele já recebe a entidade
  `Quotation`, que tem `companyId` — use de lá, não altere a assinatura pública
  sem necessidade

**(c) Global Scope — tornar o isolamento padrão**

Hoje o isolamento é feito por linhas repetidas à mão nos controllers
(`if ($x->companyId !== $companyId) return 404`). O `TenantMiddleware` **não
filtra nada** — só verifica se `company_id` está presente. Esse padrão já falhou
duas vezes.

- Implementar Global Scope do Eloquent aplicando `where company_id = ?` nos
  Models de entidades por tenant: `FreightTable`, `Contract`, `Quotation`,
  `User`, `AuditLog`, `SystemLog`
- O `company_id` do contexto deve vir de um binding de request, **não** de
  estado estático global — precisa funcionar em teste e em CLI
- **Manter as checagens manuais existentes nos controllers.** Elas viram
  defesa em profundidade. Não remova nada nesta fase.

**Critério de aceite:**
- Teste: tenant A **não** enxerga tabela de frete do tenant B em `index` nem em
  `show` (404)
- Teste: tenant A **enxerga** transportadora criada por outro tenant (catálogo
  global, comportamento esperado)
- Teste: usuário com `Role::USUARIO` recebe 403 ao tentar `POST /carriers`
- Teste: cotação do tenant A usa **apenas** tabelas de frete do tenant A

### ⛔ PARE. Reporte a Fase 1 e aguarde aprovação.

---

# FASE 2 — Ajustes de schema específicos de MySQL

Ainda rodando em SQLite. Prepare o schema para não gerar problema no destino.

## 2.1 — `timestamp` → `datetime`

Colunas afetadas: `users.last_access_at`, `contracts.cancelled_at`,
`subscriptions.created_at`, `audit_logs.created_at`, e todos os
`$table->timestamps()`.

No MySQL, `TIMESTAMP` tem faixa 1970–2038 e sofre conversão de fuso pela sessão;
`DATETIME` não sofre. Com `APP_TIMEZONE=America/Sao_Paulo` e servidor MySQL em
UTC por padrão, você ganha deslocamentos de 3 horas em algumas colunas e não em
outras, dentro da mesma linha.

Exigido: migration convertendo essas colunas para `datetime`. Em `timestamps()`,
use `$table->datetime('created_at')->nullable()` e `updated_at` equivalente, ou
`$table->timestamps()` com precisão explícita — documente a escolha.

## 2.2 — Índices

O InnoDB cria índice automaticamente em coluna de FK, então `company_id`,
`carrier_id` e `quotation_id` ficam cobertos sem ação. Faltam as colunas de
filtro puro, usadas em `where` pelos repositórios:

- `contracts.status`
- `carriers.status`
- `freight_tables.status`
- `quotations.status`
- Composto `contracts(company_id, status)` — padrão de
  `ContractRepository::findByCompany` com filtro
- `tracking_events(contract_id, date)` se houver ordenação por data

## 2.3 — Colação — ATENÇÃO, muda comportamento de negócio

`EloquentFreightTableRepository::findActiveByCarrierAndRoute` e
`EloquentCarrierRepository::findByOrigin` usam
`where('origin_city', 'like', $city)` — sem wildcard, é igualdade case-insensitive.

O `LIKE` do SQLite é case-insensitive **apenas para ASCII**. O
`utf8mb4_unicode_ci` do MySQL é case-insensitive **e accent-insensitive**. Ou
seja: `"Marilia"` passará a casar com `"Marília"` no MySQL, e hoje não casa.

Isso provavelmente é desejável, **mas é mudança no motor de cotação**.

Exigido:
- Definir charset/collation explicitamente na config de conexão
  (`utf8mb4` / `utf8mb4_unicode_ci`) — não herde o default do servidor
- Escrever teste documentando o comportamento esperado de casamento de cidade
  com e sem acento
- **Reportar essa mudança explicitamente** no relatório da fase

**Critério de aceite:** `php artisan migrate:fresh --seed` funciona; testes verdes.

### ⛔ PARE. Reporte a Fase 2 e aguarde aprovação.

---

# FASE 3 — Infraestrutura e migração efetiva

## 3.1 — Serviço MySQL no Compose

Editar `docker-compose.yml` (raiz do projeto):
- Adicionar serviço `mysql` (MySQL 8), com volume nomeado para persistência
- `healthcheck` com `mysqladmin ping`
- `backend` ganha `depends_on: mysql: condition: service_healthy`
- Credenciais via variáveis de ambiente, não hardcoded no compose

## 3.2 — Configuração da conexão

`backend/Dockerfile` **grava um `.env` em build time** via heredoc no `RUN`, com
`DB_CONNECTION=sqlite` fixo. Variáveis do compose ainda vencem (o Dotenv do
Laravel não sobrescreve env vars reais), mas é frágil e confuso.

Exigido:
- Remover a escrita de `.env` do `Dockerfile`; passar config por ambiente
- `DB_CONNECTION=mysql` + host, porta, base, usuário, senha
- O `CMD` roda `php artisan migrate --force` no boot e vai correr contra um MySQL
  ainda subindo. Adicionar espera pela disponibilidade do banco (retry com
  backoff), além do healthcheck do compose
- Atualizar `backend/.env.example` e `.env.example` da raiz

## 3.3 — Migração dos dados

- Script de migração SQLite → MySQL para os dados existentes, **idempotente**
- Verificar contagem por tabela antes e depois; abortar em divergência
- Não invente transformação de dados: se algum registro não couber no schema de
  destino, **pare e reporte**

## 3.4 — Backup

Hoje não existe backup — o volume é a única cópia dos dados. Sair do SQLite sem
resolver isso é trocar de banco sem tratar o risco maior.

- Script de `mysqldump` agendável, com retenção
- Documentar procedimento de restauração em `DOC/OperationsRunbook.md`
- **Testar a restauração**, não apenas o dump. Backup não verificado não é backup

**Critério de aceite:**
- `docker compose down -v && docker compose up --build -d` sobe do zero com MySQL
- `php artisan test` verde **contra MySQL**
- `backend/test-all.sh` passa
- Dump gerado e restaurado com sucesso em base limpa, com contagens conferidas

### ⛔ PARE. Reporte a Fase 3 e aguarde aprovação.

---

# FASE 4 — Consolidação

- Ampliar cobertura de testes: rastreamento, relatórios, auditoria, logs
- Rodar a suíte contra MySQL **e** SQLite se ainda houver suporte a ambos; caso
  contrário, remover resquícios de SQLite da configuração
- Atualizar `README.md`, `DOC/InfrastructureStack.md` e
  `DOC/DevelopmentGuide.md` para refletir MySQL
- Atualizar os diagramas Mermaid em `DOC/ArchitectureAndDesign.md` que citam SQLite

---

## FORMATO DO RELATÓRIO DE CADA FASE

```markdown
## Fase N — <nome> — CONCLUÍDA / BLOQUEADA

### Arquivos alterados
- caminho/arquivo.php — o que mudou e por quê

### Migrations criadas
- nome_da_migration — efeito no schema

### Decisões tomadas
- Onde a instrução era ambígua e o que você escolheu, com justificativa

### Testes
- Comando executado e saída resumida
- Testes adicionados (nome e o que provam)

### Critérios de aceite
- [x] critério — como foi verificado
- [ ] critério não atendido — por quê

### Encontrado mas NÃO corrigido (fora de escopo)
- Descrição e localização

### Riscos ou dúvidas para o revisor
```

---

## CONVENÇÕES DE COMMIT E PULL REQUEST

**Autoria — regra obrigatória.** Commits e pull requests devem sair **sem
qualquer marca de coautoria ou de ferramenta**. O histórico do repositório é
assinado apenas pelo mantenedor humano.

Especificamente, **não inclua**:

- Trailer `Co-Authored-By:` de qualquer natureza
- Rodapés do tipo `🤖 Generated with ...`, `Created by ...` ou equivalentes
- Menção a assistente, modelo ou ferramenta de IA no corpo da mensagem, no
  título do PR ou na descrição do PR
- Emojis de robô ou selos de geração automática

Se a sua ferramenta de commit adiciona qualquer um desses automaticamente,
**remova antes de finalizar** — verifique com `git log -1 --format=%B` e corrija
com `git commit --amend` se necessário.

**Formato do commit:**

```
<tipo>: <resumo imperativo em português, até 72 caracteres>

<corpo opcional explicando o PORQUÊ, não o o quê>
```

Tipos: `fix`, `feat`, `refactor`, `test`, `chore`, `docs`.
Exemplo: `fix: envolver contratação em transação com trava pessimista`

**Pull request:**

- Um PR por fase, contra `main`
- Título no mesmo padrão do commit
- Descrição contendo o relatório da fase (formato da seção anterior), sem
  qualquer assinatura de ferramenta
- Não faça merge por conta própria — deixe para revisão

---

## LEMBRETE FINAL

Reversibilidade acima de elegância. Toda migration precisa de `down()`
funcional. Se em qualquer ponto você não tiver certeza do comportamento
esperado do negócio, **pare e pergunte** — este sistema vai lidar com contratos
de frete com valor vinculante.
