# Melhorias de 29/10/2026 — pagamentos e loja

## Como publicar

1. Envie os arquivos alterados (lista abaixo) para o GitHub e faça o merge na `main` — o Easypanel publica sozinho.
2. No phpMyAdmin do banco que está no ar, importe `01-sistema/sql/schema_melhorias_2026_10.sql`
   (aba Importar). Pode rodar mais de uma vez sem duplicar nada.
3. Em **Configurações → PDV**, confira o novo campo "Prazo das encomendas de carne de domingo".
4. Em **Configurações → Aparência**, preencha o **e-mail da loja** (aparece na Política de privacidade).

O sistema funciona mesmo antes do passo 2: as partes novas que dependem do banco só ficam desligadas.

## Correções de pagamento (as mais importantes)

| Problema | O que acontecia | Correção |
|---|---|---|
| Pix pago depois do prazo | O pedido já tinha sido cancelado e o estoque devolvido; o webhook ignorava o pagamento. O cliente pagava e ficava **sem pedido**, sem ninguém saber. | O pedido é reaberto automaticamente se ainda houver estoque. Se não houver, aparece um **alerta em Pedidos** para o lojista devolver o dinheiro. |
| Pix do checkout valia 24 h | O link fechava em 10 min, mas o Pix gerado continuava pagável por até 24 h. | O Pix agora vence junto com o pedido (`date_of_expiration`). Mesmo ajuste no Pix do caixa (30 min) e no de dívida (60 min). |
| Cancelar pagamento pendente não cancelava nada no MP | O sistema guardava o id da *preferência* e consultava como se fosse um *pagamento* (sempre 404). O Pix do cliente continuava valendo. | Busca todas as tentativas pelo número do pedido e cancela as pendentes. |
| Pagamento de dívida não abatia | Se o lojista tivesse baixado parte da dívida à mão no meio-tempo, o pagamento recebido não abatia **nada**. | Abate sempre (sem deixar saldo negativo) e registra no log se pagou a mais. |
| "Minha dívida" travava | Link de pagamento aberto e abandonado ficava "Pendente" para sempre e impedia gerar outro. | Pendentes com mais de 60 min expiram. Se o pagamento chegar depois, é confirmado mesmo assim. |
| Testes com a própria conta MP | `sponsor_id`/taxa de marketplace eram enviados mesmo quando a loja conectava a conta do próprio integrador, o que o Mercado Pago pode recusar. | Esses campos saem do pedido nesse caso. |
| Fatura do cartão | Aparecia só "MERCADOPAGO". | Aparece o nome da loja (`statement_descriptor`) — menos contestação. |
| Aprovação de cartão | O checkout não mandava os dados do comprador. | Nome e e-mail do cliente logado vão para o Mercado Pago (melhora o antifraude). |
| Webhooks duplicados | `loja/api/notificacao_mp.php`, `notificacao_divida_mp.php` e `caixa/api/notificacao_mp.php` tinham cópias próprias da lógica, já divergentes. | Todos apontam para o webhook único `integracoes/mercado_pago/webhook.php`. |

## Melhorias na loja

- **Volta ao produto depois do login**: "Entrar ou cadastrar pra comprar" leva de volta ao mesmo produto (antes caía no catálogo). Vale também para carrinho e checkout.
- **Carnes por quilo**: produtos com "(kg)" no nome mostram "R$ 80,00 / kg" e "20 kg disponíveis" em vez de "20 unidades".
- **Prazo das encomendas de domingo** configurável e exibido no banner ("Encomende a sua até sábado às 18h").
- **Checkout** explica as formas de pagamento (Pix ou cartão no ambiente do Mercado Pago).
- Categoria **"Sorvetes & Picoles" → "Sorvetes e Picolés"** (pelo SQL).

## Arquivos alterados

- `includes/loja.php`, `includes/credito.php`, `includes/mp_client.php`, `includes/auth_cliente.php`
- `loja/index.php`, `loja/produto.php`, `loja/checkout.php`, `loja/cadastro.php`
- `loja/ajax/gerar_checkout.php`, `loja/ajax/gerar_checkout_divida.php`
- `loja/api/notificacao_mp.php`, `loja/api/notificacao_divida_mp.php`, `caixa/api/notificacao_mp.php`
- `caixa/ajax/gerar_pix.php`, `caixa/ajax/gerar_point.php`, `clientes/ajax/gerar_pix_divida.php`
- `pedidos/lista.php`, `pedidos/ajax/cancelar_pagamento_mp.php`, `config_sistema/pdv.php`
- novo: `sql/schema_melhorias_2026_10.sql`

As versões anteriores ficam no histórico do Git (branch `main` antes deste PR).

## Roteiro de teste (antes de divulgar)

1. Compra com **Pix**: pague dentro do prazo → pedido "Pago" e estoque baixado.
2. Compra com Pix pago **depois** do prazo → o pedido volta como "Pago" (ou aparece o alerta em Pedidos).
3. Compra com **cartão de crédito** → confira o nome da loja na fatura.
4. **Linha de crédito**: cliente com limite compra no "fiado"; depois paga a dívida pelo link em "Minha dívida".
5. **Caixa**: venda com Pix no balcão e com a maquininha Point.
6. Cancele um pagamento pendente em Pedidos e confira no app do Mercado Pago que o Pix foi cancelado.
