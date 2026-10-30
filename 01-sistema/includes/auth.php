<?php

require_once __DIR__ . '/travamento.php';
require_once __DIR__ . '/csrf.php';

require_once __DIR__ . '/sessao.php';

function exigirLogin(): void
{
    if (empty($_SESSION['id_usuario'])) {
        // Guarda a página pedida (ex.: conectar o Mercado Pago) pra voltar
        // nela depois do login, em vez de cair sempre no Dashboard.
        // Só páginas: chamadas AJAX/API em segundo plano não podem virar o destino.
        $caminhoPedido = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !preg_match('#/(ajax|api)/#', $caminhoPedido) && !csrfRequisicaoEsperaJson()) {
            $_SESSION['voltar_apos_login'] = (string) ($_SERVER['REQUEST_URI'] ?? '');
        }
        header('Location: /login.php');
        exit;
    }
    // Checa mesmo quem já estava logado antes do travamento ser ativado --
    // não é só uma trava no momento do login.
    global $pdo;
    if ($pdo) {
        verificarTravamentoPagamento($pdo);
    }
}

function exigirAdmin(): void
{
    exigirLogin();
    if (($_SESSION['perfil'] ?? '') !== 'Admin') {
        http_response_code(403);
        if (csrfRequisicaoEsperaJson()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Acesso restrito ao administrador.']);
            exit;
        }
        // Página de verdade (com caminho de volta) em vez de só uma linha de texto.
        echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Acesso restrito</title>'
            . '<link rel="icon" type="image/png" href="/assets/marca/favicon.png">'
            . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0E0E0E;color:#F5F5F5;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;padding:24px;box-sizing:border-box}'
            . 'main{max-width:420px;text-align:center}h1{font-size:1.5rem;color:#F2B705;margin:0 0 8px}p{color:#B8BCC2;line-height:1.5;margin:0 0 24px}'
            . 'a{display:inline-block;background:#F2B705;color:#0E0E0E;font-weight:700;text-decoration:none;padding:12px 22px;border-radius:10px}</style>'
            . '</head><body><main><h1>Acesso restrito</h1>'
            . '<p>Esta tela é só para o administrador da loja. Se você precisa dela, peça ao administrador.</p>'
            . '<a href="/dashboard.php">Voltar ao painel</a></main></body></html>';
        exit;
    }
}
