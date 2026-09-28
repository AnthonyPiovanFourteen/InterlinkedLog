# Correção da Fase 0 — fronteira de transação em `save()`

A Fase 0 foi revisada e **aprovada condicionalmente**. Há um defeito a corrigir
antes de seguir. Este é um escopo fechado: corrija exatamente o que está aqui e
nada além.

---

## O PROBLEMA

Nos dois repositórios alterados, o `updateOrCreate` da linha-mãe ficou **fora**
da transação, e apenas os filhos ficaram dentro:

```php
// EloquentQuotationRepository::save — ESTADO ATUAL, INCORRETO
Quotation::updateOrCreate([...]);          // ← autocommit, fora da transação

if (!empty($quotation->results)) {
    DB::transaction(function () use ($id, $quotation) {
        QuotationResult::where('quotation_id', $id)->delete();
        foreach ($quotation->results as $result) {
            QuotationResult::create([...]);
        }
    });
}
```

A justificativa registrada no relatório da Fase 0 foi:

> "Se a linha-mãe falhar, nada é tocado; se o delete+inserts falhar, nada é
> perdido."

**A segunda metade está incorreta.** Ela vale para o caminho de *atualização*,
mas não para o de *criação*.

`QuotationController.php:108` chama `save()` **sem transação externa**. Se os
inserts falharem ali, o rollback desfaz somente os filhos — a linha-mãe já foi
commitada em autocommit. Resultado: **cotação persistida com zero resultados**,
que é precisamente a escrita parcial que a Fase 0 existia para eliminar.

O mesmo vale para `EloquentFreightTableRepository::save`, chamado por
`FreightTableController::store` sem transação externa → tabela de frete órfã,
sem rotas, faixas de peso nem taxas.

O caminho da contratação está coberto, mas só porque `ContractController::store`
envolve tudo na própria transação. A cobertura vem do chamador, não do
repositório — e é o repositório que deve garantir a própria consistência.

---

## O QUE FAZER

### 1. `app/Infrastructure/Repositories/Eloquent/EloquentQuotationRepository.php`

Mover o `updateOrCreate` para dentro da mesma transação que já envolve os
filhos. Atenção ao detalhe: **a linha-mãe deve ser sempre gravada**, enquanto os
filhos só são regravados quando há `results`. A guarda `if (!empty(...))` fica
dentro da transação, não em volta dela.

Forma esperada:

```php
public function save(QuotationEntity $quotation): void
{
    $id = $quotation->id ?? Str::orderedUuid()->toString();

    DB::transaction(function () use ($id, $quotation) {
        Quotation::updateOrCreate(['id' => $id], [ ...campos... ]);

        if (!empty($quotation->results)) {
            QuotationResult::where('quotation_id', $id)->delete();
            foreach ($quotation->results as $result) {
                QuotationResult::create([ ...campos... ]);
            }
        }
    });
}
```

Não altere os campos nem a lógica de mapeamento — apenas a fronteira.

### 2. `app/Infrastructure/Repositories/Eloquent/EloquentFreightTableRepository.php`

Mesma correção: o `FreightTable::updateOrCreate` entra na transação que já
envolve rotas, faixas de peso e taxas.

### 3. Teste que prova a correção

Adicione um teste que **falharia antes** desta correção:

- Force uma falha na escrita dos filhos durante um `save()` de entidade **nova**
  (caminho de criação, não de atualização)
- Afirme que a exceção propaga
- Afirme que a **linha-mãe não foi persistida** (`Quotation::count() === 0`, ou
  equivalente para tabela de frete)

O mecanismo de falha fica a seu critério — violação de FK, coluna `NOT NULL`,
ou mock do model. **Documente no relatório qual escolheu e por quê.**

Cubra ao menos `EloquentQuotationRepository`. Se o mesmo teste for barato para
`EloquentFreightTableRepository`, faça os dois.

### 4. Anotar os testes de caracterização que congelam comportamento errado

A revisão confirmou que `EloquentFreightTableRepository::toEntity` monta
`weightRanges` a partir **somente da primeira rota** da tabela
(`$model->routes->first()`), e o `QuotationEngine` casa o peso contra essas
faixas independentemente do destino cotado. Numa tabela com dois destinos, o
frete de um é precificado com as faixas do outro. Não é só o prazo que sai
errado — **é o valor do frete**.

Isso continua **fora de escopo**. Mas os testes de caracterização que você
escreveu fixam esses números, e alguém vai lê-los como especificação.

Exigido: em cada asserção que codifica valor de frete ou prazo hoje incorreto,
adicione comentário no formato:

```php
// CARACTERIZAÇÃO: congela comportamento SABIDAMENTE INCORRETO.
// weightRanges vem apenas da primeira rota (toEntity), então o valor
// abaixo pode não corresponder ao destino cotado. Ver DOC/ArchitecturalReview.md.
// Ao corrigir o bug, este número DEVE mudar — não o "conserte" para o teste passar.
```

---

## O QUE **NÃO** FAZER

- **Não corrija** o bug de `weightRanges`/`deadline` em `toEntity`. Está
  planejado para fase futura. Apenas anote, conforme o item 4.
- **Não corrija** a soma dupla de taxas percentuais em `calculateFees`
  (taxa percentual recebe o mesmo número em `value` e em `percentage`, e ambos
  são somados). Pré-existente, fora de escopo.
- Não mexa em `POST /register`, `APP_DEBUG`, `cepMap`, `artisan serve` — segue
  valendo a lista de exclusões do prompt original.
- Não refatore nem reformate nada além das duas funções `save()`.

---

## CRITÉRIOS DE ACEITE

- [ ] Em ambos os `save()`, o `updateOrCreate` está dentro da `DB::transaction`
- [ ] A guarda `if (!empty(...))` dos filhos está **dentro** da transação, e a
      linha-mãe é gravada em todos os caminhos
- [ ] Existe teste que falha antes e passa depois, provando que a linha-mãe não
      é persistida quando a escrita dos filhos falha
- [ ] Asserções de caracterização com valores incorretos estão anotadas
- [ ] `php artisan test` verde, sem redução no número de testes
- [ ] Os 12 testes existentes continuam passando sem alteração de asserção
      (se algum precisou mudar, **justifique** — pode indicar regressão)

---

## COMMIT

Um único commit:

```
fix: cobrir linha-mãe na transação de save() dos repositórios
```

Valem as convenções do prompt original: mensagem em português, **sem trailer
`Co-Authored-By:`, sem rodapé de ferramenta, sem menção a assistente ou IA** no
commit ou no PR. Confira com `git log -1 --format=%B` antes de finalizar.

Ao terminar, reporte no mesmo formato das fases anteriores e aguarde revisão
antes de iniciar a Fase 1.
