<?php
/**
 * Pix direto na conta Bradesco (API Pix padrão Banco Central, "Pix Cobrança").
 *
 * PREPARADO, MAS DESLIGADO: enquanto Configurações → Pix Bradesco estiver em
 * "Mercado Pago", nada aqui é usado e o sistema segue 100% como antes.
 *
 * Como funciona quando ligado:
 *  - cada cobrança Pix (pedido da loja, venda no caixa, pagamento de dívida)
 *    vira um "cob" no Bradesco com um txid nosso e prazo de validade;
 *  - a confirmação NUNCA confia no que chega de fora: o webhook e as telas que
 *    ficam "aguardando pagamento" só servem de gatilho -- o sistema sempre
 *    consulta o Bradesco com as próprias credenciais (GET /v2/cob/{txid}) e só
 *    confirma se o status for CONCLUIDA;
 *  - a confirmação reaproveita as mesmas funções já usadas pelo Mercado Pago
 *    (processarWebhookVendaLoja, processarWebhookVendaCaixa,
 *    processarWebhookDivida), então estoque, crédito, alertas de "pagamento sem
 *    pedido" etc. funcionam igual nos dois provedores.
 *
 * Credenciais (nunca no GitHub). Variáveis de ambiente do Easypanel:
 *  - BRADESCO_CERT_PFX_BASE64: o certificado .pfx em base64 (ou BRADESCO_CERT_PATH
 *    com o caminho de um .pfx num volume fora da pasta pública);
 *  - BRADESCO_CERT_SENHA: senha do .pfx;
 *  - BRADESCO_CLIENT_SECRET: client secret (se não quiser guardar no banco).
 * Client ID, chave Pix e ambiente ficam na tela de configuração.
 *
 * Endereços da API (layout oficial do Bradesco): homologação
 * https://qrpix-h.bradesco.com.br e produção https://qrpix.bradesco.com.br.
 * BRADESCO_API_URL / BRADESCO_TOKEN_URL permitem trocar se o banco informar outro.
 */

const BRADESCO_URLS = [
    'homologacao' => 'https://qrpix-h.bradesco.com.br',
    'producao' => 'https://qrpix.bradesco.com.br',
];

/** Configuração gravada no banco, ou null se a tabela ainda não existe. */
function bradescoConfig(PDO $pdo): ?array
{
    try {
        $linha = $pdo->query('SELECT * FROM config_bradesco WHERE id_config = 1')->fetch();
        return $linha ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Quem gera os Pix: 'mercadopago' (padrão) ou 'bradesco'. Qualquer problema
 * de leitura cai no Mercado Pago, pra nunca derrubar o pagamento.
 */
function provedorPix(PDO $pdo): string
{
    $config = bradescoConfig($pdo);
    return ($config['provedor_pix'] ?? '') === 'bradesco' ? 'bradesco' : 'mercadopago';
}

function bradescoUrlBase(array $config): string
{
    $env = getenv('BRADESCO_API_URL');
    if ($env) {
        return rtrim($env, '/');
    }
    return BRADESCO_URLS[$config['ambiente'] ?? 'homologacao'] ?? BRADESCO_URLS['homologacao'];
}

function bradescoClientSecret(array $config): string
{
    $env = getenv('BRADESCO_CLIENT_SECRET');
    return $env !== false && $env !== '' ? $env : (string) ($config['client_secret'] ?? '');
}

/**
 * Converte o .pfx em arquivos PEM temporários (certificado + chave) que o cURL
 * usa no mTLS. Feito pelo PHP porque muitos .pfx ICP-Brasil usam criptografia
 * antiga que o cURL/OpenSSL 3 recusa ler direto.
 *
 * @return array{cert:string,key:string,validade:?string} | array{erro:string}
 */
function bradescoCertificado(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $pfx = null;
    $base64 = getenv('BRADESCO_CERT_PFX_BASE64');
    $caminho = getenv('BRADESCO_CERT_PATH');
    if ($base64) {
        $pfx = base64_decode(preg_replace('/\s+/', '', $base64), true);
    } elseif ($caminho && is_readable($caminho)) {
        $pfx = file_get_contents($caminho);
    }
    if (!$pfx) {
        return $cache = ['erro' => 'Certificado não configurado (variável BRADESCO_CERT_PFX_BASE64 no Easypanel).'];
    }

    $partes = [];
    if (!openssl_pkcs12_read($pfx, $partes, (string) getenv('BRADESCO_CERT_SENHA'))) {
        return $cache = ['erro' => 'Não foi possível abrir o certificado. Confira a senha (BRADESCO_CERT_SENHA). Se a senha estiver certa, o arquivo está num formato antigo: converta com "openssl pkcs12 -legacy -in cert.pfx -out novo.pfx -export" e use o novo.'];
    }

    $pasta = sys_get_temp_dir();
    $hash = substr(hash('sha256', $pfx), 0, 16);
    $arquivoCert = $pasta . '/bradesco_' . $hash . '_cert.pem';
    $arquivoChave = $pasta . '/bradesco_' . $hash . '_key.pem';
    if (!is_file($arquivoCert) || !is_file($arquivoChave)) {
        $intermediarios = implode("\n", $partes['extracerts'] ?? []);
        file_put_contents($arquivoCert, $partes['cert'] . "\n" . $intermediarios, LOCK_EX);
        file_put_contents($arquivoChave, $partes['pkey'], LOCK_EX);
        @chmod($arquivoCert, 0600);
        @chmod($arquivoChave, 0600);
    }

    $info = openssl_x509_parse($partes['cert']) ?: [];
    return $cache = [
        'cert' => $arquivoCert,
        'key' => $arquivoChave,
        'validade' => isset($info['validTo_time_t']) ? date('Y-m-d', (int) $info['validTo_time_t']) : null,
        'titular' => $info['subject']['CN'] ?? null,
    ];
}

/** Requisição HTTP com mTLS. */
function bradescoHttp(string $metodo, string $url, array $headers, ?string $corpo): array
{
    $cert = bradescoCertificado();
    if (isset($cert['erro'])) {
        throw new RuntimeException($cert['erro']);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSLCERT => $cert['cert'],
        CURLOPT_SSLKEY => $cert['key'],
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
    ]);
    // Só para testes com um servidor de homologação próprio (certificado de CA privada).
    if (getenv('BRADESCO_CA_BUNDLE')) {
        curl_setopt($ch, CURLOPT_CAINFO, getenv('BRADESCO_CA_BUNDLE'));
    }
    if ($corpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $corpo);
    }
    $resposta = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    if ($resposta === false) {
        throw new RuntimeException('Erro de conexão com o Bradesco: ' . $erro);
    }
    return ['http_code' => $http, 'dados' => json_decode($resposta, true), 'bruto' => $resposta];
}

/** Token OAuth (client_credentials), guardado no banco até perto de vencer. */
function bradescoToken(PDO $pdo, array $config, bool $forcarNovo = false): string
{
    if (!$forcarNovo && !empty($config['access_token']) && !empty($config['token_expira']) && strtotime($config['token_expira']) > time() + 60) {
        return $config['access_token'];
    }
    if (empty($config['client_id']) || bradescoClientSecret($config) === '') {
        throw new RuntimeException('Client ID / Client Secret do Bradesco não configurados.');
    }

    $url = getenv('BRADESCO_TOKEN_URL') ?: bradescoUrlBase($config) . '/oauth/token';
    $resposta = bradescoHttp('POST', $url, [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode($config['client_id'] . ':' . bradescoClientSecret($config)),
    ], http_build_query(['grant_type' => 'client_credentials']));

    $token = $resposta['dados']['access_token'] ?? null;
    if ($resposta['http_code'] >= 300 || !$token) {
        throw new RuntimeException('Bradesco recusou as credenciais (HTTP ' . $resposta['http_code'] . '). Confira Client ID, Client Secret e certificado.');
    }
    $expira = date('Y-m-d H:i:s', time() + (int) ($resposta['dados']['expires_in'] ?? 300));
    $pdo->prepare('UPDATE config_bradesco SET access_token = :t, token_expira = :e WHERE id_config = 1')
        ->execute([':t' => $token, ':e' => $expira]);
    return $token;
}

/** Chamada autenticada à API Pix (caminho já com /v2/...). Renova o token uma vez se vencer. */
function bradescoApi(PDO $pdo, string $metodo, string $caminho, ?array $payload = null): array
{
    $config = bradescoConfig($pdo);
    if (!$config) {
        throw new RuntimeException('Tabela config_bradesco não encontrada (importe sql/schema_bradesco_pix.sql).');
    }
    $corpo = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
    foreach ([false, true] as $forcarNovo) {
        $token = bradescoToken($pdo, $config, $forcarNovo);
        $resposta = bradescoHttp($metodo, bradescoUrlBase($config) . $caminho, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ], $corpo);
        if ($resposta['http_code'] !== 401) {
            return $resposta;
        }
    }
    return $resposta;
}

/** txid: 26 a 35 letras/números, único por CNPJ. Ex.: LOJA0000000123A1B2C3D4E5F6A7 */
function bradescoNovoTxid(string $origem, int $idReferencia): string
{
    return strtoupper(substr($origem, 0, 5)) . str_pad((string) $idReferencia, 10, '0', STR_PAD_LEFT) . strtoupper(bin2hex(random_bytes(7)));
}

/** Mensagem de erro legível a partir da resposta da API (padrão BCB: title/detail/violacoes). */
function bradescoMensagemErro(array $resposta): string
{
    $d = $resposta['dados'] ?? [];
    $msg = $d['detail'] ?? $d['title'] ?? $d['message'] ?? $d['mensagem'] ?? null;
    if (!empty($d['violacoes'][0]['razao'])) {
        $msg .= ' (' . $d['violacoes'][0]['razao'] . ')';
    }
    return $msg ?: 'resposta inesperada do Bradesco (HTTP ' . $resposta['http_code'] . ')';
}

/**
 * Cria uma cobrança Pix imediata no Bradesco e guarda em cobrancas_pix.
 * $origem: 'loja' (pedido online), 'venda' (caixa) ou 'divida' (movimento de crédito).
 *
 * @return array{txid:string,qr_code:string,qr_code_base64:?string}
 */
function bradescoCriarCobranca(PDO $pdo, string $origem, int $idReferencia, float $valor, int $expiraSegundos, string $descricao, ?array $devedor = null): array
{
    $config = bradescoConfig($pdo);
    if (empty($config['chave_pix'])) {
        throw new RuntimeException('Chave Pix da conta Bradesco não configurada.');
    }

    if (round($valor, 2) < 0.01) {
        throw new RuntimeException('Valor do Pix precisa ser maior que zero.');
    }

    $txid = bradescoNovoTxid($origem, $idReferencia);
    $payload = [
        'calendario' => ['expiracao' => max(60, $expiraSegundos)],
        'valor' => ['original' => number_format(round($valor, 2), 2, '.', '')],
        'chave' => $config['chave_pix'],
        'solicitacaoPagador' => mb_substr($descricao, 0, 140),
    ];
    // devedor exige CPF/CNPJ -- só manda se tiver documento válido.
    if (!empty($devedor['cpf']) || !empty($devedor['cnpj'])) {
        $payload['devedor'] = array_filter([
            'cpf' => $devedor['cpf'] ?? null,
            'cnpj' => $devedor['cnpj'] ?? null,
            'nome' => isset($devedor['nome']) ? mb_substr($devedor['nome'], 0, 200) : null,
        ]);
    }

    // /cob-emv devolve o "copia e cola" e a IMAGEM do QR Code prontos (layout
    // Bradesco). Se não existir no ambiente, cai pro /cob padrão.
    $resposta = bradescoApi($pdo, 'PUT', '/v2/cob-emv/' . $txid, $payload);
    if ($resposta['http_code'] === 404 || $resposta['http_code'] === 405) {
        $resposta = bradescoApi($pdo, 'PUT', '/v2/cob/' . $txid, $payload);
    }
    if ($resposta['http_code'] >= 300) {
        throw new RuntimeException('Erro ao gerar Pix no Bradesco: ' . bradescoMensagemErro($resposta));
    }

    $d = $resposta['dados'] ?? [];
    $copiaECola = $d['emv'] ?? $d['pixCopiaECola'] ?? $d['cob']['pixCopiaECola'] ?? null;
    $imagem = $d['base64'] ?? $d['imagemQrcode'] ?? null;
    if ($imagem && str_starts_with($imagem, 'data:image')) {
        $imagem = substr($imagem, strpos($imagem, ',') + 1);
    }
    if (!$copiaECola) {
        throw new RuntimeException('O Bradesco criou a cobrança mas não devolveu o código Pix.');
    }

    $pdo->prepare(
        'INSERT INTO cobrancas_pix (txid, provedor, origem, id_referencia, valor, status, pix_copia_cola, qr_base64, expira_em)
         VALUES (:txid, \'bradesco\', :origem, :ref, :valor, \'ATIVA\', :emv, :img, :expira)'
    )->execute([
        ':txid' => $txid,
        ':origem' => $origem,
        ':ref' => $idReferencia,
        ':valor' => round($valor, 2),
        ':emv' => $copiaECola,
        ':img' => $imagem,
        ':expira' => date('Y-m-d H:i:s', time() + max(60, $expiraSegundos)),
    ]);

    return ['txid' => $txid, 'qr_code' => $copiaECola, 'qr_code_base64' => $imagem];
}

function bradescoCobrancaPorTxid(PDO $pdo, string $txid): ?array
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM cobrancas_pix WHERE txid = :t');
        $stmt->execute([':t' => $txid]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Consulta a cobrança no Bradesco e, se estiver paga (CONCLUIDA), confirma no
 * sistema pelo mesmo caminho do Mercado Pago. Retorna true se está paga.
 * $minimoSegundosEntreConsultas evita martelar a API quando várias telas
 * ficam perguntando ao mesmo tempo.
 */
function bradescoConsultarEConfirmar(PDO $pdo, string $txid, int $minimoSegundosEntreConsultas = 0): bool
{
    $cobranca = bradescoCobrancaPorTxid($pdo, $txid);
    if (!$cobranca) {
        return false;
    }
    if ($cobranca['status'] === 'CONCLUIDA' && $cobranca['confirmado_em'] !== null) {
        return true;
    }
    if ($minimoSegundosEntreConsultas > 0 && $cobranca['consultado_em'] && strtotime($cobranca['consultado_em']) > time() - $minimoSegundosEntreConsultas) {
        return false;
    }
    $pdo->prepare('UPDATE cobrancas_pix SET consultado_em = NOW() WHERE txid = :t')->execute([':t' => $txid]);

    $resposta = bradescoApi($pdo, 'GET', '/v2/cob/' . $txid);
    if ($resposta['http_code'] >= 300) {
        error_log('Bradesco: falha ao consultar txid ' . $txid . ': ' . bradescoMensagemErro($resposta));
        return false;
    }
    $d = $resposta['dados'] ?? [];
    $status = (string) ($d['status'] ?? '');
    $pdo->prepare('UPDATE cobrancas_pix SET status = :s WHERE txid = :t')->execute([':s' => $status, ':t' => $txid]);

    if ($status !== 'CONCLUIDA') {
        return false;
    }

    // Valor efetivamente recebido (soma dos Pix da cobrança, descontadas devoluções).
    $valorPago = 0.0;
    $e2e = null;
    foreach ($d['pix'] ?? [] as $pix) {
        $valorPago += (float) ($pix['valor'] ?? 0);
        foreach ($pix['devolucoes'] ?? [] as $dev) {
            if (($dev['status'] ?? '') === 'DEVOLVIDO') {
                $valorPago -= (float) ($dev['valor'] ?? 0);
            }
        }
        $e2e = $e2e ?? ($pix['endToEndId'] ?? null);
    }
    if ($valorPago <= 0) {
        $valorPago = (float) ($d['valor']['original'] ?? $cobranca['valor']);
    }

    // Mesmo formato que as funções do Mercado Pago já entendem.
    $pagamento = ['status' => 'approved', 'transaction_amount' => round($valorPago, 2)];
    $id = (int) $cobranca['id_referencia'];
    switch ($cobranca['origem']) {
        case 'loja':
            require_once __DIR__ . '/loja.php';
            processarWebhookVendaLoja($pdo, $id, $pagamento, $txid, 'Pix Bradesco');
            break;
        case 'venda':
            require_once __DIR__ . '/caixa.php';
            processarWebhookVendaCaixa($pdo, $id, $pagamento, $txid);
            break;
        case 'divida':
            require_once __DIR__ . '/credito.php';
            processarWebhookDivida($pdo, $id, $pagamento, $txid);
            break;
    }

    $pdo->prepare('UPDATE cobrancas_pix SET confirmado_em = NOW(), e2e_id = :e WHERE txid = :t AND confirmado_em IS NULL')
        ->execute([':e' => $e2e, ':t' => $txid]);
    return true;
}

/** Cadastra no Bradesco o endereço do webhook pra chave Pix configurada. */
function bradescoCadastrarWebhook(PDO $pdo, string $urlWebhook): array
{
    $config = bradescoConfig($pdo);
    if (empty($config['chave_pix'])) {
        throw new RuntimeException('Configure a chave Pix antes.');
    }
    return bradescoApi($pdo, 'PUT', '/v2/webhook/' . rawurlencode($config['chave_pix']), ['webhookUrl' => $urlWebhook]);
}

/** Endereço do webhook com o token secreto (o Bradesco acrescenta "/pix" no fim). */
function bradescoUrlWebhook(PDO $pdo): string
{
    $config = bradescoConfig($pdo);
    $token = $config['webhook_token'] ?? '';
    if ($token === '') {
        $token = bin2hex(random_bytes(16));
        $pdo->prepare('UPDATE config_bradesco SET webhook_token = :t WHERE id_config = 1')->execute([':t' => $token]);
    }
    return urlBaseAtual() . '/integracoes/bradesco/webhook.php?t=' . $token;
}
