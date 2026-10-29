<?php
/**
 * Endereço antigo de notificação do Mercado Pago, mantido só para pagamentos
 * gerados antes da unificação. Toda a regra (loja, caixa e dívida) vive num
 * lugar só, integracoes/mercado_pago/webhook.php -- antes este arquivo tinha
 * uma cópia própria da lógica, que já estava divergindo da versão principal.
 */
require __DIR__ . '/../../integracoes/mercado_pago/webhook.php';
