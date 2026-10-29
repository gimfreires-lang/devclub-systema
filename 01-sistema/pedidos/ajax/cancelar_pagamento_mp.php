<?php
require_once __DIR__ . '/../../conecta_bd.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/loja.php';
require_once __DIR__ . '/../../includes/mp_client.php';
exigirAdmin();
header('Content-Type: application/json');

$id_venda = (int) ($_POST['id_venda'] ?? 0);

$stmt = $pdo->prepare("SELECT id_pagamento_mp, status FROM vendas WHERE id_venda = :id AND origem = 'loja'");
$stmt->execute([':id' => $id_venda]);
$venda = $stmt->fetch();

if (!$venda || $venda['status'] !== 'Reservado' || empty($venda['id_pagamento_mp'])) {
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado ou não tem pagamento pendente.']);
    exit;
}

$config = mpConfig($pdo);
if (!$config || empty($config['mp_access_token'])) {
    echo json_encode(['success' => false, 'message' => 'Loja não conectada ao Mercado Pago.']);
    exit;
}

try {
    // Confere o status de verdade antes de mexer em qualquer coisa — o webhook
    // pode ter confirmado o pagamento bem na hora que o lojista clicou em
    // cancelar; nesse caso não dá (nem deve) pra cancelar mais.
    // Na loja, o id guardado em vendas.id_pagamento_mp enquanto o pedido espera
    // pagamento é o da PREFERÊNCIA do Checkout Pro, não de um pagamento -- buscar
    // /v1/payments/<id> com ele sempre dava 404 e o Pix já gerado pelo cliente
    // continuava pagável. Por isso a busca é pelo external_reference do pedido,
    // que pega todas as tentativas (Pix, cartão) feitas nessa preferência.
    $busca = mpChamarApi('GET', 'https://api.mercadopago.com/v1/payments/search?' . http_build_query([
        'external_reference' => 'loja_' . $id_venda,
        'sort' => 'date_created',
        'criteria' => 'desc',
    ]), null, $config['mp_access_token']);
    $tentativas = $busca['dados']['results'] ?? [];

    foreach ($tentativas as $tentativa) {
        if (($tentativa['status'] ?? null) === 'approved') {
            echo json_encode(['success' => false, 'message' => 'Esse pagamento já foi aprovado — não dá pra cancelar. Atualize a página.']);
            exit;
        }
    }

    foreach ($tentativas as $tentativa) {
        if (in_array($tentativa['status'] ?? null, ['pending', 'in_process'], true)) {
            $resposta = mpChamarApi('PUT', 'https://api.mercadopago.com/v1/payments/' . $tentativa['id'], ['status' => 'cancelled'], $config['mp_access_token']);
            if ($resposta['http_code'] >= 400) {
                echo json_encode(['success' => false, 'message' => 'Erro ao cancelar no Mercado Pago: ' . ($resposta['dados']['message'] ?? 'tente novamente.')]);
                exit;
            }
        }
    }
    // Se já estava rejected/cancelled do lado do Mercado Pago, não precisa
    // chamar o cancelamento de novo — só segue pra liberar o pedido aqui.
} catch (Throwable $e) {
    error_log('cancelar_pagamento_mp: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao conectar com o Mercado Pago.']);
    exit;
}

if (cancelarVendaReservadaLoja($pdo, $id_venda)) {
    // Com motivo, o webhook sabe que foi o lojista quem cancelou e não reabre o
    // pedido sozinho se algum pagamento ainda chegar (vira alerta em Pedidos).
    $pdo->prepare('UPDATE vendas SET motivo_cancelamento = :m WHERE id_venda = :id')
        ->execute([':m' => 'Pagamento pendente cancelado pelo lojista', ':id' => $id_venda]);
}
echo json_encode(['success' => true, 'message' => 'Pagamento cancelado. Estoque liberado.']);
