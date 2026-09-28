<p align="center">
  <img src="../public/logo.png" alt="InterlinkedLog" height="80">
</p>

# Histórico de evolução

Registro das mudanças estruturais do sistema, em ordem. Cada bloco terminou com
a suíte verde e foi revisado antes do seguinte.

---

## Migração para MySQL

O sistema nascia em SQLite. A troca foi feita em fases, começando pelo que o
SQLite **mascarava**.

| | |
|---|---|
| **Concorrência** | O SQLite serializa escritas, o que escondia condições de corrida. A contratação passou a rodar em `DB::transaction` com trava pessimista na cotação — sem isso, duas requisições simultâneas gerariam dois contratos para a mesma cotação. |
| **Isolamento de tenant** | `carriers` era catálogo global mas o repositório ignorava `company_id`; `freight_tables` não tinha a coluna. Decisão: transportadora é catálogo compartilhado, tabela de frete é privada. Aplicado por Global Scope, não por checagem manual. |
| **Chave primária** | UUID v4 aleatório como PK clusterizada fragmenta o índice no InnoDB. Padronizado em `Str::orderedUuid()`. |
| **Datas** | `timestamp` → `datetime`: no MySQL o primeiro sofre conversão de fuso e tem faixa 1970–2038. |
| **Infraestrutura** | Serviço MySQL, backup com restauração testada, e a migração de dados propriamente dita. |

---

## Correção de precificação

Dois defeitos no motor que produziam **preço errado em contrato com valor
vinculante**:

- As faixas de peso vinham **apenas da primeira rota** da tabela, então o frete
  de um destino era calculado com a tabela de outro
- O prazo vinha da primeira faixa, não da que casava com o peso
- Taxa percentual era cobrada **duas vezes** — o valor cru somado junto do
  percentual

Corrigir expôs lacunas entre as faixas (`[0,30]` e `[31,100]` não cobriam 30,5),
que passaram a ser degraus contínuos.

**Pendência registrada:** `frete_minimo` e `cubagem` continuam somados como taxas
em reais. A semântica provável é piso e fator kg/m³, mas **quem define é a tabela
da transportadora** — e o modelo não permite que ela declare o comportamento de
uma linha.

---

## Segurança

- Guarda no boot: `APP_ENV=production` com a chave pública do compose **aborta**
- `POST /register` removido — criava empresa e Admin sem aprovação
- Rate limiting: 5 tentativas/min no login, 60/min no grupo autenticado
- `TrustProxies` restrito ao IP do proxy, com a porta do backend despublicada —
  confiar amplamente permitiria forjar `X-Forwarded-For` e escapar do throttle

---

## Runtime de produção

`php artisan serve` era single-threaded e atendia **uma requisição por vez**.
Substituído por nginx + php-fpm: **191 → 350 req/s** medidos.

O frontend passou a servir build de produção com SSR — descobriu-se no caminho
que ele **nunca havia renderizado uma página**: o handler era importado como
função, mas o bundle exporta `{ fetch }`, e os assets de `dist/client` não eram
servidos por ninguém.

Cache e sessão migraram para o banco: `storage/` é efêmero, e os tokens vivem no
cache — todo rebuild deslogava todos.

---

## Qualidade automatizada

| | |
|---|---|
| **Testes** | 168 no backend (unitário, feature, arquitetura) e 13 no frontend |
| **Arquitetura** | 7 regras hexagonais verificadas por PHPat, incluindo "todo model com `company_id` usa `TenantScoped`" |
| **Análise estática** | PHPStan nível 5, sem baseline e sem `@phpstan-ignore` |
| **Estilo** | Pint, ESLint, Prettier, `tsc --noEmit`, hadolint |
| **Integração** | Script que exercita o caminho real (`:3000`) e falha se o isolamento de throttle regredir |
| **CI** | 6 jobs, validados a partir de clone limpo |

A regra de arquitetura encontrou, na primeira execução, uma violação que
sobreviveu a nove revisões manuais: `DB::transaction` no controller. A correção
levou a orquestração para um caso de uso em `Application` e o mecanismo de
transação para uma porta.

---

## Cotação multi-fonte

O sistema passou a cotar em **todas as fontes disponíveis** — tabelas carregadas
pelo tenant e APIs com credencial — apresentando lado a lado o melhor preço,
prazo e custo-benefício, mais a referência histórica.

As tabelas respondem na hora; as APIs vão para a fila, **um job por
transportadora**, e os resultados aparecem conforme chegam. Transportadora lenta
ou fora do ar não atrasa nem derruba as demais.

Isso obrigou a resolver a pendência mais antiga do projeto: o escopo de tenant
lia do binding de requisição e ficava **inerte no worker**. Virou `TenantContext`
explícito — e a troca revelou duas consultas de autenticação que escapavam do
escopo por acidente.

---

## Integração com transportadoras

Cinco adaptadores: **Braspress, Jadlog, Jamef, Loggi e Rodonaves**. A porta
`CarrierGateway` não mudou em nenhum deles, apesar de serem radicalmente
diferentes:

| | |
|---|---|
| Autenticação | Basic, Bearer cru, e três variantes de OAuth2 |
| Chamadas por cotação | de 1 (Braspress) a 4 em três hosts (Rodonaves) |
| Prazo | em dias, ou como **data prevista** (Jamef) |
| Taxas | só o total, ou seis tributos discriminados (Loggi) |
| Peso cubado | calculado por eles, ou **exigido de nós** (Jadlog) |

Credenciais são por tenant, criptografadas, e **nunca retornam numa resposta**. O
formulário da interface se monta a partir do que cada adaptador declara — campos
exigidos, explicação de cada um e link da documentação.

**Rastreio** ficou separado: `automatico` quando há API, `manual` caso contrário,
definido na contratação e imutável. Em automático, o único input humano é
observação.

---

## Identidade visual e primeiro acesso

Logo aplicado na sidebar, no login, no favicon e no PDF de coleta. O teste do PDF
pegou que o dompdf exige a extensão `gd` para embutir imagem — sem ela, colocar o
logo **quebrava a geração inteira**.

Modo demonstração passou a ser **desligado por padrão**: instalação nova começa
vazia, com apenas o catálogo de transportadoras integráveis e um administrador
criado por `app:create-admin`.
