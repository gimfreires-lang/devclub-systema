# Rebranding: Brechó da Veve → Clube da Cerveja

Repositório analisado: `gimfreires-lang/devclub-systema` (o mais recente da sua conta).

## O que é o projeto hoje
Sistema em PHP + MySQL com painel administrativo, PDV (caixa), loja online com Mercado Pago, linha de crédito (fiado), WhatsApp, frete, nota fiscal e PWA. Está configurado para um brechó ("Brechó da Veve"): produtos de exemplo são roupas, todo produto tem campo "Novo/Usado", a paleta padrão é roxo/rosa e o deploy vai para `/public_html/brechodaveve/`.

O nome da loja, a logo e o tema já são configuráveis pelo painel (Aparência). Ou seja, parte do rebranding é só configuração; o resto é código.

## Atenção
Todo push na branch `main` faz deploy automático via FTP para o servidor (`/public_html/brechodaveve/`). Por isso a proposta é trabalhar numa branch separada e abrir um PR, sem publicar nada até você aprovar.

## Identidade nova (tirada da logo)
| Papel | Cor |
|---|---|
| Primária (dourado/amarelo) | `#F2B705` |
| Dourado escuro (hover/destaques) | `#C98A00` |
| Prata/metálico (secundária) | `#B8BCC2` |
| Preto (fundo do tema escuro, textos) | `#0E0E0E` |
| Branco | `#FFFFFF` |

Subtítulo: "Conveniência e Tabacaria".

## O que proponho mudar
1. **Cores em todo o sistema**: paletas fixas dos temas claro/escuro (`includes/tema.php`), cores do painel admin (`assets/css/admin.css`), loja (`assets/css/loja.css`), PWA, e-mails e defaults do banco. Tema escuro (preto + dourado) vira o padrão, combinando com a logo.
2. **Logo**: incluída em `assets/img/logo/`, usada como padrão na loja, no painel, no ícone do app (PWA) e nos e-mails.
3. **Nome**: "Clube da Cerveja" como nome da loja e do sistema (defaults do banco, manifest, textos).
4. **Nicho de conveniência de bebidas**:
   - Categorias de exemplo: Cervejas, Destilados, Vinhos, Refrigerantes e Energéticos, Gelo e Carvão, Tabacaria, Petiscos, Carnes do Domingo.
   - Produtos de exemplo trocados (cervejas, destilados, kits de churrasco, carnes assadas).
   - Campo "Novo/Usado" escondido (não faz sentido para bebidas).
   - Textos do catálogo adaptados (ex.: "Catálogo" → "Bebidas geladas e muito mais").
5. **Domingo do churrasco**: banner na loja anunciando as carnes assadas aos domingos, com destaque automático aos domingos e categoria "Carnes do Domingo" para encomendas antecipadas.

## O que NÃO mexo sem você pedir
- Caminho do arquivo de credenciais (`brechodaveve_config_credenciais.php`) e pasta de deploy: mudar isso quebra o servidor atual.
- Banco de produção: as mudanças de banco vão como script SQL para você rodar manualmente.
