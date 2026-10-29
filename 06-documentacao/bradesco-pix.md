# Pix direto no Bradesco — preparado e desligado

O sistema continua usando o **Mercado Pago**. O Pix do Bradesco já está pronto no código e
só passa a valer quando alguém escolhe "Bradesco" em **Configurações → Pix Bradesco**.
Dá pra voltar pro Mercado Pago a qualquer momento, na mesma tela.

## O que muda quando ligar

| | Mercado Pago (hoje) | Bradesco ligado |
|---|---|---|
| Pix na loja online | Tela do Mercado Pago | Tela própria da loja (QR Code + copia e cola, confirma sozinha) |
| Cartão na loja online | Sim | Não (precisa de credenciadora, ex.: Cielo — fase seguinte) |
| Pix no caixa | Mercado Pago | Bradesco |
| Pagamento de dívida (loja e painel) | Mercado Pago | Bradesco |
| Maquininha Point | Mercado Pago | Continua como está |
| Linha de crédito (fiado) | Igual | Igual |

Nos dois casos valem as mesmas regras: prazo do Pix junto com o pedido, pedido reaberto se o
Pix chegar atrasado, alerta de "pagamento sem pedido", baixa de estoque e de dívida.

## Passo a passo para ativar

### 1. Banco de dados (uma vez)
No phpMyAdmin, aba **SQL**, cole o conteúdo de `01-sistema/sql/schema_bradesco_pix.sql` e execute.
Isso só cria as tabelas — nada muda na loja.

### 2. O que o cliente pede ao Bradesco
- Conta **PJ** com **chave Pix** cadastrada.
- Liberação da **API Pix (Pix Cobrança)** com o gerente.
- **Client ID** e **Client Secret** de homologação e de produção.
- **Certificado digital** (.pfx) de homologação e de produção, com nome da empresa e CNPJ. O layout
  do Bradesco não aceita certificado autoassinado.

### 3. Certificado e senha no Easypanel
1. No Windows, abra o PowerShell na pasta do certificado e rode:
   `[Convert]::ToBase64String([IO.File]::ReadAllBytes("certificado.pfx")) | Set-Clipboard`
   (o texto do certificado fica copiado).
2. No Easypanel → app do sistema → **Environment**, crie:
   - `BRADESCO_CERT_PFX_BASE64` = cole o texto copiado
   - `BRADESCO_CERT_SENHA` = senha do certificado
   - `BRADESCO_CLIENT_SECRET` = client secret (opcional; também pode ser digitado na tela)
3. Clique em **Deploy**.

Nunca coloque o certificado nem as senhas no GitHub.

### 4. Testar em homologação
Em **Configurações → Pix Bradesco**:
1. Ambiente **Homologação**, chave Pix e Client ID → **Salvar**.
2. **Testar conexão** → tem que aparecer "Conexão com o Bradesco funcionando".
3. **Gerar Pix de teste (R$ 1,00)** → aparece o QR Code.
4. **Cadastrar webhook** (confirmação instantânea). Se o Bradesco recusar, tudo funciona do mesmo
   jeito: a tela de pagamento confere o Pix sozinha a cada 5 segundos.

### 5. Produção
1. Troque o certificado no Easypanel pelo de produção e faça o deploy.
2. Ambiente **Produção**, Client ID/Secret de produção → Salvar → Testar conexão → Cadastrar webhook.
3. Em "Quem gera os Pix", escolha **Bradesco** → Salvar. A tela só aceita trocar se a conexão
   funcionar, então a loja nunca fica sem Pix por credencial errada.
4. Faça uma compra real de valor baixo na loja, um Pix no caixa e um pagamento de dívida.
5. Quando tudo estiver certo, desconecte o Mercado Pago (se não for mais usar a maquininha Point).

## Problemas comuns

- **"Não foi possível abrir o certificado"**: senha errada, ou .pfx em formato antigo. Converta com
  `openssl pkcs12 -legacy -in certificado.pfx -out antigo.pem -nodes` e depois
  `openssl pkcs12 -export -in antigo.pem -out novo.pfx`, e use o `novo.pfx`.
- **"Bradesco recusou as credenciais (HTTP 401)"**: Client ID/Secret de um ambiente com
  certificado do outro (homologação × produção), ou credenciais erradas.
- **Endereço da API diferente**: se o Bradesco informar outro endereço, crie no Easypanel
  `BRADESCO_API_URL` (e `BRADESCO_TOKEN_URL`, se o token for em outro endereço).

## Como foi testado

O módulo foi testado contra um servidor que imita a API do Bradesco (mesma conexão mTLS com
certificado, token OAuth, criação e consulta de cobrança, webhook): certificado errado, senha
errada, credencial errada, token vencido, cobrança paga e não paga, confirmação repetida e valor
inválido. Os endereços e campos seguem o layout oficial da API Pix do Bradesco
(homologação `https://qrpix-h.bradesco.com.br`, produção `https://qrpix.bradesco.com.br`).
Falta só o teste com as credenciais reais de homologação do cliente.
