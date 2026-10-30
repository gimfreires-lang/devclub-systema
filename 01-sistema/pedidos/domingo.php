<?php
require_once __DIR__ . '/../conecta_bd.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedidos.php';
require_once __DIR__ . '/../includes/loja.php';
require_once __DIR__ . '/../includes/domingo.php';
require_once __DIR__ . '/../includes/avisos_whatsapp.php';
exigirLogin();

/*
 * Painel do domingo: produção de cada carne e lista de quem retira em cada
 * horário, com os botões "Marcar pronto e avisar" e "Marcar retirado"
 * (usam o mesmo pedidos/ajax/atualizar_status.php da tela do pedido).
 */

try {
    domingoTarefasAutomaticas($pdo);
} catch (Throwable $e) {
    error_log('pedidos/domingo.php: ' . $e->getMessage());
}

$cfg = domingoConfigBruta($pdo);
if (!$cfg) {
    $semInstalacao = true;
} else {
    $semInstalacao = false;

    // Domingos para escolher: os que já têm pedido (últimas 2 semanas em diante) + os abertos.
    $datas = $pdo->query(
        "SELECT DISTINCT retirada_data FROM vendas
         WHERE origem = 'loja' AND retirada_data IS NOT NULL AND retirada_data >= CURDATE() - INTERVAL 14 DAY
           AND status IN ('Pago', 'Reservado') ORDER BY retirada_data"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach (domingosDisponiveis($pdo) as $d) {
        $datas[] = $d['data'];
    }
    $agora = domingoAgora();
    $diasAteDomingo = (7 - (int) $agora->format('w')) % 7;
    $proximo = $agora->setTime(0, 0)->modify("+{$diasAteDomingo} days")->format('Y-m-d');
    $datas[] = $proximo;
    $datas = array_values(array_unique($datas));
    sort($datas);

    $data = $_GET['data'] ?? $proximo;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        $data = $proximo;
    }
    if (!in_array($data, $datas, true)) {
        $datas[] = $data;
        sort($datas);
    }

    $producao = domingoProducao($pdo, $data);
    $corte = domingoCorte($data, $cfg);
    $encomendasAbertas = $corte > $agora;

    $stmt = $pdo->prepare(
        "SELECT v.id_venda, v.valor_total, v.status, v.status_entrega, v.forma_pagamento, v.retirada_hora,
                v.pagamento_expira_em, c.nome AS cliente_nome, c.whatsapp AS cliente_whatsapp
         FROM vendas v
         LEFT JOIN clientes c ON c.id_cliente = v.id_cliente
         WHERE v.origem = 'loja' AND v.retirada_data = :d AND " . domingoCondicaoPedidoAtivo('v') . "
         ORDER BY v.retirada_hora, v.id_venda"
    );
    $stmt->execute([':d' => $data]);
    $pedidos = $stmt->fetchAll();

    $itensPorVenda = [];
    if ($pedidos) {
        $ids = array_map('intval', array_column($pedidos, 'id_venda'));
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmtItens = $pdo->prepare("SELECT id_venda, nome_produto, quantidade FROM itens_venda WHERE id_venda IN ($ph) AND NOT (id_produto_variacao IS NULL AND nome_produto = 'Entrega') ORDER BY id_item");
        $stmtItens->execute($ids);
        foreach ($stmtItens->fetchAll() as $it) {
            $itensPorVenda[(int) $it['id_venda']][] = (int) $it['quantidade'] . 'x ' . $it['nome_produto'];
        }
    }

    $porHora = [];
    $totalValor = 0.0;
    foreach ($pedidos as $p) {
        $porHora[domingoRotuloHora($p['retirada_hora'])][] = $p;
        $totalValor += (float) $p['valor_total'];
    }
    $vagasPorHora = [];
    foreach (domingoHorarios($pdo, $data) as $h) {
        $vagasPorHora[$h['hora']] = $h;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Domingo — encomendas de carne</title>
<style>
@media print { .site-header, .nao-imprimir { display: none !important; } .card { box-shadow: none; border: 1px solid #ccc; } }
.domingo-totais { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 20px; }
.domingo-totais .card { margin: 0; }
.domingo-totais .numero { font-size: 1.8rem; font-weight: 700; line-height: 1.1; }
.domingo-hora { margin-top: 18px; }
.domingo-hora h3 { margin-bottom: 8px; }
.domingo-pedido { display: flex; flex-wrap: wrap; gap: 10px 14px; align-items: center; padding: 12px; border: 1px solid rgba(127,127,127,.25); border-radius: 12px; margin-bottom: 8px; }
.domingo-pedido .quem { flex: 1 1 260px; min-width: 0; }
.domingo-pedido .itens { font-size: .9rem; opacity: .8; }
.domingo-pedido .acoes { display: flex; gap: 8px; flex-wrap: wrap; }
.domingo-msg { flex-basis: 100%; font-size: .85rem; }
</style>
</head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
    <div class="page-title">
        <span class="icone-titulo"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M16 2v4M8 2v4M3 10h18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
        <div>
            <h1>Encomendas de domingo</h1>
            <span class="subtitulo">Produção das carnes e retiradas por horário</span>
        </div>
    </div>

    <?php if ($semInstalacao): ?>
        <p class="alert alert-erro">O agendamento de domingo ainda não foi instalado no banco. Importe o arquivo <strong>sql/schema_domingo_2026_10.sql</strong> no phpMyAdmin.</p>
    <?php else: ?>

    <form method="get" class="busca-lista nao-imprimir" style="margin-bottom:16px;">
        <label style="flex:1;">Domingo
            <select name="data" onchange="this.form.submit()">
                <?php foreach ($datas as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= $d === $data ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst(domingoRotuloData($d))) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="button" class="btn-outline" onclick="window.print()">Imprimir lista</button>
    </form>

    <?php if (!(int) $cfg['ativo']): ?>
        <p class="alert alert-info">O agendamento está desligado em Configurações &gt; Domingo e avisos. Os clientes não estão escolhendo horário.</p>
    <?php endif; ?>

    <div class="domingo-totais">
        <div class="card"><div>Pedidos</div><div class="numero"><?= count($pedidos) ?></div></div>
        <div class="card"><div>Valor encomendado</div><div class="numero">R$ <?= number_format($totalValor, 2, ',', '.') ?></div></div>
        <div class="card"><div><?= $encomendasAbertas ? 'Encomendas fecham' : 'Encomendas fecharam' ?></div><div class="numero" style="font-size:1.2rem;"><?= htmlspecialchars(ucfirst(domingoRotuloCorte($corte))) ?></div></div>
    </div>

    <div class="card">
        <h3>Produção — <?= htmlspecialchars(domingoRotuloData($data)) ?></h3>
        <?php if (empty($producao)): ?>
            <p class="alert alert-info">Nenhuma carne cadastrada na categoria do domingo.</p>
        <?php else: ?>
        <div class="tabela-wrap">
        <table>
            <tr><th>Carne</th><th>Encomendado</th><th>Limite do domingo</th><th>Ainda vende</th></tr>
            <?php foreach ($producao as $c): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
                <td><?= (int) $c['encomendado'] ?></td>
                <td><?= $c['limite'] !== null ? (int) $c['limite'] : 'sem limite' ?></td>
                <td><?= $c['restante'] !== null ? (int) $c['restante'] : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
        <p style="font-size:.85rem; opacity:.75; margin-top:8px;">Conta pedidos pagos, na conta aberta e os que estão pagando agora no Mercado Pago.</p>
        <?php endif; ?>
    </div>

    <div class="card" style="margin-top:20px;">
        <h3>Retiradas por horário</h3>
        <?php if (empty($pedidos)): ?>
            <p class="alert alert-info">Nenhum pedido para este domingo ainda.</p>
        <?php endif; ?>
        <?php foreach ($porHora as $hora => $lista): ?>
        <div class="domingo-hora">
            <h3><?= htmlspecialchars($hora) ?>
                <?php if (isset($vagasPorHora[$hora])): ?>
                <small style="font-weight:500; opacity:.7;">· <?= (int) $vagasPorHora[$hora]['ocupados'] ?> de <?= (int) $vagasPorHora[$hora]['max'] ?> vagas</small>
                <?php endif; ?>
            </h3>
            <?php foreach ($lista as $p): ?>
            <?php
                $aguardandoPagamento = $p['status'] === 'Reservado';
                $rotulo = $aguardandoPagamento ? 'Aguardando pagamento' : rotuloStatusPedido($p['status'], $p['status_entrega'], 'retirada', $p['forma_pagamento']);
                $classe = $aguardandoPagamento ? 'alerta' : classePillStatusPedido($p['status'], $p['status_entrega']);
            ?>
            <div class="domingo-pedido" data-venda="<?= (int) $p['id_venda'] ?>">
                <div class="quem">
                    <div><a href="/pedidos/detalhe.php?id_venda=<?= (int) $p['id_venda'] ?>"><strong>#<?= (int) $p['id_venda'] ?></strong></a> · <?= htmlspecialchars($p['cliente_nome'] ?? '—') ?>
                        <?php if (!empty($p['cliente_whatsapp'])): ?> · <a href="https://wa.me/<?= htmlspecialchars(preg_replace('/\D+/', '', $p['cliente_whatsapp'])) ?>" target="_blank" rel="noopener" class="nao-imprimir">WhatsApp</a><?php endif; ?>
                    </div>
                    <div class="itens"><?= htmlspecialchars(implode(' · ', $itensPorVenda[(int) $p['id_venda']] ?? [])) ?> · R$ <?= number_format($p['valor_total'], 2, ',', '.') ?> · <?= htmlspecialchars($p['forma_pagamento'] === 'Linha de Crédito' ? 'conta aberta' : ($p['forma_pagamento'] ?: 'a pagar')) ?></div>
                </div>
                <span class="status-pill <?= htmlspecialchars($classe) ?>"><?= htmlspecialchars($rotulo) ?></span>
                <?php if (!$aguardandoPagamento): ?>
                <div class="acoes nao-imprimir">
                    <?php if (!in_array($p['status_entrega'], ['Pronto', 'Entregue'], true)): ?>
                    <button type="button" class="btn-sm js-status" data-status="Pronto">Marcar pronto e avisar</button>
                    <?php endif; ?>
                    <?php if ($p['status_entrega'] !== 'Entregue'): ?>
                    <button type="button" class="btn-sm btn-outline js-status" data-status="Entregue">Marcar retirado</button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="domingo-msg" aria-live="polite"></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<script>
document.querySelectorAll('.js-status').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const linha = btn.closest('.domingo-pedido');
        const msg = linha.querySelector('.domingo-msg');
        linha.querySelectorAll('.js-status').forEach(b => b.disabled = true);
        fetch('/pedidos/ajax/atualizar_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'id_venda=' + linha.dataset.venda + '&status_entrega=' + encodeURIComponent(btn.dataset.status)
        }).then(r => r.json()).then(function (data) {
            msg.textContent = data.message;
            if (data.success) { setTimeout(function () { window.location.reload(); }, 2500); }
            else { linha.querySelectorAll('.js-status').forEach(b => b.disabled = false); }
        }).catch(function () {
            msg.textContent = 'Erro de conexão. Tente de novo.';
            linha.querySelectorAll('.js-status').forEach(b => b.disabled = false);
        });
    });
});
</script>
</main>
</body>
</html>
