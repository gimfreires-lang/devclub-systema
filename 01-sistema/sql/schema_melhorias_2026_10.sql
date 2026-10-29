-- Melhorias de 29/10/2026 (pagamentos e loja).
-- Importe no phpMyAdmin (aba Importar) do banco que está no ar.
-- Pode rodar mais de uma vez: nada é duplicado nem apagado.

-- 1) Pagamentos aprovados no Mercado Pago que não viraram pedido
--    (ex.: o cliente pagou o Pix depois do prazo e o produto já tinha acabado).
--    Aparecem como alerta no topo de Pedidos para o lojista devolver o dinheiro.
CREATE TABLE IF NOT EXISTS pagamentos_sem_pedido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pagamento_mp VARCHAR(50) NOT NULL,
    id_venda INT NULL,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0,
    motivo VARCHAR(160) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolvido_em DATETIME NULL,
    resolvido_por INT NULL,
    UNIQUE KEY uk_pagamento_mp (id_pagamento_mp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Prazo das encomendas de carne de domingo (Configurações > PDV).
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_loja' AND COLUMN_NAME = 'prazo_encomenda_carnes');
SET @sql := IF(@existe = 0,
    'ALTER TABLE config_loja ADD COLUMN prazo_encomenda_carnes VARCHAR(80) NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Valor inicial (o lojista muda depois em Configurações > PDV).
UPDATE config_loja SET prazo_encomenda_carnes = 'até sábado às 18h'
WHERE id_config = 1 AND prazo_encomenda_carnes IS NULL;

-- 3) Nome da categoria sem acento.
UPDATE categorias SET nome = 'Sorvetes e Picolés' WHERE nome = 'Sorvetes & Picoles';

-- 4) Pagamentos de dívida abandonados (link aberto e nunca pago) que estavam
--    travando a tela "Minha dívida" do cliente. Se algum deles ainda for pago,
--    o sistema confirma mesmo assim.
UPDATE movimentos_credito SET status = 'Cancelado'
WHERE tipo = 'pagamento' AND status = 'Pendente'
  AND data_movimento < DATE_SUB(NOW(), INTERVAL 60 MINUTE);
