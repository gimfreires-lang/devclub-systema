-- Rebranding para o Clube da Cerveja (conveniência de bebidas e tabacaria,
-- com carnes assadas aos domingos). Igual às outras migrações de sql/**, não
-- entra no deploy automático: rode manualmente no banco, uma vez.
--
SET NAMES utf8mb4;

-- 1) Padrões novos pra instalações novas (nome, tema escuro preto + dourado).
ALTER TABLE config_loja
    MODIFY COLUMN nome_loja VARCHAR(150) NOT NULL DEFAULT 'Clube da Cerveja',
    MODIFY COLUMN tema ENUM('claro', 'escuro', 'personalizado') NOT NULL DEFAULT 'escuro',
    MODIFY COLUMN cor_primaria CHAR(7) NOT NULL DEFAULT '#F2B705',
    MODIFY COLUMN cor_secundaria CHAR(7) NOT NULL DEFAULT '#B8BCC2',
    MODIFY COLUMN cor_fundo CHAR(7) NOT NULL DEFAULT '#0E0E0E',
    MODIFY COLUMN cor_texto CHAR(7) NOT NULL DEFAULT '#F5F5F5';

ALTER TABLE config_dev
    MODIFY COLUMN nome_sistema VARCHAR(150) NOT NULL DEFAULT 'Clube da Cerveja';

-- 2) Aplica a marca nesta instalação. logo_arquivo vai pra NULL de propósito:
--    sem logo enviada, o sistema usa a logo padrão do Clube da Cerveja
--    (assets/img/marca/). Pra usar outra, é só enviar em Aparência depois.
UPDATE config_loja
SET nome_loja = 'Clube da Cerveja',
    tema = 'escuro',
    cor_primaria = '#F2B705',
    cor_secundaria = '#B8BCC2',
    cor_fundo = '#0E0E0E',
    cor_texto = '#F5F5F5',
    logo_arquivo = NULL
WHERE id_config = 1;

UPDATE config_dev SET nome_sistema = 'Clube da Cerveja' WHERE id_config = 1;

-- 3) Bebidas não têm "usado": produto novo já nasce como 'novo'.
ALTER TABLE produtos MODIFY COLUMN condicao ENUM('novo','usado') NOT NULL DEFAULT 'novo';
