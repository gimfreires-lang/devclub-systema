<?php
require_once __DIR__ . '/../../conecta_bd.php';
require_once __DIR__ . '/../../includes/auth_cliente.php';
require_once __DIR__ . '/../../includes/mp_client.php';
require_once __DIR__ . '/../../includes/credito.php';
exigirClienteLogado();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /loja/minha_divida.php');
    exit;
}

$id_cliente = (int) $_SESSION['id_cliente'];
$valor = converterMoedaBrParaFloat($_POST['valor'] ?? '0');

// Link aberto e abandonado não pode travar o cliente pra sempre (ver credito.php).
expirarPagamentosDividaAbandonados($pdo, $id_cliente);

$stmtCliente = $pdo->prepare('SELECT nome, email, saldo_devedor FROM clientes WHERE id_cliente = :id');
$stmtCliente->execute([':id' => $id_cliente]);
$clienteDivida = $stmtCliente->fetch() ?: [];
$saldoDevedor = (float) ($clienteDivida['saldo_devedor'] ?? 0);

// Pagamentos já gerados e ainda não confirmados contam contra o saldo: sem isso, duas abas
// (ou um back-button) geram duas preferências pra mesma dívida e o segundo pagamento
// desapareceria no piso do GREATEST(0, ...) na confirmação.
$stmtPendente = $pdo->prepare(
    "SELECT COALESCE(SUM(valor), 0) FROM movimentos_credito
     WHERE id_cliente = :id AND tipo = 'pagamento' AND status = 'Pendente'"
);
$stmtPendente->execute([':id' => $id_cliente]);
$totalPendente = (float) $stmtPendente->fetchColumn();

$disponivelParaPagar = $saldoDevedor - $totalPendente;

if ($valor <= 0 || $valor > $disponivelParaPagar + 0.01) {
    header('Location: /loja/minha_divida.php?erro=' . urlencode('Valor inválido, ou você já tem um pagamento pendente de confirmação que cobre parte dessa dívida.'));
    exit;
}

$config = mpConfig($pdo);
if (!$config || empty($config['mp_access_token'])) {
    header('Location: /loja/minha_divida.php?erro=' . urlencode('Loja temporariamente indisponível para pagamento. Tente novamente mais tarde.'));
    exit;
}

$pdo->prepare(
    "INSERT INTO movimentos_credito (id_cliente, tipo, status, valor, forma_pagamento)
     VALUES (:ic, 'pagamento', 'Pendente', :valor, 'Mercado Pago')"
)->execute([':ic' => $id_cliente, ':valor' => $valor]);
$id_movimento = (int) $pdo->lastInsertId();

$application_fee = ceil($valor * (mpTaxaMarketplace($pdo) / 100) * 100) / 100;

$nomeLojaMp = $pdo->query('SELECT nome_loja FROM config_loja WHERE id_config = 1')->fetchColumn() ?: 'Clube da Cerveja';

$preference = [
    'items' => [[
        'title' => 'Pagamento de dívida - ' . $nomeLojaMp,
        'quantity' => 1,
        'currency_id' => 'BRL',
        'unit_price' => $valor,
    ]],
    'external_reference' => 'divida_' . $id_movimento,
    'notification_url' => urlBaseAtual() . '/integracoes/mercado_pago/webhook.php',
    'back_urls' => [
        'success' => urlBaseAtual() . '/loja/minha_divida.php',
        'pending' => urlBaseAtual() . '/loja/minha_divida.php',
        'failure' => urlBaseAtual() . '/loja/minha_divida.php',
    ],
    'auto_return' => 'approved',
    // /v1/payments provou (em produção) que o Mercado Pago valida o nome dos
    // parâmetros e rejeita o que não reconhece -- não dá pra supor que
    // /checkout/preferences seja mais tolerante. marketplace_fee é o único
    // campo documentado como correto aqui.
    'marketplace_fee' => $application_fee,
    'sponsor_id' => (int) MP_SPONSOR_ID,
    'excluded_payment_types' => [
        ['id' => 'ticket'],
    ],
    // O link e o Pix gerado por ele valem o mesmo prazo que o movimento fica
    // 'Pendente' -- sem date_of_expiration o Pix do Checkout Pro continua pagável
    // por até 24 h, bem depois de a tela do cliente já ter liberado outro link.
    'expires' => true,
    'expiration_date_from' => (new DateTime())->format(DateTime::ATOM),
    'expiration_date_to' => (new DateTime('+' . PRAZO_PAGAMENTO_DIVIDA_MINUTOS . ' minutes'))->format(DateTime::ATOM),
    'date_of_expiration' => (new DateTime('+' . PRAZO_PAGAMENTO_DIVIDA_MINUTOS . ' minutes'))->format('Y-m-d\TH:i:s.vP'),
    'statement_descriptor' => mpDescricaoFatura($nomeLojaMp),
    'payer' => array_filter([
        'name' => $clienteDivida['nome'] ?? null,
        'email' => !empty($clienteDivida['email']) ? $clienteDivida['email'] : null,
    ]),
];

if (mpMesmaContaDoIntegrador($config)) {
    unset($preference['marketplace_fee'], $preference['sponsor_id']);
}

try {
    $resposta = mpChamarApi('POST', 'https://api.mercadopago.com/checkout/preferences', $preference, $config['mp_access_token']);

    if ($resposta['http_code'] !== 201 || !isset($resposta['dados']['init_point'])) {
        header('Location: /loja/minha_divida.php?erro=' . urlencode('Erro ao gerar pagamento: ' . ($resposta['dados']['message'] ?? 'resposta inesperada do Mercado Pago')));
        exit;
    }

    header('Location: ' . $resposta['dados']['init_point']);
    exit;
} catch (Throwable $e) {
    header('Location: /loja/minha_divida.php?erro=' . urlencode('Erro ao conectar com o Mercado Pago.'));
    exit;
}
