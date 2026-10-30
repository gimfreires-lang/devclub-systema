<?php
/*
 * Tarefas do domingo no horário certo (resumo de produção para a loja e
 * lembretes para os clientes quando fecham as encomendas).
 *
 * Elas já rodam sozinhas quando alguém abre a loja ou o painel. Este endereço
 * é para um agendador do servidor (cron do Easypanel, cron-job.org etc.)
 * chamar a cada 15 minutos:
 *   https://SEU-DOMINIO/cron_domingo.php?chave=CHAVE
 * A chave aparece em Configurações > Domingo e avisos.
 */
require_once __DIR__ . '/conecta_bd.php';
require_once __DIR__ . '/includes/loja.php';
require_once __DIR__ . '/includes/avisos_whatsapp.php';

header('Content-Type: text/plain; charset=utf-8');

$cfg = domingoConfigBruta($pdo);
$chave = (string) ($_GET['chave'] ?? '');
if (!$cfg || empty($cfg['cron_chave']) || !hash_equals((string) $cfg['cron_chave'], $chave)) {
    http_response_code(403);
    echo "chave invalida\n";
    exit;
}

// Várias rodadas para dar conta de todos os lembretes (3 por rodada).
for ($i = 0; $i < 10; $i++) {
    domingoTarefasAutomaticas($pdo, true);
}
echo "ok\n";
