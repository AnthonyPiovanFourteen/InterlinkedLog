# InterlinkedLog

Sistema de cotação, contratação e rastreamento de fretes. Projeto acadêmico —
recebe NF-e, calcula o melhor frete entre transportadoras cadastradas (preço,
prazo e custo-benefício), gera contrato em PDF e acompanha o status da carga.

## Stack

| Camada | Tecnologia |
|---|---|
| Frontend | React 19 + TanStack Start + TanStack Router/Query + Vite + Tailwind + shadcn/ui + recharts + react-simple-maps |
| Backend | Laravel 11 (PHP 8.4) + SQLite + dompdf |
| Autenticação | Token Bearer (gerado no login, persistido no localStorage) |
| Empacotamento | Docker Compose (frontend + backend) |

## Pré-requisitos

Só **Docker Desktop**. Nada mais precisa estar instalado na máquina.

## Como rodar

```bash
git clone <repo>
cd InterlinkedLog
docker compose up --build -d
```

Na primeira vez leva uns 2-3 minutos (baixa imagens, instala dependências,
roda migrations e seed). Nas próximas é instantâneo.

Quando os containers estiverem prontos:

- **Frontend:** http://localhost:3000
- **API:** http://localhost:8080/api/v1
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
├── docker-compose.yml      # Orquestra frontend + backend
├── docker-start.sh         # Atalho para docker compose up --build -d
├── docker-stop.sh          # Atalho para docker compose down
├── src/
│   ├── routes/             # Páginas (TanStack Router file-based)
│   ├── lib/api.ts          # Cliente HTTP do frontend
│   └── components/         # Componentes (shadcn/ui + custom)
├── public/
│   └── brazil-topo.json    # Mapa do Brasil usado no dashboard
└── backend/
    ├── Dockerfile          # Backend (php-cli + composer + sqlite)
    ├── app/
    │   ├── Console/Commands/SeedCommand.php   # `php artisan app:seed`
    │   ├── Domain/         # Entidades + repositórios + services (DDD)
    │   ├── Infrastructure/Services/QuotationEngine.php  # Motor de cotação
    │   └── Http/Controllers/Api/              # Controllers REST
    ├── database/
    │   ├── migrations/     # Schema SQLite
    │   ├── seeders/        # Dados iniciais (admin + transportadoras + tabelas)
    │   └── database.sqlite # Criado em runtime; persistido no volume Docker
    └── routes/api.php      # Definição de rotas REST
```

## Configuração

Não precisa criar nenhum `.env` para rodar via Docker.

- O **backend** gera o `.env` no build (ver `backend/Dockerfile`) e roda
  `php artisan key:generate --force` automaticamente. Cache, sessão e fila
  estão em modo `file`/`sync` porque as migrations não criam as tabelas
  correspondentes — não trocar para `database` sem antes adicionar essas
  migrations.
- O **frontend** recebe `VITE_API_URL=http://backend:8000` direto do
  `docker-compose.yml`. Esse hostname só resolve dentro da rede do compose.

Para rodar **fora do Docker** (PHP/bun nativos), use os templates
`.env.example` e `backend/.env.example` como ponto de partida.

## Persistência

O volume `backend-data` é montado em `/app/database` dentro do container do
backend. Isso inclui o `database.sqlite` (com todos os dados), mas também as
pastas `migrations/`, `seeders/` e `factories/` — em runs subsequentes elas
ficam pinned na versão da primeira build. Se você alterar migrations ou
seeders depois disso, rode `docker compose down -v && docker compose up
--build -d` para resetar o volume e forçar o re-seed.

## Decisões e gotchas

- **Composer security advisories desabilitadas no build do backend.** O
  Laravel 11.31 que o projeto depende tem advisories em aberto que bloqueiam
  o `composer install` por padrão. Para um projeto acadêmico tudo bem; em
  produção real, subir as deps para uma versão patcheada.
- **Seed roda no boot do container e é idempotente por tolerância (não por
  design).** O CMD do backend é
  `migrate --force && (app:seed || true) && serve` — o `|| true` impede que
  uma falha de constraint UNIQUE no re-seed mate o container.
- **Frontend usa Vite dev mode (não build).** Compilação on-demand: a
  primeira navegação para cada rota leva 1-3s. Pré-aqueça as rotas antes de
  uma demo ao vivo, clicando em cada item do menu uma vez.
- **CORS aberto (`*`) no backend.** OK para dev; trancar antes de qualquer
  exposição pública.
- **APP_DEBUG=true por padrão.** Stack traces vazam em erro 500 — desligar
  antes de subir pra produção.

## Próximos passos

- [ ] Substituir o cepMap hardcoded por chamada à ViaCEP (ou similar).
- [ ] Adicionar migrations para `cache`, `sessions`, `jobs` para suportar
      drivers `database`.
- [ ] Build de produção do frontend (`vite build` + servir estático) ao invés
      de dev server no container.
- [ ] Testes automatizados (PHPUnit / Vitest) — esqueleto já está no
      `phpunit.xml`.
- [ ] Pipeline CI (lint + testes) no GitHub Actions.
