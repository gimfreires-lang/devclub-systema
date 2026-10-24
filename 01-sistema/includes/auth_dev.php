<?php
/**
 * Sessão do painel_dev é completamente separada da sessão de login da loja
 * ($_SESSION['id_usuario']) — usa suas próprias chaves de sessão, então dá
 * pra estar logado como Admin da loja e como DevMaster ao mesmo tempo no
 * mesmo navegador sem um "atropelar" o outro.
 */

require_once __DIR__ . '/sessao.php';

require_once __DIR__ . '/csrf.php';

function exigirDev(): void
{
    if (empty($_SESSION['dev_usuario_id'])) {
        header('Location: /painel_dev/login.php');
        exit;
    }

    // Enquanto o cadastro não estiver completo (primeiro acesso com a senha
    // temporária, que é pública no README), só a tela de completar cadastro
    // fica liberada -- antes dava pra pular ela abrindo outra URL do painel.
    global $pdo;
    $paginaAtual = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($paginaAtual !== 'completar_cadastro.php' && $pdo instanceof PDO) {
        $stmt = $pdo->prepare('SELECT nome, email FROM dev_usuarios WHERE id_dev_usuario = :id');
        $stmt->execute([':id' => (int) $_SESSION['dev_usuario_id']]);
        $dev = $stmt->fetch();
        if (!$dev) {
            unset($_SESSION['dev_usuario_id'], $_SESSION['dev_usuario_nome']);
            header('Location: /painel_dev/login.php');
            exit;
        }
        if (empty($dev['nome']) || empty($dev['email'])) {
            if (strpos($_SERVER['REQUEST_URI'] ?? '', '/ajax/') !== false) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Complete seu cadastro primeiro.']);
                exit;
            }
            header('Location: /painel_dev/completar_cadastro.php');
            exit;
        }
    }
}
