-- Pix direto na conta Bradesco (preparado, começa DESLIGADO).
-- Cole na aba SQL do phpMyAdmin e clique em Executar. Pode rodar mais de uma vez.
-- Enquanto provedor_pix = 'mercadopago', nada muda na loja.

CREATE TABLE IF NOT EXISTS config_bradesco (
    id_config INT PRIMARY KEY,
    provedor_pix VARCHAR(20) NOT NULL DEFAULT 'mercadopago',
    ambiente VARCHAR(20) NOT NULL DEFAULT 'homologacao',
    client_id VARCHAR(200) NULL,
    client_secret VARCHAR(255) NULL,
    chave_pix VARCHAR(120) NULL,
    webhook_token VARCHAR(64) NULL,
    access_token TEXT NULL,
    token_expira DATETIME NULL,
    atualizado_em DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO config_bradesco (id_config) VALUES (1);

CREATE TABLE IF NOT EXISTS cobrancas_pix (
    txid VARCHAR(35) PRIMARY KEY,
    provedor VARCHAR(20) NOT NULL DEFAULT 'bradesco',
    origem VARCHAR(10) NOT NULL,
    id_referencia INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'ATIVA',
    pix_copia_cola TEXT NOT NULL,
    qr_base64 MEDIUMTEXT NULL,
    expira_em DATETIME NOT NULL,
    e2e_id VARCHAR(64) NULL,
    consultado_em DATETIME NULL,
    confirmado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_origem_ref (origem, id_referencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
