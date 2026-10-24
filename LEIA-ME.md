# Cerveja-Clube

Projeto completo do **Clube da Cerveja** (conveniência, tabacaria e carnes de domingo),
já com todas as correções da revisão de publicação (24/10/2026).

O código desta pasta é o mesmo do PR #4 no GitHub
(`gimfreires-lang/devclub-systema`, branch `ajustes-revisao`).
O site no ar só muda depois do merge desse PR: o Easypanel publica sozinho a partir da `main`.

| Pasta | O que tem |
|---|---|
| 01-sistema | Código completo da loja, do PDV e dos painéis (PHP + MySQL), pronto para publicar. |
| 02-banco-de-dados | `SysVeronica.sql` (banco completo para instalação nova) e `carnes_e_contato_clube.sql` (carnes com fotos, endereço e telefone, para o banco que já está no ar). |
| 03-marca | Logo do Clube da Cerveja em vários tamanhos e o favicon. |
| 04-fotos-carnes | Fotos das carnes usadas na loja. Linguicinha e coxa de frango são ilustrativas. |
| 05-previa-das-telas | Capturas da loja, do painel e do celular, feitas com o código desta pasta. |
| 06-documentacao | Instalação e publicação no Easypanel (com o checklist antes de divulgar) e o plano da mudança de marca. |

## Abrir a prévia no computador

Dê dois cliques em **`ABRIR-PREVIA.bat`**. O navegador abre a loja em `http://127.0.0.1:8001/loja/`.

- Usa o PHP e o banco de prévia que já estão em `PROJETO CERVEJA\.preview`, por isso as duas
  pastas precisam ficar lado a lado na Área de Trabalho.
- Painel do lojista: `http://127.0.0.1:8001/login.php`, e-mail `sistema@gmail.com`, senha `Previa@2026`.
- Painel do desenvolvedor: `http://127.0.0.1:8001/painel_dev/login.php`, usuário `DevMaster`, senha `Previa@2026`.
- São senhas só da prévia no seu computador; o site no ar tem as próprias.

## O que ainda depende de você

1. Fazer o merge do PR #4 no GitHub.
2. Criar a variável `SENHA_INSTALACAO` no Easypanel (recomendado).
3. Colocar fotos nos produtos que estão com "Foto em breve" ou desativar os que não vende.
4. Conectar o Mercado Pago.
5. Fazer uma compra de teste na loja e uma venda no caixa antes de divulgar.

Para abrir tudo no VS Code: dois cliques em `Cerveja-Clube.code-workspace`.
