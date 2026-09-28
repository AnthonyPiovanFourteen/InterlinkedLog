# Roteiro de Apresentação — InterlinkedLog

---

## Slide 1 — Abertura

**Título:** InterlinkedLog
**Subtítulo:** Plataforma SaaS de cotação, contratação e rastreamento de fretes

**Corpo:**
Uma empresa que precisa transportar carga hoje liga, manda WhatsApp, espera retorno, anota em planilha, compara manualmente e ainda assim não tem certeza se fez o melhor negócio.
O InterlinkedLog resolve isso — do upload da nota fiscal ao rastreamento da entrega, tudo em um só lugar.

---

### 🎙️ FALA
Bom dia. Vou apresentar o InterlinkedLog — uma plataforma que centraliza todo o processo de frete: cotar, contratar e rastrear. Mas antes de mostrar a solução, preciso mostrar o problema que ela resolve.

---

---

## Slide 2 — Qual é o problema?

**Título:** Qual é o problema?

**Corpo:**
Hoje, contratar um frete envolve:

1. Ligar ou mandar WhatsApp para cada transportadora separadamente
2. Aguardar o retorno de cada uma — nem sempre no mesmo dia
3. Comparar preços manualmente em planilha ou papel
4. Entrar em contato novamente para confirmar a contratação
5. Acompanhar a entrega por canais externos, sem visibilidade real

**Consequências diretas:**
- Uma cotação simples consome horas de trabalho operacional
- Erros de digitação e comparações incorretas são frequentes
- Não há histórico de quem contratou o quê, por qual preço ou em qual prazo
- Quando a entrega atrasa, ninguém sabe onde a carga está

**Frase de fechamento do slide:**
> O problema não é a transportadora. É a falta de um processo centralizado.

---

### 🎙️ FALA
Esse fluxo acontece em empresas reais, todo dia. Cada carga vira uma mini-operação manual. Com 10, 20, 30 embarques por semana, isso consome horas de trabalho que poderiam estar em outra coisa. E o pior: quando o gestor pergunta quanto foi gasto com frete no mês — ninguém sabe responder de imediato.

---

---

## Slide 3 — Público-alvo

**Título:** Para quem é o InterlinkedLog?

**Corpo:**
O InterlinkedLog é para empresas que já cresceram além da planilha, mas ainda não têm um sistema de logística estruturado.

**Perfil ideal:**
- Pequenas e médias empresas com 5 ou mais embarques por semana
- Que trabalham com 2 ou mais transportadoras simultaneamente
- Que precisam de histórico, controle e rastreabilidade das contratações
- Que não têm equipe de TI própria para implantar sistemas complexos

**Quem usa dentro da empresa:**

| Perfil | O que ganha |
|--------|------------|
| Operador de logística | Para de cotar manualmente — o sistema faz em segundos |
| Gestor de compras | Visualiza custos, economia gerada e desempenho por transportadora |
| Diretor / sócio | Tem histórico completo, auditoria e KPIs no painel |

**Frase de fechamento do slide:**
> Não é para quem já tem ERP de logística. É para quem ainda depende de planilha e WhatsApp.

---

### 🎙️ FALA
Grandes operadores já têm solução. Nosso público é a empresa que cresceu além do WhatsApp e da planilha, mas ainda não deu o próximo passo. O operador quer ganhar tempo. O gestor quer ver número. O dono quer controle. O InterlinkedLog atende os três sem precisar de TI.

---

---

## Slide 4 — Solução: como funciona e o que cobre

**Título:** Como o InterlinkedLog resolve

**O fluxo completo:**

```
Upload do XML da NF-e
        ↓
Sistema extrai automaticamente: CEP origem e destino, peso, volume, valor da carga
        ↓
Motor de cotação consulta todas as transportadoras cadastradas
Calcula: frete base + ad valorem + GRIS + despacho + pedágio + TDE
        ↓
Ranking comparativo: Melhor Preço · Melhor Prazo · Melhor Custo-Benefício
        ↓
Operador seleciona a transportadora
PDF da Solicitação de Coleta gerado automaticamente
        ↓
Transportadora coleta e emite o CT-e
Operador registra o número no sistema
        ↓
Rastreamento: timeline de eventos com alerta automático de atraso
```

**O que a plataforma cobre:**

| Módulo | Função |
|--------|--------|
| Painel de controle | KPIs, gráficos, mapa de rotas ativas no Brasil, taxa de conversão |
| Cotações | Histórico completo filtrado por status: válida, contratada, expirada, cancelada |
| Contratações | PDF Solicitação de Coleta + campo para registrar o CT-e da transportadora |
| Rastreamento | Timeline de eventos + comparativo prazo contratado vs. prazo real |
| Transportadoras | Cadastro de transportadoras + upload de tabela de frete em .xlsx |
| Usuários | Controle de acesso por perfil (Admin vê tudo; Usuário vê o operacional) |
| Auditoria | Registro de quem fez o quê, quando e em qual entidade |

**Frase de fechamento do slide:**
> Do upload do XML ao alerta de atraso na entrega — sem sair da plataforma.

---

### 🎙️ FALA
O fluxo começa com o upload do XML da nota fiscal. O sistema lê o arquivo e preenche tudo sozinho. Com um clique, o motor de cotação calcula o custo completo de cada transportadora cadastrada e devolve um ranking. O operador escolhe, o PDF é gerado na hora. Depois é só registrar o CT-e quando a transportadora emitir e acompanhar pela timeline. Se atrasar, o sistema mostra automaticamente.

---

---

## Slide 5 — Conclusão

**Título:** O que muda na prática

**Antes × Depois:**

| Antes | Depois |
|-------|--------|
| Horas cotando por telefone e WhatsApp | Segundos — sistema processa automaticamente via XML |
| Comparação manual em planilha | Ranking visual com destaque de melhor preço, prazo e custo-benefício |
| Contrato feito por e-mail ou verbal | PDF da Solicitação de Coleta gerado na hora |
| Rastreamento por WhatsApp com a transportadora | Timeline centralizada com alerta de atraso automático |
| Sem histórico — "deixa eu procurar no e-mail" | Dashboard com KPIs, histórico completo e auditoria |

**O que não muda:**
Você continua trabalhando com as mesmas transportadoras. O que muda é como você chega até a decisão — e o que você consegue provar depois.

**Acesse:**
```
http://localhost:3000
admin@interlinked.io  ·  admin123
```

---

### 🎙️ FALA
O InterlinkedLog não elimina a transportadora nem muda sua operação. Ele elimina o trabalho manual que acontece antes e depois de cada frete. Em vez de horas, segundos. Em vez de planilha, um painel. Em vez de "acho que foi entregue no prazo", um número. Para quem quiser ver funcionando, o acesso está disponível agora. Obrigado.

---

---

## Estrutura de tempo sugerida

| Slide | Tema | Tempo |
|-------|------|-------|
| 1 | Abertura | 30s |
| 2 | O Problema | 1 min 30s |
| 3 | Público-alvo | 1 min 30s |
| 4 | Como funciona e o que cobre | 2 min |
| 5 | Conclusão | 1 min |
| **Total** | | **~6 min 30s** |

---

---

# Prompt para gerar os slides

> Cole o prompt abaixo no ChatGPT, Claude, Gamma ou qualquer ferramenta de geração de apresentações.

---

```
Crie uma apresentação de 5 slides para o produto InterlinkedLog. O estilo deve ser direto e literal — os slides precisam ter texto suficiente para ser lido durante a apresentação, não apenas tópicos soltos. Cada slide deve contar uma parte da história de forma completa.

---

SLIDE 1 — ABERTURA
Título: InterlinkedLog
Subtítulo: Plataforma SaaS de cotação, contratação e rastreamento de fretes
Corpo: "Uma empresa que precisa transportar carga hoje liga, manda WhatsApp, espera retorno, anota em planilha, compara manualmente e ainda assim não tem certeza se fez o melhor negócio. O InterlinkedLog resolve isso — do upload da nota fiscal ao rastreamento da entrega, tudo em um só lugar."
Visual: fundo escuro (azul-marinho), logo centralizado, ícone de caminhão + conexão digital.

---

SLIDE 2 — QUAL É O PROBLEMA?
Título: Qual é o problema?
Corpo principal — lista numerada com o fluxo atual:
1. Ligar ou mandar WhatsApp para cada transportadora separadamente
2. Aguardar o retorno de cada uma — nem sempre no mesmo dia
3. Comparar preços manualmente em planilha ou papel
4. Entrar em contato novamente para confirmar a contratação
5. Acompanhar a entrega por canais externos, sem visibilidade real

Coluna lateral ou bloco de destaque com as consequências (ícone ❌ em cada item):
- Uma cotação simples consome horas de trabalho operacional
- Erros de digitação e comparações incorretas são frequentes
- Não há histórico de quem contratou o quê, por qual preço ou prazo
- Quando a entrega atrasa, ninguém sabe onde a carga está

Frase de fechamento em destaque (fundo levemente diferente): "O problema não é a transportadora. É a falta de um processo centralizado."
Visual: layout dividido, lado esquerdo com ícones de telefone/WhatsApp/planilha conectados de forma desorganizada, lado direito com os impactos em vermelho.

---

SLIDE 3 — PÚBLICO-ALVO
Título: Para quem é o InterlinkedLog?
Parágrafo de abertura: "Para empresas que já cresceram além da planilha, mas ainda não têm um sistema de logística estruturado."

Perfil ideal — 4 bullets:
- Pequenas e médias empresas com 5 ou mais embarques por semana
- Que trabalham com 2 ou mais transportadoras simultaneamente
- Que precisam de histórico, controle e rastreabilidade das contratações
- Que não têm equipe de TI própria para implantar sistemas complexos

Tabela com 3 perfis de usuário interno:
- Operador de logística → Para de cotar manualmente — o sistema faz em segundos
- Gestor de compras → Visualiza custos, economia gerada e desempenho por transportadora
- Diretor / sócio → Tem histórico completo, auditoria e KPIs no painel

Frase de fechamento em destaque: "Não é para quem já tem ERP de logística. É para quem ainda depende de planilha e WhatsApp."
Visual: 3 cards com ícone de pessoa, cada um com o perfil e o ganho correspondente. Cores: azul para perfis, verde para ganhos.

---

SLIDE 4 — SOLUÇÃO: COMO FUNCIONA E O QUE COBRE
Título: Como o InterlinkedLog resolve

Fluxo vertical com setas (coluna esquerda):
Upload do XML da NF-e → Sistema extrai CEP, peso, volume, valor → Motor de cotação calcula custo completo (frete base + ad valorem + GRIS + despacho + pedágio + TDE) → Ranking comparativo: Melhor Preço · Melhor Prazo · Melhor Custo-Benefício → Operador seleciona → PDF da Solicitação de Coleta gerado → Registro do CT-e → Rastreamento com alerta de atraso

Tabela de módulos (coluna direita):
- Painel de controle: KPIs, gráficos, mapa de rotas ativas no Brasil
- Cotações: histórico com filtros por status
- Contratações: PDF automático + campo para CT-e
- Rastreamento: timeline + alerta de prazo
- Transportadoras: cadastro + upload de tabela .xlsx
- Auditoria: log completo de ações

Frase de fechamento em destaque: "Do upload do XML ao alerta de atraso na entrega — sem sair da plataforma."
Visual: layout dois painéis. Esquerda: fluxo com ícones e setas verticais. Direita: tabela de módulos com ícones. Este é o slide mais importante — dar mais espaço e destaque visual.

---

SLIDE 5 — CONCLUSÃO
Título: O que muda na prática

Tabela Antes × Depois (duas colunas contrastadas):
Coluna ❌ Antes (fundo vermelho suave):
- Horas cotando por telefone e WhatsApp
- Comparação manual em planilha
- Contrato por e-mail ou verbal
- Rastreamento por WhatsApp com a transportadora
- "Deixa eu procurar no e-mail"

Coluna ✅ Depois (fundo verde suave):
- Segundos — sistema processa via XML automaticamente
- Ranking visual com destaque de melhor preço, prazo e custo-benefício
- PDF da Solicitação de Coleta gerado na hora
- Timeline centralizada com alerta de atraso automático
- Dashboard com KPIs, histórico e auditoria completa

Bloco de texto abaixo da tabela: "Você continua trabalhando com as mesmas transportadoras. O que muda é como você chega até a decisão — e o que você consegue provar depois."

Rodapé em caixa destacada (fundo escuro, texto branco): "Acesse agora → http://localhost:3000 | admin@interlinked.io · admin123"

---

DIRETRIZES GERAIS:
- Paleta: azul-marinho (#0f172a), azul médio (#1e40af), verde (#16a34a), vermelho (#dc2626), cinza claro (#f1f5f9)
- Tipografia: Inter ou similar sans-serif — títulos bold, corpo regular
- Os slides devem ter texto suficiente para ser lido durante a apresentação — não são slides minimalistas
- Linguagem: português brasileiro, tom direto e profissional
- Frases de fechamento de cada slide devem ser visualmente destacadas (caixa, cor diferente ou itálico bold)
```
