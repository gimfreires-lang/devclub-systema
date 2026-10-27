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
        echo 'Acesso restrito ao administrador.';
        exit;
    }
}
