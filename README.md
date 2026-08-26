# InterlinkedLog

Sistema de cotação, contratação e rastreamento de fretes. Projeto acadêmico —
recebe NF-e, calcula o melhor frete entre transportadoras cadastradas (preço,
prazo e custo-benefício), gera contrato em PDF e acompanha o status da carga.

## Stack

| Camada | Tecnologia |
|---|---|
| Frontend | React 19 + TanStack Start + TanStack Router/Query + Vite + Tailwind + shadcn/ui + recharts + react-simple-maps |
| Backend | Laravel 11 (PHP 8.4) + MySQL 8 + dompdf |
| Autenticação | Token Bearer (gerado no login, persistido no localStorage) |
| Empacotamento | Docker Compose (mysql + frontend + backend) |

## Pré-requisitos

Só **Docker Desktop** (ou Docker Engine + Compose). Nada mais precisa estar
instalado na máquina.

## Como rodar

```bash
git clone <repo>
cd InterlinkedLog
docker compose up --build -d
```

Na primeira vez leva alguns minutos (baixa imagens, instala dependências,
inicializa o MySQL, roda migrations e seed). Nas próximas é mais rápido.

Quando os containers estiverem prontos:

- **Frontend:** http://localhost:3000
- **API (via frontend):** http://localhost:3000/api/v1 — o backend não expõe porta pública
- **Login:** `admin@interlinked.io` / `admin123`

## Comandos úteis

```bash
# Logs ao vivo
docker compose logs -f

# Parar (mantém dados no volume)
docker compose stop

# Iniciar de novo
docker compose start

# Derrubar mantendo o banco
docker compose down

# Derrubar e APAGAR o banco (reset total)
docker compose down -v

# Rebuild forçado (se mexer no Dockerfile ou em deps)
docker compose up --build -d
```

## CEPs aceitos pelo motor de cotação

O `QuotationEngine` mapeia CEP → cidade por prefixo (5 primeiros dígitos).
Hoje a tabela é hardcoded e cobre só algumas cidades. **Se você usar um CEP
fora dessa lista, ele cai em "São Paulo, SP" por padrão e a cotação pode
voltar com `results: []` (sem transportadoras).**

Para a demo, use estes:

| Prefixo | Cidade |
|---|---|
| 01000, 02000 | São Paulo / SP |
| 17500 | Marília / SP |
| 20000, 21000 | Rio de Janeiro / RJ |
| 30000, 31000 | Belo Horizonte / MG |
| 40000, 41000 | Salvador / BA |
| 50000, 51000 | Recife / PE |
| 60000, 61000 | Fortaleza / CE |
| 69000 | Manaus / AM |
| 70000, 71000 | Brasília / DF |
| 74000 | Goiânia / GO |
| 80000, 81000 | Curitiba / PR |
| 86020 | Londrina / PR |
| 90000, 91000 | Porto Alegre / RS |

A tabela vive em `backend/app/Infrastructure/Services/QuotationEngine.php`.
Pra suportar mais CEPs em produção, trocar por uma API tipo ViaCEP.

## Login e fluxo principal

1. Loga com as credenciais acima.
2. **Cotações → Nova cotação**: preenche os dados da NF (ou faz upload do XML
   da NF-e) e o sistema retorna as 8 transportadoras ranqueadas por preço,
   prazo e custo-benefício.
3. **Contratar**: escolhe a transportadora e gera o contrato.
4. **Contratações**: lista os contratos, permite gerar/baixar o PDF, atualizar
   CT-e ou cancelar.
5. **Rastreamento**: mostra eventos dos contratos em andamento.
6. **Auditoria / Logs**: histórico de ações e logs do sistema.

## Estrutura

```
.
├── Dockerfile              # Frontend (bun + vite dev server)
├── docker-compose.yml      # Orquestra mysql + frontend + backend
├── docker-start.sh         # Atalho para docker compose up --build -d
├── docker-stop.sh          # Atalho para docker compose down
├── mysql-init/init.sql     # Cria interlinkedlog_test na 1ª subida do MySQL
├── scripts/                # backup-mysql.sh, restore-mysql.sh
├── src/
│   ├── routes/             # Páginas (TanStack Router file-based)
│   ├── lib/api.ts          # Cliente HTTP do frontend
│   └── components/         # Componentes (shadcn/ui + custom)
├── public/
│   └── brazil-topo.json    # Mapa do Brasil usado no dashboard
└── backend/
    ├── Dockerfile          # Backend (php-cli + composer + pdo_mysql)
    ├── docker-entrypoint.sh# Espera o MySQL e roda migrate + seed + serve
    ├── app/
    │   ├── Console/Commands/SeedCommand.php   # `php artisan app:seed`
    │   ├── Domain/         # Entidades + repositórios + services (DDD)
    │   ├── Infrastructure/Services/QuotationEngine.php  # Motor de cotação
    │   └── Http/Controllers/Api/              # Controllers REST
    ├── database/
    │   ├── migrations/     # Schema MySQL
    │   ├── seeders/        # Dados iniciais (admin + transportadoras + tabelas)
    │   └── scripts/        # migrate-sqlite-to-mysql.php (migração legada)
    └── routes/api.php      # Definição de rotas REST
```

## Configuração

Não precisa criar nenhum `.env` para rodar via Docker — toda a configuração
vem de variáveis de ambiente injetadas pelo `docker-compose.yml` (o backend
**não** gera `.env` na imagem). Defaults de dev para credenciais do MySQL,
`APP_ENV=production` e `APP_DEBUG=false` podem ser sobrescritos criando um
`.env` na raiz (ver `.env.example`).

Cache, sessão e fila seguem em `file`/`sync` — não trocar para `database`
sem antes adicionar as migrations correspondentes.

Para rodar **fora do Docker**, use os templates `.env.example` e
`backend/.env.example` como ponto de partida (o backend exige PHP com
`pdo_mysql`).

### Chave da aplicação em produção

O `docker-compose.yml` traz um `APP_KEY` **default público** (apenas para
desenvolvimento). Com `APP_ENV=production`, o boot do backend **aborta** se
esse default estiver em uso — a chave precisa ser definida no ambiente:

```bash
# gere uma chave
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"

# no .env da raiz (gitignored):
# APP_KEY=base64:<chave gerada>
```

Sem isso, a stack em produção não sobe (mensagem clara no log do container).
Em desenvolvimento (`APP_ENV=local`), o default público é aceito.

## Persistência e backup

O MySQL persiste no volume `mysql-data`. Não há backup automático — agende
`./scripts/backup-mysql.sh` (dump com retenção) e restaure periodicamente em
um banco separado conferindo as contagens por tabela. Backup não verificado
não é backup.

## Checagem de integração (rede/proxy)

```bash
docker compose up -d
./scripts/integration-check.sh
```

Rode **antes de deploy** e **após mexer em rede, proxy ou `trustProxies`**.
Cobre: E2E (login → cotação → contratação via `:3000`), isolamento do
throttle de login por cliente (duas origens distintas via frontend) e
bloqueio de `X-Forwarded-For` forjado em requisição direta. Exit code ≠ 0
quando algo regredir — em especial, se o IP fixo do frontend
(`172.28.0.10`) deixar de ser confiado pelo `trustProxies`, o teste de
isolamento falha (o throttle volta a ser global). Idempotente: zera os
contadores de throttle no início, preservando os tokens de sessão.

## Testes

```bash
docker compose up -d
docker compose run --rm test            # suíte PHPUnit contra interlinkedlog_test
./test-all.sh                           # smoke test HTTP local (usa o build PHP sqlite próprio)
```

O serviço `test` (profile `test`, não sobe com `up -d`) usa a imagem com
dependências de desenvolvimento e **monta o código do working tree como
volume** (`./backend:/app`) — testes novos são executados sem rebuild manual.
No host, `php artisan test` exige PHP com `pdo_mysql` (não é o caso do
ambiente atual).

## Cotações e CEP

O CEP é resolvido primeiro pelo mapa local (22 prefixos, sem rede) e, fora
dele, pelo ViaCEP com timeout de 5s e cache de 30 dias no banco. CEP não
encontrado → **422** com o CEP na mensagem (nunca um "São Paulo" silencioso);
serviço de CEP indisponível → **503** explícito. Status escolhidos: 422 é
entrada inválida do usuário; 503 é indisponibilidade de infraestrutura.

## Decisões e gotchas

- **Tenancy por Global Scope.** `FreightTable`, `Contract`, `Quotation`,
  `User`, `AuditLog` e `SystemLog` são filtrados por `company_id` lido do
  request HTTP. Em workers de fila o escopo fica inerte — manter
  `QUEUE_CONNECTION=sync` ou carregar `company_id` no payload do job.
- **Colação `utf8mb4_unicode_ci` é accent-insensitive** — o `LIKE` da camada
  SQL casa "Marilia" com "Marília", mas o `QuotationEngine` valida a rota em
  PHP (`mb_strtolower`), que não remove acentos: o resultado final da cotação
  não casa acentos em nenhum banco.
  **Pergunta ao mantenedor (decisão de negócio pendente):** normalizar
  acentos na comparação do engine. A resolução de CEP por ViaCEP (Fase D)
  **ampliou a superfície deste problema**: antes, CEP fora do mapa virava
  "São Paulo" (preço errado, mas com resultado); agora, mais CEPs resolvem
  para a cidade canônica acentuada — se a tabela de frete veio de planilha
  sem acento (`routes[].city` é entrada livre, há `.xlsx` em `examples/`),
  a transportadora é silenciosamente pulada e a cotação volta vazia.
  Trocou-se "preço errado silencioso" por "sem resultado silencioso": é
  melhor (não gera contrato errado), mas a normalização deixou de ser
  ergonomia e virou correção de um caminho que a D.1 tornou mais provável.
- **Composer security advisories desabilitadas no build do backend.** O
  Laravel 11.31 que o projeto depende tem advisories em aberto que bloqueiam
  o `composer install` por padrão. Para um projeto acadêmico tudo bem; em
  produção real, subir as deps para uma versão patcheada.
- **Seed roda no boot apenas quando o banco está vazio.** O entrypoint
  verifica se `companies` tem registros antes de semear; em banco populado o
  seed é ignorado (não há duplicação de empresas no restart). A falha de
  constraint UNIQUE no re-seed continua tolerada com `|| true`.
- **Frontend usa Vite dev mode (não build).** Compilação on-demand: a
  primeira navegação para cada rota leva 1-3s. Pré-aqueça as rotas antes de
  uma demo ao vivo, clicando em cada item do menu uma vez.
- **CORS aberto (`*`) no backend.** OK para dev; trancar antes de qualquer
  exposição pública.
- **Token de sessão em `localStorage` (decisão adiada).** Qualquer XSS
  exfiltra a sessão. Migrar para cookie `httpOnly` + `SameSite` exige
  mudança coordenada de backend e frontend (emissão do cookie no login,
  esquema CSRF — o cookie `httpOnly` não é legível por JS — e remoção do
  `localStorage` no cliente), com risco de implementar pela metade (dupla
  fonte de verdade ou `SameSite` incorreto). Adiado até existir demanda
  explícita; o risco assumido é o vazamento de sessão via XSS.
- **`TrustProxies` não configurado (decisão documentada).** O arranjo de
  produção é nginx → php-fpm via FastCGI, que **não injeta**
  `X-Forwarded-For`: o `REMOTE_ADDR` recebido pelo PHP já é o IP do cliente,
  então `$request->ip()` e o rate limiting por IP (Fase B) funcionam sem
  confiar em headers. Configurar `trustProxies` aqui abriria a porta para
  `X-Forwarded-For` forjado (o cliente escaparia do throttle). Se um
  proxy/LB externo entrar no caminho, configurar a faixa dele no
  `trustProxies` passa a ser obrigatório — antes disso, verificar
  `real_ip`/`proxy_protocol` no nginx.
- **`APP_DEBUG=false` e `APP_ENV=production` por padrão** no compose;
  sobrescreva com `APP_DEBUG=true`/`APP_ENV=local` no `.env` da raiz para
  desenvolvimento.

## Próximos passos

- [ ] Substituir o cepMap hardcoded por chamada à ViaCEP (ou similar).
- [ ] Adicionar migrations para `cache`, `sessions`, `jobs` para suportar
      drivers `database`.
- [ ] Build de produção do frontend (`vite build` + servir estático) ao invés
      de dev server no container.
- [ ] Pipeline CI (lint + testes PHPUnit contra MySQL) no GitHub Actions.
