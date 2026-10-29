<?php
/**
 * Configurações → Pix Bradesco. Começa em "Mercado Pago" (nada muda na loja).
 * Quando o cliente tiver a API Pix do Bradesco liberada, preenche os dados,
 * testa e só então troca o provedor.
 */
require_once __DIR__ . '/../conecta_bd.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bradesco_pix.php';
exigirAdmin();

$sucesso = '';
$erro = '';
$pixTeste = null;
$config = bradescoConfig($pdo);

if ($config && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    try {
        if ($acao === 'salvar') {
            $provedor = ($_POST['provedor_pix'] ?? '') === 'bradesco' ? 'bradesco' : 'mercadopago';
            $ambiente = ($_POST['ambiente'] ?? '') === 'producao' ? 'producao' : 'homologacao';
            $clientId = trim($_POST['client_id'] ?? '');
            $chave = trim($_POST['chave_pix'] ?? '');
            $secretNovo = trim($_POST['client_secret'] ?? '');

            $mudouCredencial = $ambiente !== $config['ambiente'] || $clientId !== (string) $config['client_id'] || $secretNovo !== '';
            $pdo->prepare(
                'UPDATE config_bradesco SET ambiente = :amb, client_id = :cid, chave_pix = :chave, atualizado_em = NOW()'
                . ($secretNovo !== '' ? ', client_secret = :sec' : '')
                . ($mudouCredencial ? ', access_token = NULL, token_expira = NULL' : '')
                . ' WHERE id_config = 1'
            )->execute(array_merge(
                [':amb' => $ambiente, ':cid' => $clientId ?: null, ':chave' => $chave ?: null],
                $secretNovo !== '' ? [':sec' => $secretNovo] : []
            ));
            $config = bradescoConfig($pdo);

            if ($provedor === 'bradesco') {
                // Só liga se der pra pegar um token de verdade -- assim a loja
                // nunca fica sem Pix por causa de credencial errada.
                $cert = bradescoCertificado();
                if (isset($cert['erro'])) {
                    throw new RuntimeException('Dados salvos, mas o Pix continua no Mercado Pago: ' . $cert['erro']);
                }
                if (empty($config['chave_pix'])) {
                    throw new RuntimeException('Dados salvos, mas o Pix continua no Mercado Pago: falta a chave Pix.');
                }
                bradescoToken($pdo, $config, true);
            }
            $pdo->prepare('UPDATE config_bradesco SET provedor_pix = :p WHERE id_config = 1')->execute([':p' => $provedor]);
            $sucesso = $provedor === 'bradesco'
                ? 'Salvo. A partir de agora os Pix da loja, do caixa e das dívidas são gerados pelo Bradesco.'
                : 'Salvo. Os Pix continuam sendo gerados pelo Mercado Pago.';
        } elseif ($acao === 'testar') {
            bradescoToken($pdo, $config, true);
            $sucesso = 'Conexão com o Bradesco funcionando (certificado e credenciais aceitos).';
        } elseif ($acao === 'webhook') {
            $resposta = bradescoCadastrarWebhook($pdo, bradescoUrlWebhook($pdo));
            if ($resposta['http_code'] >= 300) {
                throw new RuntimeException('Bradesco recusou o webhook: ' . bradescoMensagemErro($resposta) . '. Os pagamentos continuam sendo confirmados pela consulta automática da tela de pagamento.');
            }
            $sucesso = 'Webhook cadastrado no Bradesco.';
        } elseif ($acao === 'pix_teste') {
            // Cobrança avulsa de R$ 1,00 (origem "teste"): não mexe em pedido nenhum.
            $pixTeste = bradescoCriarCobranca($pdo, 'teste', 0, 1.00, 900, 'Teste de integração');
            $sucesso = 'Pix de teste gerado (vale 15 minutos).';
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
    $config = bradescoConfig($pdo);
}

$cert = $config ? bradescoCertificado() : null;
$h = fn($v) => htmlspecialchars((string) $v);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pix Bradesco</title></head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
    <div class="page-title">
        <span class="icone-titulo"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18M12 3l9 5H3l9-5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></span>
        <div>
            <h1>Pix Bradesco</h1>
            <span class="subtitulo">Receber Pix direto na conta Bradesco, sem Mercado Pago</span>
        </div>
    </div>

    <?php if (!$config): ?>
    <p class="alert alert-erro">Para usar esta tela, cole o arquivo <strong>sql/schema_bradesco_pix.sql</strong> na aba SQL do phpMyAdmin e execute.</p>
    <?php else: ?>

    <?php if ($sucesso): ?><p class="alert alert-sucesso"><?= $h($sucesso) ?></p><?php endif; ?>
    <?php if ($erro): ?><p class="alert alert-erro"><?= $h($erro) ?></p><?php endif; ?>

    <?php if ($pixTeste): ?>
    <div class="card" style="max-width:560px; text-align:center;">
        <h2>Pix de teste — R$ 1,00</h2>
        <?php if ($pixTeste['qr_code_base64']): ?><img src="data:image/png;base64,<?= $h($pixTeste['qr_code_base64']) ?>" width="220" alt="QR Code Pix"><?php endif; ?>
        <textarea readonly style="width:100%; margin-top:10px;"><?= $h($pixTeste['qr_code']) ?></textarea>
        <p style="color:var(--cor-texto-suave); font-size:0.85rem;">txid <?= $h($pixTeste['txid']) ?>. Em homologação, pague pelo simulador do Bradesco.</p>
    </div>
    <?php endif; ?>

    <div class="card" style="max-width:560px;">
        <p>
            Pix sendo gerado por:
            <span class="status-pill<?= $config['provedor_pix'] === 'bradesco' ? ' sucesso' : '' ?>"><?= $config['provedor_pix'] === 'bradesco' ? 'Bradesco' : 'Mercado Pago' ?></span>
        </p>
        <p style="color:var(--cor-texto-suave); font-size:0.85rem;">
            Com o Bradesco ligado, a loja online aceita <strong>só Pix</strong> (cartão no site depende de uma credenciadora, como a Cielo).
            O crédito da loja continua igual, e a maquininha do caixa continua como está.
        </p>

        <form method="post">
            <input type="hidden" name="acao" value="salvar">
            <label>Quem gera os Pix
                <select name="provedor_pix">
                    <option value="mercadopago"<?= $config['provedor_pix'] !== 'bradesco' ? ' selected' : '' ?>>Mercado Pago (atual)</option>
                    <option value="bradesco"<?= $config['provedor_pix'] === 'bradesco' ? ' selected' : '' ?>>Bradesco</option>
                </select>
            </label>
            <label>Ambiente
                <select name="ambiente">
                    <option value="homologacao"<?= $config['ambiente'] !== 'producao' ? ' selected' : '' ?>>Homologação (testes do Bradesco)</option>
                    <option value="producao"<?= $config['ambiente'] === 'producao' ? ' selected' : '' ?>>Produção (dinheiro de verdade)</option>
                </select>
            </label>
            <label>Chave Pix da conta Bradesco
                <input type="text" name="chave_pix" value="<?= $h($config['chave_pix']) ?>" placeholder="CNPJ, e-mail, telefone ou chave aleatória">
            </label>
            <label>Client ID
                <input type="text" name="client_id" value="<?= $h($config['client_id']) ?>" autocomplete="off">
            </label>
            <label>Client Secret
                <input type="password" name="client_secret" value="" autocomplete="new-password" placeholder="<?= getenv('BRADESCO_CLIENT_SECRET') ? 'Definido no Easypanel (BRADESCO_CLIENT_SECRET)' : (!empty($config['client_secret']) ? '•••••••• (deixe vazio para manter)' : '') ?>">
            </label>

            <p style="font-size:0.9rem;">
                <strong>Certificado digital:</strong>
                <?php if (isset($cert['erro'])): ?>
                    <span style="color:var(--cor-erro, #b00020);"><?= $h($cert['erro']) ?></span>
                <?php else: ?>
                    carregado<?= $cert['titular'] ? ' — ' . $h($cert['titular']) : '' ?><?= $cert['validade'] ? ', válido até ' . date('d/m/Y', strtotime($cert['validade'])) : '' ?>.
                <?php endif; ?>
            </p>
            <button type="submit" class="btn-bloco">Salvar</button>
        </form>

        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
            <form method="post"><input type="hidden" name="acao" value="testar"><button type="submit" class="btn-outline">Testar conexão</button></form>
            <form method="post"><input type="hidden" name="acao" value="pix_teste"><button type="submit" class="btn-outline">Gerar Pix de teste (R$ 1,00)</button></form>
            <form method="post"><input type="hidden" name="acao" value="webhook"><button type="submit" class="btn-outline">Cadastrar webhook</button></form>
        </div>
    </div>

    <div class="card" style="max-width:560px;">
        <h2>Passo a passo</h2>
        <ol style="padding-left:18px; line-height:1.6;">
            <li>O cliente pede ao gerente do Bradesco a liberação da <strong>API Pix</strong> para a conta PJ e recebe <strong>Client ID</strong>, <strong>Client Secret</strong> e o <strong>certificado digital (.pfx)</strong> — um para homologação e outro para produção.</li>
            <li>No Easypanel, em <em>Environment</em> do app, crie: <code>BRADESCO_CERT_PFX_BASE64</code> (o .pfx convertido em base64), <code>BRADESCO_CERT_SENHA</code> e, se preferir, <code>BRADESCO_CLIENT_SECRET</code>. Faça o deploy de novo.</li>
            <li>Aqui: ambiente <strong>Homologação</strong>, preencha chave Pix e Client ID, salve e clique em <strong>Testar conexão</strong> e <strong>Gerar Pix de teste</strong>.</li>
            <li>Clique em <strong>Cadastrar webhook</strong> (confirmação instantânea). Mesmo sem ele, a tela de pagamento confere sozinha a cada poucos segundos.</li>
            <li>Troque para <strong>Produção</strong> com as credenciais e o certificado de produção, teste de novo e mude "Quem gera os Pix" para <strong>Bradesco</strong>.</li>
            <li>Para voltar atrás a qualquer momento, escolha <strong>Mercado Pago</strong> e salve.</li>
        </ol>
        <p style="color:var(--cor-texto-suave); font-size:0.85rem;">Endereço do webhook: <code style="word-break:break-all;"><?= $h(preg_replace('/t=.*/', 't=•••', bradescoUrlWebhook($pdo))) ?></code></p>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
