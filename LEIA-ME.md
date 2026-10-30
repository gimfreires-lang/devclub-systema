# Cerveja-Clube

Projeto completo do **Clube da Cerveja** (conveniência, tabacaria e carnes de domingo),
O código fica no GitHub em `gimfreires-lang/devclub-systema`. O site no ar muda quando um PR
entra na `main`: o Easypanel publica sozinho (com **Caminho de build** `/01-sistema`).

| Pasta | O que tem |
|---|---|
| 01-sistema | Código completo da loja, do PDV e dos painéis (PHP + MySQL), pronto para publicar. |
| 02-banco-de-dados | `SysVeronica.sql` (banco completo para instalação nova), `carnes_e_contato_clube.sql` (carnes com fotos, endereço e telefone) e `bebidas_clube.sql` (15 bebidas, gelo, carvão, tabacaria e petiscos). Os dois últimos são para o banco que já está no ar: importe no phpMyAdmin, aba Importar. Rodar de novo não duplica nem desfaz o que você mudou. |
| 03-marca | Logo do Clube da Cerveja em vários tamanhos e o favicon. |
| 04-fotos-carnes | Fotos das carnes usadas na loja. Linguicinha e coxa de frango são ilustrativas. |
| 05-previa-das-telas | Capturas da loja, do painel e do celular, feitas com o código desta pasta. |
| 06-documentacao | Instalação e publicação no Easypanel (com o checklist antes de divulgar) e o plano da mudança de marca. |

## Abrir a prévia no computador

Dê dois cliques em **`ABRIR-PREVIA.bat`**. O navegador abre a loja em `http://127.0.0.1:8001/loja/`.

- Na primeira vez, se o computador ainda não tiver o PHP e o banco da prévia, ele baixa os dois
  sozinho (uns 150 MB, só uma vez) para `.previa\runtime` e cria o banco com os produtos do Clube.
  Se existir a pasta antiga `PROJETO CERVEJA\.preview` ao lado do projeto, ela continua sendo usada.
- Se aparecer aviso de "Visual C++ Redistributable", instale o arquivo que o navegador baixar e abra a prévia de novo.
- Painel do lojista: `http://127.0.0.1:8001/login.php`, e-mail `sistema@gmail.com`, senha `Previa@2026`.
- Painel do desenvolvedor: `http://127.0.0.1:8001/painel_dev/login.php`, usuário `DevMaster`, senha `Previa@2026`.
- São senhas só da prévia no seu computador; o site no ar tem as próprias.

## O que ainda depende de você

1. Criar a variável `SENHA_INSTALACAO` no Easypanel (recomendado).
2. Colocar fotos nos produtos que estão com "Foto em breve" ou desativar os que não vende.
3. Conectar o Mercado Pago.
4. Fazer uma compra de teste na loja e uma venda no caixa antes de divulgar.

Para abrir tudo no VS Code: dois cliques em `Cerveja-Clube.code-workspace`.
