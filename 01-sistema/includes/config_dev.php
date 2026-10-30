<?php

/**
 * Nome do sistema exibido (cabeçalho do admin
 * e tela de login) — configurável pelo painel_dev. Cai no nome padrão se a
 * linha ainda não tiver sido preenchida.
 */
function nomeDoSistema(PDO $pdo): string
{
    $nome = $pdo->query('SELECT nome_sistema FROM config_dev WHERE id_config = 1')->fetchColumn();
    return ($nome !== false && $nome !== null && $nome !== '') ? $nome : 'Clube da Cerveja';
}

/**
 * Linha inteira de config_dev — usada pelo próprio painel_dev pra preencher
 * o formulário com os valores salvos.
 */
function buscarConfigDev(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM config_dev WHERE id_config = 1')->fetch() ?: [];
}

/**
 * Crédito "Desenvolvido por ..." do rodapé da loja — cada desenvolvedor que
 * instalar este sistema pra outro cliente pode trocar pelo próprio
 * nome/link no painel_dev; em branco mostra gimfreires.tech.
 */
function creditoRodape(PDO $pdo): array
{
    $config = buscarConfigDev($pdo);
    $link = trim((string) ($config['footer_link'] ?? ''));
    if ($link === '') {
        $link = 'https://gimfreires.tech';
    } elseif (!preg_match('#^https?://#i', $link)) {
        // Endereço digitado sem o protocolo certo (ex.: "htpps://gimfreires.tech" ou
        // "gimfreires.tech"): o navegador não abria o link. Remove o que vier antes
        // do domínio e põe https://.
        $link = 'https://' . preg_replace('#^([a-z]*:/*|/+)#i', '', $link);
    }
    return [
        'nome' => !empty($config['footer_nome']) ? $config['footer_nome'] : 'gimfreires.tech',
        'link' => $link,
    ];
}
