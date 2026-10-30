<?php
require_once __DIR__ . '/../conecta_bd.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loja.php';
require_once __DIR__ . '/../includes/domingo.php';
require_once __DIR__ . '/../includes/avisos_whatsapp.php';
exigirAdmin();

/*
 * Configurações > Domingo e avisos: prazo das encomendas, horários de
 * retirada e vagas, limite de cada carne por domingo, domingos sem
 * churrasco, número que recebe os avisos da loja, teste de envio e os
 * últimos avisos de WhatsApp (com o motivo, quando falham).
 */

$cfg = domingoConfigBruta($pdo);
$msg = '';
$erro = '';

if ($cfg && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    try {
        if ($acao === 'geral') {
            $numero = trim((string) ($_POST['whatsapp_avisos_loja'] ?? ''));
            $numeroNorm = $numero === '' ? null : normalizarWhatsappEnvio($numero);
            if ($numero !== '' && $numeroNorm === '') {
                throw new DomainException('WhatsApp para avisos inválido. Use DDD + número, ex.: (45) 99818-5568.');
            }
            $hora = preg_match('/^\d{2}:\d{2}$/', (string) ($_POST['corte_hora'] ?? '')) ? $_POST['corte_hora'] . ':00' : '18:00:00';
            $pdo->prepare(
                'UPDATE domingo_config SET ativo = :ativo, id_categoria_carnes = :cat, corte_dia_semana = :dia, corte_hora = :hora,
                        domingos_a_frente = :frente, whatsapp_avisos_loja = :num WHERE id = 1'
            )->execute([
                ':ativo' => !empty($_POST['ativo']) ? 1 : 0,
                ':cat' => (int) ($_POST['id_categoria_carnes'] ?? 0) ?: null,
                ':dia' => max(0, min(6, (int) ($_POST['corte_dia_semana'] ?? 6))),
                ':hora' => $hora,
                ':frente' => max(1, min(8, (int) ($_POST['domingos_a_frente'] ?? 2))),
                ':num' => $numeroNorm,
            ]);
            $msg = 'Configurações salvas.';
        } elseif ($acao === 'horario_novo') {
            $hora = (string) ($_POST['hora'] ?? '');
            if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
                throw new DomainException('Informe o horário no formato 00:00.');
            }
            $pdo->prepare('INSERT INTO domingo_horarios (hora, max_pedidos, ativo) VALUES (:h, :m, 1) ON DUPLICATE KEY UPDATE max_pedidos = VALUES(max_pedidos), ativo = 1')
                ->execute([':h' => $hora . ':00', ':m' => max(1, (int) ($_POST['max_pedidos'] ?? 8))]);
            $msg = 'Horário salvo.';
        } elseif ($acao === 'horarios_salvar') {
            foreach ((array) ($_POST['max'] ?? []) as $id => $max) {
                $pdo->prepare('UPDATE domingo_horarios SET max_pedidos = :m, ativo = :a WHERE id = :id')
                    ->execute([':m' => max(0, (int) $max), ':a' => !empty($_POST['ativo_h'][$id]) ? 1 : 0, ':id' => (int) $id]);
            }
            $msg = 'Horários salvos.';
        } elseif ($acao === 'horario_excluir') {
            $pdo->prepare('DELETE FROM domingo_horarios WHERE id = :id')->execute([':id' => (int) ($_POST['id'] ?? 0)]);
            $msg = 'Horário excluído.';
        } elseif ($acao === 'limites') {
            foreach ((array) ($_POST['limite'] ?? []) as $idProduto => $valor) {
                $valor = trim((string) $valor);
                $pdo->prepare('UPDATE produtos SET limite_domingo = :l WHERE id_produto = :id')
                    ->execute([':l' => $valor === '' ? null : max(0, (int) $valor), ':id' => (int) $idProduto]);
            }
            $msg = 'Limites salvos.';
        } elseif ($acao === 'bloquear') {
            $data = (string) ($_POST['data'] ?? '');
            $d = DateTime::createFromFormat('Y-m-d', $data);
            if (!$d || $d->format('w') !== '0') {
                throw new DomainException('Escolha uma data que seja domingo.');
            }
            $pdo->prepare('INSERT INTO domingo_bloqueios (data, motivo) VALUES (:d, :m) ON DUPLICATE KEY UPDATE motivo = VALUES(motivo)')
                ->execute([':d' => $data, ':m' => mb_substr(trim((string) ($_POST['motivo'] ?? '')), 0, 80) ?: null]);
            $msg = 'Domingo bloqueado.';
        } elseif ($acao === 'desbloquear') {
            $pdo->prepare('DELETE FROM domingo_bloqueios WHERE data = :d')->execute([':d' => (string) ($_POST['data'] ?? '')]);
            $msg = 'Domingo liberado.';
        } elseif ($acao === 'teste') {
            $destino = trim((string) ($_POST['numero_teste'] ?? '')) ?: whatsappNumeroAvisosLoja($pdo);
            $ok = whatsappEnviarTexto($pdo, $destino, 'Teste do sistema da loja: se você recebeu esta mensagem, os avisos de pedido pelo WhatsApp estão funcionando.', 'teste');
            if ($ok) {
                $msg = 'Mensagem de teste enviada para ' . $destino . '.';
            } else {
                $ultimo = $pdo->query("SELECT detalhe FROM whatsapp_log WHERE tipo = 'teste' ORDER BY id DESC LIMIT 1")->fetchColumn();
                $erro = 'A mensagem de teste não saiu: ' . ($ultimo ?: 'motivo desconhecido');
            }
        }
    } catch (DomainException $e) {
        $erro = $e->getMessage();
    }
    $cfg = domingoConfigBruta($pdo);
}

$categorias = $pdo->query('SELECT id_categoria, nome FROM categorias ORDER BY nome')->fetchAll();
$horarios = $cfg ? $pdo->query('SELECT * FROM domingo_horarios ORDER BY hora')->fetchAll() : [];
$carnes = [];
if ($cfg && !empty($cfg['id_categoria_carnes'])) {
    $stmt = $pdo->prepare('SELECT id_produto, nome, limite_domingo FROM produtos WHERE id_categoria = :c AND ativo = 1 ORDER BY nome');
    $stmt->execute([':c' => (int) $cfg['id_categoria_carnes']]);
    $carnes = $stmt->fetchAll();
}
$bloqueios = $cfg ? $pdo->query('SELECT * FROM domingo_bloqueios WHERE data >= CURDATE() ORDER BY data')->fetchAll() : [];
$log = [];
try {
    $log = $pdo->query('SELECT * FROM whatsapp_log ORDER BY id DESC LIMIT 25')->fetchAll();
} catch (Throwable $e) {
}
$whatsappOk = false;
try {
    $whatsappOk = whatsappInstanciaAtiva($pdo) !== null;
} catch (Throwable $e) {
}
$diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Domingo e avisos</title></head>
<body>
<?php require __DIR__ . '/../includes/admin_header.php'; ?>
    <div class="page-title">
        <span class="icone-titulo"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M16 2v4M8 2v4M3 10h18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
        <div>
            <h1>Domingo e avisos</h1>
            <span class="subtitulo">Agendamento da carne de domingo e mensagens de WhatsApp dos pedidos</span>
        </div>
    </div>
    <?php if ($msg): ?><p class="alert alert-sucesso"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <?php if ($erro): ?><p class="alert alert-erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

    <?php if (!$cfg): ?>
        <p class="alert alert-erro">Falta instalar no banco: importe <strong>sql/schema_domingo_2026_10.sql</strong> no phpMyAdmin e volte aqui.</p>
    <?php else: ?>

    <div class="grade-2col">
        <div class="card">
            <h3>Prazos</h3>
            <form method="post">
                <input type="hidden" name="acao" value="geral">
                <label style="display:flex; gap:8px; align-items:center;"><input type="checkbox" name="ativo" value="1" <?= (int) $cfg['ativo'] ? 'checked' : '' ?> style="width:auto;"> Pedir domingo e horário quando o carrinho tiver carne</label>
                <label>Categoria das carnes
                    <select name="id_categoria_carnes">
                        <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int) $c['id_categoria'] ?>" <?= (int) $c['id_categoria'] === (int) $cfg['id_categoria_carnes'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Encomendas fecham no(a)
                    <select name="corte_dia_semana">
                        <?php foreach ([6, 5, 4, 0] as $d): ?>
                        <option value="<?= $d ?>" <?= (int) $cfg['corte_dia_semana'] === $d ? 'selected' : '' ?>><?= $diasSemana[$d] ?><?= $d === 0 ? ' (o próprio domingo)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Às
                    <input type="time" name="corte_hora" value="<?= htmlspecialchars(substr((string) $cfg['corte_hora'], 0, 5)) ?>">
                </label>
                <label>Aceitar encomendas para quantos domingos à frente
                    <input type="number" name="domingos_a_frente" min="1" max="8" value="<?= (int) $cfg['domingos_a_frente'] ?>">
                </label>
                <label>WhatsApp que recebe os avisos da loja
                    <input type="text" name="whatsapp_avisos_loja" placeholder="Vazio = o WhatsApp da loja (Aparência)" value="<?= htmlspecialchars(formatarWhatsappParaEdicao((string) ($cfg['whatsapp_avisos_loja'] ?? ''))) ?>">
                </label>
                <p style="font-size:.85rem; opacity:.75; margin-top:-4px;">Use o celular do dono, de preferência diferente do número conectado da loja: mensagem para o próprio número aparece só em "Você" no WhatsApp.</p>
                <button type="submit">Salvar</button>
            </form>
        </div>

        <div class="card">
            <h3>WhatsApp dos pedidos</h3>
            <?php if ($whatsappOk): ?>
                <p class="alert alert-sucesso">WhatsApp da loja conectado. Os avisos de pedido estão ligados.</p>
            <?php else: ?>
                <p class="alert alert-erro">O WhatsApp da loja não está conectado, então nenhuma mensagem sai. Conecte em Configurações &gt; WhatsApp (QR code). Se esse menu não aparece, o recurso precisa ser ligado no painel do desenvolvedor.</p>
            <?php endif; ?>
            <form method="post" class="form-linha-compacta">
                <input type="hidden" name="acao" value="teste">
                <label>Enviar mensagem de teste para
                    <input type="text" name="numero_teste" placeholder="Vazio = número de avisos da loja">
                </label>
                <button type="submit">Enviar teste</button>
            </form>
            <p style="font-size:.85rem; opacity:.75;">Avisos automáticos: pedido confirmado (cliente e loja), lembrete quando fecham as encomendas, resumo de produção para a loja, pronto para retirar (ao marcar "Pronto") e horário alterado.</p>
        </div>
    </div>

    <div class="card" style="margin-top:20px;">
        <h3>Horários de retirada</h3>
        <form method="post">
            <input type="hidden" name="acao" value="horarios_salvar">
            <div class="tabela-wrap">
            <table>
                <tr><th>Horário</th><th>Máximo de pedidos</th><th>Ativo</th><th></th></tr>
                <?php foreach ($horarios as $h): ?>
                <tr>
                    <td><strong><?= htmlspecialchars(domingoRotuloHora($h['hora'])) ?></strong></td>
                    <td><input type="number" min="0" name="max[<?= (int) $h['id'] ?>]" value="<?= (int) $h['max_pedidos'] ?>" style="max-width:110px;" aria-label="Máximo de pedidos às <?= htmlspecialchars(domingoRotuloHora($h['hora'])) ?>"></td>
                    <td><input type="checkbox" name="ativo_h[<?= (int) $h['id'] ?>]" value="1" <?= (int) $h['ativo'] ? 'checked' : '' ?> style="width:auto;" aria-label="Horário <?= htmlspecialchars(domingoRotuloHora($h['hora'])) ?> ativo"></td>
                    <td><button type="submit" form="excluir-h-<?= (int) $h['id'] ?>" class="btn-sm btn-perigo">excluir</button></td>
                </tr>
                <?php endforeach; ?>
            </table>
            </div>
            <button type="submit" style="margin-top:10px;">Salvar horários</button>
        </form>
        <?php foreach ($horarios as $h): ?>
        <form method="post" id="excluir-h-<?= (int) $h['id'] ?>" data-confirm="Excluir o horário <?= htmlspecialchars(domingoRotuloHora($h['hora'])) ?>?">
            <input type="hidden" name="acao" value="horario_excluir">
            <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
        </form>
        <?php endforeach; ?>
        <form method="post" class="linha-form-rapido" style="margin-top:14px;">
            <input type="hidden" name="acao" value="horario_novo">
            <input type="time" name="hora" required aria-label="Novo horário">
            <input type="number" name="max_pedidos" min="1" value="8" aria-label="Máximo de pedidos">
            <button type="submit">Adicionar horário</button>
        </form>
    </div>

    <div class="grade-2col" style="margin-top:20px;">
        <div class="card">
            <h3>Limite de cada carne por domingo</h3>
            <p style="font-size:.85rem; opacity:.75; margin-top:-8px;">Cada domingo começa com este limite (não soma com a semana anterior). Em branco = sem limite por domingo, vale só o estoque.</p>
            <?php if (empty($carnes)): ?>
                <p class="alert alert-info">Nenhum produto ativo na categoria escolhida.</p>
            <?php else: ?>
            <form method="post">
                <input type="hidden" name="acao" value="limites">
                <?php foreach ($carnes as $c): ?>
                <label><?= htmlspecialchars($c['nome']) ?>
                    <input type="number" min="0" name="limite[<?= (int) $c['id_produto'] ?>]" value="<?= $c['limite_domingo'] !== null ? (int) $c['limite_domingo'] : '' ?>">
                </label>
                <?php endforeach; ?>
                <button type="submit">Salvar limites</button>
            </form>
            <p style="font-size:.85rem; opacity:.75;">Importante: o estoque normal do produto também precisa ter quantidade. Para aceitar encomendas de 2 domingos, deixe o estoque com a soma dos dois (ex.: limite 20, estoque 40), ou desmarque "controlar estoque" na carne.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Domingos sem churrasco</h3>
            <?php if ($bloqueios): ?>
            <div class="tabela-wrap">
            <table>
                <?php foreach ($bloqueios as $b): ?>
                <tr>
                    <td><?= htmlspecialchars(ucfirst(domingoRotuloData($b['data']))) ?>/<?= htmlspecialchars(substr($b['data'], 0, 4)) ?></td>
                    <td><?= htmlspecialchars($b['motivo'] ?? '') ?></td>
                    <td><form method="post"><input type="hidden" name="acao" value="desbloquear"><input type="hidden" name="data" value="<?= htmlspecialchars($b['data']) ?>"><button type="submit" class="btn-sm btn-outline">liberar</button></form></td>
                </tr>
                <?php endforeach; ?>
            </table>
            </div>
            <?php else: ?>
            <p class="alert alert-info">Nenhum domingo bloqueado.</p>
            <?php endif; ?>
            <form method="post" class="linha-form-rapido" style="margin-top:12px;">
                <input type="hidden" name="acao" value="bloquear">
                <input type="date" name="data" required aria-label="Domingo a bloquear">
                <input type="text" name="motivo" placeholder="Motivo (ex.: Natal)">
                <button type="submit">Bloquear</button>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:20px;">
        <h3>Últimos avisos de WhatsApp</h3>
        <?php if (empty($log)): ?>
            <p class="alert alert-info">Nenhuma mensagem tentada ainda.</p>
        <?php else: ?>
        <div class="tabela-wrap">
        <table>
            <tr><th>Quando</th><th>Tipo</th><th>Pedido</th><th>Para</th><th>Resultado</th></tr>
            <?php foreach ($log as $l): ?>
            <tr>
                <td><?= htmlspecialchars(date('d/m H:i', strtotime($l['criado_em']))) ?></td>
                <td><?= htmlspecialchars($l['tipo']) ?></td>
                <td><?= $l['id_venda'] ? '#' . (int) $l['id_venda'] : '—' ?></td>
                <td><?= htmlspecialchars(formatarWhatsappExibicao((string) $l['telefone'])) ?></td>
                <td><span class="status-pill <?= (int) $l['sucesso'] ? 'sucesso' : 'erro' ?>"><?= (int) $l['sucesso'] ? 'Enviado' : 'Falhou' ?></span>
                    <?php if (!(int) $l['sucesso']): ?><br><small><?= htmlspecialchars($l['detalhe']) ?></small><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
        <?php endif; ?>
        <p style="font-size:.85rem; opacity:.75; margin-top:8px;">Para disparar lembretes e resumo no horário certo mesmo sem ninguém abrir o site, agende no servidor uma chamada a cada 15 minutos para:<br><code><?= htmlspecialchars(urlBaseAtual()) ?>/cron_domingo.php?chave=<?= htmlspecialchars((string) $cfg['cron_chave']) ?></code></p>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
