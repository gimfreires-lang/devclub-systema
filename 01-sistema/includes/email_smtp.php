<?php
/**
 * Envio de e-mail via SMTP autenticado (sem depender de biblioteca externa — só
 * socket + protocolo SMTP puro), usando as credenciais SMTP_* definidas em
 * brechodaveve_config_credenciais.php (mesmo arquivo externo que já guarda as
 * credenciais do banco — nunca fica dentro do repositório).
 */

/**
 * Monta um e-mail HTML com a cara da própria loja (logo + nome da loja vindos de
 * config_loja, não do CoderNex) — quem recebe é cliente da loja, não da CoderNex.
 * Mostra um código bem destacado (não um botão/link) -- o cliente digita esse
 * código no popup de validação do próprio site.
 */
function montarEmailHtmlLoja(PDO $pdo, string $titulo, array $paragrafos, string $codigo): string
{
    $configLoja = $pdo->query('SELECT nome_loja, logo_arquivo FROM config_loja WHERE id_config = 1')->fetch();
    $nomeLoja = $configLoja['nome_loja'] ?? 'Clube da Cerveja';

    $paragrafosHtml = '';
    foreach ($paragrafos as $paragrafo) {
        $paragrafosHtml .= '<p style="margin:0 0 14px; color:#444444; font-size:14px; line-height:1.55;">' . $paragrafo . '</p>';
    }

    // Sem logo enviada em Aparência, cai na logo padrão do Clube da Cerveja.
    require_once __DIR__ . '/marca.php';
    $cabecalhoLogo = '<img src="' . htmlspecialchars(urlBaseAtual()) . '/' . htmlspecialchars(logoDaLoja($configLoja['logo_arquivo'] ?? null, true)) . '" alt="' . htmlspecialchars($nomeLoja) . '" style="max-height:96px; width:auto;">';

    return '
    <div style="background:#0E0E0E; padding:32px 16px; font-family:Arial, Helvetica, sans-serif;">
      <div style="max-width:460px; margin:0 auto;">
        <div style="text-align:center; padding-bottom:22px;">' . $cabecalhoLogo . '</div>
        <div style="background:#ffffff; border-radius:10px; padding:32px; box-shadow:0 2px 12px rgba(0,0,0,0.07);">
          <h2 style="margin:0 0 18px; font-size:19px; color:#222222;">' . htmlspecialchars($titulo) . '</h2>
          ' . $paragrafosHtml . '
          <div style="text-align:center; margin:28px 0 8px;">
            <div style="display:inline-block; background:#FDF3D0; border:1.5px dashed #C98A00; border-radius:10px; padding:16px 26px; font-size:30px; font-weight:800; letter-spacing:8px; color:#141414;">' . htmlspecialchars($codigo) . '</div>
          </div>
          <p style="font-size:12px; color:#999999; margin-top:24px; line-height:1.5; text-align:center;">Se você não pediu esse código, pode ignorar este e-mail.</p>
        </div>
        <p style="text-align:center; color:#B8BCC2; font-size:12px; margin-top:24px;">' . htmlspecialchars($nomeLoja) . ' · ' . MARCA_SUBTITULO . '</p>
      </div>
    </div>';
}

/**
 * Mesma ideia de montarEmailHtmlLoja(), mas pro dev do sistema (não pro
 * cliente da loja) -- usa o nome do sistema (config_dev.nome_sistema) em
 * vez da marca da loja, e mostra um botão/link em vez de um código, já que
 * os e-mails de segurança do painel_dev linkam pra uma página de ação
 * (redefinir senha), não pedem um código digitado num popup. $botaoTexto/
 * $botaoUrl são opcionais -- omitidos, o e-mail só tem os parágrafos.
 */
function montarEmailHtmlDev(PDO $pdo, string $titulo, array $paragrafos, ?string $botaoTexto = null, ?string $botaoUrl = null): string
{
    $nomeSistema = nomeDoSistema($pdo);

    $paragrafosHtml = '';
    foreach ($paragrafos as $paragrafo) {
        $paragrafosHtml .= '<p style="margin:0 0 14px; color:#444444; font-size:14px; line-height:1.55;">' . $paragrafo . '</p>';
    }

    $botaoHtml = '';
    if ($botaoTexto && $botaoUrl) {
        $botaoHtml = '
          <div style="text-align:center; margin:28px 0 8px;">
            <a href="' . htmlspecialchars($botaoUrl) . '" style="display:inline-block; background:#F2B705; color:#141414; text-decoration:none; font-weight:700; font-size:15px; padding:14px 28px; border-radius:8px;">' . htmlspecialchars($botaoTexto) . '</a>
          </div>';
    }

    return '
    <div style="background:#0E0E0E; padding:32px 16px; font-family:Arial, Helvetica, sans-serif;">
      <div style="max-width:460px; margin:0 auto;">
        <div style="text-align:center; padding-bottom:22px;"><span style="font-family:Impact,Arial Narrow,sans-serif; font-size:24px; letter-spacing:1px; text-transform:uppercase; color:#F2B705;">' . htmlspecialchars($nomeSistema) . '</span></div>
        <div style="background:#ffffff; border-radius:10px; padding:32px; box-shadow:0 2px 12px rgba(0,0,0,0.07);">
          <h2 style="margin:0 0 18px; font-size:19px; color:#222222;">' . htmlspecialchars($titulo) . '</h2>
          ' . $paragrafosHtml . $botaoHtml . '
          <p style="font-size:12px; color:#999999; margin-top:24px; line-height:1.5; text-align:center;">Painel do desenvolvedor — se você não reconhece essa atividade, sua conta continua protegida enquanto o e-mail/senha não forem comprometidos.</p>
        </div>
        <p style="text-align:center; color:#B8BCC2; font-size:12px; margin-top:24px;">' . htmlspecialchars($nomeSistema) . '</p>
      </div>
    </div>';
}

/**
 * Credenciais SMTP: vêm do painel_dev (config_dev) se o desenvolvedor já
 * preencheu por lá, senão caem nas constantes SMTP_* definidas em
 * brechodaveve_config_credenciais.php, do jeito que sempre funcionou.
 */
function smtpCredenciais(PDO $pdo): array
{
    $configDev = $pdo->query('SELECT smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from_email, smtp_from_name FROM config_dev WHERE id_config = 1')->fetch();

    return [
        'host' => $configDev['smtp_host'] ?? null ?: (defined('SMTP_HOST') ? SMTP_HOST : null),
        'port' => $configDev['smtp_port'] ?? null ?: (defined('SMTP_PORT') ? SMTP_PORT : null),
        'user' => $configDev['smtp_user'] ?? null ?: (defined('SMTP_USER') ? SMTP_USER : null),
        'pass' => $configDev['smtp_pass'] ?? null ?: (defined('SMTP_PASS') ? SMTP_PASS : null),
        'from_email' => $configDev['smtp_from_email'] ?? null ?: (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : null),
        'from_name' => $configDev['smtp_from_name'] ?? null ?: (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Loja'),
    ];
}

/**
 * $corpoTexto é opcional — se não informado, é gerado automaticamente a partir do
 * HTML (tags removidas), pra clientes de e-mail que não mostram HTML caírem numa
 * versão em texto puro em vez de ver o código HTML cru.
 *
 * Segue o padrão SMTP à risca, porque os provedores (Gmail, Outlook, Hostinger,
 * Zoho...) passaram a recusar mensagens fora dele:
 *  - toda quebra de linha é CRLF (LF "solto" é recusado desde o caso "SMTP smuggling");
 *  - corpo em base64 com linhas de 76 caracteres (nunca passa do limite de 998);
 *  - cabeçalhos Date e Message-ID presentes (o Gmail recusa sem Message-ID);
 *  - cada resposta do servidor é conferida, e o erro devolvido é o do passo que falhou.
 */
function enviarEmailSMTP(PDO $pdo, string $destinatario, string $assunto, string $corpoHtml, ?string $corpoTexto = null): array
{
    $smtp = smtpCredenciais($pdo);
    if (!$smtp['host'] || !$smtp['user'] || !$smtp['pass']) {
        return smtpFalha('SMTP não configurado (preencha em Painel do desenvolvedor > E-mail (SMTP)).');
    }

    // Sem "e-mail de envio" preenchido, usa o próprio usuário SMTP -- a maioria
    // dos provedores só aceita remetente igual à conta autenticada.
    $remetente = trim((string) ($smtp['from_email'] ?: $smtp['user']));
    if (!filter_var($remetente, FILTER_VALIDATE_EMAIL)) {
        return smtpFalha('E-mail de envio inválido: "' . $remetente . '". Preencha "E-mail de envio" no SMTP.');
    }
    $destinatario = trim($destinatario);
    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        return smtpFalha('E-mail do destinatário inválido: "' . $destinatario . '".');
    }

    $porta = (int) ($smtp['port'] ?: 587);
    // Porta 465 = SSL implícito (a conexão já nasce criptografada). Qualquer outra
    // (587, 25, 2525) = STARTTLS. Usar o esquema errado pra porta trava ou é recusado.
    $usa_ssl_implicito = ($porta === 465);
    $esquema = $usa_ssl_implicito ? 'ssl://' : 'tcp://';

    $socket = @stream_socket_client($esquema . $smtp['host'] . ':' . $porta, $errno, $errstr, 15);
    if (!$socket) {
        return smtpFalha("Não foi possível conectar em {$smtp['host']}:{$porta} ($errstr). Confira host/porta e se a hospedagem libera essa porta de saída.");
    }
    stream_set_timeout($socket, 20);

    $lerResposta = function () use ($socket) {
        $resposta = '';
        while (($linha = fgets($socket, 1024)) !== false) {
            $resposta .= $linha;
            // Última linha de resposta multi-linha tem espaço na 4ª coluna ("250 "),
            // as anteriores têm hífen ("250-").
            if (!isset($linha[3]) || $linha[3] === ' ') {
                break;
            }
        }
        return $resposta;
    };
    // Manda o comando e confere se o código de resposta é um dos esperados.
    $passo = function (?string $cmd, array $esperados) use ($socket, $lerResposta) {
        if ($cmd !== null) {
            fwrite($socket, $cmd . "\r\n");
        }
        $resposta = $lerResposta();
        return [in_array((int) substr($resposta, 0, 3), $esperados, true), trim($resposta)];
    };
    $abortar = function (string $etapa, string $resposta) use ($socket) {
        @fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return smtpFalha("SMTP recusou em {$etapa}: " . ($resposta !== '' ? $resposta : 'sem resposta (tempo esgotado)'));
    };

    [$ok, $r] = $passo(null, [220]);
    if (!$ok) { return $abortar('a saudação', $r); }

    // EHLO precisa de um nome de host válido -- HTTP_HOST pode trazer ":porta".
    $ehlo = preg_replace('/[^A-Za-z0-9.-]/', '', explode(':', dominioAtual())[0]) ?: 'localhost';

    [$ok, $r] = $passo('EHLO ' . $ehlo, [250]);
    if (!$ok) { return $abortar('EHLO', $r); }

    if (!$usa_ssl_implicito) {
        [$ok, $r] = $passo('STARTTLS', [220]);
        if ($ok) {
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return smtpFalha('Falha ao negociar TLS (STARTTLS) com o servidor SMTP.');
            }
            [$ok, $r] = $passo('EHLO ' . $ehlo, [250]);
            if (!$ok) { return $abortar('EHLO após TLS', $r); }
        }
    }

    [$ok, $r] = $passo('AUTH LOGIN', [334]);
    if (!$ok) { return $abortar('AUTH LOGIN', $r); }
    [$ok, $r] = $passo(base64_encode($smtp['user']), [334]);
    if (!$ok) { return $abortar('o usuário SMTP', $r); }
    [$ok, $r] = $passo(base64_encode($smtp['pass']), [235]);
    if (!$ok) { return $abortar('a senha SMTP (usuário/senha recusados; no Gmail use "senha de app")', $r); }

    [$ok, $r] = $passo("MAIL FROM:<{$remetente}>", [250]);
    if (!$ok) { return $abortar('o remetente (MAIL FROM)', $r); }
    [$ok, $r] = $passo("RCPT TO:<{$destinatario}>", [250, 251]);
    if (!$ok) { return $abortar('o destinatário (RCPT TO)', $r); }
    [$ok, $r] = $passo('DATA', [354]);
    if (!$ok) { return $abortar('DATA', $r); }

    if ($corpoTexto === null) {
        // Fallback automático: HTML -> texto puro (quebra de linha nos <br>/</p>, tags removidas)
        $corpoTexto = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>|<\/div>/i', "\n", $corpoHtml)), ENT_QUOTES, 'UTF-8'));
        $corpoTexto = preg_replace("/[ \t]+/", ' ', $corpoTexto);
        $corpoTexto = preg_replace("/\n\s*\n\s*/", "\n\n", $corpoTexto);
    }

    $codificar = fn (string $texto) => '=?UTF-8?B?' . base64_encode($texto) . '?=';
    $base64 = fn (string $texto) => rtrim(chunk_split(base64_encode($texto), 76, "\r\n"));
    $dominioRemetente = substr(strrchr($remetente, '@'), 1);
    $boundary = 'clube_' . bin2hex(random_bytes(12));

    // multipart/alternative: texto puro + HTML na mesma mensagem.
    $cabecalhos = implode("\r\n", [
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $dominioRemetente . '>',
        'From: ' . $codificar((string) $smtp['from_name']) . " <{$remetente}>",
        "To: <{$destinatario}>",
        'Subject: ' . $codificar($assunto),
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
    ]);

    $mensagem = "--{$boundary}\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . $base64($corpoTexto) . "\r\n"
              . "--{$boundary}\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . $base64($corpoHtml) . "\r\n"
              . "--{$boundary}--";

    // Base64 nunca começa linha com "." nem tem LF solto, então não precisa escapar.
    [$ok, $r] = $passo($cabecalhos . "\r\n\r\n" . $mensagem . "\r\n.", [250]);
    if (!$ok) { return $abortar('o envio da mensagem', $r); }

    @fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return ['success' => true, 'message' => 'E-mail enviado.'];
}

function smtpFalha(string $mensagem): array
{
    error_log('[SMTP] ' . $mensagem);
    return ['success' => false, 'message' => $mensagem];
}
