-- Carnes do Domingo (com fotos) e contato do Clube da Cerveja.
-- Pode importar no phpMyAdmin do banco que já está no ar: é seguro rodar de
-- novo, porque atualiza em vez de duplicar.
--
-- As fotos ficam em assets/carnes/ (vêm junto com o código, fora do volume
-- assets/img do Easypanel). Fotos novas enviadas pelo painel continuam indo
-- pra assets/img/produtos/ normalmente.

SET NAMES utf8mb4;

UPDATE config_loja SET
    endereco_loja = 'Rua Cabo Clodoaldo Ursulano, 1213 - Santa Felicidade, Cascavel - PR, CEP 85803-450',
    whatsapp_loja = '5545998185568',
    superfrete_cep_origem = '85803450'
WHERE id_config = 1;

INSERT IGNORE INTO categorias (nome) VALUES ('Carnes do Domingo');

INSERT INTO produtos (id_produto, nome, descricao, id_categoria, condicao, preco_base, ativo) VALUES
(9201, 'Costela Minga (kg)', 'Costela minga assada lentamente na brasa, servida aos domingos. Preço por quilo.', (SELECT id_categoria FROM categorias WHERE nome = 'Carnes do Domingo'), 'novo', 80.00, 1),
(9202, 'Costela Suína (peça)', 'Costela suína assada com a pururuca crocante. Preço por peça.', (SELECT id_categoria FROM categorias WHERE nome = 'Carnes do Domingo'), 'novo', 40.00, 1),
(9203, 'Costela Ripa (kg)', 'Costela bovina em ripa, assada lentamente na brasa. Preço por quilo.', (SELECT id_categoria FROM categorias WHERE nome = 'Carnes do Domingo'), 'novo', 70.00, 1),
(9204, 'Fraldinha (kg)', 'Fraldinha assada na brasa, macia e suculenta. Preço por quilo.', (SELECT id_categoria FROM categorias WHERE nome = 'Carnes do Domingo'), 'novo', 90.00, 1),
(9205, 'Linguicinha (unidade)', 'Linguicinha assada na brasa. Preço por unidade.', (SELECT id_categoria FROM categorias WHERE nome = 'Carnes do Domingo'), 'novo', 5.00, 1),
(9206, 'Coxa de Frango (unidade)', 'Coxa de frango assada na brasa. Preço por unidade.', (SELECT id_categoria FROM categorias WHERE nome = 'Carnes do Domingo'), 'novo', 7.00, 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao),
    id_categoria = VALUES(id_categoria), preco_base = VALUES(preco_base), ativo = 1;

-- Estoque inicial de exemplo: ajuste em Produtos > Editar.
INSERT IGNORE INTO produto_variacoes (id_produto_variacao, id_produto, preco, estoque) VALUES
(9201, 9201, NULL, 20), (9202, 9202, NULL, 20), (9203, 9203, NULL, 20),
(9204, 9204, NULL, 20), (9205, 9205, NULL, 100), (9206, 9206, NULL, 100);

-- Linguicinha e coxa de frango usam imagem ilustrativa até chegar a foto real.
DELETE FROM produto_fotos WHERE id_produto BETWEEN 9201 AND 9206 AND caminho_arquivo LIKE 'assets/carnes/%';
INSERT IGNORE INTO produto_fotos (id_produto, ordem, caminho_arquivo) VALUES
(9201, 1, 'assets/carnes/costela-minga.jpg'),
(9202, 1, 'assets/carnes/costela-suina.jpg'),
(9203, 1, 'assets/carnes/costela-ripa.jpg'),
(9204, 1, 'assets/carnes/fraldinha.jpg'),
(9205, 1, 'assets/carnes/linguicinha.jpg'),
(9206, 1, 'assets/carnes/coxa-de-frango.jpg');
