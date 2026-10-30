<?php

/**
 * Agendamento da carne de domingo.
 *
 * Quando o carrinho tem item da categoria de carnes (domingo_config.
 * id_categoria_carnes), o cliente escolhe qual domingo e em que horário vai
 * retirar. As regras:
 *  - são oferecidos os próximos N domingos (domingos_a_frente), menos os bloqueados;
 *  - as encomendas de um domingo fecham no dia/hora de corte (padrão sábado 18h);
 *  - cada horário aceita até max_pedidos pedidos;
 *  - cada carne pode ter um limite por domingo (produtos.limite_domingo).
 *
 * Tudo em horário de Brasília. Sem a tabela domingo_config (migração
 * sql/schema_domingo_2026_10.sql não rodada) o recurso fica desligado e a
 * loja funciona como antes.
 *
 * Para testar sem esperar o sábado, defina em conecta_bd.php:
 *   define('DOMINGO_AGORA_TESTE', '2026-10-31 18:30:00');
 */

const DOMINGO_TZ = 'America/Sao_Paulo';
const DOMINGO_DIAS_SEMANA = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

/** Linha de domingo_config, ou null se o recurso estiver desligado/não instalado. */
function domingoConfig(PDO $pdo): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    try {
        $c = $pdo->query('SELECT * FROM domingo_config WHERE id = 1')->fetch();
    } catch (PDOException $e) {
        return $cache = null;
    }
    if (!$c || !(int) $c['ativo'] || empty($c['id_categoria_carnes'])) {
        return $cache = null;
    }
    return $cache = $c;
}

/** Igual a domingoConfig(), mas devolve a linha mesmo com o recurso desligado (tela de configuração). */
function domingoConfigBruta(PDO $pdo): ?array
{
    try {
        return $pdo->query('SELECT * FROM domingo_config WHERE id = 1')->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function domingoAgora(): DateTimeImmutable
{
    $tz = new DateTimeZone(DOMINGO_TZ);
    if (defined('DOMINGO_AGORA_TESTE')) {
        return new DateTimeImmutable((string) DOMINGO_AGORA_TESTE, $tz);
    }
    return new DateTimeImmutable('now', $tz);
}

/** Momento em que fecham as encomendas para o domingo $data ('Y-m-d'). */
function domingoCorte(string $data, array $cfg): DateTimeImmutable
{
    $tz = new DateTimeZone(DOMINGO_TZ);
    $diaCorte = max(0, min(6, (int) $cfg['corte_dia_semana']));
    $diasAntes = (7 - $diaCorte) % 7; // sábado = 1 dia antes, sexta = 2, domingo = no próprio dia
    $hora = substr((string) $cfg['corte_hora'], 0, 5) ?: '18:00';
    return (new DateTimeImmutable($data . ' ' . $hora . ':00', $tz))->modify("-{$diasAntes} days");
}

/** "domingo, 01/11" */
function domingoRotuloData(string $data): string
{
    $d = new DateTimeImmutable($data, new DateTimeZone(DOMINGO_TZ));
    return DOMINGO_DIAS_SEMANA[(int) $d->format('w')] . ', ' . $d->format('d/m');
}

/** "sábado, 31/10, às 18h" (ou "às 18h30") */
function domingoRotuloCorte(DateTimeImmutable $corte): string
{
    $min = $corte->format('i');
    return DOMINGO_DIAS_SEMANA[(int) $corte->format('w')] . ', ' . $corte->format('d/m') . ', às ' . $corte->format('G') . 'h' . ($min !== '00' ? $min : '');
}

/** "11:30" */
function domingoRotuloHora(?string $hora): string
{
    return substr((string) $hora, 0, 5);
}

/** "domingo, 01/11, às 11:30" */
function domingoRotuloRetirada(?string $data, ?string $hora): string
{
    if (!$data) {
        return '';
    }
    return domingoRotuloData($data) . ($hora ? ', às ' . domingoRotuloHora($hora) : '');
}

/**
 * Domingos em que ainda dá pra encomendar.
 *
 * @return list<array{data:string,rotulo:string,corte:DateTimeImmutable,rotulo_corte:string}>
 */
function domingosDisponiveis(PDO $pdo): array
{
    $cfg = domingoConfig($pdo);
    if (!$cfg) {
        return [];
    }
    $quantos = max(1, min(8, (int) $cfg['domingos_a_frente']));
    $agora = domingoAgora();

    $bloqueados = [];
    foreach ($pdo->query('SELECT data FROM domingo_bloqueios WHERE data >= CURDATE() - INTERVAL 1 DAY')->fetchAll(PDO::FETCH_COLUMN) as $b) {
        $bloqueados[$b] = true;
    }

    // Domingo desta semana (hoje, se hoje for domingo) e os seguintes.
    $diasAteDomingo = (7 - (int) $agora->format('w')) % 7;
    $domingo = $agora->setTime(0, 0)->modify("+{$diasAteDomingo} days");

    $lista = [];
    for ($i = 0; $i < 12 && count($lista) < $quantos; $i++) {
        $data = $domingo->modify('+' . (7 * $i) . ' days')->format('Y-m-d');
        $corte = domingoCorte($data, $cfg);
        if ($corte <= $agora || isset($bloqueados[$data])) {
            continue;
        }
        $lista[] = ['data' => $data, 'rotulo' => domingoRotuloData($data), 'corte' => $corte, 'rotulo_corte' => domingoRotuloCorte($corte)];
    }
    return $lista;
}

/**
 * Pedidos que ocupam vaga/estoque de um domingo: pagos, ou aguardando
 * pagamento dentro do prazo (o cliente está no Mercado Pago agora).
 */
function domingoCondicaoPedidoAtivo(string $alias = 'v'): string
{
    return "({$alias}.status = 'Pago' OR ({$alias}.status = 'Reservado' AND {$alias}.pagamento_expira_em IS NOT NULL AND {$alias}.pagamento_expira_em > NOW()))";
}

/**
 * Horários do domingo $data com as vagas que sobram.
 *
 * @return list<array{hora:string,max:int,ocupados:int,vagas:int}>
 */
function domingoHorarios(PDO $pdo, string $data, int $ignorarVenda = 0): array
{
    $ativo = domingoCondicaoPedidoAtivo('v');
    $stmt = $pdo->prepare(
        "SELECT h.hora, h.max_pedidos,
                (SELECT COUNT(*) FROM vendas v
                  WHERE v.origem = 'loja' AND v.retirada_data = :data AND v.retirada_hora = h.hora
                    AND v.id_venda <> :ignorar AND $ativo) AS ocupados
         FROM domingo_horarios h
         WHERE h.ativo = 1
         ORDER BY h.hora"
    );
    $stmt->execute([':data' => $data, ':ignorar' => $ignorarVenda]);
    $lista = [];
    foreach ($stmt->fetchAll() as $h) {
        $max = (int) $h['max_pedidos'];
        $ocupados = (int) $h['ocupados'];
        $lista[] = ['hora' => domingoRotuloHora($h['hora']), 'max' => $max, 'ocupados' => $ocupados, 'vagas' => max(0, $max - $ocupados)];
    }
    return $lista;
}

/** A venda tem algum item da categoria de carnes do domingo? */
function vendaTemCarneDomingo(PDO $pdo, int $id_venda): bool
{
    $cfg = domingoConfig($pdo);
    if (!$cfg) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM itens_venda iv
         JOIN produto_variacoes pv ON pv.id_produto_variacao = iv.id_produto_variacao
         JOIN produtos p ON p.id_produto = pv.id_produto
         WHERE iv.id_venda = :iv AND p.id_categoria = :cat LIMIT 1'
    );
    $stmt->execute([':iv' => $id_venda, ':cat' => (int) $cfg['id_categoria_carnes']]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Quantidade de cada carne já encomendada para o domingo $data, e o limite.
 *
 * @return list<array{id_produto:int,nome:string,limite:?int,encomendado:int,restante:?int}>
 */
function domingoProducao(PDO $pdo, string $data, int $ignorarVenda = 0): array
{
    $cfg = domingoConfig($pdo) ?? domingoConfigBruta($pdo);
    if (!$cfg || empty($cfg['id_categoria_carnes'])) {
        return [];
    }
    $ativo = domingoCondicaoPedidoAtivo('v');
    $stmt = $pdo->prepare(
        "SELECT p.id_produto, p.nome, p.limite_domingo,
                COALESCE((SELECT SUM(iv.quantidade) FROM itens_venda iv
                          JOIN produto_variacoes pv ON pv.id_produto_variacao = iv.id_produto_variacao
                          JOIN vendas v ON v.id_venda = iv.id_venda
                          WHERE pv.id_produto = p.id_produto AND v.origem = 'loja'
                            AND v.retirada_data = :data AND v.id_venda <> :ignorar AND $ativo), 0) AS encomendado
         FROM produtos p
         WHERE p.id_categoria = :cat AND p.ativo = 1
         ORDER BY p.nome"
    );
    $stmt->execute([':data' => $data, ':ignorar' => $ignorarVenda, ':cat' => (int) $cfg['id_categoria_carnes']]);
    $lista = [];
    foreach ($stmt->fetchAll() as $r) {
        $limite = $r['limite_domingo'] !== null ? (int) $r['limite_domingo'] : null;
        $enc = (int) $r['encomendado'];
        $lista[] = [
            'id_produto' => (int) $r['id_produto'],
            'nome' => $r['nome'],
            'limite' => $limite,
            'encomendado' => $enc,
            'restante' => $limite !== null ? max(0, $limite - $enc) : null,
        ];
    }
    return $lista;
}

/**
 * Confere domingo, horário, prazo, vagas e limite das carnes e grava a
 * retirada na venda. Tudo numa transação travando domingo_config, pra dois
 * clientes não pegarem a última vaga ao mesmo tempo.
 *
 * Venda sem carne: limpa a retirada e devolve null (nada a validar).
 *
 * @return string|null mensagem de erro, ou null se deu certo
 */
function domingoValidarEGravarRetirada(PDO $pdo, int $id_venda, string $data, string $hora): ?string
{
    if (!domingoConfig($pdo)) {
        return null;
    }
    if (!vendaTemCarneDomingo($pdo, $id_venda)) {
        $pdo->prepare('UPDATE vendas SET retirada_data = NULL, retirada_hora = NULL WHERE id_venda = :id')->execute([':id' => $id_venda]);
        return null;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !preg_match('/^\d{2}:\d{2}$/', $hora)) {
        return 'Escolha o domingo e o horário de retirada da carne.';
    }

    $jaEmTransacao = $pdo->inTransaction();
    if (!$jaEmTransacao) {
        $pdo->beginTransaction();
    }
    try {
        $pdo->query('SELECT id FROM domingo_config WHERE id = 1 FOR UPDATE')->fetch();

        $domingoOk = false;
        foreach (domingosDisponiveis($pdo) as $d) {
            if ($d['data'] === $data) {
                $domingoOk = true;
                break;
            }
        }
        if (!$domingoOk) {
            throw new DomainException('As encomendas para esse domingo já fecharam. Escolha outro domingo.');
        }

        $horaOk = null;
        foreach (domingoHorarios($pdo, $data, $id_venda) as $h) {
            if ($h['hora'] === $hora) {
                $horaOk = $h;
                break;
            }
        }
        if (!$horaOk) {
            throw new DomainException('Esse horário de retirada não existe mais. Escolha outro.');
        }
        if ($horaOk['vagas'] <= 0) {
            throw new DomainException('O horário das ' . $hora . ' lotou agora há pouco. Escolha outro horário.');
        }

        // Limite de cada carne no domingo escolhido.
        $stmtCarrinho = $pdo->prepare(
            'SELECT pv.id_produto, SUM(iv.quantidade) AS qtd FROM itens_venda iv
             JOIN produto_variacoes pv ON pv.id_produto_variacao = iv.id_produto_variacao
             WHERE iv.id_venda = :iv GROUP BY pv.id_produto'
        );
        $stmtCarrinho->execute([':iv' => $id_venda]);
        $noCarrinho = [];
        foreach ($stmtCarrinho->fetchAll() as $r) {
            $noCarrinho[(int) $r['id_produto']] = (int) $r['qtd'];
        }
        foreach (domingoProducao($pdo, $data, $id_venda) as $carne) {
            $qtd = $noCarrinho[$carne['id_produto']] ?? 0;
            if ($qtd > 0 && $carne['restante'] !== null && $qtd > $carne['restante']) {
                throw new DomainException($carne['restante'] > 0
                    ? 'Para ' . domingoRotuloData($data) . ' só restam ' . $carne['restante'] . ' de ' . $carne['nome'] . '. Diminua a quantidade ou escolha outro domingo.'
                    : $carne['nome'] . ' esgotou para ' . domingoRotuloData($data) . '. Escolha outro domingo.');
            }
        }

        $pdo->prepare('UPDATE vendas SET retirada_data = :d, retirada_hora = :h WHERE id_venda = :id')
            ->execute([':d' => $data, ':h' => $hora . ':00', ':id' => $id_venda]);

        if (!$jaEmTransacao) {
            $pdo->commit();
        }
        return null;
    } catch (DomainException $e) {
        if (!$jaEmTransacao && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return $e->getMessage();
    } catch (Throwable $e) {
        if (!$jaEmTransacao && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('domingoValidarEGravarRetirada: ' . $e->getMessage());
        return 'Não foi possível reservar o horário agora. Tente novamente.';
    }
}

/** O cliente ainda pode trocar o horário deste pedido? (antes do corte do domingo marcado) */
function domingoPodeMudar(PDO $pdo, array $venda): bool
{
    $cfg = domingoConfig($pdo);
    if (!$cfg || empty($venda['retirada_data']) || $venda['status'] === 'Cancelado') {
        return false;
    }
    if (in_array($venda['status_entrega'] ?? null, ['Pronto', 'Enviado', 'Entregue'], true)) {
        return false;
    }
    return domingoCorte($venda['retirada_data'], $cfg) > domingoAgora();
}

/**
 * Dados prontos para montar os seletores de domingo/horário (checkout e
 * "mudar horário"): cada domingo disponível com seus horários e vagas.
 *
 * @return list<array{data:string,rotulo:string,rotulo_corte:string,horarios:list<array>}>
 */
function domingoOpcoes(PDO $pdo, int $ignorarVenda = 0): array
{
    $opcoes = [];
    foreach (domingosDisponiveis($pdo) as $d) {
        $opcoes[] = [
            'data' => $d['data'],
            'rotulo' => $d['rotulo'],
            'rotulo_corte' => $d['rotulo_corte'],
            'horarios' => domingoHorarios($pdo, $d['data'], $ignorarVenda),
        ];
    }
    return $opcoes;
}
