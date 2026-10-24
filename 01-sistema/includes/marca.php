<?php

/**
 * Identidade padrão do Clube da Cerveja (conveniência de bebidas e
 * tabacaria, com carnes assadas aos domingos). Usada como fallback quando o
 * lojista ainda não subiu uma logo própria em Aparência ou o nome ainda não
 * foi preenchido no banco — assim a marca aparece mesmo numa instalação nova.
 */

const MARCA_NOME = 'Clube da Cerveja';
const MARCA_SUBTITULO = 'Conveniência e Tabacaria';

// Logo em tamanho cheio (ícone do app, e-mails) e reduzida (cabeçalhos).
// Ficam fora de assets/img de propósito: no Easypanel essa pasta vira um
// volume (README, Parte 9) que começa vazio e esconderia estes arquivos.
const MARCA_LOGO = 'assets/marca/clube-da-cerveja.png';
const MARCA_LOGO_PEQUENA = 'assets/marca/clube-da-cerveja-160.png';
const MARCA_FAVICON = 'assets/marca/favicon.png';

/**
 * Caminho (relativo à raiz do site, sem barra inicial) da logo a exibir:
 * a enviada pelo lojista em Aparência, se o arquivo existir, senão a logo
 * padrão do Clube da Cerveja.
 */
function logoDaLoja(?string $arquivo, bool $pequena = false): string
{
    $arquivo = ltrim((string) $arquivo, '/');
    if ($arquivo !== '' && is_file(__DIR__ . '/../' . $arquivo)) {
        return $arquivo;
    }
    return $pequena ? MARCA_LOGO_PEQUENA : MARCA_LOGO;
}

/**
 * Cor do texto que vai em cima de um fundo na cor $hex (botões, pílulas
 * ativas, banners): escuro em cores claras como o dourado, branco nas
 * escuras. Assim o tema personalizado continua legível com qualquer cor.
 */
function corSobre(string $hex): string
{
    if (!preg_match('/^#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/', $hex, $m)) {
        return '#FFFFFF';
    }
    $luz = (0.299 * hexdec($m[1]) + 0.587 * hexdec($m[2]) + 0.114 * hexdec($m[3])) / 255;
    return $luz > 0.6 ? '#141414' : '#FFFFFF';
}

/** Domingo é dia de carne assada no Clube — usado pra destacar o banner na loja. */
function hojeEDomingoDeChurrasco(): bool
{
    // Horário de Brasília fixo: o timezone do PHP no servidor pode ser UTC.
    return (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('w') === '0';
}
