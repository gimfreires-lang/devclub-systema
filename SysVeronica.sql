-- Banco de dados completo do sistema (Clube da Cerveja), para instalações novas.
-- É o arquivo que o README manda importar na Parte 4 (phpMyAdmin > Importar).
-- Já inclui todas as atualizações de sql/** e a identidade do Clube da Cerveja
-- (sql/schema_clube_da_cerveja.sql). Não traz produtos nem usuários da loja:
-- o primeiro acesso é feito pelo painel do desenvolvedor (/painel_dev).
-- Para um banco que JÁ está no ar, NÃO importe este arquivo: rode só
-- sql/schema_clube_da_cerveja.sql.


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `caixa_sessoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `caixa_sessoes` (
  `id_caixa` int(11) NOT NULL AUTO_INCREMENT,
  `numero_caixa` int(11) NOT NULL DEFAULT 1,
  `valor_inicial` decimal(10,2) NOT NULL,
  `aberto_por` int(11) NOT NULL,
  `data_abertura` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('aberto','fechado') NOT NULL DEFAULT 'aberto',
  `fechado_por` int(11) DEFAULT NULL,
  `data_fechamento` datetime DEFAULT NULL,
  `valor_final_informado` decimal(10,2) DEFAULT NULL,
  `valor_esperado` decimal(10,2) DEFAULT NULL,
  `diferenca` decimal(10,2) DEFAULT NULL,
  `observacao_fechamento` varchar(255) DEFAULT NULL,
  `numero_caixa_aberto` int(11) GENERATED ALWAYS AS (if(`status` = 'aberto',`numero_caixa`,NULL)) STORED,
  PRIMARY KEY (`id_caixa`),
  UNIQUE KEY `uniq_numero_caixa_aberto` (`numero_caixa_aberto`),
  KEY `aberto_por` (`aberto_por`),
  KEY `fechado_por` (`fechado_por`),
  CONSTRAINT `caixa_sessoes_ibfk_1` FOREIGN KEY (`aberto_por`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `caixa_sessoes_ibfk_2` FOREIGN KEY (`fechado_por`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `caixa_sessoes` DISABLE KEYS */;
/*!40000 ALTER TABLE `caixa_sessoes` ENABLE KEYS */;
DROP TABLE IF EXISTS `caixa_terminais_point`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `caixa_terminais_point` (
  `numero_caixa` int(11) NOT NULL,
  `terminal_id` varchar(150) NOT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`numero_caixa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `caixa_terminais_point` DISABLE KEYS */;
/*!40000 ALTER TABLE `caixa_terminais_point` ENABLE KEYS */;
DROP TABLE IF EXISTS `categoria_variacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categoria_variacoes` (
  `id_categoria` int(11) NOT NULL,
  `id_variacao` int(11) NOT NULL,
  PRIMARY KEY (`id_categoria`,`id_variacao`),
  KEY `id_variacao` (`id_variacao`),
  CONSTRAINT `categoria_variacoes_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON DELETE CASCADE,
  CONSTRAINT `categoria_variacoes_ibfk_2` FOREIGN KEY (`id_variacao`) REFERENCES `variacoes` (`id_variacao`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `categoria_variacoes` DISABLE KEYS */;
/*!40000 ALTER TABLE `categoria_variacoes` ENABLE KEYS */;
DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  PRIMARY KEY (`id_categoria`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `whatsapp` varchar(20) NOT NULL,
  `email` varchar(160) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `excluido_em` datetime DEFAULT NULL,
  `excluido_por` int(11) DEFAULT NULL,
  `login_whatsapp_bloqueado` tinyint(1) NOT NULL DEFAULT 0,
  `prazo_dias_credito` int(11) NOT NULL DEFAULT 30,
  `whatsapp_ultimo_envio_em` datetime DEFAULT NULL,
  `whatsapp_verificado_em` datetime DEFAULT NULL,
  `token_verificacao_whatsapp` varchar(10) DEFAULT NULL,
  `token_verificacao_whatsapp_expira_em` datetime DEFAULT NULL,
  `limite_credito` decimal(10,2) NOT NULL DEFAULT 0.00,
  `saldo_devedor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `senha_hash` varchar(255) DEFAULT NULL,
  `email_verificado_em` datetime DEFAULT NULL,
  `token_verificacao_email` varchar(64) DEFAULT NULL,
  `token_verificacao_expira_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `whatsapp` (`whatsapp`),
  UNIQUE KEY `uniq_clientes_email` (`email`),
  UNIQUE KEY `uniq_clientes_token_verificacao` (`token_verificacao_email`),
  KEY `idx_clientes_excluido_em` (`excluido_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
DROP TABLE IF EXISTS `config_dev`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `config_dev` (
  `id_config` int(11) NOT NULL,
  `nome_sistema` varchar(150) NOT NULL DEFAULT 'Clube da Cerveja',
  `mp_app_client_id` varchar(100) DEFAULT NULL,
  `mp_app_client_secret` varchar(100) DEFAULT NULL,
  `mp_webhook_secret` varchar(150) DEFAULT NULL,
  `marketplace_fee_percentual` decimal(5,2) NOT NULL DEFAULT 1.00,
  `quantidade_caixas` int(11) NOT NULL DEFAULT 1,
  `caixas_compartilhados` tinyint(1) NOT NULL DEFAULT 1,
  `smtp_host` varchar(150) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `smtp_user` varchar(150) DEFAULT NULL,
  `smtp_pass` varchar(255) DEFAULT NULL,
  `smtp_from_email` varchar(150) DEFAULT NULL,
  `smtp_from_name` varchar(150) DEFAULT NULL,
  `footer_nome` varchar(150) DEFAULT NULL,
  `footer_link` varchar(255) DEFAULT NULL,
  `superfrete_modo` enum('desativado','consulta','etiquetas') NOT NULL DEFAULT 'desativado',
  `superfrete_ambiente` enum('producao','sandbox') NOT NULL DEFAULT 'producao',
  `superfrete_token_dev` text DEFAULT NULL,
  `travamento_ativo` enum('pagamento','manutencao') DEFAULT NULL,
  `travamento_pagamento_mensagem` text DEFAULT NULL,
  `travamento_manutencao_mensagem` text DEFAULT NULL,
  `whatsapp_verificacao_ativo` tinyint(1) NOT NULL DEFAULT 0,
  `evolution_base_url` varchar(255) DEFAULT NULL,
  `evolution_api_key` varchar(255) DEFAULT NULL,
  `evolution_instancia` varchar(150) DEFAULT NULL,
  `n8n_webhook_url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id_config`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `config_dev` DISABLE KEYS */;
INSERT INTO `config_dev` VALUES (1,'Clube da Cerveja',NULL,NULL,NULL,1.00,1,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'desativado','producao','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3NjMwNTcyNDksInN1YiI6Im9zdDVHRUlMbXBkdkhITVFobGlFUnZXejNQODMifQ.U9bWTx6l3SrvdGxGT1LIdl0KWiCULS9mqUOeW605w6A',NULL,NULL,NULL,0,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `config_dev` ENABLE KEYS */;
DROP TABLE IF EXISTS `config_loja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `config_loja` (
  `id_config` int(11) NOT NULL,
  `nome_loja` varchar(150) NOT NULL DEFAULT 'Clube da Cerveja',
  `logo_arquivo` varchar(255) DEFAULT NULL,
  `cor_primaria` char(7) NOT NULL DEFAULT '#F2B705',
  `cor_secundaria` char(7) NOT NULL DEFAULT '#B8BCC2',
  `whatsapp_loja` varchar(20) DEFAULT NULL,
  `endereco_loja` varchar(255) DEFAULT NULL,
  `horario_atendimento` varchar(255) DEFAULT NULL,
  `email_loja` varchar(160) DEFAULT NULL,
  `superfrete_token` text DEFAULT NULL,
  `superfrete_cep_origem` char(8) DEFAULT NULL,
  `superfrete_servicos` varchar(30) NOT NULL DEFAULT '1,2',
  `superfrete_seguro` tinyint(1) NOT NULL DEFAULT 0,
  `tema` enum('claro','escuro','personalizado') NOT NULL DEFAULT 'escuro',
  `cor_fundo` char(7) NOT NULL DEFAULT '#0E0E0E',
  `cor_texto` char(7) NOT NULL DEFAULT '#F5F5F5',
  `imprimir_automatico` tinyint(1) NOT NULL DEFAULT 0,
  `prazo_reserva_minutos` int(11) NOT NULL DEFAULT 15,
  `prazo_pagamento_minutos` int(11) NOT NULL DEFAULT 10,
  PRIMARY KEY (`id_config`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `config_loja` DISABLE KEYS */;
INSERT INTO `config_loja` VALUES (1,'Clube da Cerveja',NULL,'#F2B705','#B8BCC2',NULL,NULL,NULL,NULL,NULL,NULL,'1,2',0,'escuro','#0E0E0E','#F5F5F5',0,15,10);
/*!40000 ALTER TABLE `config_loja` ENABLE KEYS */;
DROP TABLE IF EXISTS `config_pagamento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `config_pagamento` (
  `id_config` int(11) NOT NULL,
  `mp_access_token` varchar(255) DEFAULT NULL,
  `mp_refresh_token` varchar(255) DEFAULT NULL,
  `mp_public_key` varchar(255) DEFAULT NULL,
  `mp_user_id` varchar(50) DEFAULT NULL,
  `mp_token_expira` datetime DEFAULT NULL,
  `mp_oauth_nonce` varchar(64) DEFAULT NULL,
  `mp_oauth_nonce_expira` datetime DEFAULT NULL,
  PRIMARY KEY (`id_config`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `config_pagamento` DISABLE KEYS */;
INSERT INTO `config_pagamento` VALUES (1,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `config_pagamento` ENABLE KEYS */;
DROP TABLE IF EXISTS `dev_usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dev_usuarios` (
  `id_dev_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `senha_recuperacao_hash` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `tentativas_falhas` int(11) NOT NULL DEFAULT 0,
  `ciclos_bloqueio` int(11) NOT NULL DEFAULT 0,
  `bloqueado_ate` datetime DEFAULT NULL,
  `bloqueio_seguranca` tinyint(1) NOT NULL DEFAULT 0,
  `token_redefinicao_senha` varchar(64) DEFAULT NULL,
  `token_redefinicao_expira_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id_dev_usuario`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `dev_usuarios` DISABLE KEYS */;
INSERT INTO `dev_usuarios` VALUES (1,'DevMaster',NULL,NULL,'$2y$12$r2YlA3uugU9jA3XMVbHbxO1a.eWX3NYXczLQ5XHCF43PTGlUXdQEG',NULL,'2026-10-22 18:12:57',0,0,NULL,0,NULL,NULL);
/*!40000 ALTER TABLE `dev_usuarios` ENABLE KEYS */;
DROP TABLE IF EXISTS `favoritos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `favoritos` (
  `id_favorito` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_produto` int(11) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_favorito`),
  UNIQUE KEY `uniq_cliente_produto` (`id_cliente`,`id_produto`),
  KEY `id_produto` (`id_produto`),
  CONSTRAINT `favoritos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  CONSTRAINT `favoritos_ibfk_2` FOREIGN KEY (`id_produto`) REFERENCES `produtos` (`id_produto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `favoritos` DISABLE KEYS */;
/*!40000 ALTER TABLE `favoritos` ENABLE KEYS */;
DROP TABLE IF EXISTS `fiscal_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fiscal_config` (
  `id_config` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `ativo` tinyint(1) NOT NULL DEFAULT 0,
  `token` varchar(300) DEFAULT NULL,
  `ambiente` enum('homologacao','producao') NOT NULL DEFAULT 'homologacao',
  `conectado_em` datetime DEFAULT NULL,
  `emite_nfce` tinyint(1) NOT NULL DEFAULT 1,
  `emite_nfe` tinyint(1) NOT NULL DEFAULT 1,
  `aceite_em` datetime DEFAULT NULL,
  `aceite_por` int(11) DEFAULT NULL,
  `uf_loja` char(2) DEFAULT NULL,
  `cfop_padrao` char(4) NOT NULL DEFAULT '5102',
  `origem_padrao` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `unidade_padrao` varchar(6) NOT NULL DEFAULT 'UN',
  `cod_tributacao_padrao` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id_config`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `fiscal_config` DISABLE KEYS */;
INSERT INTO `fiscal_config` VALUES (1,0,NULL,'homologacao',NULL,1,1,NULL,NULL,NULL,'5102',0,'UN',NULL);
/*!40000 ALTER TABLE `fiscal_config` ENABLE KEYS */;
DROP TABLE IF EXISTS `formas_entrega`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `formas_entrega` (
  `id_entrega` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `tipo` enum('retirada','entrega') NOT NULL,
  `prazo_dias` int(11) DEFAULT NULL,
  `custo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `fixa` tinyint(1) NOT NULL DEFAULT 0,
  `superfrete` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_entrega`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `formas_entrega` DISABLE KEYS */;
INSERT INTO `formas_entrega` VALUES (1,'Retirar na loja','retirada',5,0.00,1,1,0);
INSERT INTO `formas_entrega` VALUES (2,'SuperFrete','entrega',NULL,0.00,1,0,1);
/*!40000 ALTER TABLE `formas_entrega` ENABLE KEYS */;
DROP TABLE IF EXISTS `itens_venda`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `itens_venda` (
  `id_item` int(11) NOT NULL AUTO_INCREMENT,
  `id_venda` int(11) NOT NULL,
  `nome_produto` varchar(150) NOT NULL,
  `descricao_combinacao` varchar(255) DEFAULT NULL,
  `id_produto_variacao` int(11) DEFAULT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unit` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_item`),
  KEY `id_venda` (`id_venda`),
  CONSTRAINT `itens_venda_ibfk_1` FOREIGN KEY (`id_venda`) REFERENCES `vendas` (`id_venda`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `itens_venda` DISABLE KEYS */;
/*!40000 ALTER TABLE `itens_venda` ENABLE KEYS */;
DROP TABLE IF EXISTS `movimentos_credito`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimentos_credito` (
  `id_movimento` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `tipo` enum('compra','pagamento') NOT NULL,
  `status` enum('Confirmado','Pendente','Cancelado') NOT NULL DEFAULT 'Confirmado',
  `valor` decimal(10,2) NOT NULL,
  `id_venda` int(11) DEFAULT NULL,
  `forma_pagamento` varchar(50) DEFAULT NULL,
  `id_pagamento_mp` varchar(50) DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `data_movimento` datetime NOT NULL DEFAULT current_timestamp(),
  `id_caixa` int(11) DEFAULT NULL,
  `observacao` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_movimento`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_venda` (`id_venda`),
  KEY `criado_por` (`criado_por`),
  KEY `id_caixa` (`id_caixa`),
  CONSTRAINT `movimentos_credito_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  CONSTRAINT `movimentos_credito_ibfk_2` FOREIGN KEY (`id_venda`) REFERENCES `vendas` (`id_venda`),
  CONSTRAINT `movimentos_credito_ibfk_3` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `movimentos_credito_ibfk_4` FOREIGN KEY (`id_caixa`) REFERENCES `caixa_sessoes` (`id_caixa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `movimentos_credito` DISABLE KEYS */;
/*!40000 ALTER TABLE `movimentos_credito` ENABLE KEYS */;
DROP TABLE IF EXISTS `notas_fiscais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notas_fiscais` (
  `id_nota` int(11) NOT NULL AUTO_INCREMENT,
  `id_venda` int(11) NOT NULL,
  `tipo` enum('nfce','nfe') NOT NULL,
  `ambiente` enum('homologacao','producao') NOT NULL,
  `status` enum('processando','autorizada','rejeitada','cancelada') NOT NULL DEFAULT 'processando',
  `identificador` varchar(80) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `destinatario_doc` varchar(14) DEFAULT NULL,
  `destinatario_nome` varchar(140) DEFAULT NULL,
  `numero` varchar(30) DEFAULT NULL,
  `serie` varchar(10) DEFAULT NULL,
  `chave` varchar(60) DEFAULT NULL,
  `protocolo` varchar(60) DEFAULT NULL,
  `arquivo_base64` mediumtext DEFAULT NULL,
  `arquivo_tipo` varchar(8) DEFAULT NULL,
  `xml_base64` mediumtext DEFAULT NULL,
  `erro` text DEFAULT NULL,
  `itens_json` mediumtext DEFAULT NULL,
  `token_publico` char(32) NOT NULL,
  `emitida_por` varchar(120) NOT NULL,
  `autorizada_em` datetime DEFAULT NULL,
  `cancelada_em` datetime DEFAULT NULL,
  `cancelada_por` varchar(120) DEFAULT NULL,
  `motivo_cancelamento` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_nota`),
  UNIQUE KEY `uniq_nota_identificador` (`identificador`),
  UNIQUE KEY `uniq_nota_token` (`token_publico`),
  KEY `idx_nota_venda` (`id_venda`),
  KEY `idx_nota_pendente` (`status`,`criado_em`),
  CONSTRAINT `notas_fiscais_ibfk_1` FOREIGN KEY (`id_venda`) REFERENCES `vendas` (`id_venda`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `notas_fiscais` DISABLE KEYS */;
/*!40000 ALTER TABLE `notas_fiscais` ENABLE KEYS */;
DROP TABLE IF EXISTS `produto_fotos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_fotos` (
  `id_foto` int(11) NOT NULL AUTO_INCREMENT,
  `id_produto` int(11) NOT NULL,
  `ordem` tinyint(4) NOT NULL,
  `caminho_arquivo` varchar(255) NOT NULL,
  PRIMARY KEY (`id_foto`),
  UNIQUE KEY `uniq_ordem_por_produto` (`id_produto`,`ordem`),
  CONSTRAINT `produto_fotos_ibfk_1` FOREIGN KEY (`id_produto`) REFERENCES `produtos` (`id_produto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `produto_fotos` DISABLE KEYS */;
/*!40000 ALTER TABLE `produto_fotos` ENABLE KEYS */;
DROP TABLE IF EXISTS `produto_variacao_valores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_variacao_valores` (
  `id_produto_variacao` int(11) NOT NULL,
  `id_valor` int(11) NOT NULL,
  PRIMARY KEY (`id_produto_variacao`,`id_valor`),
  KEY `id_valor` (`id_valor`),
  CONSTRAINT `produto_variacao_valores_ibfk_1` FOREIGN KEY (`id_produto_variacao`) REFERENCES `produto_variacoes` (`id_produto_variacao`) ON DELETE CASCADE,
  CONSTRAINT `produto_variacao_valores_ibfk_2` FOREIGN KEY (`id_valor`) REFERENCES `variacao_valores` (`id_valor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `produto_variacao_valores` DISABLE KEYS */;
/*!40000 ALTER TABLE `produto_variacao_valores` ENABLE KEYS */;
DROP TABLE IF EXISTS `produto_variacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_variacoes` (
  `id_produto_variacao` int(11) NOT NULL AUTO_INCREMENT,
  `id_produto` int(11) NOT NULL,
  `preco` decimal(10,2) DEFAULT NULL,
  `estoque` int(11) NOT NULL DEFAULT 0,
  `estoque_reservado` int(11) NOT NULL DEFAULT 0,
  `liberado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id_produto_variacao`),
  KEY `id_produto` (`id_produto`),
  CONSTRAINT `produto_variacoes_ibfk_1` FOREIGN KEY (`id_produto`) REFERENCES `produtos` (`id_produto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `produto_variacoes` DISABLE KEYS */;
/*!40000 ALTER TABLE `produto_variacoes` ENABLE KEYS */;
DROP TABLE IF EXISTS `produtos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produtos` (
  `id_produto` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `id_categoria` int(11) NOT NULL,
  `condicao` enum('novo','usado') NOT NULL DEFAULT 'novo',
  `preco_base` decimal(10,2) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `codigo` varchar(30) DEFAULT NULL,
  `peso_gramas` int(11) DEFAULT NULL,
  `caixa_tamanho` tinyint(4) DEFAULT NULL,
  `estoque_gerenciado` tinyint(1) NOT NULL DEFAULT 1,
  `fiscal_ncm` char(8) DEFAULT NULL,
  `fiscal_cfop` char(4) DEFAULT NULL,
  `fiscal_origem` tinyint(3) unsigned DEFAULT NULL,
  `fiscal_unidade` varchar(6) DEFAULT NULL,
  `fiscal_cod_tributacao` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id_produto`),
  UNIQUE KEY `uniq_produtos_codigo` (`codigo`),
  KEY `id_categoria` (`id_categoria`),
  CONSTRAINT `produtos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `produtos` DISABLE KEYS */;
/*!40000 ALTER TABLE `produtos` ENABLE KEYS */;
DROP TABLE IF EXISTS `solicitacoes_credito`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `solicitacoes_credito` (
  `id_solicitacao` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `valor_solicitado` decimal(10,2) NOT NULL,
  `status` enum('Pendente','Aprovada','Rejeitada') NOT NULL DEFAULT 'Pendente',
  `valor_aprovado` decimal(10,2) DEFAULT NULL,
  `observacao_admin` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `respondido_em` datetime DEFAULT NULL,
  `respondido_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_solicitacao`),
  KEY `id_cliente` (`id_cliente`),
  KEY `respondido_por` (`respondido_por`),
  CONSTRAINT `solicitacoes_credito_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  CONSTRAINT `solicitacoes_credito_ibfk_2` FOREIGN KEY (`respondido_por`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `solicitacoes_credito` DISABLE KEYS */;
/*!40000 ALTER TABLE `solicitacoes_credito` ENABLE KEYS */;
DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `perfil` enum('Admin','Funcionario') NOT NULL DEFAULT 'Funcionario',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
DROP TABLE IF EXISTS `variacao_valores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `variacao_valores` (
  `id_valor` int(11) NOT NULL AUTO_INCREMENT,
  `id_variacao` int(11) NOT NULL,
  `valor` varchar(100) NOT NULL,
  PRIMARY KEY (`id_valor`),
  UNIQUE KEY `uniq_valor_por_variacao` (`id_variacao`,`valor`),
  CONSTRAINT `variacao_valores_ibfk_1` FOREIGN KEY (`id_variacao`) REFERENCES `variacoes` (`id_variacao`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `variacao_valores` DISABLE KEYS */;
/*!40000 ALTER TABLE `variacao_valores` ENABLE KEYS */;
DROP TABLE IF EXISTS `variacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `variacoes` (
  `id_variacao` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  PRIMARY KEY (`id_variacao`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `variacoes` DISABLE KEYS */;
/*!40000 ALTER TABLE `variacoes` ENABLE KEYS */;
DROP TABLE IF EXISTS `venda_pagamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `venda_pagamentos` (
  `id_pagamento` int(11) NOT NULL AUTO_INCREMENT,
  `id_venda` int(11) NOT NULL,
  `forma_pagamento` varchar(50) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_pagamento` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pagamento`),
  KEY `id_venda` (`id_venda`),
  CONSTRAINT `venda_pagamentos_ibfk_1` FOREIGN KEY (`id_venda`) REFERENCES `vendas` (`id_venda`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `venda_pagamentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `venda_pagamentos` ENABLE KEYS */;
DROP TABLE IF EXISTS `vendas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendas` (
  `id_venda` int(11) NOT NULL AUTO_INCREMENT,
  `id_caixa` int(11) DEFAULT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `valor_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Reservado','Pago','Cancelado') NOT NULL DEFAULT 'Reservado',
  `status_entrega` enum('Aguardando preparo','Preparando','Pronto','Enviado','Entregue') DEFAULT NULL,
  `forma_pagamento` varchar(50) DEFAULT NULL,
  `id_pagamento_mp` varchar(50) DEFAULT NULL,
  `id_order_mp` varchar(60) DEFAULT NULL,
  `data_venda` datetime NOT NULL DEFAULT current_timestamp(),
  `pagamento_expira_em` datetime DEFAULT NULL,
  `link_pagamento_mp` varchar(500) DEFAULT NULL,
  `token_recibo` varchar(64) DEFAULT NULL,
  `origem` enum('pdv','loja') NOT NULL DEFAULT 'pdv',
  `id_entrega` int(11) DEFAULT NULL,
  `motivo_cancelamento` text DEFAULT NULL,
  PRIMARY KEY (`id_venda`),
  UNIQUE KEY `uniq_vendas_token_recibo` (`token_recibo`),
  KEY `id_caixa` (`id_caixa`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_entrega` (`id_entrega`),
  KEY `idx_loja_expira` (`origem`,`status`,`data_venda`),
  CONSTRAINT `vendas_ibfk_1` FOREIGN KEY (`id_caixa`) REFERENCES `caixa_sessoes` (`id_caixa`),
  CONSTRAINT `vendas_ibfk_2` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  CONSTRAINT `vendas_ibfk_3` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `vendas_ibfk_4` FOREIGN KEY (`id_entrega`) REFERENCES `formas_entrega` (`id_entrega`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `vendas` DISABLE KEYS */;
/*!40000 ALTER TABLE `vendas` ENABLE KEYS */;
DROP TABLE IF EXISTS `vendas_envio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendas_envio` (
  `id_venda` int(11) NOT NULL,
  `servico_id` int(11) DEFAULT NULL,
  `servico_nome` varchar(60) DEFAULT NULL,
  `prazo_dias` int(11) DEFAULT NULL,
  `valor_frete` decimal(10,2) NOT NULL DEFAULT 0.00,
  `destino_nome` varchar(150) DEFAULT NULL,
  `destino_documento` varchar(14) DEFAULT NULL,
  `destino_telefone` varchar(15) DEFAULT NULL,
  `destino_email` varchar(150) DEFAULT NULL,
  `destino_cep` char(8) DEFAULT NULL,
  `destino_endereco` varchar(200) DEFAULT NULL,
  `destino_numero` varchar(20) DEFAULT NULL,
  `destino_complemento` varchar(100) DEFAULT NULL,
  `destino_bairro` varchar(100) DEFAULT NULL,
  `destino_cidade` varchar(100) DEFAULT NULL,
  `destino_uf` char(2) DEFAULT NULL,
  `volume_json` text DEFAULT NULL,
  `superfrete_order_id` varchar(60) DEFAULT NULL,
  `superfrete_status` varchar(30) DEFAULT NULL,
  `superfrete_rastreio` varchar(60) DEFAULT NULL,
  `superfrete_etiqueta_url` varchar(500) DEFAULT NULL,
  `superfrete_valor_etiqueta` decimal(10,2) DEFAULT NULL,
  `nota_numero` varchar(20) DEFAULT NULL,
  `nota_chave` char(44) DEFAULT NULL,
  `etiqueta_gerada_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id_venda`),
  CONSTRAINT `vendas_envio_ibfk_1` FOREIGN KEY (`id_venda`) REFERENCES `vendas` (`id_venda`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `vendas_envio` DISABLE KEYS */;
/*!40000 ALTER TABLE `vendas_envio` ENABLE KEYS */;
DROP TABLE IF EXISTS `whatsapp_conexao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_conexao` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `instancia` varchar(80) DEFAULT NULL,
  `estado` enum('desconectado','conectando','conectado') NOT NULL DEFAULT 'desconectado',
  `numero` varchar(20) DEFAULT NULL,
  `aceite_em` datetime DEFAULT NULL,
  `aceite_por` int(11) DEFAULT NULL,
  `conectado_em` datetime DEFAULT NULL,
  `verificado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `whatsapp_conexao` DISABLE KEYS */;
INSERT INTO `whatsapp_conexao` VALUES (1,NULL,'desconectado',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `whatsapp_conexao` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

