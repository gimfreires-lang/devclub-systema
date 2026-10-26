<?php
/**
 * Política de privacidade da loja (LGPD, Lei 13.709/2018). Nome, endereço,
 * WhatsApp e e-mail vêm de Configurações > Aparência, então a página se
 * mantém certa quando o lojista muda esses dados.
 */
require_once __DIR__ . '/../conecta_bd.php';
require_once __DIR__ . '/../includes/auth_cliente.php';
require_once __DIR__ . '/../includes/loja.php';
require_once __DIR__ . '/../includes/marca.php';

$dadosLoja = $pdo->query('SELECT nome_loja, endereco_loja, whatsapp_loja, email_loja FROM config_loja WHERE id_config = 1')->fetch() ?: [];
$nomeLoja = $dadosLoja['nome_loja'] ?: MARCA_NOME;
$enderecoLoja = $dadosLoja['endereco_loja'] ?? '';
$whatsLoja = !empty($dadosLoja['whatsapp_loja']) ? formatarWhatsappExibicao($dadosLoja['whatsapp_loja']) : '';
$emailLoja = $dadosLoja['email_loja'] ?? '';
$ATUALIZADA_EM = '26/10/2026';
$h = fn($v) => htmlspecialchars((string) $v);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Política de privacidade — <?= $h($nomeLoja) ?></title>
<meta name="description" content="Como o <?= $h($nomeLoja) ?> coleta, usa e protege os seus dados pessoais.">
</head>
<body>
<?php require __DIR__ . '/../includes/loja_header.php'; ?>
    <article class="texto-legal">
        <div class="page-title">
            <h1>Política de privacidade</h1>
            <p>Atualizada em <?= $ATUALIZADA_EM ?></p>
        </div>

        <p>Esta política explica quais dados pessoais o <strong><?= $h($nomeLoja) ?></strong> coleta quando você usa a loja online, para que eles servem e quais são os seus direitos, conforme a Lei Geral de Proteção de Dados (Lei 13.709/2018).</p>

        <h2>Quem cuida dos seus dados</h2>
        <p>O responsável pelos dados (controlador) é o <?= $h($nomeLoja) ?><?= $enderecoLoja ? ', localizado em ' . $h($enderecoLoja) : '' ?>. Para qualquer assunto sobre privacidade, fale com a gente pelos contatos do fim desta página.</p>

        <h2>Quais dados coletamos</h2>
        <ul>
            <li><strong>Cadastro:</strong> nome, e-mail, número de WhatsApp e senha (guardada de forma criptografada; ninguém da loja consegue ver).</li>
            <li><strong>Pedidos:</strong> produtos comprados, valores, forma de pagamento, endereço de entrega e CEP, quando você escolhe entrega.</li>
            <li><strong>Crédito na loja:</strong> limite, compras a prazo e pagamentos, quando você usa a linha de crédito.</li>
            <li><strong>Uso do site:</strong> só o necessário para o site funcionar, como o cookie de sessão.</li>
        </ul>
        <p>Não guardamos dados de cartão. O pagamento por Pix ou cartão é feito diretamente no ambiente do Mercado Pago.</p>

        <h2>Para que usamos</h2>
        <ul>
            <li>Criar e manter a sua conta e confirmar o seu e-mail ou WhatsApp.</li>
            <li>Processar, preparar, entregar e acompanhar os seus pedidos, inclusive as encomendas de carne de domingo.</li>
            <li>Avisar sobre o andamento do pedido e responder ao seu contato.</li>
            <li>Emitir nota fiscal e cumprir obrigações legais e fiscais.</li>
            <li>Proteger a sua conta e a loja contra fraudes e acessos indevidos.</li>
        </ul>
        <p>As bases legais são a execução do contrato de compra, o cumprimento de obrigação legal e o legítimo interesse em manter o site seguro (art. 7º da LGPD). Não vendemos os seus dados.</p>

        <h2>Com quem compartilhamos</h2>
        <p>Só com quem precisa dos dados para o serviço acontecer:</p>
        <ul>
            <li><strong>Mercado Pago</strong>, para processar pagamentos.</li>
            <li><strong>Transportadora ou serviço de frete</strong>, quando o pedido é enviado.</li>
            <li><strong>Emissor de nota fiscal</strong> e autoridades fiscais, quando a lei exige.</li>
            <li><strong>Serviços de envio de e-mail e WhatsApp</strong>, para mensagens de confirmação e aviso de pedido.</li>
            <li><strong>Empresa de hospedagem</strong>, onde o site e o banco de dados ficam guardados.</li>
        </ul>

        <h2>Cookies e dados no navegador</h2>
        <p>Usamos apenas o necessário para o site funcionar: um cookie de sessão, que mantém você conectado e protege os formulários, e o registro no seu navegador de que você confirmou ter 18 anos. Não usamos cookies de publicidade.</p>

        <h2>Menores de idade</h2>
        <p>A loja vende bebidas alcoólicas e produtos de tabacaria, proibidos para menores de 18 anos. O site não é destinado a menores e não coletamos dados de menores de propósito.</p>

        <h2>Por quanto tempo guardamos</h2>
        <p>Os dados da conta ficam guardados enquanto ela existir. Dados de pedidos, pagamentos e notas fiscais ficam pelo prazo exigido pela legislação fiscal (em geral, 5 anos). Depois disso, são apagados ou anonimizados.</p>

        <h2>Os seus direitos</h2>
        <p>Você pode, a qualquer momento, pedir para:</p>
        <ul>
            <li>confirmar se tratamos os seus dados e ter acesso a eles;</li>
            <li>corrigir dados incompletos ou desatualizados (boa parte dá para fazer em <a href="/loja/minha_conta.php">Minha conta</a>);</li>
            <li>excluir a sua conta e os dados que não precisamos guardar por lei;</li>
            <li>saber com quem compartilhamos os seus dados;</li>
            <li>revogar um consentimento que tenha dado.</li>
        </ul>
        <p>Respondemos em até 15 dias. Você também pode reclamar na Autoridade Nacional de Proteção de Dados (ANPD).</p>

        <h2>Segurança</h2>
        <p>O site usa conexão criptografada (HTTPS), senhas criptografadas e acesso restrito ao painel da loja. Nenhum sistema é 100% seguro, mas, se acontecer um incidente que possa trazer risco a você, avisaremos e informaremos a ANPD.</p>

        <h2>Fale com a gente</h2>
        <ul>
            <?php if ($whatsLoja): ?><li>WhatsApp: <?= $h($whatsLoja) ?></li><?php endif; ?>
            <?php if ($emailLoja): ?><li>E-mail: <?= $h($emailLoja) ?></li><?php endif; ?>
            <?php if ($enderecoLoja): ?><li>Endereço: <?= $h($enderecoLoja) ?></li><?php endif; ?>
        </ul>

        <p class="texto-legal-rodape">Podemos atualizar esta política. A data da última atualização fica sempre no topo da página.</p>
    </article>
</main>
<?php require __DIR__ . '/../includes/loja_footer.php'; ?>
</body>
</html>
