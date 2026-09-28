# Adendo da Fase 1 — validar tenant em `TrackingController::show`

A Fase 1 foi **aprovada**. Este é um adendo pequeno e fechado, decorrente de um
item que você mesmo reportou como fora de escopo. Reavaliei o custo e ele mudou
por causa do que a própria Fase 1 entregou.

Corrija exatamente o que está aqui e nada além.

---

## O PROBLEMA

`GET /api/v1/tracking/{contractId}` devolve os eventos de rastreio de **qualquer
contrato**, de qualquer tenant, para qualquer usuário autenticado.

`app/Http/Controllers/Api/TrackingController.php`:

```php
public function show(string $contractId): JsonResponse   // ← sem Request, sem checagem
{
    $events = $this->trackingRepository->findByContract($contractId);
    // ...devolve os eventos direto
}
```

`index` e `store` no mesmo arquivo validam tenant corretamente. Apenas o `show`
não valida.

**Por que agora é barato.** Seu argumento original foi que `TrackingEvent` não
possui `company_id` e adicioná-lo estava fora do plano. O argumento está certo —
e continua valendo. Mas a coluna não é necessária: a Fase 1 aplicou
`TenantScoped` ao model `Contract`, então
`$this->contractRepository->findById($contractId)` **já retorna `null`** quando o
contrato pertence a outro tenant. Basta resolver o contrato antes e devolver 404.

Sem migration. Sem alteração de schema. Sem tocar em `TrackingEvent`.

---

## O QUE FAZER

### 1. `app/Http/Controllers/Api/TrackingController.php`

Aplicar em `show` **exatamente o mesmo padrão já usado em `store`** neste arquivo
— inclusive a checagem manual redundante, que é a defesa em profundidade que a
Fase 1 determinou manter:

```php
public function show(Request $request, string $contractId): JsonResponse
{
    $companyId = $request->attributes->get('company_id');
    $contract = $this->contractRepository->findById($contractId);

    if (!$contract || $contract->companyId !== $companyId) {
        return response()->json(['message' => 'Contratação não encontrada'], 404);
    }

    $events = $this->trackingRepository->findByContract($contractId);

    // ...resto do método inalterado
}
```

Mensagem e status devem ser idênticos aos de `store` (`'Contratação não
encontrada'`, 404) — não invente variação, e **não** use 403: o 404 evita revelar
que o contrato existe.

Confira se a rota em `routes/api.php` continua funcionando com a nova assinatura.
O Laravel injeta `Request` automaticamente quando tipado como primeiro
parâmetro, então nenhuma mudança de rota deve ser necessária — mas verifique.

### 2. Testes

Adicione a `tests/Feature/` (arquivo novo ou o de tenancy existente, o que fizer
mais sentido):

- **Vazamento fechado:** tenant A pede `GET /api/v1/tracking/{id}` com contrato
  do tenant B → **404**
- **Sem regressão:** tenant A pede o rastreio do **próprio** contrato → 200 com
  os eventos esperados (prove que a correção não quebrou o caminho legítimo)
- **Contrato inexistente:** ID que não existe → 404

O teste de vazamento deve **falhar no código atual**. Verifique isso antes de
aplicar a correção e informe no relatório como comprovou.

---

## O QUE **NÃO** FAZER

- **Não adicione** `company_id` a `tracking_events`. Não é necessário e não está
  no plano.
- **Não altere** `index` nem `store` — já estão corretos.
- Não refatore o mapeamento de eventos nem o formato da resposta.
- Seguem valendo as exclusões dos prompts anteriores: `POST /register`,
  `APP_DEBUG`, `cepMap`, `artisan serve`, bug de `weightRanges`, soma dupla de
  taxas percentuais.

---

## CRITÉRIOS DE ACEITE

- [ ] `show` recebe `Request`, resolve o contrato e devolve 404 quando não
      pertence ao tenant autenticado
- [ ] Padrão idêntico ao de `store` no mesmo arquivo, incluindo a checagem manual
- [ ] Teste de vazamento falha antes da correção e passa depois (comprovado)
- [ ] Teste de não-regressão: rastreio do próprio contrato segue retornando 200
- [ ] `php artisan test` verde, sem redução no número de testes (21 → 24)
- [ ] Nenhuma asserção existente alterada

---

## COMMIT

Um único commit:

```
fix: validar tenant em TrackingController::show
```

Valem as convenções de sempre: mensagem em português, **sem trailer
`Co-Authored-By:`, sem rodapé de ferramenta, sem menção a assistente ou IA** no
commit ou no PR. Confira com `git log -1 --format=%B` antes de finalizar.

Reporte no formato das fases anteriores e aguarde revisão antes de iniciar a
Fase 2.
