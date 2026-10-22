<?php

/**
 * PWA (aplicativo instalável) da loja online e do painel admin: manifesto,
 * ícone gerado a partir do logo e aviso "Instalar aplicativo". São dois apps
 * separados ('loja' e 'admin'), cada um com o seu manifesto.
 */

/** Cores do app: [tema (barra do celular), fundo (tela de abertura)]. */
function pwaCores(PDO $pdo, string $app): array
{
    // Preto da logo do Clube da Cerveja na barra do celular e no ícone.
    if ($app === 'admin') {
        return ['#0E0E0E', '#FFFFFF'];
    }
    $c = $pdo->query('SELECT tema, cor_primaria, cor_fundo FROM config_loja WHERE id_config = 1')->fetch() ?: [];
    $tema = $c['tema'] ?? 'escuro';
    if ($tema === 'personalizado' && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $c['cor_primaria']) && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $c['cor_fundo'])) {
        return [$c['cor_primaria'], $c['cor_fundo']];
    }
    return $tema === 'claro' ? ['#141414', '#FFFFFF'] : ['#0E0E0E', '#0E0E0E'];
}

/**
 * Caminho absoluto + data de modificação do logo da loja (o enviado em
 * Aparência ou, sem ele, a logo padrão do Clube da Cerveja), ou null se
 * nenhum arquivo existir.
 */
function pwaLogo(PDO $pdo): ?array
{
    require_once __DIR__ . '/marca.php';
    $arq = logoDaLoja((string) $pdo->query('SELECT logo_arquivo FROM config_loja WHERE id_config = 1')->fetchColumn());
    $caminho = __DIR__ . '/../' . $arq;
    if (!is_file($caminho)) {
        return null;
    }
    return ['caminho' => $caminho, 'mtime' => (int) filemtime($caminho)];
}

/** Imprime as tags do PWA. $app = 'loja' ou 'admin'. Pode ser chamada no <head> ou no <body>. */
function pwaTags(PDO $pdo, string $app): void
{
    $app = $app === 'admin' ? 'admin' : 'loja';
    $versao = (pwaLogo($pdo)['mtime'] ?? 0) . '-' . (int) @filemtime(__DIR__ . '/../manifest.php');
    $tema = pwaCores($pdo, $app)[0];
    $css = (int) @filemtime(__DIR__ . '/../assets/css/pwa.css');
    $js = (int) @filemtime(__DIR__ . '/../assets/js/pwa.js');
    ?>
<link rel="stylesheet" href="/assets/css/pwa.css?v=<?= $css ?>">
<link rel="apple-touch-icon" href="/icone_pwa.php?app=<?= $app ?>&amp;tam=180&amp;v=<?= htmlspecialchars($versao) ?>">
<script src="/assets/js/pwa.js?v=<?= $js ?>" data-sw="/sw.js" data-app="<?= $app ?>" data-manifest="/manifest.php?app=<?= $app ?>&amp;v=<?= htmlspecialchars($versao) ?>" data-tema="<?= htmlspecialchars($tema) ?>" defer></script>
<?php
}
