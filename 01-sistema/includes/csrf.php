<?php
/**
 * Proteção contra CSRF (um site de fora fazendo o navegador de quem está
 * logado enviar um formulário pro sistema sem a pessoa saber).
 *
 * Funciona sozinho, sem mexer em cada página:
 *  - toda página que carrega includes/auth.php, auth_cliente.php ou
 *    auth_dev.php passa por aqui;
 *  - todo POST precisa trazer o token da sessão, no campo `_csrf` ou no
 *    cabeçalho `X-CSRF-Token`;
 *  - o HTML da resposta ganha o token automaticamente: um campo escondido em
 *    cada <form method="post"> e, no <head>, uma meta tag + um script que
 *    acrescenta o cabeçalho em todo fetch() pro próprio site (fetch pra
 *    outros sites, como o ViaCEP, fica intocado).
 *
 * Webhooks do Mercado Pago (pastas api/ e integracoes/mercado_pago/webhook,
 * callback) não carregam os arquivos de login, então não passam por aqui.
 */

require_once __DIR__ . '/sessao.php';

function csrfToken(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrfCampo(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES) . '">';
}

function csrfRequisicaoEsperaJson(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return strpos($uri, '/ajax/') !== false
        || stripos($accept, 'application/json') !== false
        || !empty($_SERVER['HTTP_X_CSRF_TOKEN']);
}

function csrfValidarRequisicao(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $enviado = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $esperado = $_SESSION['_csrf'] ?? '';
    if (is_string($enviado) && $esperado !== '' && hash_equals($esperado, $enviado)) {
        return;
    }

    http_response_code(419);
    $mensagem = 'Sua sessão expirou ou a página ficou aberta por muito tempo. Recarregue a página e tente de novo.';
    if (csrfRequisicaoEsperaJson()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'sucesso' => false, 'message' => $mensagem, 'mensagem' => $mensagem, 'erro' => $mensagem]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        $voltar = htmlspecialchars($_SERVER['HTTP_REFERER'] ?? '/', ENT_QUOTES);
        echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Recarregue a página</title></head>'
            . '<body style="font-family:system-ui,sans-serif;max-width:520px;margin:60px auto;padding:0 16px;line-height:1.5">'
            . '<h1 style="font-size:22px">Recarregue a página</h1><p>' . $mensagem . '</p>'
            . '<p><a href="' . $voltar . '">Voltar</a></p></body></html>';
    }
    exit;
}

/** Injeta o token no HTML que a página gerar (só em respostas HTML). */
function csrfInjetarNoHtml(string $html): string
{
    foreach (headers_list() as $cabecalho) {
        if (stripos($cabecalho, 'Content-Type:') === 0 && stripos($cabecalho, 'text/html') === false) {
            return $html;
        }
    }
    if (stripos($html, '<form') === false && stripos($html, '<head') === false) {
        return $html;
    }

    $campo = csrfCampo();
    $html = preg_replace_callback(
        '/<form\b[^>]*\bmethod\s*=\s*["\']?post\b[^>]*>/i',
        static fn(array $m) => $m[0] . $campo,
        $html
    ) ?? $html;

    $token = htmlspecialchars(csrfToken(), ENT_QUOTES);
    $script = '<meta name="csrf-token" content="' . $token . '"><script>(function(){'
        . 'var t=document.querySelector(\'meta[name="csrf-token"]\').content,f=window.fetch;if(!f)return;'
        . 'window.fetch=function(i,o){try{var u=new URL(typeof i==="string"?i:(i&&i.url)||"",location.href);'
        . 'if(u.origin===location.origin){o=Object.assign({},o||{});var h=new Headers(o.headers||(i instanceof Request?i.headers:undefined));'
        . 'if(!h.has("X-CSRF-Token"))h.set("X-CSRF-Token",t);o.headers=h;}}catch(e){}return f.call(this,i,o);};})();</script>';
    // Logo depois de <head>, antes de qualquer outro script da página usar fetch().
    $novo = preg_replace('/<head\b[^>]*>/i', '$0' . str_replace(['\\', '$'], ['\\\\', '\\$'], $script), $html, 1);
    return $novo ?? $html;
}

if (!defined('CSRF_ATIVO')) {
    define('CSRF_ATIVO', true);
    csrfToken();
    csrfValidarRequisicao();
    ob_start('csrfInjetarNoHtml');
}
