# Plano de Fases — Backlog pós-migração MySQL

> Continuação do trabalho concluído em `DOC/PROMPT-Migracao-MySQL.md` (Fases 0–4).
> Mesmas regras de execução: uma fase por vez, parada obrigatória ao fim de cada
> uma, sem scope creep, commit em português sem marca de ferramenta.

---

## Ordem e dependências

```
FASE A — Precificação          ← única com custo em dinheiro; faça primeiro
   ↓
FASE B — Segurança             ← destrava integração com APIs de transportadora
   ↓
FASE C — Runtime de produção   ← gargalo dominante do relatório inicial
   ↓
FASE D — Motor de cotação      ← depende de decisões de negócio
```

**A Fase A deve vir antes de qualquer integração com API de transportadora.**
Se você ligar a API por cima de um motor que precifica errado, toda divergência
entre o valor da API e o valor da tabela vira investigação — e não haverá
referência confiável para comparar.

---

# FASE A — Correção de precificação

**Por que primeiro:** o sistema gera contrato com valor vinculante calculado a
partir destes bugs. É o único grupo do backlog com custo financeiro direto.

## A.1 — `weightRanges` vem apenas da primeira rota

`EloquentFreightTableRepository::toEntity`:

```php
$weightRanges = [];
if ($model->routes->isNotEmpty()) {
    $firstRoute = $model->routes->first();          // ← SOMENTE a primeira rota
    $weightRanges = $firstRoute->weightRanges->map(...)->toArray();
}
```

O `QuotationEngine::process` casa o peso contra `$table->weightRanges` — ou seja,
contra as faixas da primeira rota, **independentemente do destino cotado**.

Numa tabela com São Paulo→Curitiba e São Paulo→Recife, uma cotação para Recife é
precificada com as faixas de Curitiba. Quando nenhuma faixa casa, o motor devolve
`$freightValue = 0` silenciosamente.

**Correção exigida:** as faixas de peso precisam ficar aninhadas por rota na
entidade de domínio, e o motor precisa casar o peso contra as faixas da **rota
que casou com o destino**.

Isso muda o formato de `FreightTable::$routes` / `$weightRanges` — é alteração de
entidade de domínio, não só de mapeamento. Avalie manter `weightRanges` no topo
por compatibilidade **ou** removê-lo; documente a escolha.

## A.2 — Prazo vem da primeira faixa de peso

No mesmo `toEntity`, o prazo de cada rota é lido de `$route->weightRanges->first()->deadline_days`.
Como `deadline_days` está na faixa de peso, faixas diferentes podem ter prazos
diferentes — e hoje sempre vence o da primeira.

**Correção exigida:** o prazo deve vir da faixa de peso que efetivamente casou
com o peso da carga, resolvido junto com A.1.

## A.3 — Taxa percentual é cobrada duas vezes

`is_percentage` é booleano; o número mora em `value`. O mapeamento produz:

```php
'value'      => (float) $fee->value,                        // sempre o número cru
'percentage' => $fee->is_percentage ? (float) $fee->value : 0,
```

E `QuotationEngine::calculateFees` soma **os dois campos**:

```php
$total += $fee['value'] ?? 0;                               // ← indevido p/ percentual
if (!empty($fee['percentage'])) {
    $total += $cargoValue * ($fee['percentage'] / 100);
}
```

Com o seed (ad valorem `0.30` percentual, pedágio `5.00` percentual) sobre carga
de R$ 5.000:

| Taxa | Hoje | Correto |
|---|---|---|
| ad_valorem | 0,30 + 15,00 = **15,30** | 15,00 |
| pedagio | 5,00 + 250,00 = **255,00** | 250,00 |
| **Total da tabela** | **704,20** | **698,90** |

O erro é fixo em R$ 5,30 (soma dos valores crus das taxas percentuais), não
proporcional — portanto relativamente pior em cargas pequenas.

**Correção exigida:** uma taxa percentual não deve somar seu valor cru. O
mesmo vale para `getFeesBreakdown`, que replica a lógica.

## A.4 — Testes de caracterização

Os testes em `QuotationFlowTest` congelam os valores errados (142, 704.20,
846.20, prazo 2) e estão anotados com comentários `// CARACTERIZAÇÃO`.

**Ao corrigir, esses números DEVEM mudar.** Atualize-os para os valores corretos
e **remova as anotações de caracterização**, substituindo por asserções normais.
Registre no relatório o antes/depois de cada número, com a conta que justifica o
valor novo.

Os comentários referenciam `DOC/ArchitecturalReview.md`, que não está
versionado — ao reescrevê-los, torne a descrição autocontida.

## A.5 — Pergunta de negócio (NÃO corrigir)

`frete_minimo` é somado como taxa. Semanticamente, frete mínimo deveria ser um
**piso sobre o valor do frete**, não um acréscimo. Idem `cubagem`, somada como
valor fixo.

Não altere. Apenas registre no relatório como pergunta pendente ao mantenedor.

## Critérios de aceite — Fase A

- [ ] Faixas de peso resolvidas por rota; cotação para destino B não usa faixas do destino A
- [ ] Prazo vem da faixa que casou com o peso
- [ ] Taxa percentual não soma o valor cru; `getFeesBreakdown` consistente com `calculateFees`
- [ ] Teste novo: tabela com **duas rotas** e faixas distintas, provando que cada destino recebe o próprio preço e prazo (este teste falha no código atual)
- [ ] Teste novo: taxa percentual produz exatamente `cargoValue × pct / 100`
- [ ] Números de caracterização atualizados, anotações removidas, contas registradas
- [ ] Suíte verde contra MySQL (`docker compose run --rm test`)

---

# FASE B — Segurança

**Por que depois de A:** nenhum item aqui produz cálculo errado, mas B.1 é
pré-requisito para integrar com APIs de transportadora.

## B.1 — `APP_KEY` versionada *(bloqueante para integrações)*

`backend/.env` está no git com a `APP_KEY` real. Ela é a chave de criptografia
da aplicação — qualquer pessoa com acesso ao repositório descriptografa o que
for guardado com `encrypted` cast.

Enquanto isso for verdade, **não é possível armazenar credenciais de API de
transportadoras**.

Exigido:
- Remover `backend/.env` do versionamento (`git rm --cached`), adicionar ao `.gitignore`
- **Rotacionar** a `APP_KEY` — a antiga está comprometida por estar no histórico
- Conferir se algum dado existente foi criptografado com a chave antiga; se sim,
  planejar reencriptação antes de trocar
- O default de dev no `docker-compose.yml` pode permanecer, desde que seja
  explicitamente marcado como chave de desenvolvimento

## B.2 — `POST /register` quebrado e público

`AuthController.php` usa a regra `unique_check:users`, que não existe no Laravel
e não é registrada em lugar nenhum — o endpoint retorna 500 em 100% das chamadas.

**Atenção à ordem:** esse bug é o que hoje impede auto-provisionamento anônimo de
tenants. Corrigir a validação sem tratar o acesso **ativa** a exposição.

Decida e implemente uma das opções, justificando:
- **(a)** Remover a rota — cadastro de tenant passa a ser operação administrativa
- **(b)** Corrigir a validação (`unique:users,email`) **e** proteger o endpoint
  (convite, aprovação ou chave de registro)

## B.3 — Rate limiting no login

`POST /api/v1/login` não tem `throttle`. Bcrypt com 12 rounds encarece o
brute-force, não o impede.

Aplicar `throttle` na rota de login e nas demais rotas públicas.

## B.4 — Token em `localStorage`

`src/lib/api.ts` guarda o token em `localStorage` — qualquer XSS exfiltra a
sessão.

Esta é a única alteração de frontend do plano. Avalie migrar para cookie
`httpOnly` + `SameSite`, o que exige mudança coordenada de backend e frontend.
**Se o custo for alto, documente a decisão de adiar em vez de implementar pela
metade.**

## Critérios de aceite — Fase B

- [ ] `.env` fora do versionamento, `APP_KEY` rotacionada, aplicação sobe do zero
- [ ] `/register` resolvido conforme a opção escolhida, com teste cobrindo o comportamento
- [ ] `throttle` no login, com teste provando o bloqueio após N tentativas
- [ ] B.4 implementado **ou** decisão de adiar registrada com justificativa
- [ ] Suíte verde

---

# FASE C — Runtime de produção

**Por que depois de B:** é o gargalo dominante identificado no relatório
inicial, mas não produz dado errado nem vazamento — só limita capacidade.

## C.1 — Substituir `php artisan serve`

`backend/docker-entrypoint.sh` termina em `php artisan serve`, que é
single-threaded e **atende uma requisição por vez**. Qualquer otimização de query
ou índice é irrelevante enquanto isso estiver no ar.

Opções: php-fpm + nginx (dois processos, config maior) ou FrankenPHP (um
binário, mais simples para mantenedor solo). Recomendo avaliar FrankenPHP
primeiro dado o contexto de manutenção individual.

O entrypoint precisa preservar: espera do MySQL com retry, `migrate --force`, e
o seed condicional em banco vazio.

## C.2 — Build de produção no frontend

O `Dockerfile` da raiz roda `bun run dev` — Vite dev server em container. O
diretório `dist/` já existe no projeto; é trocar para build + servidor estático
ou SSR de produção.

## C.3 — Volume para `storage/`

`CACHE_STORE=file` e `SESSION_DRIVER=file`, mas `storage/` **não está em volume**
no compose. Todo rebuild invalida todas as sessões — os tokens vivem no cache.

Adicionar volume **ou** migrar cache/sessão para o banco. A segunda opção é mais
robusta e não exige volume novo.

## Critérios de aceite — Fase C

- [ ] Servidor de produção substituindo `artisan serve`; requisições concorrentes atendidas em paralelo (comprove com teste de carga simples, ex.: `ab` ou `hey`)
- [ ] Frontend servindo build de produção
- [ ] Sessões sobrevivem a `docker compose restart backend`
- [ ] `down -v && up --build` sobe do zero; suíte verde; `test-all.sh` passa

---

# FASE D — Motor de cotação

**Depende de decisões de negócio.** Não inicie sem as respostas.

## D.1 — `cepMap` hardcoded com fallback silencioso

`QuotationEngine::cepToCity` tem 22 prefixos e **assume São Paulo** para todo o
resto do país:

```php
return $this->cepMap[$prefix] ?? ['São Paulo', 'SP'];
```

Um CEP desconhecido não gera erro — gera **cotação errada com aparência de
correta**. Esse fallback é pior que uma exceção.

Correção: integração com serviço de CEP (ViaCEP, BrasilAPI) com cache local, e
**erro explícito** quando o CEP não resolver. A porta `QuotationEngineService`
já isola isso.

## D.2 — Normalização de acentos *(decisão pendente)*

`Marilia` não casa com `Marília` em nenhum banco. Hoje o motor usa
`mb_strtolower`, que resolve caixa mas não acento.

**Pergunta ao negócio:** usuário digitando destino sem acento deve casar? O
argumento a favor é ergonomia; contra, aumenta risco de casar cidades homônimas.

Se a resposta for sim, exige `Transliterator` ou `iconv` — e vale notar que a
D.1 pode tornar isso irrelevante, já que o CEP passaria a resolver a cidade
canônica.

## Critérios de aceite — Fase D

- [ ] CEP não resolvido produz erro explícito, nunca fallback silencioso
- [ ] Serviço externo com cache, timeout e degradação controlada
- [ ] D.2 implementado conforme a decisão, ou registrado como descartado

---

# Fora de todas as fases

Itens conhecidos, deliberadamente não planejados:

- Constraint única em `companies` (o seed condicional resolve o sintoma operacional)
- Duplicação Model/Entity com mapeamento manual — custo aceito da arquitetura hexagonal
- Fila assíncrona para PDF. **Se for implementada**, o `company_id` precisa
  viajar no payload do job: o `TenantScope` lê do binding de request e fica
  inerte no worker. Há um check em `scripts/validate.sh` que dispara
  automaticamente quando `QUEUE_CONNECTION` sair de `sync`.
