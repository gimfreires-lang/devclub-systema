<?php
/**
 * Limite de tentativas no login do painel do lojista (/login.php), contra
 * quem tenta adivinhar a senha. O painel_dev tem a trava própria em
 * includes/dev_seguranca.php.
 *
 * Regra: 5 senhas erradas para o mesmo e-mail, ou 20 vindas do mesmo IP,
 * dentro de 15 minutos, travam novas tentativas até a janela passar.
 * Tempo sempre calculado no SQL (NOW()), como no resto do projeto.
 *
 * Também serve a loja online (login do cliente, código de verificação e
 * "esqueci minha senha"): cada uso passa um $escopo próprio ('loja',
 * 'codigo', 'reset'...), gravado como "escopo|chave" na coluna email. Assim
 * os contadores de um não somam no outro -- nem por e-mail nem por IP (um
 * cliente errando senha no wifi da loja não trava o login do lojista).
 *
 * A tabela é criada sozinha na primeira vez (CREATE TABLE IF NOT EXISTS),
 * então nenhum banco já no ar precisa de passo manual. Se o banco recusar
 * (usuário sem permissão de CREATE), o login continua funcionando sem a trava.
 */

const LOGIN_MAX_POR_EMAIL = 5;
const LOGIN_MAX_POR_IP = 20;
const LOGIN_JANELA_MINUTOS = 15;

function loginTentativasTabelaPronta(PDO $pdo): bool
{
    static $pronta = null;
    if ($pronta !== null) {
        return $pronta;
    }
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS login_tentativas (
                id_tentativa INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(150) NOT NULL,
                ip VARCHAR(45) NOT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_login_tentativas_email (email, criado_em),
                KEY idx_login_tentativas_ip (ip, criado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $pronta = true;
    } catch (Throwable $e) {
        error_log('login_tentativas indisponível: ' . $e->getMessage());
        $pronta = false;
    }
    return $pronta;
}

function loginIpCliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Chave gravada na coluna email: o próprio e-mail no painel, "escopo|chave" nos demais usos. */
function loginChave(string $email, string $escopo): string
{
    $email = mb_strtolower(trim($email));
    return mb_substr($escopo === '' ? $email : $escopo . '|' . $email, 0, 150);
}

/** Filtro SQL que separa as linhas de cada escopo na contagem por IP. */
function loginFiltroEscopo(string $escopo): string
{
    return $escopo === ''
        ? "LOCATE('|', email) = 0"
        // Escopo é sempre um nome fixo do código ('loja', 'codigo'...), nunca entrada do usuário.
        : "email LIKE '" . preg_replace('/[^a-z_]/', '', $escopo) . "|%'";
}

/**
 * Minutos que faltam para liberar, ou 0 se pode tentar. $maxPorChave troca o
 * limite por e-mail/chave (padrão LOGIN_MAX_POR_EMAIL).
 */
function loginMinutosBloqueado(PDO $pdo, string $email, string $escopo = '', ?int $maxPorChave = null): int
{
    if (!loginTentativasTabelaPronta($pdo)) {
        return 0;
    }
    try {
        $janela = (int) LOGIN_JANELA_MINUTOS;
        $sql = "SELECT COUNT(*) AS qtd,
                       TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(MIN(criado_em), INTERVAL {$janela} MINUTE)) AS segundos
                FROM login_tentativas
                WHERE %s = :valor AND %s AND criado_em > DATE_SUB(NOW(), INTERVAL {$janela} MINUTE)";
        $filtro = loginFiltroEscopo($escopo);
        foreach ([['email', loginChave($email, $escopo), $maxPorChave ?? LOGIN_MAX_POR_EMAIL], ['ip', loginIpCliente(), LOGIN_MAX_POR_IP]] as [$coluna, $valor, $limite]) {
            $stmt = $pdo->prepare(sprintf($sql, $coluna, $filtro));
            $stmt->execute([':valor' => $valor]);
            $linha = $stmt->fetch();
            if ($linha && (int) $linha['qtd'] >= $limite) {
                return max(1, (int) ceil(((int) $linha['segundos']) / 60));
            }
        }
    } catch (Throwable $e) {
        error_log('login_tentativas: ' . $e->getMessage());
    }
    return 0;
}

function loginRegistrarFalha(PDO $pdo, string $email, string $escopo = ''): void
{
    if (!loginTentativasTabelaPronta($pdo)) {
        return;
    }
    try {
        $pdo->prepare('INSERT INTO login_tentativas (email, ip) VALUES (:email, :ip)')
            ->execute([':email' => loginChave($email, $escopo), ':ip' => loginIpCliente()]);
        // Faxina: apaga o que já saiu da janela há muito tempo.
        $pdo->exec('DELETE FROM login_tentativas WHERE criado_em < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    } catch (Throwable $e) {
        error_log('login_tentativas: ' . $e->getMessage());
    }
}

function loginLimparFalhas(PDO $pdo, string $email, string $escopo = ''): void
{
    if (!loginTentativasTabelaPronta($pdo)) {
        return;
    }
    try {
        $pdo->prepare('DELETE FROM login_tentativas WHERE email = :email')->execute([':email' => loginChave($email, $escopo)]);
    } catch (Throwable $e) {
        error_log('login_tentativas: ' . $e->getMessage());
    }
}
