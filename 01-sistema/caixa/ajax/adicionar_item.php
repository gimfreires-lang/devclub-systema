<?php
require_once __DIR__ . '/../../conecta_bd.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/caixa.php';
exigirLogin();
header('Content-Type: application/json');

$id_venda = (int) ($_POST['id_venda'] ?? 0);
$id_produto_variacao = (int) ($_POST['id_produto_variacao'] ?? 0);
$quantidade = max(1, (int) ($_POST['quantidade'] ?? 1));

$stmtV = $pdo->prepare(
    "SELECT v.id_venda FROM vendas v
     JOIN caixa_sessoes cs ON cs.id_caixa = v.id_caixa
     WHERE v.id_venda = :id AND v.id_usuario = :iu AND v.status = 'Reservado' AND cs.status = 'aberto'"
);
$stmtV->execute([':id' => $id_venda, ':iu' => $_SESSION['id_usuario']]);
if (!$stmtV->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Venda não encontrada ou já finalizada.']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT pv.estoque, pv.estoque_reservado, p.estoque_gerenciado, COALESCE(pv.preco, p.preco_base) AS preco, p.nome AS nome_produto,
            GROUP_CONCAT(vv.valor SEPARATOR " / ") AS descricao_combinacao
     FROM produto_variacoes pv
     JOIN produtos p ON p.id_produto = pv.id_produto
     LEFT JOIN produto_variacao_valores pvv ON pvv.id_produto_variacao = pv.id_produto_variacao
     LEFT JOIN variacao_valores vv ON vv.id_valor = pvv.id_valor
     WHERE pv.id_produto_variacao = :id
     GROUP BY pv.estoque, pv.estoque_reservado, p.estoque_gerenciado, pv.preco, p.preco_base, p.nome'
);
$stmt->execute([':id' => $id_produto_variacao]);
$produto = $stmt->fetch();

if (!$produto) {
    echo json_encode(['success' => false, 'message' => 'Produto não encontrado.']);
    exit;
}

$preco_unit = (float) $produto['preco'];

// Mesmo produto bipado de novo soma na linha que já está no carrinho (2x, 3x...)
// em vez de abrir uma linha nova a cada clique. Só junta se o preço for o mesmo.
$stmtExistente = $pdo->prepare(
    'SELECT id_item, quantidade FROM itens_venda
     WHERE id_venda = :iv AND id_produto_variacao = :ipv AND preco_unit = :preco
     ORDER BY id_item LIMIT 1'
);
$stmtExistente->execute([':iv' => $id_venda, ':ipv' => $id_produto_variacao, ':preco' => $preco_unit]);
$itemExistente = $stmtExistente->fetch();

if ((int) $produto['estoque_gerenciado']) {
    // Conta o que já está nesta venda: antes dava pra bipar além do estoque e a
    // venda só travava no "Finalizar".
    $stmtNoCarrinho = $pdo->prepare('SELECT COALESCE(SUM(quantidade), 0) FROM itens_venda WHERE id_venda = :iv AND id_produto_variacao = :ipv');
    $stmtNoCarrinho->execute([':iv' => $id_venda, ':ipv' => $id_produto_variacao]);
    $jaNoCarrinho = (int) $stmtNoCarrinho->fetchColumn();
    $disponivel = $produto['estoque'] - $produto['estoque_reservado'] - $jaNoCarrinho;
    if ($disponivel < $quantidade) {
        echo json_encode(['success' => false, 'message' => 'Estoque insuficiente (disponível: ' . max(0, $disponivel) . ').']);
        exit;
    }
}

if ($itemExistente) {
    $novaQuantidade = (int) $itemExistente['quantidade'] + $quantidade;
    $pdo->prepare('UPDATE itens_venda SET quantidade = :qtd, subtotal = :subtotal WHERE id_item = :id')
        ->execute([':qtd' => $novaQuantidade, ':subtotal' => $preco_unit * $novaQuantidade, ':id' => $itemExistente['id_item']]);
    recalcularTotalVenda($pdo, $id_venda);
    echo json_encode(['success' => true]);
    exit;
}

$subtotal = $preco_unit * $quantidade;

$pdo->prepare(
    'INSERT INTO itens_venda (id_venda, nome_produto, descricao_combinacao, id_produto_variacao, quantidade, preco_unit, subtotal)
     VALUES (:iv, :nome, :desc, :ipv, :qtd, :preco, :subtotal)'
)->execute([
    ':iv' => $id_venda,
    ':nome' => $produto['nome_produto'],
    ':desc' => $produto['descricao_combinacao'] ?: null,
    ':ipv' => $id_produto_variacao,
    ':qtd' => $quantidade,
    ':preco' => $preco_unit,
    ':subtotal' => $subtotal,
]);

recalcularTotalVenda($pdo, $id_venda);

echo json_encode(['success' => true]);
