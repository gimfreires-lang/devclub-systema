<?php
require_once __DIR__ . '/../../conecta_bd.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/caixa.php';
exigirLogin();
header('Content-Type: application/json');

$id_item = (int) ($_POST['id_item'] ?? 0);
$id_venda = (int) ($_POST['id_venda'] ?? 0);

$stmtV = $pdo->prepare("SELECT id_venda FROM vendas WHERE id_venda = :id AND id_usuario = :iu AND status = 'Reservado'");
$stmtV->execute([':id' => $id_venda, ':iu' => $_SESSION['id_usuario']]);
if (!$stmtV->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Venda não encontrada ou já finalizada.']);
    exit;
}

// Linha com 2x ou mais tira uma unidade por clique; com 1x, sai do carrinho.
// (subtotal vem antes de quantidade: o MySQL aplica o SET da esquerda pra direita.)
$tirouUma = $pdo->prepare('UPDATE itens_venda SET subtotal = preco_unit * (quantidade - 1), quantidade = quantidade - 1 WHERE id_item = :id AND id_venda = :iv AND quantidade > 1');
$tirouUma->execute([':id' => $id_item, ':iv' => $id_venda]);
if ($tirouUma->rowCount() === 0) {
    $pdo->prepare('DELETE FROM itens_venda WHERE id_item = :id AND id_venda = :iv')
        ->execute([':id' => $id_item, ':iv' => $id_venda]);
}

recalcularTotalVenda($pdo, $id_venda);

echo json_encode(['success' => true]);
