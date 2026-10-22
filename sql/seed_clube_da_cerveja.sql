-- Categorias e produtos de exemplo do Clube da Cerveja, pra testar a loja com
-- cara de conveniência. NÃO é migração de schema — é dado de teste e, como o
-- resto de sql/**, não entra no deploy: rode manualmente se quiser.
--
-- IDs 9101-9118 (faixa alta pra não colidir com produtos reais). Os produtos
-- vêm sem foto: a loja mostra a imagem de "produto indisponível" até você
-- subir as fotos pelo painel.

SET NAMES utf8mb4;

INSERT IGNORE INTO categorias (nome) VALUES
('Cervejas'), ('Destilados'), ('Vinhos'), ('Refrigerantes e Energéticos'),
('Gelo e Carvão'), ('Tabacaria'), ('Petiscos'), ('Carnes do Domingo');

INSERT INTO produtos (id_produto, nome, descricao, id_categoria, condicao, preco_base, ativo) VALUES
(9101, 'Cerveja Pilsen Lata 350ml', 'Lata de 350ml, trincando de gelada.', (SELECT id_categoria FROM categorias WHERE nome = 'Cervejas'), 'novo', 4.99, 1),
(9102, 'Fardo Cerveja Pilsen 12 latas', 'Fardo com 12 latas de 350ml. Ideal pro churrasco.', (SELECT id_categoria FROM categorias WHERE nome = 'Cervejas'), 'novo', 54.90, 1),
(9103, 'Cerveja Long Neck Puro Malte 330ml', 'Long neck puro malte, gelada.', (SELECT id_categoria FROM categorias WHERE nome = 'Cervejas'), 'novo', 7.49, 1),
(9104, 'Cerveja Litrão 1L', 'Garrafa retornável de 1 litro. Traga o casco e pague menos.', (SELECT id_categoria FROM categorias WHERE nome = 'Cervejas'), 'novo', 11.90, 1),
(9105, 'Cerveja Artesanal IPA 500ml', 'IPA artesanal, amargor marcante e aroma cítrico.', (SELECT id_categoria FROM categorias WHERE nome = 'Cervejas'), 'novo', 22.90, 1),
(9106, 'Vodka 1L', 'Vodka destilada, garrafa de 1 litro.', (SELECT id_categoria FROM categorias WHERE nome = 'Destilados'), 'novo', 39.90, 1),
(9107, 'Cachaça Envelhecida 700ml', 'Cachaça envelhecida em barril de carvalho.', (SELECT id_categoria FROM categorias WHERE nome = 'Destilados'), 'novo', 49.90, 1),
(9108, 'Whisky 8 anos 1L', 'Whisky blended 8 anos.', (SELECT id_categoria FROM categorias WHERE nome = 'Destilados'), 'novo', 119.90, 1),
(9109, 'Vinho Tinto Seco 750ml', 'Vinho tinto seco, ótimo com carne vermelha.', (SELECT id_categoria FROM categorias WHERE nome = 'Vinhos'), 'novo', 44.90, 1),
(9110, 'Refrigerante Cola 2L', 'Garrafa PET de 2 litros.', (SELECT id_categoria FROM categorias WHERE nome = 'Refrigerantes e Energéticos'), 'novo', 10.99, 1),
(9111, 'Energético Lata 473ml', 'Energético em lata de 473ml.', (SELECT id_categoria FROM categorias WHERE nome = 'Refrigerantes e Energéticos'), 'novo', 9.90, 1),
(9112, 'Gelo em Cubo 5kg', 'Saco de gelo em cubo de 5kg.', (SELECT id_categoria FROM categorias WHERE nome = 'Gelo e Carvão'), 'novo', 12.00, 1),
(9113, 'Carvão Vegetal 4kg', 'Saco de carvão vegetal de 4kg.', (SELECT id_categoria FROM categorias WHERE nome = 'Gelo e Carvão'), 'novo', 24.90, 1),
(9114, 'Seda e Isqueiro (kit)', 'Kit com seda e isqueiro. Venda proibida para menores de 18 anos.', (SELECT id_categoria FROM categorias WHERE nome = 'Tabacaria'), 'novo', 12.90, 1),
(9115, 'Amendoim Torrado 500g', 'Amendoim torrado e salgado.', (SELECT id_categoria FROM categorias WHERE nome = 'Petiscos'), 'novo', 14.90, 1),
(9116, 'Picanha Assada (kg)', 'Picanha assada na brasa, servida aos domingos. Encomende com antecedência.', (SELECT id_categoria FROM categorias WHERE nome = 'Carnes do Domingo'), 'novo', 129.90, 1),
(9117, 'Costela Assada (kg)', 'Costela bovina assada lentamente na brasa. Só aos domingos.', (SELECT id_categoria FROM categorias WHERE nome = 'Carnes do Domingo'), 'novo', 79.90, 1),
(9118, 'Kit Domingo (1kg de carne + fardo de cerveja)', 'Combo de domingo: 1kg de carne assada + 12 latas de cerveja gelada.', (SELECT id_categoria FROM categorias WHERE nome = 'Carnes do Domingo'), 'novo', 159.90, 1);

-- Uma única variação por produto (preco NULL cai no preco_base, mesma regra
-- do resto do sistema).
INSERT INTO produto_variacoes (id_produto_variacao, id_produto, preco, estoque) VALUES
(9101, 9101, NULL, 240), (9102, 9102, NULL, 30), (9103, 9103, NULL, 120),
(9104, 9104, NULL, 48), (9105, 9105, NULL, 24), (9106, 9106, NULL, 12),
(9107, 9107, NULL, 10), (9108, 9108, NULL, 6), (9109, 9109, NULL, 18),
(9110, 9110, NULL, 36), (9111, 9111, NULL, 48), (9112, 9112, NULL, 40),
(9113, 9113, NULL, 25), (9114, 9114, NULL, 30), (9115, 9115, NULL, 20),
(9116, 9116, NULL, 15), (9117, 9117, NULL, 20), (9118, 9118, NULL, 10);
