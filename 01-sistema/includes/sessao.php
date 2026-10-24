<?php
/**
 * Início de sessão único do sistema, com o cookie de sessão endurecido:
 *  - HttpOnly: JavaScript da página não lê o cookie;
 *  - SameSite=Lax: o navegador não manda o cookie em POST vindo de outro site
 *    (o retorno do Mercado Pago é um GET, então continua funcionando);
 *  - Secure quando o site está em HTTPS (inclusive atrás do proxy do Easypanel,
 *    que avisa por X-Forwarded-Proto);
 *  - use_strict_mode: recusa ID de sessão inventado por quem está de fora.
 */

function requisicaoEmHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => requisicaoEmHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
