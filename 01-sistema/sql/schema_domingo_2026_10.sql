-- Agendamento da carne de domingo + avisos de pedido por WhatsApp + correção
-- do link "Desenvolvido por" (30/10/2026).
--
-- Importe no phpMyAdmin (aba Importar) do banco que está no ar, uma vez.
-- Pode rodar de novo sem problema: nada é duplicado nem apagado.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1) Configuração do domingo (uma linha só)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS domingo_config (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    id_categoria_carnes INT NULL,
    corte_dia_semana TINYINT NOT NULL DEFAULT 6,          -- 0 = domingo ... 6 = sábado
    corte_hora TIME NOT NULL DEFAULT '18:00:00',
    domingos_a_frente TINYINT NOT NULL DEFAULT 2,
    whatsapp_avisos_loja VARCHAR(20) NULL,                -- número que recebe os avisos da loja
    ultimo_resumo_data DATE NULL,                         -- domingo cujo resumo de produção já foi enviado
    tarefas_verificadas_em DATETIME NULL,
    cron_chave VARCHAR(64) NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO domingo_config (id, cron_chave) VALUES (1, SHA2(CONCAT(RAND(), NOW()), 256));

UPDATE domingo_config
SET id_categoria_carnes = (SELECT id_categoria FROM categorias WHERE nome = 'Carnes do Domingo' LIMIT 1)
WHERE id = 1 AND id_categoria_carnes IS NULL;

-- ---------------------------------------------------------------------------
-- 2) Horários de retirada e domingos sem churrasco
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS domingo_horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hora TIME NOT NULL,
    max_pedidos SMALLINT NOT NULL DEFAULT 8,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uk_hora (hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO domingo_horarios (hora) VALUES
    ('10:30:00'), ('11:00:00'), ('11:30:00'), ('12:00:00'), ('12:30:00'), ('13:00:00');

CREATE TABLE IF NOT EXISTS domingo_bloqueios (
    data DATE NOT NULL PRIMARY KEY,
    motivo VARCHAR(80) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 3) Limite de cada carne por domingo (NULL = sem limite por domingo)
-- ---------------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos' AND COLUMN_NAME = 'limite_domingo');
SET @sql := IF(@existe = 0, 'ALTER TABLE produtos ADD COLUMN limite_domingo INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Começa com o estoque atual de cada carne como limite do domingo.
UPDATE produtos p
JOIN domingo_config dc ON dc.id = 1 AND dc.id_categoria_carnes = p.id_categoria
SET p.limite_domingo = (SELECT COALESCE(SUM(pv.estoque), 0) FROM produto_variacoes pv WHERE pv.id_produto = p.id_produto)
WHERE p.limite_domingo IS NULL;

-- ---------------------------------------------------------------------------
-- 4) Agendamento gravado no pedido
-- ---------------------------------------------------------------------------
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vendas' AND COLUMN_NAME = 'retirada_data');
SET @sql := IF(@existe = 0,
    'ALTER TABLE vendas
        ADD COLUMN retirada_data DATE NULL,
        ADD COLUMN retirada_hora TIME NULL,
        ADD COLUMN lembrete_enviado_em DATETIME NULL,
        ADD COLUMN aviso_pronto_em DATETIME NULL,
        ADD INDEX idx_retirada (retirada_data, retirada_hora)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 5) Registro de todas as mensagens de WhatsApp que o sistema tenta enviar
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS whatsapp_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo VARCHAR(40) NOT NULL,
    id_venda INT NULL,
    telefone VARCHAR(20) NULL,
    sucesso TINYINT(1) NOT NULL DEFAULT 0,
    http_code SMALLINT NULL,
    detalhe VARCHAR(500) NULL,
    texto TEXT NULL,
    INDEX idx_criado (criado_em),
    INDEX idx_venda (id_venda)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 6) Link "Desenvolvido por" do rodapé estava salvo como "htpps://..."
-- ---------------------------------------------------------------------------
UPDATE config_dev
SET footer_link = 'https://gimfreires.tech'
WHERE id_config = 1 AND footer_link IS NOT NULL AND footer_link NOT LIKE 'http://%' AND footer_link NOT LIKE 'https://%';
