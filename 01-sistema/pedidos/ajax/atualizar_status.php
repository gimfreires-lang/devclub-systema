<?php
require_once __DIR__ . '/../../conecta_bd.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pedidos.php';
exigirLogin();
header('Content-Type: application/json');

$id_venda = (int) ($_POST['id_venda'] ?? 0);
$status_entrega = $_POST['status_entrega'] ?? '';

if (!in_array($status_entrega, STATUS_ENTREGA_VALIDOS, true)) {
    echo json_encode(['success' => false, 'message' => 'Etapa inválida.']);
    exit;
}

$stmt = $pdo->prepare("UPDATE vendas SET status_entrega = :se WHERE id_venda = :id AND origem = 'loja' AND status = 'Pago'");
$stmt->execute([':se' => $status_entrega, ':id' => $id_venda]);

if ($stmt->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado ou não está pago.']);
    exit;
}

// "Pronto": avisa o cliente pelo WhatsApp (uma vez só por pedido).
$avisou = null;
if ($status_entrega === 'Pronto') {
    try {
        require_once __DIR__ . '/../../includes/avisos_whatsapp.php';
        avisarPedidoPronto($pdo, $id_venda);
        $ultimo = $pdo->prepare("SELECT sucesso, detalhe FROM whatsapp_log WHERE id_venda = :id AND tipo = 'pedido_pronto' ORDER BY id DESC LIMIT 1");
        $ultimo->execute([':id' => $id_venda]);
        $avisou = $ultimo->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('atualizar_status: aviso de pronto falhou: ' . $e->getMessage());
    }
}

$mensagem = 'Status atualizado.';
if ($avisou) {
    $mensagem .= (int) $avisou['sucesso'] ? ' Cliente avisado no WhatsApp.' : ' WhatsApp não enviado: ' . $avisou['detalhe'];
}
echo json_encode(['success' => true, 'message' => $mensagem]);
