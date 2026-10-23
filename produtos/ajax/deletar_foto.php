<?php
require_once __DIR__ . '/../../conecta_bd.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirLogin();

$id_foto = (int) ($_POST['id_foto'] ?? 0);
$id_produto = (int) ($_POST['id_produto'] ?? 0);

$stmt = $pdo->prepare('SELECT caminho_arquivo FROM produto_fotos WHERE id_foto = :id AND id_produto = :ip');
$stmt->execute([':id' => $id_foto, ':ip' => $id_produto]);
$foto = $stmt->fetch();

if ($foto) {
    // Só apaga do disco o que foi enviado pelo painel: as fotos que vêm com o
    // código (assets/carnes/) voltariam no próximo deploy de qualquer jeito.
    $caminhoAbsoluto = __DIR__ . '/../../' . $foto['caminho_arquivo'];
    if (str_starts_with($foto['caminho_arquivo'], 'assets/img/produtos/') && is_file($caminhoAbsoluto)) {
        unlink($caminhoAbsoluto);
    }
    $pdo->prepare('DELETE FROM produto_fotos WHERE id_foto = :id')->execute([':id' => $id_foto]);
}

header('Location: /produtos/editar.php?id=' . $id_produto);
exit;
