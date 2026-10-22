<?php

/**
 * Lê config_loja e imprime as variáveis CSS de cor do tema ativo, dentro
 * de um <style>. Os temas claro/escuro têm paleta fixa (ignoram as cores
 * salvas no banco) — só o personalizado usa as 4 cores escolhidas pelo
 * lojista. Cores derivadas (bordas, hover, etc) ficam em assets/css/loja.css,
 * calculadas via color-mix() a partir dessas 4 — nunca aqui.
 */
require_once __DIR__ . '/marca.php';

function imprimirVariaveisTema(PDO $pdo): void
{
    $config = $pdo->query('SELECT tema, cor_primaria, cor_secundaria, cor_fundo, cor_texto FROM config_loja WHERE id_config = 1')->fetch();
    $tema = $config['tema'] ?? 'escuro';

    // Paletas do Clube da Cerveja, tiradas da logo: dourado das letras e
    // estrelas, prata do escudo metálico, preto do fundo. O escuro é o
    // padrão porque é o que mais se parece com a logo; no claro o dourado
    // vira secundária, porque texto dourado em fundo branco fica ilegível.
    $paletasFixas = [
        'claro' => [
            'primaria' => '#141414',
            'secundaria' => '#F2B705',
            'fundo' => '#FFFFFF',
            'texto' => '#141414',
        ],
        'escuro' => [
            'primaria' => '#F2B705',
            'secundaria' => '#B8BCC2',
            'fundo' => '#0E0E0E',
            'texto' => '#F5F5F5',
        ],
    ];

    if ($tema === 'personalizado') {
        $cores = [
            'primaria' => $config['cor_primaria'],
            'secundaria' => $config['cor_secundaria'],
            'fundo' => $config['cor_fundo'],
            'texto' => $config['cor_texto'],
        ];
    } else {
        $cores = $paletasFixas[$tema] ?? $paletasFixas['escuro'];
    }
    ?>
    <style>
        :root {
            --cor-primaria: <?= htmlspecialchars($cores['primaria']) ?>;
            --cor-secundaria: <?= htmlspecialchars($cores['secundaria']) ?>;
            --cor-fundo: <?= htmlspecialchars($cores['fundo']) ?>;
            --cor-texto: <?= htmlspecialchars($cores['texto']) ?>;
            --cor-sobre-primaria: <?= corSobre((string) $cores['primaria']) ?>;
            --cor-sobre-secundaria: <?= corSobre((string) $cores['secundaria']) ?>;
        }
    </style>
    <?php
}
