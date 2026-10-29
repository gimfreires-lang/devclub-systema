<?php
/**
 * Usado pela tela loja/pagar_pix.php: diz se o Pix do Bradesco já foi pago.
 * Consulta o Bradesco no máximo a cada 4 segundos por cobrança.
 */
require_once __DIR__ . '/../../conecta_bd.php';
require_once __DIR__ . '/../../includes/auth_cliente.php';
require_once __DIR__ . '/../../includes/bradesco_pix.php';
exigirClienteLogado(true);
header('Content-Type: application/json');

$id_cliente = (int) $_SESSION['id_cliente'];
$tipo = ($_GET['tipo'] ?? '') === 'divida' ? 'divida' : 'loja';
$id = (int) ($_GET['id'] ?? 0);

if ($tipo === 'loja') {
    $stmt = $pdo->prepare("SELECT status FROM vendas WHERE id_venda = :id AND id_cliente = :ic AND origem = 'loja'");
    $redirect = '/loja/pedido_status.php?id_venda=' . $id;
} else {
    $stmt = $pdo->prepare("SELECT status FROM movimentos_credito WHERE id_movimento = :id AND id_cliente = :ic AND tipo = 'pagamento'");
    $redirect = '/loja/minha_divida.php?pago=1';
}
$stmt->execute([':id' => $id, ':ic' => $id_cliente]);
$status = $stmt->fetchColumn();
if ($status === false) {
    echo json_encode(['pago' => false, 'encerrado' => true, 'redirect' => '/loja/index.php']);
    exit;
}

$pago = ($tipo === 'loja' && $status === 'Pago') || ($tipo === 'divida' && $status === 'Confirmado');

if (!$pago) {
    $stmtCob = $pdo->prepare('SELECT txid FROM cobrancas_pix WHERE origem = :o AND id_referencia = :r ORDER BY criado_em DESC LIMIT 1');
    $stmtCob->execute([':o' => $tipo, ':r' => $id]);
    $txid = $stmtCob->fetchColumn();
    if ($txid) {
        try {
            bradescoConsultarEConfirmar($pdo, (string) $txid, 4);
        } catch (Throwable $e) {
            error_log('status_pix ' . $txid . ': ' . $e->getMessage());
        }
        $stmt->execute([':id' => $id, ':ic' => $id_cliente]);
        $status = $stmt->fetchColumn();
        $pago = ($tipo === 'loja' && $status === 'Pago') || ($tipo === 'divida' && $status === 'Confirmado');
    }
}

// Prazo acabou sem pagamento: cancela (devolve o estoque / libera a dívida) e
// manda o cliente de volta pra tela do pedido ou da conta.
if (!$pago) {
    if ($tipo === 'loja') {
        require_once __DIR__ . '/../../includes/loja.php';
        liberarReservasExpiradas($pdo);
    } else {
        require_once __DIR__ . '/../../includes/credito.php';
        expirarPagamentosDividaAbandonados($pdo, $id_cliente);
    }
    $stmt->execute([':id' => $id, ':ic' => $id_cliente]);
    $status = $stmt->fetchColumn();
}
$encerrado = !$pago && $status === 'Cancelado';
if ($encerrado && $tipo === 'divida') {
    $redirect = '/loja/minha_divida.php';
}
echo json_encode(['pago' => $pago, 'encerrado' => $encerrado, 'redirect' => $redirect]);
