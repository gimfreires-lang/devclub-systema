<?php
require_once __DIR__ . '/conecta_bd.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config_dev.php';
require_once __DIR__ . '/includes/login_tentativas.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $minutosBloqueado = loginMinutosBloqueado($pdo, $email);
    if ($minutosBloqueado > 0) {
        $erro = "Muitas tentativas com senha errada. Tente de novo em {$minutosBloqueado} minuto" . ($minutosBloqueado === 1 ? '' : 's') . '.';
    } else {
        $stmt = $pdo->prepare('SELECT id_usuario, nome, senha_hash, perfil FROM usuarios WHERE email = :email AND ativo = 1');
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            loginLimparFalhas($pdo, $email);
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['nome'] = $usuario['nome'];
            $_SESSION['perfil'] = $usuario['perfil'];
            session_regenerate_id(true);
            // Só caminhos internos ("/algo", nunca "//site" nem "http..."): evita
            // que um link malicioso use o login pra mandar a pessoa pra outro site.
            $voltar = (string) ($_SESSION['voltar_apos_login'] ?? '');
            unset($_SESSION['voltar_apos_login']);
            $destino = preg_match('#^/(?![/\\\\])[^\s]*$#', $voltar) && !str_starts_with($voltar, '/login.php') ? $voltar : '/dashboard.php';
            header('Location: ' . $destino);
            exit;
        }

        loginRegistrarFalha($pdo, $email);
        $erro = 'E-mail ou senha inválidos.';
    }
}

$versaoCssAdmin = @filemtime(__DIR__ . '/assets/css/admin.css') ?: time();
$nomeSistemaAtual = nomeDoSistema($pdo);
require_once __DIR__ . '/includes/marca.php';
$logoLogin = logoDaLoja((string) $pdo->query('SELECT logo_arquivo FROM config_loja WHERE id_config = 1')->fetchColumn());
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — <?= htmlspecialchars($nomeSistemaAtual) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= $versaoCssAdmin ?>">
    <link rel="icon" type="image/png" href="/<?= MARCA_FAVICON ?>">
    <?php require_once __DIR__ . '/includes/pwa.php'; pwaTags($pdo, 'admin'); ?>
</head>
<body class="tela-login">
<main class="container">
    <div class="auth-card">
        <img class="auth-logo" src="/<?= htmlspecialchars($logoLogin) ?>" alt="" onerror="this.remove()">
        <h1><?= htmlspecialchars($nomeSistemaAtual) ?></h1>
        <?php if ($erro): ?>
            <p class="alert alert-erro"><?= htmlspecialchars($erro) ?></p>
        <?php endif; ?>
        <form method="post">
            <label>E-mail<input type="email" name="email" required></label>
            <label>Senha<input type="password" name="senha" required></label>
            <button type="submit" class="btn-bloco">Entrar</button>
        </form>
    </div>
</main>
</body>
</html>
