# Solicitação de Integração via API

**De:** [Sua empresa] — plataforma InterlinkedLog
**Para:** [Nome da transportadora]
**Assunto:** Integração automática de cotação e rastreamento

---

## PARTE 1 — Para o contato comercial

> **Esta primeira página não tem nada técnico.** Leia, e se fizer sentido,
> encaminhe a Parte 2 para quem cuida de sistemas / TI na sua empresa.

### O que queremos fazer

Hoje, quando precisamos cotar um frete com vocês, alguém da nossa equipe
consulta a tabela que vocês nos enviaram, ou entra em contato por telefone ou
e-mail. Isso funciona, mas é lento e sujeito a erro de digitação.

Queremos que **nosso sistema converse direto com o sistema de vocês** — do mesmo
jeito que um site de viagens consulta o preço de várias companhias aéreas ao
mesmo tempo e mostra tudo junto na tela.

### O que isso muda para vocês

- **Vocês recebem mais pedidos de cotação**, porque passam a ser consultados
  automaticamente em toda carga que damos entrada — não só quando alguém lembra
  de ligar.
- **Menos trabalho manual** da equipe de vocês respondendo cotação por e-mail.
- **Preço sempre atualizado.** Hoje trabalhamos com a tabela que vocês nos
  mandaram por planilha; se ela desatualiza, cotamos errado. Com a integração,
  o valor vem direto da fonte.
- **Não pedimos exclusividade nem mudança contratual.** É só a forma de trocar
  informação que muda.

### O que precisamos de vocês

Nada que você precise resolver agora. Só precisamos falar com **a pessoa ou
empresa que cuida do sistema de vocês** — pode ser TI interno, o fornecedor do
ERP/TMS, ou o desenvolvedor que mantém o site.

### 👉 Como encaminhar

Se vocês **já têm** uma API (integração para clientes), repasse a Parte 2 para o
TI e peça a documentação.

Se vocês **não sabem** se têm, repasse mesmo assim — o TI vai saber responder.

Se vocês **não têm** API, também nos avise. Continuamos trabalhando com a tabela
em planilha normalmente, sem nenhum prejuízo à parceria. Só queremos saber para
planejar.

**Contato do nosso lado:**
[Nome] · [e-mail] · [telefone]

---
---

## PARTE 2 — Para o time de TI / Sistemas

Somos uma plataforma de cotação, contratação e rastreamento de fretes.
Queremos integrar com a API de vocês. Abaixo está o que precisamos receber.

**Se vocês já têm documentação pronta (Swagger/OpenAPI, PDF, Postman), pode
mandar só isso e ignorar o checklist** — nós extraímos o que falta e voltamos
com dúvidas pontuais.

### 1. Acesso e ambientes

- [ ] URL base de **produção**
- [ ] URL base de **homologação/sandbox** (existe? é isolado da produção?)
- [ ] Como obter credenciais de teste, e quanto tempo costuma levar
- [ ] Precisa liberar nosso IP em firewall? Se sim, qual o processo?

### 2. Autenticação

- [ ] Método: API key em header · OAuth2 (client credentials) · Basic Auth ·
      token com expiração · certificado digital · outro
- [ ] As credenciais são **por cliente** (uma para cada empresa que contrata
      vocês) ou uma só para o integrador? *(Isso é importante para nós: cada
      cliente nosso tem contrato próprio com vocês e precisa cotar com o preço
      dele.)*
- [ ] Token expira? Qual o tempo e como renovar?

### 3. Cotação de frete — o mínimo que precisamos

- [ ] Endpoint, método HTTP e formato (JSON / XML / SOAP)
- [ ] **Campos de entrada obrigatórios.** Do nosso lado temos: CEP de origem,
      CEP de destino, peso total (kg), volume/cubagem (m³), quantidade de
      volumes, valor da mercadoria (NF-e), CNPJ do remetente e do destinatário
- [ ] **Campos de retorno**, especialmente: valor do frete, prazo em dias,
      e o **detalhamento das taxas** (ad valorem, GRIS, pedágio, despacho,
      frete mínimo) — precisamos mostrar a composição do preço, não só o total
- [ ] A cotação retornada é **vinculante**? Por quanto tempo o preço vale?
- [ ] Existe número/protocolo de cotação que possamos referenciar depois na
      contratação?
- [ ] Como vocês respondem quando **não atendem** a rota? Erro, lista vazia, ou
      valor zerado?

### 4. Contratação / solicitação de coleta *(se existir)*

- [ ] Dá para **fechar o frete** pela API, ou a contratação continua manual?
- [ ] É possível emitir/receber o **CT-e** pela integração?
- [ ] Como solicitar coleta e consultar se foi aceita?
- [ ] Existe cancelamento? Qual a janela?

### 5. Rastreamento *(se existir)*

- [ ] Consulta por CT-e, NF-e ou código próprio?
- [ ] Vocês oferecem **webhook** (avisam quando o status muda) ou precisamos
      **consultar periodicamente**? Se for consulta, qual a frequência aceitável?
- [ ] Lista dos **status possíveis** e o que cada um significa

### 6. Limites e operação

- [ ] Limite de requisições (por minuto/hora/dia)
- [ ] Tempo de resposta típico e timeout recomendado
- [ ] Janela de manutenção programada
- [ ] Tabela de **códigos de erro** e o significado de cada um
- [ ] Canal para reportar problema de integração (e-mail, chamado, WhatsApp)
- [ ] Contato técnico direto: nome e e-mail

### 7. Se a documentação formal não existir

Sem problema — é comum. Nesse caso, o que mais ajuda, em ordem de preferência:

1. Coleção do **Postman** ou **Insomnia** exportada
2. **Exemplos reais** de requisição e resposta (pode ser print ou texto colado),
   um de sucesso e um de erro
3. Uma **conversa de 30 minutos** com quem conhece a API — nós documentamos e
   mandamos de volta para vocês validarem

### Sobre segurança

As credenciais que vocês nos fornecerem serão armazenadas **criptografadas**,
isoladas por cliente, e nunca aparecem em tela, log ou relatório. Podemos
assinar NDA se for requisito de vocês.

---

**Contato técnico do nosso lado:**
[Nome] · [e-mail] · [telefone]
