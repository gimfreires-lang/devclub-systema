<?php
require_once __DIR__ . '/../../conecta_bd.php';
require_once __DIR__ . '/../../includes/auth_cliente.php';
require_once __DIR__ . '/../../includes/loja.php';
exigirClienteLogado(true);
header('Content-Type: application/json');

$id_cliente = (int) $_SESSION['id_cliente'];

// Conferência com limite de tentativas: ver conferirCodigoCliente() em includes/loja.php.
$resultado = conferirCodigoCliente($pdo, $id_cliente, 'whatsapp', (string) ($_POST['codigo'] ?? ''));
if (!$resultado['ok']) {
    echo json_encode(['success' => false, 'message' => $resultado['message']]);
    exit;
}

$pdo->prepare('UPDATE clientes SET whatsapp_verificado_em = NOW(), login_whatsapp_bloqueado = 0, token_verificacao_whatsapp = NULL, token_verificacao_whatsapp_expira_em = NULL WHERE id_cliente = :id')
    ->execute([':id' => $id_cliente]);

echo json_encode(['success' => true, 'message' => 'WhatsApp confirmado!']);
