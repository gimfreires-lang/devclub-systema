<?php
// Entrega o modelo do workflow do n8n só para o desenvolvedor logado.
// O .json em si não é servido direto pelo servidor web (nginx.template.conf / .htaccess).
require_once __DIR__ . '/../conecta_bd.php';
require_once __DIR__ . '/../includes/auth_dev.php';
exigirDev();

$arquivo = __DIR__ . '/n8n-whatsapp-verificacao.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="n8n-whatsapp-verificacao.json"');
header('Content-Length: ' . filesize($arquivo));
readfile($arquivo);
