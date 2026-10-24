-- Limite de tentativas no login do painel do lojista (/login.php).
-- O sistema cria esta tabela sozinho no primeiro login (includes/login_tentativas.php).
-- Rode manualmente só se o usuário do banco não tiver permissão de CREATE TABLE.
CREATE TABLE IF NOT EXISTS login_tentativas (
    id_tentativa INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_tentativas_email (email, criado_em),
    KEY idx_login_tentativas_ip (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
