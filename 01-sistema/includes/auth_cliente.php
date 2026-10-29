<?php

require_once __DIR__ . '/sessao.php';

require_once __DIR__ . '/csrf.php';

// Cliente movido pra lixeira (clientes.excluido_em) perde o acesso na hora --
// derruba a sessão dele em toda página que carrega este arquivo, em vez de
// esperar ele deslogar sozinho.
if (!empty($_SESSION['id_cliente']) && isset($pdo) && $pdo instanceof PDO) {
    $stmtLixeira = $pdo->prepare('SELECT excluido_em FROM clientes WHERE id_cliente = :id');
    $stmtLixeira->execute([':id' => (int) $_SESSION['id_cliente']]);
    $linhaLixeira = $stmtLixeira->fetch();
    if (!$linhaLixeira || $linhaLixeira['excluido_em'] !== null) {
        unset($_SESSION['id_cliente'], $_SESSION['nome_cliente']);
    }
}

/**
 * Endereço interno da loja pra onde o cliente volta depois de entrar ou se
 * cadastrar. Só aceita caminhos de /loja/ (nunca um site de fora), pra não
 * virar um redirecionamento aberto.
 */
function destinoLojaSeguro(?string $destino): ?string
{
    $destino = (string) $destino;
    if ($destino === '' || !str_starts_with($destino, '/loja/') || str_contains($destino, '//')
        || preg_match('/[\x00-\x1F\x5C]/', $destino) || str_starts_with($destino, '/loja/cadastro.php')) {
        return null;
    }
    return $destino;
}

/** Usado por loja/cadastro.php logo depois de logar o cliente. */
function destinoAposLoginCliente(): string
{
    $destino = destinoLojaSeguro($_SESSION['voltar_apos_login'] ?? null);
    unset($_SESSION['voltar_apos_login']);
    return $destino ?? '/loja/index.php';
}

function exigirClienteLogado(bool $modo_json = false): void
{
    if (empty($_SESSION['id_cliente'])) {
        if ($modo_json) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
        } else {
            // Volta pra mesma página depois do login (ex.: carrinho, checkout).
            $voltar = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? destinoLojaSeguro($_SERVER['REQUEST_URI'] ?? '') : null;
            header('Location: /loja/cadastro.php' . ($voltar ? '?voltar=' . urlencode($voltar) : ''));
        }
        exit;
    }
}
