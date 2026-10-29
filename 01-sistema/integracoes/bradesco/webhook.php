<?php
/**
 * Webhook do Pix Bradesco (padrão Banco Central). O Bradesco chama
 * <URL cadastrada>/pix com {"pix": [{"txid": ..., "endToEndId": ..., "valor": ...}]}.
 *
 * Segurança: o conteúdo recebido NUNCA é usado como prova de pagamento. Cada
 * txid só serve de gatilho -- bradescoConsultarEConfirmar() consulta o próprio
 * Bradesco com as credenciais da loja antes de confirmar qualquer coisa. O token
 * na URL só evita que qualquer um faça o sistema consultar a API à toa.
 */
require_once __DIR__ . '/../../conecta_bd.php';
require_once __DIR__ . '/../../includes/bradesco_pix.php';

http_response_code(200);
header('Content-Type: application/json');

$config = bradescoConfig($pdo);
// O padrão BCB acrescenta "/pix" no fim da URL cadastrada; como o token é o
// último parâmetro, ele chega como "abc123/pix".
$tokenRecebido = preg_replace('#/pix$#', '', (string) ($_GET['t'] ?? ''));
if (!$config || empty($config['webhook_token']) || !hash_equals((string) $config['webhook_token'], $tokenRecebido)) {
    error_log('Webhook Bradesco: token inválido.');
    echo json_encode(['ok' => true]);
    exit;
}

$corpo = json_decode((string) file_get_contents('php://input'), true);
$txids = [];
foreach ($corpo['pix'] ?? [] as $pix) {
    if (!empty($pix['txid']) && preg_match('/^[A-Za-z0-9]{26,35}$/', $pix['txid'])) {
        $txids[$pix['txid']] = true;
    }
}

foreach (array_keys($txids) as $txid) {
    try {
        bradescoConsultarEConfirmar($pdo, $txid);
    } catch (Throwable $e) {
        error_log('Webhook Bradesco ' . $txid . ': ' . $e->getMessage());
    }
}

echo json_encode(['ok' => true]);
