<?php
require_once __DIR__ . '/../conecta_bd.php';
require_once __DIR__ . '/../includes/auth_cliente.php';
require_once __DIR__ . '/../includes/loja.php';

if (!empty($_SESSION['id_cliente'])) {
    header('Location: /loja/index.php');
    exit;
}

function normalizarEmail(string $email): string
{
    return mb_strtolower(trim($email));
}

// Só primeiro nome não identifica ninguém direito pra loja — exige nome
// completo (pelo menos duas palavras).
function nomeCompleto(string $nome): bool
{
    return count(array_filter(preg_split('/\s+/', trim($nome)))) >= 2;
}

$whatsappLoginHabilitado = whatsappAtivo($pdo);

$erro = '';
$sucesso = '';
$emailMascarado = '';
$destinoMascarado = '';
$etapa = 'email';
$emailNormalizado = '';
$identificador = '';
$tipoIdentificador = 'email';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'verificar_email') {
        $entrada = trim($_POST['identificador'] ?? '');
        $pareceEmail = str_contains($entrada, '@');

        if ($pareceEmail) {
            $emailNormalizado = normalizarEmail($entrada);
            $identificador = $emailNormalizado;
            $tipoIdentificador = 'email';

            if (!filter_var($emailNormalizado, FILTER_VALIDATE_EMAIL)) {
                $erro = 'Informe um e-mail válido.';
                $etapa = 'email';
            } else {
                $stmt = $pdo->prepare('SELECT id_cliente, senha_hash, excluido_em FROM clientes WHERE email = :e');
                $stmt->execute([':e' => $emailNormalizado]);
                $clienteExistente = $stmt->fetch();

                if ($clienteExistente && $clienteExistente['excluido_em'] !== null) {
                    $erro = 'Esse cadastro não está disponível. Fale com a loja.';
                    $etapa = 'email';
                } elseif (!$clienteExistente) {
                    $etapa = 'cadastro';
                } elseif (empty($clienteExistente['senha_hash'])) {
                    $etapa = 'ativar';
                } else {
                    $etapa = 'login';
                }
            }
        } else {
            $whatsappNormalizado = normalizarWhatsapp($entrada);
            $identificador = $whatsappNormalizado;
            $tipoIdentificador = 'whatsapp';

            if ($whatsappNormalizado === '') {
                $erro = $whatsappLoginHabilitado
                    ? 'Informe um WhatsApp válido, com DDD e o 9 na frente (ex: (11) 90000-0000).'
                    : 'Informe um e-mail válido, ou o WhatsApp de uma conta que já existe.';
                $etapa = 'email';
            } else {
                $stmt = $pdo->prepare('SELECT id_cliente, senha_hash, excluido_em, login_whatsapp_bloqueado FROM clientes WHERE whatsapp = :w');
                $stmt->execute([':w' => $whatsappNormalizado]);
                $clienteExistente = $stmt->fetch();

                if ($clienteExistente && $clienteExistente['excluido_em'] !== null) {
                    $erro = 'Esse cadastro não está disponível. Fale com a loja.';
                    $etapa = 'email';
                } elseif ($clienteExistente && (int) $clienteExistente['login_whatsapp_bloqueado'] === 1) {
                    $erro = 'O acesso por WhatsApp foi desativado nesta conta. Entre com o seu e-mail.';
                    $etapa = 'email';
                } elseif (!$clienteExistente && !$whatsappLoginHabilitado) {
                    // Login por número de quem já tem conta continua valendo com o recurso
                    // desligado, mas cadastro novo só com número precisa dele ligado.
                    $erro = 'Esse WhatsApp não tem cadastro. Cadastre-se com seu e-mail.';
                    $etapa = 'email';
                } elseif (!$clienteExistente) {
                    // Cadastro só com o número: sem e-mail, o cliente entra e valida
                    // o WhatsApp depois (código enviado logo após o cadastro).
                    $etapa = 'cadastro';
                } elseif (empty($clienteExistente['senha_hash'])) {
                    $etapa = 'ativar';
                } else {
                    $etapa = 'login';
                }
            }
        }
    } elseif ($acao === 'cadastro' && $whatsappLoginHabilitado && ($_POST['tipo_identificador'] ?? '') === 'whatsapp') {
        // Cadastro começado pelo número de WhatsApp (sem e-mail). O número chega
        // de um campo readonly, mas um POST direto poderia trazer qualquer coisa --
        // normaliza e checa duplicidade de novo, igual ao caminho por e-mail.
        $whatsappNormalizado = normalizarWhatsapp($_POST['whatsapp'] ?? '');
        $nome = trim($_POST['nome'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $tipoIdentificador = 'whatsapp';
        $identificador = $whatsappNormalizado;

        if ($whatsappNormalizado === '') {
            $erro = 'Informe um WhatsApp válido, com DDD e o 9 na frente (ex: (11) 90000-0000).';
            $etapa = 'email';
        } elseif (!nomeCompleto($nome)) {
            $erro = 'Informe seu nome completo (nome e sobrenome).';
            $etapa = 'cadastro';
        } elseif (strlen($senha) < 6) {
            $erro = 'A senha precisa ter pelo menos 6 caracteres.';
            $etapa = 'cadastro';
        } else {
            $stmtWhats = $pdo->prepare('SELECT id_cliente FROM clientes WHERE whatsapp = :w');
            $stmtWhats->execute([':w' => $whatsappNormalizado]);

            if ($stmtWhats->fetch()) {
                $erro = 'Esse WhatsApp já tem cadastro. Tente entrar em vez de se cadastrar.';
                $etapa = 'email';
            } else {
                try {
                    $stmt = $pdo->prepare('INSERT INTO clientes (nome, whatsapp, senha_hash) VALUES (:nome, :whatsapp, :senha)');
                    $stmt->execute([':nome' => $nome, ':whatsapp' => $whatsappNormalizado, ':senha' => password_hash($senha, PASSWORD_DEFAULT)]);
                    $idClienteNovo = (int) $pdo->lastInsertId();
                    $_SESSION['id_cliente'] = $idClienteNovo;
                    $_SESSION['nome_cliente'] = $nome;
                    session_regenerate_id(true);
                    // Mesmo papel da verificação de e-mail no cadastro por e-mail: o
                    // cliente já navega, só fica sem o carrinho até validar o contato.
                    dispararVerificacaoWhatsapp($pdo, $idClienteNovo, $whatsappNormalizado, $nome);
                    header('Location: /loja/index.php');
                    exit;
                } catch (PDOException $e) {
                    error_log('PDOException in cadastro (whatsapp): ' . $e->getMessage());
                    $erro = 'Erro ao cadastrar. Tente novamente.';
                    $etapa = 'email';
                }
            }
        }
    } elseif ($acao === 'cadastro') {
        // O e-mail chega de um campo hidden que esta própria página renderizou já
        // normalizado, mas um POST direto (sem passar por verificar_email) poderia trazer
        // outro formato e escapar da checagem de duplicidade. Normaliza de novo, igual ao
        // verificar_email.
        $emailNormalizado = normalizarEmail($_POST['email'] ?? '');
        $whatsappNormalizado = normalizarWhatsapp($_POST['whatsapp'] ?? '');
        $nome = trim($_POST['nome'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (!filter_var($emailNormalizado, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido. Volte e informe um e-mail válido.';
            $etapa = 'email';
        } elseif (!nomeCompleto($nome)) {
            $erro = 'Informe seu nome completo (nome e sobrenome).';
            $etapa = 'cadastro';
        } elseif ($whatsappNormalizado === '') {
            $erro = 'Informe um WhatsApp válido, com DDD e o 9 na frente (ex: (11) 90000-0000).';
            $etapa = 'cadastro';
        } elseif (strlen($senha) < 6) {
            $erro = 'A senha precisa ter pelo menos 6 caracteres.';
            $etapa = 'cadastro';
        } else {
            // Checagem prévia (em vez de só confiar na constraint UNIQUE do banco) pra
            // poder avisar exatamente qual dos dois campos já está em uso — email e
            // whatsapp são únicos e independentes.
            $stmtEmail = $pdo->prepare('SELECT id_cliente FROM clientes WHERE email = :e');
            $stmtEmail->execute([':e' => $emailNormalizado]);
            $stmtWhats = $pdo->prepare('SELECT id_cliente FROM clientes WHERE whatsapp = :w');
            $stmtWhats->execute([':w' => $whatsappNormalizado]);

            if ($stmtEmail->fetch()) {
                $erro = 'Esse e-mail já tem cadastro. Tente entrar em vez de se cadastrar.';
                $etapa = 'email';
            } elseif ($stmtWhats->fetch()) {
                $erro = 'Esse WhatsApp já tem um cadastro em nosso sistema, mas sem esse e-mail vinculado. Fale com a loja pra adicionarmos seu e-mail e você conseguir entrar.';
                $etapa = 'email';
            } else {
                try {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('INSERT INTO clientes (nome, whatsapp, email, senha_hash) VALUES (:nome, :whatsapp, :email, :senha)');
                    $stmt->execute([':nome' => $nome, ':whatsapp' => $whatsappNormalizado, ':email' => $emailNormalizado, ':senha' => $hash]);
                    $idClienteNovo = (int) $pdo->lastInsertId();
                    $_SESSION['id_cliente'] = $idClienteNovo;
                    $_SESSION['nome_cliente'] = $nome;
                    session_regenerate_id(true);
                    // Cliente já entra navegando normalmente — só fica sem poder usar o
                    // carrinho até confirmar que o e-mail é de verdade dele.
                    dispararVerificacaoEmail($pdo, $idClienteNovo, $emailNormalizado, $nome);
                    header('Location: /loja/index.php');
                    exit;
                } catch (PDOException $e) {
                    // Corrida rara (duas abas cadastrando ao mesmo tempo) — a checagem prévia
                    // acima cobre o caso comum, isso aqui é só a rede de segurança final.
                    error_log('PDOException in cadastro: ' . $e->getMessage());
                    $erro = 'Erro ao cadastrar. Tente novamente.';
                    $etapa = 'email';
                }
            }
        }
    } elseif ($acao === 'ativar_enviar' || $acao === 'ativar') {
        // Conta criada pelo lojista no PDV, ainda sem senha. Antes, quem digitasse o
        // e-mail/WhatsApp de alguém criava a senha e entrava na conta dele (vendo
        // débitos e pedidos). Agora só cria a senha quem recebe o código no contato
        // cadastrado: ativar_enviar manda o código, ativar confere e grava a senha.
        $tipoIdentificador = ($_POST['tipo_identificador'] ?? '') === 'whatsapp' ? 'whatsapp' : 'email';
        $identificador = trim($_POST['identificador'] ?? '');
        $cliente = buscarClienteParaAtivar($pdo, $tipoIdentificador, $identificador);
        $canal = $cliente ? canalAtivacao($pdo, $cliente, $tipoIdentificador) : null;
        $destinoMascarado = $canal ? $canal['destino'] : '';

        if (!$cliente) {
            $erro = 'Não foi possível ativar a conta. Verifique os dados.';
            $etapa = 'email';
        } elseif (!$canal) {
            $etapa = 'ativar'; // a tela 'ativar' já explica que precisa falar com a loja
        } elseif ($acao === 'ativar_enviar') {
            $etapa = 'ativar_codigo';
            $chaveEnvio = 'envio:' . $cliente['id_cliente'];
            if (($espera = loginMinutosBloqueado($pdo, $chaveEnvio, 'reset', 3)) > 0) {
                $erro = "Já enviamos códigos demais pra essa conta. Confira o código recebido ou tente de novo em {$espera} minuto" . ($espera === 1 ? '' : 's') . '.';
            } else {
                loginRegistrarFalha($pdo, $chaveEnvio, 'reset');
                $envio = $canal['tipo'] === 'whatsapp'
                    ? reenviarCodigoVerificacaoWhatsapp($pdo, (int) $cliente['id_cliente'], $cliente['whatsapp'], $cliente['nome'])
                    : reenviarCodigoVerificacaoEmail($pdo, (int) $cliente['id_cliente'], $cliente['email'], $cliente['nome']);
                if ($envio['success']) {
                    $sucesso = 'Enviamos um código de 6 dígitos para ' . $destinoMascarado . '.' . ($canal['tipo'] === 'email' ? ' Confira a caixa de entrada e o spam.' : '');
                } else {
                    $erro = $canal['tipo'] === 'whatsapp' && str_starts_with((string) $envio['message'], 'Espera')
                        ? $envio['message']
                        : 'Não conseguimos enviar o código agora. Tente de novo em alguns minutos ou fale com a loja.';
                }
            }
        } else {
            $etapa = 'ativar_codigo';
            $senha = $_POST['senha'] ?? '';
            if (strlen($senha) < 6) {
                $erro = 'A senha precisa ter pelo menos 6 caracteres.';
            } else {
                $conferencia = conferirCodigoCliente($pdo, (int) $cliente['id_cliente'], $canal['tipo'], (string) ($_POST['codigo'] ?? ''));
                if (!$conferencia['ok']) {
                    $erro = $conferencia['message'];
                } else {
                    // O código provou que o contato é dele: já fica verificado (carrinho liberado).
                    $marcarVerificado = $canal['tipo'] === 'whatsapp'
                        ? 'whatsapp_verificado_em = COALESCE(whatsapp_verificado_em, NOW()), token_verificacao_whatsapp = NULL, token_verificacao_whatsapp_expira_em = NULL'
                        : 'email_verificado_em = COALESCE(email_verificado_em, NOW()), token_verificacao_email = NULL, token_verificacao_expira_em = NULL';
                    $stmt = $pdo->prepare("UPDATE clientes SET senha_hash = :senha, $marcarVerificado WHERE id_cliente = :id AND senha_hash IS NULL AND excluido_em IS NULL");
                    $stmt->execute([':senha' => password_hash($senha, PASSWORD_DEFAULT), ':id' => (int) $cliente['id_cliente']]);

                    if ($stmt->rowCount() > 0) {
                        $_SESSION['id_cliente'] = (int) $cliente['id_cliente'];
                        $_SESSION['nome_cliente'] = $cliente['nome'];
                        session_regenerate_id(true);
                        header('Location: /loja/index.php');
                        exit;
                    }
                    $erro = 'Não foi possível ativar a conta. Verifique os dados.';
                    $etapa = 'email';
                }
            }
        }
    } elseif ($acao === 'login') {
        $tipoIdentificador = ($_POST['tipo_identificador'] ?? '') === 'whatsapp' ? 'whatsapp' : 'email';
        $identificador = trim($_POST['identificador'] ?? '');
        $coluna = $tipoIdentificador === 'whatsapp' ? 'whatsapp' : 'email';
        $senha = $_POST['senha'] ?? '';

        // Limite de tentativas (5 por conta / 20 por IP em 15 min), o mesmo do
        // painel do lojista -- sem isso, um robô testaria senhas à vontade.
        $minutosBloqueado = loginMinutosBloqueado($pdo, $tipoIdentificador . ':' . $identificador, 'loja');
        if ($minutosBloqueado > 0) {
            $erro = "Muitas tentativas com senha errada. Tente de novo em {$minutosBloqueado} minuto" . ($minutosBloqueado === 1 ? '' : 's') . ', ou use "Esqueci minha senha".';
            $etapa = 'login';
            $cliente = null;
        } else {
            $stmt = $pdo->prepare("SELECT id_cliente, nome, email, email_verificado_em, senha_hash, excluido_em, login_whatsapp_bloqueado FROM clientes WHERE $coluna = :id");
            $stmt->execute([':id' => $identificador]);
            $cliente = $stmt->fetch();
        }

        // senha_hash pode ser NULL (cliente criado pelo PDV em clientes/novo.php e nunca
        // ativado na loja) — password_verify() com NULL dispara deprecation/warning.
        if ($cliente && $cliente['excluido_em'] === null && !($tipoIdentificador === 'whatsapp' && (int) $cliente['login_whatsapp_bloqueado'] === 1) && $cliente['senha_hash'] !== null && password_verify($senha, $cliente['senha_hash'])) {
            loginLimparFalhas($pdo, $tipoIdentificador . ':' . $identificador, 'loja');
            $_SESSION['id_cliente'] = (int) $cliente['id_cliente'];
            $_SESSION['nome_cliente'] = $cliente['nome'];
            session_regenerate_id(true);
            // E-mail ainda não confirmado: manda o código já no login. Antes só o
            // cadastro mandava -- se aquele e-mail falhou ou o código venceu (30 min),
            // o cliente entrava, via "Enviamos um código" e nada chegava.
            if (!empty($cliente['email']) && $cliente['email_verificado_em'] === null) {
                reenviarCodigoVerificacaoEmail($pdo, (int) $cliente['id_cliente'], $cliente['email'], $cliente['nome']);
            }
            header('Location: /loja/index.php');
            exit;
        }

        if ($minutosBloqueado === 0) {
            loginRegistrarFalha($pdo, $tipoIdentificador . ':' . $identificador, 'loja');
            $erro = $tipoIdentificador === 'whatsapp' ? 'WhatsApp ou senha inválidos.' : 'E-mail ou senha inválidos.';
        }
        $etapa = 'login';
    } elseif ($acao === 'esqueci_enviar') {
        // "Esqueci minha senha", passo 1: manda um código pro e-mail da conta.
        // Funciona também pra quem entra pelo WhatsApp, desde que a conta tenha e-mail.
        $tipoIdentificador = ($_POST['tipo_identificador'] ?? '') === 'whatsapp' ? 'whatsapp' : 'email';
        $identificador = trim($_POST['identificador'] ?? '');
        $coluna = $tipoIdentificador === 'whatsapp' ? 'whatsapp' : 'email';

        $stmt = $pdo->prepare("SELECT id_cliente, nome, email FROM clientes WHERE $coluna = :id AND excluido_em IS NULL AND senha_hash IS NOT NULL");
        $stmt->execute([':id' => $identificador]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            $erro = 'Não encontramos essa conta. Volte e confira o que digitou.';
            $etapa = 'email';
        } elseif (empty($cliente['email'])) {
            $erro = 'Essa conta não tem e-mail cadastrado pra receber o código. Fale com a loja pelo WhatsApp pra redefinir sua senha.';
            $etapa = 'login';
        } elseif (($espera = loginMinutosBloqueado($pdo, 'envio:' . $cliente['id_cliente'], 'reset', 3)) > 0) {
            // Até 3 envios a cada 15 min por conta: evita lotar a caixa de alguém.
            $erro = "Já enviamos códigos demais pra essa conta. Confira seu e-mail (e o spam) ou tente de novo em {$espera} minuto" . ($espera === 1 ? '' : 's') . '.';
            $etapa = 'redefinir';
            $emailMascarado = mascararEmail($cliente['email']);
        } else {
            loginRegistrarFalha($pdo, 'envio:' . $cliente['id_cliente'], 'reset');
            $envio = enviarCodigoRedefinicaoSenha($pdo, (int) $cliente['id_cliente'], $cliente['email'], $cliente['nome']);
            $emailMascarado = mascararEmail($cliente['email']);
            if ($envio['success']) {
                $sucesso = "Enviamos um código de 6 dígitos para {$emailMascarado}. Confira a caixa de entrada e o spam.";
            } else {
                $erro = 'Não conseguimos enviar o e-mail agora. Tente de novo em alguns minutos ou fale com a loja.';
            }
            $etapa = 'redefinir';
        }
    } elseif ($acao === 'esqueci_redefinir') {
        // "Esqueci minha senha", passo 2: confere o código e grava a senha nova.
        $tipoIdentificador = ($_POST['tipo_identificador'] ?? '') === 'whatsapp' ? 'whatsapp' : 'email';
        $identificador = trim($_POST['identificador'] ?? '');
        $coluna = $tipoIdentificador === 'whatsapp' ? 'whatsapp' : 'email';
        $novaSenha = $_POST['senha'] ?? '';
        $etapa = 'redefinir';

        $stmt = $pdo->prepare("SELECT id_cliente, nome, email FROM clientes WHERE $coluna = :id AND excluido_em IS NULL AND senha_hash IS NOT NULL");
        $stmt->execute([':id' => $identificador]);
        $cliente = $stmt->fetch();
        $emailMascarado = $cliente && $cliente['email'] ? mascararEmail($cliente['email']) : '';

        if (!$cliente || empty($cliente['email'])) {
            $erro = 'Não encontramos essa conta. Comece de novo.';
            $etapa = 'email';
        } elseif (strlen($novaSenha) < 6) {
            $erro = 'A senha precisa ter pelo menos 6 caracteres.';
        } else {
            $conferencia = conferirCodigoCliente($pdo, (int) $cliente['id_cliente'], 'email', (string) ($_POST['codigo'] ?? ''));
            if (!$conferencia['ok']) {
                $erro = $conferencia['message'];
            } else {
                // O código chegou no e-mail da conta: isso também confirma o e-mail.
                $pdo->prepare(
                    'UPDATE clientes SET senha_hash = :senha, email_verificado_em = COALESCE(email_verificado_em, NOW()),
                            token_verificacao_email = NULL, token_verificacao_expira_em = NULL
                     WHERE id_cliente = :id'
                )->execute([':senha' => password_hash($novaSenha, PASSWORD_DEFAULT), ':id' => (int) $cliente['id_cliente']]);
                loginLimparFalhas($pdo, $tipoIdentificador . ':' . $identificador, 'loja');

                $_SESSION['id_cliente'] = (int) $cliente['id_cliente'];
                $_SESSION['nome_cliente'] = $cliente['nome'];
                session_regenerate_id(true);
                header('Location: /loja/index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Entrar</title></head>
<body>
<?php require __DIR__ . '/../includes/loja_header.php'; ?>
    <div class="auth-card">
    <?php if ($erro): ?><p class="alert alert-erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
    <?php if ($sucesso): ?><p class="alert alert-sucesso"><?= htmlspecialchars($sucesso) ?></p><?php endif; ?>

    <?php if ($etapa === 'email'): ?>
    <h1>Entrar na loja</h1>
    <form method="post">
        <input type="hidden" name="acao" value="verificar_email">
        <label>E-mail ou WhatsApp<input type="text" name="identificador" id="campo-identificador" required placeholder="voce@email.com ou (11) 90000-0000" value="<?= htmlspecialchars($identificador) ?>"></label>
        <button type="submit" class="btn-bloco">Continuar</button>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        ativarMascaraIdentificadorLogin(document.getElementById('campo-identificador'));
    });
    </script>
    <?php elseif ($etapa === 'cadastro'): ?>
    <h2>Complete seu cadastro</h2>
    <p class="alert alert-erro" id="erro-cadastro" hidden></p>
    <form method="post" id="form-cadastro">
        <input type="hidden" name="acao" value="cadastro">
        <?php if ($tipoIdentificador === 'whatsapp'): ?>
        <input type="hidden" name="tipo_identificador" value="whatsapp">
        <?php else: ?>
        <input type="hidden" name="email" value="<?= htmlspecialchars($emailNormalizado) ?>">
        <?php endif; ?>
        <label>Nome completo<input type="text" name="nome" id="campo-nome" required></label>
        <label>WhatsApp (com DDD)<input type="text" name="whatsapp" id="campo-whatsapp" required placeholder="(11) 98765-4321" inputmode="numeric" maxlength="16"<?= $tipoIdentificador === 'whatsapp' ? ' readonly value="' . htmlspecialchars(formatarWhatsappParaEdicao($identificador)) . '"' : '' ?>></label>
        <?php if ($tipoIdentificador === 'whatsapp'): ?><p style="margin-top:-8px; font-size:0.85rem; color:var(--cor-texto-suave);">Esse é o número que você digitou. <a href="/loja/cadastro.php">Trocar</a></p><?php endif; ?>
        <label>Crie uma senha<input type="password" name="senha" id="campo-senha" required minlength="6"></label>
        <p class="aviso-privacidade">Ao se cadastrar, você confirma que tem 18 anos ou mais e concorda com a <a href="/loja/privacidade.php" target="_blank" rel="noopener">política de privacidade</a>.</p>
        <button type="submit" class="btn-bloco">Cadastrar</button>
    </form>
    <script>
    // Este script roda inline (sem defer) durante o parse do HTML, então
    // executa ANTES do loja.js (carregado com defer) — sem esperar
    // DOMContentLoaded, ativarMascaraTelefone()/ativarFeedbackSenha() ainda
    // não existiriam nesse ponto.
    document.addEventListener('DOMContentLoaded', function () {
        const campoWhatsapp = document.getElementById('campo-whatsapp');
        ativarMascaraTelefoneComNoveAutomatico(campoWhatsapp);

        const campoSenha = document.getElementById('campo-senha');
        ativarFeedbackSenha(campoSenha);

        function contarNomes(nome) {
            return nome.trim().split(/\s+/).filter(Boolean).length;
        }

        const erroEl = document.getElementById('erro-cadastro');
        document.getElementById('form-cadastro').addEventListener('submit', function (e) {
            const nome = document.getElementById('campo-nome').value;
            const digitosWhatsapp = campoWhatsapp.value.replace(/\D/g, '');
            const senha = campoSenha.value;
            let mensagem = '';

            if (contarNomes(nome) < 2) {
                mensagem = 'Informe seu nome completo (nome e sobrenome).';
            } else if (digitosWhatsapp.length !== 11 || digitosWhatsapp[2] !== '9') {
                mensagem = 'Informe um WhatsApp válido, com DDD e o 9 na frente (ex: (11) 90000-0000).';
            } else if (senha.length < 6) {
                mensagem = 'A senha precisa ter pelo menos 6 caracteres.';
            }

            if (mensagem) {
                e.preventDefault();
                erroEl.textContent = mensagem;
                erroEl.hidden = false;
            } else {
                erroEl.hidden = true;
            }
        });
    });
    </script>
    <?php elseif ($etapa === 'ativar'): ?>
    <?php
    $clienteAtivar = $clienteAtivar ?? buscarClienteParaAtivar($pdo, $tipoIdentificador, $identificador);
    $canalAtivar = $clienteAtivar ? canalAtivacao($pdo, $clienteAtivar, $tipoIdentificador) : null;
    ?>
    <h2>Ativar minha conta</h2>
    <?php if ($canalAtivar): ?>
    <p>Encontramos seu cadastro. Pra confirmar que a conta é sua, vamos mandar um código de 6 dígitos para <strong><?= htmlspecialchars($canalAtivar['destino']) ?></strong>.</p>
    <form method="post">
        <input type="hidden" name="acao" value="ativar_enviar">
        <input type="hidden" name="identificador" value="<?= htmlspecialchars($identificador) ?>">
        <input type="hidden" name="tipo_identificador" value="<?= htmlspecialchars($tipoIdentificador) ?>">
        <button type="submit" class="btn-bloco">Enviar código</button>
    </form>
    <?php else: ?>
    <p>Encontramos seu cadastro, mas ele não tem um contato que a gente consiga usar pra confirmar que é você. Fale com a loja pelo WhatsApp pra ativar seu acesso.</p>
    <?php endif; ?>
    <?php elseif ($etapa === 'ativar_codigo'): ?>
    <h2>Ativar minha conta</h2>
    <?php if ($destinoMascarado): ?><p style="color:var(--cor-texto-suave); font-size:0.9rem;">Digite o código enviado para <strong><?= htmlspecialchars($destinoMascarado) ?></strong> e crie sua senha. O código vale por 30 minutos.</p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="acao" value="ativar">
        <input type="hidden" name="identificador" value="<?= htmlspecialchars($identificador) ?>">
        <input type="hidden" name="tipo_identificador" value="<?= htmlspecialchars($tipoIdentificador) ?>">
        <label>Código<input type="text" name="codigo" inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code" required></label>
        <label>Crie uma senha<input type="password" name="senha" required minlength="6" autocomplete="new-password"></label>
        <button type="submit" class="btn-bloco">Ativar e entrar</button>
    </form>
    <form method="post" style="text-align:center; margin-top:14px;">
        <input type="hidden" name="acao" value="ativar_enviar">
        <input type="hidden" name="identificador" value="<?= htmlspecialchars($identificador) ?>">
        <input type="hidden" name="tipo_identificador" value="<?= htmlspecialchars($tipoIdentificador) ?>">
        <button type="submit" class="btn-texto" style="padding:0;">Não recebi, enviar outro código</button>
    </form>
    <?php elseif ($etapa === 'login'): ?>
    <h2>Entrar</h2>
    <form method="post">
        <input type="hidden" name="acao" value="login">
        <input type="hidden" name="identificador" value="<?= htmlspecialchars($identificador) ?>">
        <input type="hidden" name="tipo_identificador" value="<?= htmlspecialchars($tipoIdentificador) ?>">
        <label>Senha<input type="password" name="senha" required></label>
        <button type="submit" class="btn-bloco">Entrar</button>
    </form>
    <form method="post" style="text-align:center; margin-top:14px;">
        <input type="hidden" name="acao" value="esqueci_enviar">
        <input type="hidden" name="identificador" value="<?= htmlspecialchars($identificador) ?>">
        <input type="hidden" name="tipo_identificador" value="<?= htmlspecialchars($tipoIdentificador) ?>">
        <button type="submit" class="btn-texto" style="padding:0;">Esqueci minha senha</button>
    </form>
    <?php elseif ($etapa === 'redefinir'): ?>
    <h2>Criar nova senha</h2>
    <?php if ($emailMascarado): ?><p style="color:var(--cor-texto-suave); font-size:0.9rem;">Digite o código que enviamos para <strong><?= htmlspecialchars($emailMascarado) ?></strong> e escolha uma senha nova. O código vale por 30 minutos.</p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="acao" value="esqueci_redefinir">
        <input type="hidden" name="identificador" value="<?= htmlspecialchars($identificador) ?>">
        <input type="hidden" name="tipo_identificador" value="<?= htmlspecialchars($tipoIdentificador) ?>">
        <label>Código<input type="text" name="codigo" inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code" required></label>
        <label>Nova senha<input type="password" name="senha" required minlength="6" autocomplete="new-password"></label>
        <button type="submit" class="btn-bloco">Salvar nova senha e entrar</button>
    </form>
    <form method="post" style="text-align:center; margin-top:14px;">
        <input type="hidden" name="acao" value="esqueci_enviar">
        <input type="hidden" name="identificador" value="<?= htmlspecialchars($identificador) ?>">
        <input type="hidden" name="tipo_identificador" value="<?= htmlspecialchars($tipoIdentificador) ?>">
        <button type="submit" class="btn-texto" style="padding:0;">Não recebi o e-mail, enviar outro código</button>
    </form>
    <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/../includes/loja_footer.php'; ?>
</body>
</html>
