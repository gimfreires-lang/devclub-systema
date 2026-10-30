<?php
require_once __DIR__ . '/../conecta_bd.php';
require_once __DIR__ . '/../includes/auth_cliente.php';
require_once __DIR__ . '/../includes/loja.php';
require_once __DIR__ . '/../includes/superfrete.php';
require_once __DIR__ . '/../includes/bradesco_pix.php';
require_once __DIR__ . '/../includes/domingo.php';
exigirClienteLogado();

liberarReservasExpiradas($pdo);

$id_cliente = (int) $_SESSION['id_cliente'];
$id_venda = buscarCarrinhoDoCliente($pdo, $id_cliente);

if (!$id_venda) {
    header('Location: /loja/carrinho.php');
    exit;
}

// Mesmo cronômetro do carrinho — some vira "Ir para o checkout" de mentirinha
// se o cliente entra aqui mas o prazo continua correndo por trás; precisa
// continuar visível e valendo até ele realmente clicar em pagar.
$stmtV = $pdo->prepare(
    "SELECT v.valor_total, v.data_venda, cl.prazo_reserva_minutos,
            GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(v.data_venda, INTERVAL cl.prazo_reserva_minutos MINUTE))) AS segundos_restantes
     FROM vendas v
     JOIN config_loja cl ON cl.id_config = 1
     WHERE v.id_venda = :id"
);
$stmtV->execute([':id' => $id_venda]);
$venda = $stmtV->fetch();

// prazo_reserva_minutos = 0 é o "carrinho livre" (ver loja/carrinho.php) — sem
// isso a conta do cronômetro dava 0 segundos restantes (data_venda + 0
// minutos já passou) e a página achava, assim que abria, que o tempo tinha
// acabado, redirecionando o cliente de volta pro catálogo antes até de ele
// conseguir pagar. buscarCarrinhoDoCliente() só encontra vendas que ainda
// não foram pro Mercado Pago (pagamento_expira_em NULL), então esta tela
// sempre trata de um carrinho ainda em montagem, nunca de um pagamento já em
// andamento — esse caso agora vive em loja/pedido_status.php, aberto a
// partir de "Meus pedidos".
$carrinhoLivre = (int) $venda['prazo_reserva_minutos'] === 0;

$stmtIds = $pdo->prepare('SELECT DISTINCT pv.id_produto FROM itens_venda iv JOIN produto_variacoes pv ON pv.id_produto_variacao = iv.id_produto_variacao WHERE iv.id_venda = :id');
$stmtIds->execute([':id' => $id_venda]);
$idsProdutosCheckout = array_map('intval', $stmtIds->fetchAll(PDO::FETCH_COLUMN));

$stmtCliente = $pdo->prepare('SELECT nome, email, endereco, limite_credito, saldo_devedor FROM clientes WHERE id_cliente = :id');
$stmtCliente->execute([':id' => $id_cliente]);
$cliente = $stmtCliente->fetch();

$creditoDisponivel = (float) $cliente['limite_credito'] - (float) $cliente['saldo_devedor'];
$temLimiteCredito = (float) $cliente['limite_credito'] > 0;

$superfreteOn = superfreteAtivo($pdo);
$formasEntrega = $pdo->query('SELECT id_entrega, nome, tipo, prazo_dias, custo, superfrete FROM formas_entrega WHERE ativo = 1' . ($superfreteOn ? '' : ' AND superfrete = 0') . ' ORDER BY fixa DESC, nome')->fetchAll();

// Total só dos itens (sem a linha de "Entrega", que ainda não foi escolhida ou pode
// mudar) — é a base que o JS soma ao custo da entrega selecionada, pra o Total
// exibido acompanhar a escolha na hora, sem esperar o formulário ser enviado.
$stmtItensSubtotal = $pdo->prepare(
    "SELECT COALESCE(SUM(subtotal), 0) FROM itens_venda
     WHERE id_venda = :id AND NOT (id_produto_variacao IS NULL AND nome_produto = 'Entrega')"
);
$stmtItensSubtotal->execute([':id' => $id_venda]);
$itensSubtotal = (float) $stmtItensSubtotal->fetchColumn();

$erro = $_GET['erro'] ?? '';
$pixBradesco = provedorPix($pdo) === 'bradesco';

// Carne do domingo: o cliente escolhe qual domingo e o horário de retirada.
$temCarneDomingo = vendaTemCarneDomingo($pdo, $id_venda);
$opcoesDomingo = $temCarneDomingo ? domingoOpcoes($pdo, $id_venda) : [];
$retiradaAtual = ['data' => '', 'hora' => ''];
if ($temCarneDomingo) {
    $stmtRet = $pdo->prepare('SELECT retirada_data, retirada_hora FROM vendas WHERE id_venda = :id');
    $stmtRet->execute([':id' => $id_venda]);
    $r = $stmtRet->fetch() ?: [];
    $retiradaAtual = ['data' => (string) ($r['retirada_data'] ?? ''), 'hora' => domingoRotuloHora($r['retirada_hora'] ?? '')];
    $datasValidas = array_column($opcoesDomingo, 'data');
    if (!in_array($retiradaAtual['data'], $datasValidas, true)) {
        $retiradaAtual = ['data' => $datasValidas[0] ?? '', 'hora' => ''];
    }
}
$semDomingoDisponivel = $temCarneDomingo && empty($opcoesDomingo);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Checkout</title></head>
<body>
<?php require __DIR__ . '/../includes/loja_header.php'; ?>
    <div class="page-title">
        <span class="icone-titulo"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.5 14.5-4-4 1.4-1.4 2.6 2.6 6.6-6.6 1.4 1.4Z" fill="currentColor"/></svg></span>
        <h1>Checkout</h1>
    </div>
    <?php if ($erro): ?><p class="alert alert-erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

    <?php if (!$carrinhoLivre): ?>
    <div class="timer-card" id="timer-card">
        <svg class="icon" style="width:1.6rem; height:1.6rem;" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm-1 3v6l5 3 1-1.6-4-2.4V7Z"/></svg>
        <span class="relogio" id="contagem">--:--</span>
        <span class="texto">Tempo pra pagar antes dos itens voltarem pro estoque</span>
    </div>
    <?php endif; ?>

    <form method="post" id="form-checkout" action="/loja/ajax/gerar_checkout.php">
    <div class="layout-colunas">
        <div>
            <?php if ($temCarneDomingo): ?>
            <div class="resumo-card domingo-bloco" id="domingo-bloco">
                <div class="domingo-cabecalho">
                    <span class="domingo-icone" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 2v4M8 2v4M3 10h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
                    <div>
                        <h2>Retirada da carne do domingo</h2>
                        <p>Seu pedido tem carne assada. Escolha quando vai buscar.</p>
                    </div>
                </div>

                <?php if ($semDomingoDisponivel): ?>
                <p class="alert alert-erro">As encomendas de carne estão fechadas no momento. Tire a carne do carrinho para comprar o restante, ou fale com a loja pelo WhatsApp.</p>
                <?php else: ?>
                <fieldset class="domingo-grupo">
                    <legend>Qual domingo?</legend>
                    <div class="domingo-opcoes domingo-opcoes-datas">
                        <?php foreach ($opcoesDomingo as $i => $d): ?>
                        <label class="domingo-opcao">
                            <input type="radio" name="retirada_data" value="<?= htmlspecialchars($d['data']) ?>" data-corte="<?= htmlspecialchars($d['rotulo_corte']) ?>" data-rotulo="<?= htmlspecialchars($d['rotulo']) ?>" <?= $d['data'] === $retiradaAtual['data'] ? 'checked' : '' ?> required>
                            <span class="domingo-opcao-texto"><strong><?= htmlspecialchars(ucfirst($d['rotulo'])) ?></strong><small><?= $i === 0 ? 'Mais próximo' : 'Seguinte' ?></small></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="domingo-aviso">Encomendas para este domingo até <strong id="domingo-corte"><?= htmlspecialchars($opcoesDomingo[0]['rotulo_corte']) ?></strong>.</p>
                </fieldset>

                <fieldset class="domingo-grupo">
                    <legend>Horário de retirada</legend>
                    <?php foreach ($opcoesDomingo as $d): ?>
                    <div class="domingo-opcoes domingo-opcoes-horas" data-domingo="<?= htmlspecialchars($d['data']) ?>">
                        <?php $temVaga = false; ?>
                        <?php foreach ($d['horarios'] as $h): ?>
                        <?php $lotado = $h['vagas'] <= 0; $temVaga = $temVaga || !$lotado; ?>
                        <label class="domingo-opcao<?= $lotado ? ' lotado' : '' ?>">
                            <input type="radio" name="retirada_hora" value="<?= htmlspecialchars($h['hora']) ?>" <?= $lotado ? 'disabled' : '' ?> <?= !$lotado && $d['data'] === $retiradaAtual['data'] && $h['hora'] === $retiradaAtual['hora'] ? 'checked' : '' ?> required>
                            <span class="domingo-opcao-texto"><strong><?= htmlspecialchars($h['hora']) ?></strong><small><?= $lotado ? 'Lotado' : ($h['vagas'] === 1 ? '1 vaga' : $h['vagas'] . ' vagas') ?></small></span>
                        </label>
                        <?php endforeach; ?>
                        <?php if (!$temVaga): ?>
                        <p class="alert alert-erro" style="grid-column:1/-1; margin:0;">Todos os horários deste domingo lotaram. Escolha o outro domingo.</p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </fieldset>
                <p class="domingo-aviso">As bebidas e os outros itens do pedido saem junto com a carne, no mesmo horário.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="resumo-card">
                <label>Forma de entrega
                    <select name="id_entrega" id="id_entrega">
                        <?php foreach ($formasEntrega as $f): ?>
                        <option value="<?= $f['id_entrega'] ?>" data-tipo="<?= htmlspecialchars($f['tipo']) ?>" data-superfrete="<?= (int) $f['superfrete'] ?>" data-custo="<?= number_format((float) $f['custo'], 2, '.', '') ?>">
                            <?= htmlspecialchars($f['nome']) ?>
                            <?= $f['prazo_dias'] !== null && !($temCarneDomingo && $f['tipo'] === 'retirada') ? '(Prazo de ' . (int) $f['prazo_dias'] . ' dias)' : '' ?>
                            <?= $f['superfrete'] ? '— calculado pelo CEP' : '— R$ ' . number_format($f['custo'], 2, ',', '.') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div id="campo-superfrete" style="display:none;">
                    <p style="color:var(--cor-texto-suave); font-size:0.85rem; margin:10px 0;">Informe os dados de quem vai receber esta compra. Eles valem só para este pedido e não ficam no seu cadastro.</p>
                    <label>Nome de quem recebe<input type="text" name="envio_nome" value="<?= htmlspecialchars($cliente['nome'] ?? '') ?>"></label>
                    <label>CPF (ou CNPJ)<input type="text" name="envio_documento" id="envio_documento" inputmode="numeric" maxlength="14" placeholder="Somente números"></label>
                    <label>Telefone (opcional)<input type="text" name="envio_telefone" inputmode="numeric" maxlength="11" placeholder="DDD + número"></label>
                    <label>E-mail (opcional)<input type="email" name="envio_email" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>"></label>
                    <label>CEP<input type="text" name="envio_cep" id="envio_cep" inputmode="numeric" maxlength="9" placeholder="00000-000"></label>
                    <label>Rua<input type="text" name="envio_endereco" id="envio_endereco"></label>
                    <label>Número<input type="text" name="envio_numero" id="envio_numero"></label>
                    <label>Complemento (opcional)<input type="text" name="envio_complemento"></label>
                    <label>Bairro<input type="text" name="envio_bairro" id="envio_bairro"></label>
                    <label>Cidade<input type="text" name="envio_cidade" id="envio_cidade"></label>
                    <label>Estado (UF)<input type="text" name="envio_uf" id="envio_uf" maxlength="2" style="text-transform:uppercase;"></label>
                    <button type="button" class="btn-outline btn-bloco" id="btn-calcular-frete" style="margin-top:10px;">Calcular frete</button>
                    <p id="frete-msg" class="alert alert-erro" style="display:none; margin-top:10px;"></p>
                    <div id="frete-opcoes" style="margin-top:10px;"></div>
                </div>
                <div id="campo-endereco">
                    <label>Endereço de entrega
                        <textarea name="endereco"><?= htmlspecialchars($cliente['endereco'] ?? '') ?></textarea>
                    </label>
                </div>
            </div>
        </div>

        <div class="painel-lateral">
            <div class="resumo-card">
                <div class="resumo-total" style="border-top:none; margin-top:0; padding-top:0;">
                    <span>Total</span>
                    <span id="valor-total-checkout">R$ <?= number_format($itensSubtotal, 2, ',', '.') ?></span>
                </div>
                <button type="submit" formaction="/loja/ajax/gerar_checkout.php" class="btn-lg btn-bloco"<?= $semDomingoDisponivel ? ' disabled' : '' ?>>
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 10V8a6 6 0 1 1 12 0v2h1a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V11a1 1 0 0 1 1-1h1Zm2 0h8V8a4 4 0 1 0-8 0v2Z"/></svg>
                    <?= $pixBradesco ? 'Pagar com Pix' : 'Pagar com Mercado Pago' ?>
                </button>
                <p style="color:var(--cor-texto-suave); font-size:0.85rem; margin:8px 0 0; text-align:center;">
                    <?php if ($pixBradesco): ?>
                    Você recebe o QR Code e o código "copia e cola". A confirmação é automática, na hora.
                    <?php else: ?>
                    Pix (aprovado na hora) ou cartão, no ambiente seguro do Mercado Pago.
                    Nenhum dado do seu cartão fica salvo na loja.
                    <?php endif; ?>
                </p>

                <?php if ($temLimiteCredito): ?>
                <button type="submit" id="btn-pagar-credito" formaction="/loja/ajax/finalizar_credito.php" class="btn-outline btn-bloco" style="margin-top:10px;">
                    <span class="rotulo-credito">Pagar com Linha de Crédito</span>
                    <span id="texto-credito-disponivel"></span>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </form>

<script>
function atualizarCampoEndereco() {
    const select = document.getElementById('id_entrega');
    const opcao = select.options[select.selectedIndex];
    const tipo = opcao ? opcao.dataset.tipo : null;
    const ehSf = !!opcao && opcao.dataset.superfrete === '1';
    document.getElementById('campo-endereco').style.display = (tipo === 'retirada' || ehSf) ? 'none' : '';
    document.getElementById('campo-superfrete').style.display = ehSf ? '' : 'none';
}
document.getElementById('id_entrega').addEventListener('change', atualizarCampoEndereco);
atualizarCampoEndereco();

// O total exibido (e o botão de Linha de Crédito) acompanha a forma de entrega
// escolhida na hora — antes disso, mudar a entrega não somava o custo dela ao
// total mostrado, só quando o formulário já tinha sido enviado.
const itensSubtotalCheckout = <?= json_encode($itensSubtotal) ?>;
const creditoDisponivelCheckout = <?= json_encode($creditoDisponivel) ?>;

// Só pra mostrar o total na tela: quem cobra é o servidor, com o preço da
// cotação guardada na sessão (o navegador manda só o ID do serviço).
let freteEscolhido = 0;

function formatarMoeda(valor) {
    return 'R$ ' + valor.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d)(?=,))/g, '.');
}

function atualizarTotalCheckout() {
    const select = document.getElementById('id_entrega');
    const opcao = select.options[select.selectedIndex];
    let custoEntrega = opcao ? parseFloat(opcao.dataset.custo || '0') : 0;
    if (opcao && opcao.dataset.superfrete === '1') { custoEntrega = freteEscolhido; }
    const total = itensSubtotalCheckout + custoEntrega;

    document.getElementById('valor-total-checkout').textContent = formatarMoeda(total);

    const btnCredito = document.getElementById('btn-pagar-credito');
    if (btnCredito) {
        const textoCredito = document.getElementById('texto-credito-disponivel');
        const insuficiente = creditoDisponivelCheckout < total;
        btnCredito.disabled = insuficiente || <?= $semDomingoDisponivel ? 'true' : 'false' ?>;
        textoCredito.textContent = insuficiente
            ? ' (insuficiente: ' + formatarMoeda(creditoDisponivelCheckout) + ')'
            : ' (disponível: ' + formatarMoeda(creditoDisponivelCheckout) + ')';
    }
}
document.getElementById('id_entrega').addEventListener('change', atualizarTotalCheckout);
atualizarTotalCheckout();

// ---- Carne do domingo: mostra só os horários do domingo escolhido ----
(function () {
    const bloco = document.getElementById('domingo-bloco');
    if (!bloco) { return; }
    const datas = bloco.querySelectorAll('input[name="retirada_data"]');
    const grupos = bloco.querySelectorAll('.domingo-opcoes-horas');
    const corte = document.getElementById('domingo-corte');
    function atualizarDomingo() {
        const marcado = bloco.querySelector('input[name="retirada_data"]:checked');
        const data = marcado ? marcado.value : null;
        grupos.forEach(function (g) {
            const ativo = g.dataset.domingo === data;
            g.hidden = !ativo;
            g.querySelectorAll('input').forEach(function (inp) {
                const lotado = inp.closest('.domingo-opcao').classList.contains('lotado');
                inp.disabled = !ativo || lotado;
                if (!ativo) { inp.checked = false; }
            });
        });
        if (marcado && corte) { corte.textContent = marcado.dataset.corte; }
    }
    datas.forEach(function (d) { d.addEventListener('change', atualizarDomingo); });
    atualizarDomingo();
})();

// ---- SuperFrete: CEP -> ViaCEP preenche o endereço (editável) -> cotação ----
const campoCep = document.getElementById('envio_cep');
if (campoCep) {
    const soDigitos = v => v.replace(/\D/g, '');
    campoCep.addEventListener('input', function () {
        const d = soDigitos(campoCep.value).slice(0, 8);
        campoCep.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
        if (d.length === 8) {
            fetch('https://viacep.com.br/ws/' + d + '/json/').then(r => r.json()).then(function (e) {
                if (e.erro) { return; }
                document.getElementById('envio_endereco').value = e.logradouro || '';
                document.getElementById('envio_bairro').value = e.bairro || '';
                document.getElementById('envio_cidade').value = e.localidade || '';
                document.getElementById('envio_uf').value = e.uf || '';
                document.getElementById('envio_numero').focus();
            }).catch(function () {});
        }
    });
    document.getElementById('envio_documento').addEventListener('input', function (e) {
        e.target.value = soDigitos(e.target.value).slice(0, 14);
    });

    const msgFrete = document.getElementById('frete-msg');
    const boxOpcoes = document.getElementById('frete-opcoes');
    function limparFrete() { freteEscolhido = 0; boxOpcoes.innerHTML = ''; atualizarTotalCheckout(); }
    campoCep.addEventListener('change', limparFrete);

    document.getElementById('btn-calcular-frete').addEventListener('click', function () {
        msgFrete.style.display = 'none';
        limparFrete();
        fetch('/loja/ajax/cotar_frete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'cep=' + encodeURIComponent(campoCep.value)
        }).then(r => r.json()).then(function (data) {
            if (!data.success) { msgFrete.textContent = data.message; msgFrete.style.display = ''; return; }
            data.opcoes.forEach(function (o) {
                const label = document.createElement('label');
                label.style.display = 'block';
                const radio = document.createElement('input');
                radio.type = 'radio'; radio.name = 'frete_servico'; radio.value = o.id;
                radio.addEventListener('change', function () { freteEscolhido = o.valor; atualizarTotalCheckout(); });
                label.appendChild(radio);
                label.appendChild(document.createTextNode(' ' + o.nome + (o.prazo ? ' — ' + o.prazo + ' dias úteis' : '') + ' — ' + formatarMoeda(o.valor)));
                boxOpcoes.appendChild(label);
            });
        }).catch(function () { msgFrete.textContent = 'Erro de conexão. Tente novamente.'; msgFrete.style.display = ''; });
    });
}

<?php if (!$carrinhoLivre): ?>
let restante = <?= (int) $venda['segundos_restantes'] ?>;
const idsProdutosCheckout = <?= json_encode($idsProdutosCheckout) ?>;

function formatarTempo(segundos) {
    const min = Math.floor(segundos / 60);
    const seg = segundos % 60;
    return min + ':' + String(seg).padStart(2, '0');
}

function atualizarContagemCheckout() {
    const painel = document.getElementById('timer-card');
    const rotulo = document.getElementById('contagem');
    if (!rotulo) { return; }
    rotulo.textContent = formatarTempo(restante);
    if (painel) { painel.classList.toggle('urgente', restante <= 60); }

    if (restante <= 0) {
        clearInterval(intervaloCheckout);
        const ids = idsProdutosCheckout.join(',');
        window.location.href = '/loja/index.php' + (ids ? '?voltou=' + ids : '');
        return;
    }
    restante--;
}

let intervaloCheckout = null;
atualizarContagemCheckout();
intervaloCheckout = setInterval(atualizarContagemCheckout, 1000);
<?php endif; ?>
</script>
</main>
<?php require __DIR__ . '/../includes/loja_footer.php'; ?>
</body>
</html>
