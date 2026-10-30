<?php
require_once __DIR__ . '/../../conecta_bd.php';
require_once __DIR__ . '/../../includes/auth_cliente.php';
require_once __DIR__ . '/../../includes/loja.php';
require_once __DIR__ . '/../../includes/domingo.php';
exigirClienteLogado();

/*
 * Cliente troca o domingo/horário de retirada da carne de um pedido já feito.
 * Só até o horário-limite do domingo marcado, e só se o novo horário tiver
 * vaga e a carne não tiver esgotado no novo domingo. Avisa cliente e loja.
 */

$id_venda = (int) ($_POST['id_venda'] ?? 0);
$voltar = '/loja/pedido_status.php?id_venda=' . $id_venda;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id_venda <= 0) {
    header('Location: /loja/meus_pedidos.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id_venda, status, status_entrega, retirada_data, retirada_hora FROM vendas
     WHERE id_venda = :id AND id_cliente = :ic AND origem = 'loja'"
);
$stmt->execute([':id' => $id_venda, ':ic' => (int) $_SESSION['id_cliente']]);
$venda = $stmt->fetch();

if (!$venda) {
    header('Location: /loja/meus_pedidos.php');
    exit;
}
if (!domingoPodeMudar($pdo, $venda)) {
    header('Location: ' . $voltar . '&erro=' . urlencode('O prazo para mudar o horário deste pedido já passou. Fale com a loja pelo WhatsApp.'));
    exit;
}

$novaData = (string) ($_POST['retirada_data'] ?? '');
$novaHora = (string) ($_POST['retirada_hora'] ?? '');
if ($novaData === $venda['retirada_data'] && $novaHora === domingoRotuloHora($venda['retirada_hora'])) {
    header('Location: ' . $voltar);
    exit;
}

$antes = domingoRotuloRetirada($venda['retirada_data'], $venda['retirada_hora']);
$erro = domingoValidarEGravarRetirada($pdo, $id_venda, $novaData, $novaHora);
if ($erro !== null) {
    header('Location: ' . $voltar . '&erro=' . urlencode($erro));
    exit;
}

try {
    require_once __DIR__ . '/../../includes/avisos_whatsapp.php';
    avisarRetiradaAlterada($pdo, $id_venda, $antes);
} catch (Throwable $e) {
    error_log('mudar_retirada: aviso de WhatsApp falhou: ' . $e->getMessage());
}

header('Location: ' . $voltar . '&msg=' . urlencode('Pronto! A retirada mudou para ' . domingoRotuloRetirada($novaData, $novaHora) . '.'));
exit;
