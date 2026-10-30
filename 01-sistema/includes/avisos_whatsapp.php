<?php
require_once __DIR__ . '/whatsapp_conexao.php';
require_once __DIR__ . '/domingo.php';

/**
 * Avisos de pedido por WhatsApp (cliente e loja).
 *
 * Antes deste arquivo, o sistema só usava o WhatsApp para o código de
 * verificação do cadastro: nenhum pedido gerava mensagem. Agora todo envio
 * passa por whatsappEnviarTexto(), que fala direto com a Evolution API pela
 * instância que o lojista conectou (Configurações > WhatsApp) e grava cada
 * tentativa em whatsapp_log (Configurações > Domingo mostra os últimos).
 *
 * Nenhum aviso derruba o fluxo do pedido: erro de envio vira só registro.
 */

/** Grava uma tentativa de envio. Se a tabela não existir, fica no log do servidor. */
function whatsappLogar(PDO $pdo, string $tipo, ?int $idVenda, ?string $telefone, bool $sucesso, ?int $httpCode, string $detalhe, string $texto): void
{
    if (!$sucesso) {
        error_log("WhatsApp [{$tipo}] venda {$idVenda} para {$telefone}: {$detalhe}");
    }
    try {
        $pdo->prepare(
            'INSERT INTO whatsapp_log (tipo, id_venda, telefone, sucesso, http_code, detalhe, texto)
             VALUES (:tipo, :iv, :tel, :ok, :http, :det, :txt)'
        )->execute([
            ':tipo' => $tipo,
            ':iv' => $idVenda,
            ':tel' => $telefone,
            ':ok' => $sucesso ? 1 : 0,
            ':http' => $httpCode,
            ':det' => mb_substr($detalhe, 0, 500),
            ':txt' => $texto,
        ]);
    } catch (Throwable $e) {
        error_log('whatsappLogar: tabela whatsapp_log ausente? ' . $e->getMessage());
    }
}

/**
 * Envia um texto pelo WhatsApp conectado da loja.
 * $telefone em qualquer formato brasileiro ("(45) 99818-5568", "5545998185568").
 */
function whatsappEnviarTexto(PDO $pdo, string $telefone, string $texto, string $tipo, ?int $idVenda = null): bool
{
    $numero = normalizarWhatsappEnvio($telefone);
    if ($numero === '') {
        whatsappLogar($pdo, $tipo, $idVenda, $telefone, false, null, 'Número de WhatsApp inválido ou vazio.', $texto);
        return false;
    }

    try {
        $instancia = whatsappInstanciaAtiva($pdo);
    } catch (Throwable $e) {
        $instancia = null;
    }
    if ($instancia === null) {
        $motivo = whatsappModuloDevAtivo($pdo)
            ? 'O WhatsApp da loja não está conectado (Configurações > WhatsApp > conectar pelo QR code).'
            : 'Recurso de WhatsApp desligado no painel do desenvolvedor, ou servidor Evolution sem URL/chave.';
        whatsappLogar($pdo, $tipo, $idVenda, $numero, false, null, $motivo, $texto);
        return false;
    }

    try {
        $g = evolutionConfigGlobal($pdo);
        $r = evolutionChamar($g['base'], $g['chave'], 'POST', '/message/sendText/' . rawurlencode($instancia), [
            'number' => $numero,
            'text' => $texto,
        ], 10);
    } catch (Throwable $e) {
        whatsappLogar($pdo, $tipo, $idVenda, $numero, false, null, $e->getMessage(), $texto);
        return false;
    }

    $ok = $r['http_code'] >= 200 && $r['http_code'] < 300;
    $detalhe = $ok ? 'Enviado.' : 'Evolution respondeu HTTP ' . $r['http_code'] . ': ' . mb_substr((string) json_encode($r['dados'], JSON_UNESCAPED_UNICODE), 0, 400);
    whatsappLogar($pdo, $tipo, $idVenda, $numero, $ok, $r['http_code'], $detalhe, $texto);
    return $ok;
}

/** Só dígitos com DDI 55. Aceita fixo e celular (o número da loja pode ser fixo com WhatsApp Business). */
function normalizarWhatsappEnvio(string $telefone): string
{
    $d = preg_replace('/\D+/', '', $telefone) ?? '';
    if (strlen($d) === 10 || strlen($d) === 11) {
        $d = '55' . $d;
    }
    return preg_match('/^55\d{10,11}$/', $d) ? $d : '';
}

/** Número que recebe os avisos da loja: o configurado em Domingo, senão o WhatsApp da loja (Aparência). */
function whatsappNumeroAvisosLoja(PDO $pdo): string
{
    $cfg = domingoConfigBruta($pdo);
    if ($cfg && trim((string) $cfg['whatsapp_avisos_loja']) !== '') {
        return (string) $cfg['whatsapp_avisos_loja'];
    }
    return (string) ($pdo->query('SELECT whatsapp_loja FROM config_loja WHERE id_config = 1')->fetchColumn() ?: '');
}

/** Dados do pedido usados nas mensagens. */
function avisoDadosPedido(PDO $pdo, int $id_venda): ?array
{
    $stmt = $pdo->prepare(
        "SELECT v.id_venda, v.valor_total, v.forma_pagamento, v.retirada_data, v.retirada_hora, v.status, v.status_entrega,
                c.nome AS cliente_nome, c.whatsapp AS cliente_whatsapp,
                fe.nome AS entrega_nome, fe.tipo AS entrega_tipo,
                cl.nome_loja, cl.endereco_loja
         FROM vendas v
         LEFT JOIN clientes c ON c.id_cliente = v.id_cliente
         LEFT JOIN formas_entrega fe ON fe.id_entrega = v.id_entrega
         JOIN config_loja cl ON cl.id_config = 1
         WHERE v.id_venda = :id AND v.origem = 'loja'"
    );
    $stmt->execute([':id' => $id_venda]);
    $p = $stmt->fetch();
    if (!$p) {
        return null;
    }
    $itens = $pdo->prepare("SELECT nome_produto, quantidade FROM itens_venda WHERE id_venda = :id AND NOT (id_produto_variacao IS NULL AND nome_produto = 'Entrega') ORDER BY id_item");
    $itens->execute([':id' => $id_venda]);
    $p['itens'] = $itens->fetchAll();
    $p['primeiro_nome'] = explode(' ', trim((string) $p['cliente_nome']))[0] ?: 'cliente';
    $p['loja'] = $p['nome_loja'] ?: 'Clube da Cerveja';
    return $p;
}

function avisoListaItens(array $p, string $prefixo = '• '): string
{
    $linhas = [];
    foreach ($p['itens'] as $it) {
        $linhas[] = $prefixo . (int) $it['quantidade'] . 'x ' . $it['nome_produto'];
    }
    return implode("\n", $linhas);
}

function avisoValor(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function avisoFormaPagamento(array $p): string
{
    return $p['forma_pagamento'] === 'Linha de Crédito' ? 'na conta aberta' : 'pago (' . ($p['forma_pagamento'] ?: 'online') . ')';
}

/** Pedido finalizado (Pix/cartão confirmado ou conta aberta): avisa cliente e loja. */
function avisarPedidoConfirmado(PDO $pdo, int $id_venda): void
{
    $p = avisoDadosPedido($pdo, $id_venda);
    if (!$p) {
        return;
    }
    $linkPedido = (function_exists('urlBaseAtual') && !empty($_SERVER['HTTP_HOST']) ? urlBaseAtual() : '') . '/loja/pedido_status.php?id_venda=' . $id_venda;
    $retirada = domingoRotuloRetirada($p['retirada_data'], $p['retirada_hora']);

    $cliente = "*{$p['loja']}* — Pedido #{$id_venda} confirmado!\n\n";
    if ($retirada !== '') {
        $cliente .= "Retirada: *{$retirada}*\n";
    } elseif ($p['entrega_nome']) {
        $cliente .= "Entrega: {$p['entrega_nome']}\n";
    }
    $cliente .= avisoListaItens($p) . "\n";
    $cliente .= 'Total: ' . avisoValor((float) $p['valor_total']) . ' (' . avisoFormaPagamento($p) . ")\n";
    if ($p['entrega_tipo'] === 'retirada' && $p['endereco_loja']) {
        $cliente .= "\nEndereço: {$p['endereco_loja']}\n";
    }
    $cliente .= "\nAcompanhe ou mude o horário: {$linkPedido}";

    if (!empty($p['cliente_whatsapp'])) {
        whatsappEnviarTexto($pdo, (string) $p['cliente_whatsapp'], $cliente, 'pedido_confirmado', $id_venda);
    }

    $loja = "Novo pedido #{$id_venda} — {$p['cliente_nome']}\n";
    if ($retirada !== '') {
        $loja .= "Retirada: {$retirada}\n";
    } elseif ($p['entrega_nome']) {
        $loja .= "Entrega: {$p['entrega_nome']}\n";
    }
    $loja .= avisoListaItens($p) . "\n" . avisoValor((float) $p['valor_total']) . ' · ' . avisoFormaPagamento($p);

    $numeroLoja = whatsappNumeroAvisosLoja($pdo);
    if ($numeroLoja !== '') {
        whatsappEnviarTexto($pdo, $numeroLoja, $loja, 'pedido_novo_loja', $id_venda);
    }
}

/** Lojista marcou o pedido como "Pronto": avisa o cliente (uma vez só). */
function avisarPedidoPronto(PDO $pdo, int $id_venda): void
{
    $marcou = $pdo->prepare('UPDATE vendas SET aviso_pronto_em = NOW() WHERE id_venda = :id AND aviso_pronto_em IS NULL');
    try {
        $marcou->execute([':id' => $id_venda]);
    } catch (Throwable $e) {
        return; // migração não rodada
    }
    if ($marcou->rowCount() === 0) {
        return;
    }
    $p = avisoDadosPedido($pdo, $id_venda);
    if (!$p || empty($p['cliente_whatsapp'])) {
        return;
    }
    $texto = $p['entrega_tipo'] === 'retirada'
        ? "Oi, {$p['primeiro_nome']}! Seu pedido #{$id_venda} está *pronto para retirar* no {$p['loja']}. É só chegar no balcão e falar seu nome."
        : "Oi, {$p['primeiro_nome']}! Seu pedido #{$id_venda} do {$p['loja']} está pronto e já vai sair para entrega.";
    whatsappEnviarTexto($pdo, (string) $p['cliente_whatsapp'], $texto, 'pedido_pronto', $id_venda);
}

/** Cliente trocou o domingo/horário: avisa cliente e loja. */
function avisarRetiradaAlterada(PDO $pdo, int $id_venda, string $antes): void
{
    $p = avisoDadosPedido($pdo, $id_venda);
    if (!$p) {
        return;
    }
    $agora = domingoRotuloRetirada($p['retirada_data'], $p['retirada_hora']);
    if (!empty($p['cliente_whatsapp'])) {
        whatsappEnviarTexto($pdo, (string) $p['cliente_whatsapp'], "Pronto, {$p['primeiro_nome']}! A retirada do pedido #{$id_venda} mudou para *{$agora}*.", 'retirada_alterada', $id_venda);
    }
    $numeroLoja = whatsappNumeroAvisosLoja($pdo);
    if ($numeroLoja !== '') {
        whatsappEnviarTexto($pdo, $numeroLoja, "Pedido #{$id_venda} ({$p['cliente_nome']}) mudou a retirada.\nAntes: {$antes}\nAgora: {$agora}", 'retirada_alterada_loja', $id_venda);
    }
}

/**
 * Tarefas que dependem do horário (rodam "de carona" nas páginas da loja e
 * do painel, no máximo a cada 5 minutos, e também por cron_domingo.php):
 *  - quando fecham as encomendas de um domingo, manda o resumo de produção pra loja;
 *  - e o lembrete para cada cliente com retirada nesse domingo (até 3 por rodada,
 *    pra nenhuma página ficar lenta).
 */
function domingoTarefasAutomaticas(PDO $pdo, bool $forcar = false): void
{
    $cfg = domingoConfig($pdo);
    if (!$cfg) {
        return;
    }
    if (!$forcar) {
        $marcou = $pdo->prepare(
            'UPDATE domingo_config SET tarefas_verificadas_em = NOW()
             WHERE id = 1 AND (tarefas_verificadas_em IS NULL OR tarefas_verificadas_em < NOW() - INTERVAL 5 MINUTE)'
        );
        $marcou->execute();
        if ($marcou->rowCount() === 0) {
            return;
        }
    }

    $agora = domingoAgora();
    $diasAteDomingo = (7 - (int) $agora->format('w')) % 7;
    $proximoDomingo = $agora->setTime(0, 0)->modify("+{$diasAteDomingo} days")->format('Y-m-d');
    $corte = domingoCorte($proximoDomingo, $cfg);
    if ($agora < $corte) {
        return; // encomendas ainda abertas para o próximo domingo
    }

    // Resumo de produção (uma vez por domingo).
    $marcouResumo = $pdo->prepare('UPDATE domingo_config SET ultimo_resumo_data = :d WHERE id = 1 AND (ultimo_resumo_data IS NULL OR ultimo_resumo_data <> :d2)');
    $marcouResumo->execute([':d' => $proximoDomingo, ':d2' => $proximoDomingo]);
    if ($marcouResumo->rowCount() > 0) {
        $linhas = [];
        foreach (domingoProducao($pdo, $proximoDomingo) as $c) {
            if ($c['encomendado'] > 0) {
                $linhas[] = $c['nome'] . ': ' . $c['encomendado'];
            }
        }
        $stmtQtd = $pdo->prepare("SELECT COUNT(*) FROM vendas v WHERE v.origem = 'loja' AND v.status = 'Pago' AND v.retirada_data = :d");
        $stmtQtd->execute([':d' => $proximoDomingo]);
        $qtdPedidos = (int) $stmtQtd->fetchColumn();
        $texto = '*Produção de ' . domingoRotuloData($proximoDomingo) . "*\n"
            . ($linhas ? implode("\n", $linhas) : 'Nenhuma carne encomendada.')
            . "\n\n{$qtdPedidos} pedido(s). Lista completa no painel: Pedidos > Domingo.";
        $numeroLoja = whatsappNumeroAvisosLoja($pdo);
        if ($numeroLoja !== '' && $qtdPedidos > 0) {
            whatsappEnviarTexto($pdo, $numeroLoja, $texto, 'resumo_producao');
        }
    }

    // Lembretes para os clientes (só no próprio fim de semana).
    if ($agora->format('Y-m-d') > $proximoDomingo) {
        return;
    }
    $stmt = $pdo->prepare(
        "SELECT v.id_venda FROM vendas v
         WHERE v.origem = 'loja' AND v.status = 'Pago' AND v.retirada_data = :d
           AND v.lembrete_enviado_em IS NULL AND (v.status_entrega IS NULL OR v.status_entrega IN ('Aguardando preparo', 'Preparando'))
         ORDER BY v.retirada_hora LIMIT 3"
    );
    $stmt->execute([':d' => $proximoDomingo]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $idVenda) {
        $idVenda = (int) $idVenda;
        $marcou = $pdo->prepare('UPDATE vendas SET lembrete_enviado_em = NOW() WHERE id_venda = :id AND lembrete_enviado_em IS NULL');
        $marcou->execute([':id' => $idVenda]);
        if ($marcou->rowCount() === 0) {
            continue;
        }
        $p = avisoDadosPedido($pdo, $idVenda);
        if (!$p || empty($p['cliente_whatsapp'])) {
            continue;
        }
        $texto = "Oi, {$p['primeiro_nome']}! Lembrete do {$p['loja']}: seu pedido #{$idVenda} fica pronto *"
            . domingoRotuloRetirada($p['retirada_data'], $p['retirada_hora']) . "*.\n" . avisoListaItens($p);
        whatsappEnviarTexto($pdo, (string) $p['cliente_whatsapp'], $texto, 'lembrete_domingo', $idVenda);
    }
}
