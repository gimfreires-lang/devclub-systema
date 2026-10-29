<?php
/**
 * Tela de pagamento Pix quando o Pix é gerado direto pelo Bradesco (com o
 * Mercado Pago, quem mostra essa tela é o próprio MP). Serve pra pedido da
 * loja (?id_venda=) e pra pagamento de dívida (?id_movimento=).
 * Fica perguntando ao servidor a cada poucos segundos; o servidor consulta o
 * Bradesco e confirma sozinho.
 */
require_once __DIR__ . '/../conecta_bd.php';
require_once __DIR__ . '/../includes/auth_cliente.php';
require_once __DIR__ . '/../includes/loja.php';
require_once __DIR__ . '/../includes/bradesco_pix.php';
exigirClienteLogado();

$id_cliente = (int) $_SESSION['id_cliente'];
$id_venda = (int) ($_GET['id_venda'] ?? 0);
$id_movimento = (int) ($_GET['id_movimento'] ?? 0);

if ($id_venda > 0) {
    $stmt = $pdo->prepare("SELECT status, valor_total FROM vendas WHERE id_venda = :id AND id_cliente = :ic AND origem = 'loja'");
    $stmt->execute([':id' => $id_venda, ':ic' => $id_cliente]);
    $alvo = $stmt->fetch();
    if (!$alvo || $alvo['status'] !== 'Reservado') {
        header('Location: /loja/pedido_status.php?id_venda=' . $id_venda);
        exit;
    }
    $origem = 'loja';
    $idRef = $id_venda;
    $titulo = 'Pedido #' . $id_venda;
    $voltar = '/loja/pedido_status.php?id_venda=' . $id_venda;
} elseif ($id_movimento > 0) {
    $stmt = $pdo->prepare("SELECT status, valor FROM movimentos_credito WHERE id_movimento = :id AND id_cliente = :ic AND tipo = 'pagamento'");
    $stmt->execute([':id' => $id_movimento, ':ic' => $id_cliente]);
    $alvo = $stmt->fetch();
    if (!$alvo || $alvo['status'] !== 'Pendente') {
        header('Location: /loja/minha_divida.php');
        exit;
    }
    $origem = 'divida';
    $idRef = $id_movimento;
    $titulo = 'Pagamento da sua conta';
    $voltar = '/loja/minha_divida.php';
} else {
    header('Location: /loja/index.php');
    exit;
}

$stmtCob = $pdo->prepare('SELECT txid, valor, pix_copia_cola, qr_base64, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expira_em)) AS segundos FROM cobrancas_pix WHERE origem = :o AND id_referencia = :r ORDER BY criado_em DESC LIMIT 1');
$stmtCob->execute([':o' => $origem, ':r' => $idRef]);
$cobranca = $stmtCob->fetch();
if (!$cobranca) {
    header('Location: ' . $voltar);
    exit;
}
$h = fn($v) => htmlspecialchars((string) $v);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pagar com Pix</title></head>
<body>
<?php require __DIR__ . '/../includes/loja_header.php'; ?>
    <div class="page-title">
        <h1>Pagar com Pix</h1>
    </div>

    <div class="resumo-card" style="max-width:460px; margin:0 auto; text-align:center;">
        <p style="margin-top:0;"><?= $h($titulo) ?></p>
        <p class="produto-preco" style="margin:4px 0 12px;">R$ <?= number_format((float) $cobranca['valor'], 2, ',', '.') ?></p>

        <div id="pix-qr" style="display:flex; justify-content:center; min-height:220px; align-items:center;">
            <?php if ($cobranca['qr_base64']): ?>
            <img src="data:image/png;base64,<?= $h($cobranca['qr_base64']) ?>" width="240" height="240" alt="QR Code do Pix">
            <?php else: ?>
            <p style="color:var(--cor-texto-suave);">Use o código abaixo no app do seu banco (Pix → Copia e cola).</p>
            <?php endif; ?>
        </div>

        <label style="text-align:left;">Pix copia e cola
            <textarea id="pix-codigo" readonly rows="3" style="width:100%; font-size:0.8rem;"><?= $h($cobranca['pix_copia_cola']) ?></textarea>
        </label>
        <button type="button" id="btn-copiar" class="btn-lg btn-bloco">Copiar código Pix</button>

        <div class="timer-card" id="timer-card" style="justify-content:center; margin-top:16px;">
            <span class="relogio" id="contagem">--:--</span>
            <span class="texto">para pagar</span>
        </div>
        <p id="status-pix" style="font-weight:600;">Aguardando pagamento… a confirmação aparece aqui sozinha.</p>
        <p style="color:var(--cor-texto-suave); font-size:0.85rem;">Abra o app do seu banco, escolha Pix, e leia o QR Code ou cole o código.</p>
        <a href="<?= $h($voltar) ?>" class="btn-outline">Voltar</a>
    </div>

<script>
(function () {
    const tipo = <?= json_encode($origem) ?>;
    const id = <?= (int) $idRef ?>;
    let restante = <?= (int) $cobranca['segundos'] ?>;
    const rotulo = document.getElementById('contagem');
    const statusEl = document.getElementById('status-pix');

    document.getElementById('btn-copiar').addEventListener('click', function () {
        const campo = document.getElementById('pix-codigo');
        const botao = this;
        const ok = function () { botao.textContent = 'Código copiado!'; setTimeout(function () { botao.textContent = 'Copiar código Pix'; }, 2500); };
        if (navigator.clipboard) {
            navigator.clipboard.writeText(campo.value).then(ok, function () { campo.select(); document.execCommand('copy'); ok(); });
        } else {
            campo.select(); document.execCommand('copy'); ok();
        }
    });

    function tick() {
        const m = Math.floor(restante / 60), s = restante % 60;
        rotulo.textContent = m + ':' + String(s).padStart(2, '0');
        document.getElementById('timer-card').classList.toggle('urgente', restante <= 60);
        if (restante > 0) { restante--; }
    }
    tick();
    setInterval(tick, 1000);

    function verificar() {
        fetch('/loja/ajax/status_pix.php?tipo=' + tipo + '&id=' + id, {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.pago) {
                    statusEl.textContent = 'Pagamento confirmado! Redirecionando…';
                    window.location.href = d.redirect;
                } else if (d.encerrado) {
                    window.location.href = d.redirect;
                }
            })
            .catch(function () {});
    }
    // Continua conferindo um pouco depois do fim do prazo: um Pix pago no
    // último segundo ainda é confirmado (o pedido é reaberto se houver estoque).
    setInterval(verificar, 5000);
})();
</script>
</main>
<?php require __DIR__ . '/../includes/loja_footer.php'; ?>
</body>
</html>
