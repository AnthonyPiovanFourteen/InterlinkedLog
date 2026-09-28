# Revisão Arquitetural — InterlinkedLog

> Auditoria conduzida em 2026-08-24 sobre o commit `b32c9cc`.
> Contexto declarado: multi-tenant real · mantido por 1 pessoa · VPS simples ·
> acadêmico hoje, produção depois.

---

## 1. Resumo Executivo

O InterlinkedLog está **arquiteturalmente bem posicionado e operacionalmente
despreparado**. A separação hexagonal (`Domain/` com portas, `Infrastructure/`
com adaptadores Eloquent, inversão de dependência registrada no
`AppServiceProvider`) é real, não decorativa — é uma base melhor do que a maioria
dos projetos nesse estágio. O problema não é a forma; é que **o isolamento entre
tenants, que você declarou ser requisito de segurança, não existe em duas
entidades e é aplicado por convenção manual em todas as outras**.

O `TenantMiddleware` tem nome enganoso: ele apenas verifica se `company_id` está
presente na request e devolve 403 se não estiver. Ele **não filtra nada**. Todo o
isolamento real acontece em linhas repetidas à mão dentro dos controllers, no
formato `if (!$x || $x->companyId !== $companyId) return 404`. Esse padrão
funciona em `Contract`, `Quotation` e `User` — e **já falhou** em `Carrier` e
`FreightTable`. Não é hipótese: é o estado atual do código.

Três achados exigem decisão antes de qualquer outra coisa:

1. **Vazamento cross-tenant em transportadoras e tabelas de frete** — incluindo
   `DELETE` destrutivo de dados de outro tenant.
2. **Bug de status `'Ativo'` vs `'Ativa'`** que torna invisível ao motor de
   cotação toda transportadora cadastrada pela interface.
3. **`POST /api/v1/register` quebrado** por regra de validação inexistente.

Nenhum dos três é de arquitetura. São de execução — e é por isso que a
recomendação central deste relatório é **não mexer na arquitetura agora**.

---

## 2. Análise por Pilar

### 2.1 Segurança — CRÍTICO

Este é o pilar que reprova. Dado que você confirmou multi-tenancy real, os
itens abaixo deixam de ser "dívida" e passam a ser exposição de dados entre
clientes distintos.

| # | Achado | Evidência | Impacto |
|---|---|---|---|
| S1 | `EloquentCarrierRepository` ignora `company_id` em `findAll`, `findById`, `save` e `delete` — apesar da coluna existir na migration | `app/Infrastructure/Repositories/Eloquent/EloquentCarrierRepository.php` vs `database/migrations/..._create_carriers_table.php` | Tenant A lista, edita e **apaga** transportadoras do Tenant B via `DELETE /api/v1/carriers/{id}` |
| S2 | `freight_tables` **não possui** coluna `company_id`; `findAll()` é irrestrito | `..._create_freight_tables_table.php`, `EloquentFreightTableRepository` | Tabelas de preço negociado — o ativo comercial mais sensível do domínio — visíveis entre concorrentes |
| S3 | `CarrierController` não lê `company_id` da request em nenhum método | `app/Http/Controllers/Api/CarrierController.php` | IDOR completo, leitura e escrita |
| S4 | Isolamento por convenção manual, não por invariante | `TenantMiddleware.php` (só checa presença) | Toda rota nova nasce insegura por padrão; o custo do erro é vazamento |
| S5 | `APP_DEBUG=true` e `APP_ENV=local` gravados dentro da imagem Docker | `backend/Dockerfile` (heredoc do `.env`) | Qualquer 500 devolve stack trace com variáveis de ambiente |
| S6 | `.env` versionado com `APP_KEY` real | `backend/.env` rastreado no git | Chave de criptografia da aplicação pública no repositório |
| S7 | Sem rate limiting em `POST /login` | `routes/api.php` — nenhum `throttle` | Brute-force ilimitado (bcrypt 12 encarece, não impede) |
| S8 | Token em `localStorage` | `src/lib/api.ts` | Qualquer XSS exfiltra sessão; sem `httpOnly`/`SameSite` |
| S9 | Credenciais reais no README | `admin@interlinked.io / admin123` | Aceitável em demo, indefensável em produção |
| S10 | `POST /register` público criaria tenant + Admin sem aprovação | `routes/api.php` | Auto-provisionamento por qualquer anônimo — *atualmente neutralizado pelo bug B3 abaixo* |

**Nota sobre tokens:** a implementação é melhor do que aparenta. Os tokens vivem
no `Cache` com TTL de 1 dia e rotação do token anterior
(`EloquentUserRepository::setToken`), o que resolve expiração e sessão única.
O problema é o driver: `CACHE_STORE=file`, e `storage/` **não está em volume**
no `docker-compose.yml` (só `/app/database` está). Todo rebuild desloga todos os
usuários, e o driver `file` inviabiliza mais de um processo servindo a API.

### 2.2 Escalabilidade — FRÁGIL

| # | Achado | Evidência |
|---|---|---|
| E1 | **`php artisan serve` como servidor de produção** | `backend/Dockerfile`, última linha |
| E2 | **Zero índices** em todo o schema, exceto `users.email` | `grep index database/migrations/` → 1 ocorrência |
| E3 | N+1 no motor de cotação | `QuotationEngine::process` — `findAll()` + 1 query com `whereHas` por transportadora |
| E4 | `QUEUE_CONNECTION=sync` com dompdf | `backend/.env` + `ContractController::pdf` |
| E5 | Frontend rodando `bun run dev` (Vite dev server) em container | `Dockerfile` raiz — `dist/` existe e é ignorado |
| E6 | SQLite: escritor único, lock global de banco | `DB_CONNECTION=sqlite` |

O item **E1 é o gargalo dominante e anula todos os outros**: `artisan serve`
é single-threaded e atende **uma requisição por vez**. Otimizar query com o
servidor de desenvolvimento no ar é enxugar gelo. É também a correção mais
barata do relatório (`php-fpm` + nginx, ou FrankenPHP em um único container).

Sobre **E6**: SQLite é frequentemente subestimado. Para o volume plausível deste
domínio — cotações e contratos de um punhado de empresas — ele aguenta com folga
em WAL mode, e a decisão original é defensável. O que o torna insustentável não é
performance: é que `audit_logs` e `system_logs` crescem sem limite no mesmo
arquivo, e que **não há backup** — o volume `backend-data` é a única cópia, e o
README ensina `docker compose down -v`, que a destrói.

### 2.3 Manutenibilidade — BOA COM ATRITO

O acerto: dependências apontam para dentro, `Domain/` não conhece Eloquent, e
trocar de ORM ou de banco exigiria mexer só em `Infrastructure/`. Isso é
arquitetura hexagonal de verdade e vale defender em banca.

O atrito é o **custo pago sem o benefício correspondente**:

- **Dupla modelagem.** Cada conceito existe como `App\Models\X` (Eloquent) e
  `App\Domain\Entities\X` (entidade imutável), com mapeamento manual `toEntity()`
  em cada repositório. Toda mudança de campo exige tocar 3 arquivos.
- **Entidades anêmicas.** São imutáveis mas sem comportamento. Como não há
  método de mutação, atualizar um contrato significa **reconstruir o objeto
  inteiro com ~18 argumentos nomeados dentro do controller** —
  `ContractController::cancel` e `::updateCte` fazem exatamente isso. A regra de
  negócio vazou para a camada HTTP, que é justamente o que o hexágono deveria
  impedir.
- **Camada `Application` pela metade.** `UseCases` existe só para `Auth`. Os
  outros 10 controllers orquestram domínio diretamente.
- **Zero transações.** `ContractController::store` escreve contrato, atualiza
  cotação e cria evento de rastreio em três operações independentes. Falha no
  meio deixa contrato órfão sem rastreio, ou cotação marcada como contratada sem
  contrato. `grep DB::transaction app/` → 0 resultados.
- **Zero testes.** `tests/` contém apenas os dois `ExampleTest` do skeleton do
  Laravel. `test-all.sh` é smoke test de shell contra a API, não rede de
  segurança para refatoração.

### 2.4 Custo-Benefício — EXCELENTE

Aqui o projeto acerta e não deve ser mexido. Dois containers, sem serviço
gerenciado, sem broker, sem cache distribuído — roda em VPS de menor faixa.
Para um mantenedor solo, isso não é limitação: é a decisão correta. Qualquer
proposta que aumente o número de peças a operar precisa justificar o custo
operacional em pessoa-hora, não só em reais.

---

## 3. Bugs de Correção Confirmados

Três defeitos funcionais achados durante a auditoria. Não são opinião
arquitetural.

### B1 — `'Ativo'` vs `'Ativa'`: transportadoras cadastradas pela UI não cotam

Há duas grafias do mesmo status em uso simultâneo, e elas se contradizem:

| Local | Valor | Efeito |
|---|---|---|
| `Domain/Entities/Carrier.php:37` (`create()`) | `'Ativa'` | Toda transportadora criada pela API |
| `CarrierController::update` (validação) | `in:Ativa,Inativa` | A API só aceita `'Ativa'` |
| `..._create_carriers_table.php` (default) | `'Ativo'` | Default do schema |
| `DatabaseSeeder.php:80` | `'Ativo'` | Transportadoras do seed |
| `QuotationEngine.php:45` | `!== 'Ativo'` → `continue` | **Ignora tudo que for `'Ativa'`** |
| `EloquentCarrierRepository::findByOrigin` | `where('status','Ativa')` | **Ignora tudo que for `'Ativo'`** |

Consequência: **toda transportadora cadastrada pela interface é silenciosamente
pulada pelo motor de cotação**, retornando `results: []`. O seed funciona porque
usa `'Ativo'` — a demo passa, o uso real não. Simetricamente, `findByOrigin`
não enxerga nenhuma transportadora do seed.

O README atribui `results: []` exclusivamente ao CEP fora do `cepMap`. Essa é
uma causa real, mas é a **segunda**. Esta aqui é independente e mais provável no
uso normal do sistema.

### B2 — `POST /register` retorna 500 sempre

`AuthController.php:58` usa a regra `unique_check:users`. Essa regra não existe
no Laravel e não é registrada em lugar nenhum — `AppServiceProvider::boot()`
está vazio e não há `Validator::extend` no projeto. O framework lança
`BadMethodCallException` ao resolver a regra. O endpoint está publicamente
roteado e quebrado em 100% das chamadas com e-mail válido.

Efeito colateral: isso é o que impede, hoje, o auto-provisionamento anônimo de
tenants (S10). Ao corrigir B2, **S10 vira exposição ativa** — as duas correções
precisam sair juntas.

### B3 — Escrita não transacional na contratação

Descrito em 2.3. Listado aqui porque produz corrupção de dados observável, não
apenas desconforto de manutenção.

---

## 4. SPOF e Acoplamento

**Pontos únicos de falha**

1. Container `backend` — instância única, sem réplica. `restart: unless-stopped`
   é a totalidade da estratégia de recuperação.
2. Arquivo `database.sqlite` — cópia única, sem backup, sem replicação. Perda do
   volume é perda total e irreversível do negócio.
3. Cache em `file` fora de volume — reinício invalida todas as sessões.
4. `frontend` depende de `backend` healthy (`depends_on`); backend fora do ar
   derruba a aplicação inteira, sem página degradada.

**Acoplamentos relevantes**

- **Controllers → Domínio**: reconstrução de entidades dentro do controller
  acopla HTTP à estrutura completa do agregado.
- **`QuotationEngine` → geografia hardcoded**: `cepMap` com 22 prefixos dentro
  de classe de `Infrastructure`, com fallback silencioso para São Paulo. Um CEP
  desconhecido não gera erro — gera **cotação errada com aparência de correta**.
  Esse fallback é pior que uma exceção.
- **Frontend → formato de resposta**: `src/lib/api.ts` consome JSON solto sem
  contrato tipado compartilhado; mudança no controller quebra a UI em runtime.

---

## 5. Comparação com Alternativas de Mercado

### 5.1 Arquitetura de implantação

| Opção | Prós | Contras | Veredito para o seu contexto |
|---|---|---|---|
| **Monolito hexagonal (atual)** | Um deploy; portas já definidas; custo mínimo; um dev consegue segurar | Escala vertical; sem isolamento de falha | ✅ **Manter** |
| **Modular monolith** | Fronteiras explícitas por módulo (Cotação, Contratação, Rastreio); prepara extração futura | Exige disciplina de imports; ganho baixo com 1 dev | ⚠️ Adotar só a nomenclatura de módulos, sem reestruturar agora |
| **Microserviços** | Escala e deploy independentes | Rede, observabilidade distribuída, consistência eventual, N pipelines | ❌ **Rejeitar.** Um mantenedor solo em VPS simples pagaria todo o custo operacional sem nenhum dos benefícios |

O `Domain/` com portas já é o investimento que torna a extração possível *se um
dia* fizer sentido. Esse valor já está no banco — não precisa ser sacado agora.

### 5.2 Persistência

| Opção | Avaliação |
|---|---|
| **SQLite (atual)** | Defensável no volume atual, **em WAL mode**. Inviável em produção por ausência de backup, escritor único e logs crescendo no mesmo arquivo |
| **PostgreSQL** | ✅ **Recomendado.** Migração barata (Eloquent abstrai), habilita RLS — que resolve o problema de tenancy **no banco**, não na convenção — e destrava backup, PITR e réplica. Custo: +1 container |
| **NoSQL (documento) para o núcleo** | ❌ Domínio é fortemente relacional (cotação → resultados → contrato → eventos) e exige transação. NoSQL aqui piora |
| **NoSQL / append-only para logs** | ⚠️ Faz sentido **parcial**: `audit_logs` e `system_logs` são append-only, alto volume e nunca sofrem join. Separá-los é o único ponto onde a pergunta "NoSQL faz sentido?" tem resposta sim. Mas resolva primeiro com uma tabela particionada e retenção — não adicione tecnologia antes da dor existir |

### 5.3 Estratégia de multi-tenancy

| Opção | Avaliação |
|---|---|
| **Coluna discriminadora + checagem manual (atual)** | ❌ Já falhou duas vezes. Falha em silêncio, e o modo de falha é vazamento |
| **Coluna + Global Scope do Eloquent** | ✅ **Recomendado agora.** Isolamento vira padrão do framework; esquecer passa a ser seguro. Baixo esforço, alto retorno |
| **PostgreSQL Row-Level Security** | ✅ **Alvo de produção.** Isolamento imposto pelo banco; nem SQL cru escapa. Combina com 5.2 |
| **Banco por tenant** | ❌ Isolamento máximo, mas N migrations e N backups para um mantenedor solo |

### 5.4 Autenticação

| Opção | Avaliação |
|---|---|
| **Token próprio em Cache (atual)** | Melhor que a média (TTL + rotação), mas caseiro, sem escopo, sem revogação em massa, preso ao driver `file` |
| **Laravel Sanctum** | ✅ **Recomendado.** Substituição quase 1:1 pelo que já existe, com tokens persistidos, hash, abilities e revogação. Elimina código próprio no caminho crítico de segurança |
| **JWT stateless** | ⚠️ Escala melhor horizontalmente, mas revogação é problema conhecido. Sem ganho no seu volume |

---

## 6. Matriz SWOT

### Forças
- Arquitetura hexagonal genuína, com inversão de dependência real e verificável
- `Domain/` livre de framework — banco e ORM são substituíveis de fato
- Documentação técnica acima da média (~3.000 linhas em `DOC/`, com C4 e Mermaid)
- Custo operacional próximo de zero; `docker compose up` funciona de fato
- Modelagem de domínio coerente e fiel ao negócio de frete (NF-e, CT-e, faixas de peso, taxas)
- Tokens já com TTL e rotação — a base de sessão é melhor do que o comum

### Fraquezas
- Isolamento de tenant ausente em `Carrier` e `FreightTable`; por convenção no resto
- `php artisan serve` e Vite dev server como runtime de produção
- Zero índices, zero transações, zero testes automatizados
- Bug `Ativo`/`Ativa` inutiliza o fluxo principal para dados criados via UI
- `register` quebrado em produção
- `APP_DEBUG=true` e `APP_KEY` na imagem e no repositório
- Duplicação Model/Entity com mapeamento manual triplica o custo de mudança
- `cepMap` hardcoded com fallback silencioso para São Paulo — gera cotação errada sem sinalizar
- Backup inexistente; volume único é a única cópia dos dados

### Oportunidades
- Global Scopes eliminam a classe inteira de bugs de tenancy com esforço de horas
- Migração para PostgreSQL destrava RLS, backup e concorrência de uma vez
- Sanctum remove código de segurança próprio com risco baixo
- Substituir `cepMap` por integração de CEP (ViaCEP/BrasilAPI) com cache é feature de produto, não só correção
- Extrair `Application/UseCases` para os demais fluxos consolida a narrativa hexagonal — forte na defesa acadêmica
- `dist/` já existe: build de produção do frontend é troca de uma linha no Dockerfile
- Logs de auditoria são base natural para analytics de performance de transportadora

### Ameaças
- **Vazamento cross-tenant de tabela de preço** — dano comercial e reputacional direto ao primeiro cliente real
- **`DELETE` cross-tenant** — perda de dados de terceiro, sem trilha de recuperação
- Perda total e irreversível de dados por falha de volume ou `down -v` acidental
- LGPD: dados de NF-e (CNPJ de remetente/destinatário) sem retenção, criptografia em repouso ou trilha de exclusão
- Cotação errada por fallback silencioso de CEP → risco contratual e financeiro real
- Refatorar sem testes, sozinho, é o caminho mais provável para regressão silenciosa
- Fator-ônibus 1

---

## 7. Três Perguntas Críticas para o Negócio

Não são perguntas técnicas — são as que mudam o desenho da solução e que eu não
consigo responder lendo código.

### P1 — Transportadora é dado global da plataforma ou dado privado de cada empresa?

Esta é **a** pergunta. O código está esquizofrênico: a migration de `carriers`
tem `company_id` **nullable**, o repositório ignora a coluna, e `freight_tables`
não a tem.

- Se a transportadora é **catálogo compartilhado** (a "Transportadora XYZ" é a
  mesma para todos), então o vazamento é parcialmente intencional — mas a
  **tabela de frete continua sendo privada**, porque cada empresa negocia seu
  preço. O modelo correto vira: carrier global + freight_table por tenant.
- Se é **privada por empresa**, então `company_id` deve ser obrigatório e ambas
  as entidades precisam de escopo.

As duas respostas geram migrations incompatíveis. Enquanto não houver decisão,
qualquer correção de tenancy é chute.

### P2 — Qual o custo real de uma cotação errada?

O `cepMap` cobre 22 prefixos e **assume São Paulo** para todo o resto do país,
sem avisar. Se a cotação é orientativa e um humano confere antes de fechar, é
dívida tolerável. Se ela alimenta contrato com valor vinculante, é **exposição
financeira e jurídica ativa** — e a integração de CEP deixa de ser melhoria para
virar bloqueador de produção. Isso depende de como o negócio usa o número, não
de engenharia.

### P3 — Qual é a obrigação legal de retenção e imutabilidade dos contratos e logs?

Contratos de frete e dados de NF-e têm prazo de guarda fiscal, e os CNPJs de
remetente/destinatário são dado pessoal quando há pessoa física envolvida. Hoje
não há retenção definida, criptografia em repouso, nem qualquer backup. Preciso
saber: quantos anos de guarda, se a trilha de auditoria precisa ser imutável
(append-only / WORM), e se há requisito de exclusão a pedido do titular. A
resposta decide se `audit_logs` pode continuar no mesmo SQLite ou precisa de
armazenamento próprio com garantias diferentes.

---

## 8. Ordem de Ataque Recomendada

Sequência por relação risco-eliminado ÷ esforço. Não é plano de execução —
aguarda sua instrução.

**Faixa 0 — Correção, não arquitetura (horas)**
1. Unificar `'Ativo'`/`'Ativa'` em constante única no domínio + migration de dados (B1)
2. Corrigir ou remover `POST /register` (B2) — **junto com** decisão sobre S10
3. `APP_DEBUG=false` e `APP_ENV=production` na imagem; remover `.env` do git e rotacionar `APP_KEY` (S5, S6)
4. `throttle` em `/login` (S7)

**Faixa 1 — Estancar vazamento (dias) — depende de P1**
5. Escopo de tenant em `Carrier` e `FreightTable`
6. Global Scope substituindo checagem manual, com teste de regressão por entidade
7. `DB::transaction` no fluxo de contratação (B3)

**Faixa 2 — Runtime de produção (dias)**
8. Trocar `artisan serve` por php-fpm/nginx ou FrankenPHP (E1)
9. Build de produção no frontend (E5)
10. Índices nas FKs e colunas de filtro (E2)
11. Volume para `storage/`, ou cache em Redis/banco (S-tokens)

**Faixa 3 — Fundação (semanas)**
12. PostgreSQL + backup automatizado + WAL/PITR
13. RLS substituindo Global Scopes
14. Sanctum no lugar do token próprio
15. Suíte de testes cobrindo cotação, contratação e isolamento de tenant
16. Fila para geração de PDF

---

*Relatório gerado por auditoria direta do código-fonte. Achados de segurança e
bugs foram verificados no repositório, não inferidos da documentação.*
