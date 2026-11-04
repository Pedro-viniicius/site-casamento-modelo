<?php
// POST: envia recado (fica aguardando moderação) e permite desfazer pelo token devolvido.
declare(strict_types=1);
require __DIR__ . '/../boot.php';

exigir_post();
$in = entrada();
if (!csrf_ok($in['csrf'] ?? null)) {
    responder(false, 'Sua sessão expirou. Recarregue a página, por favor.', [], 419, 'recados');
}
$pdo = db_exigir();

if (($in['acao'] ?? '') === 'desfazer') {
    $token = is_string($in['token'] ?? null) ? $in['token'] : '';
    $st = $pdo->prepare('DELETE FROM recados WHERE token_hash = ? AND aprovado = 0 AND criado_em > (NOW() - INTERVAL 1 DAY)');
    $st->execute([token_hash($token)]);
    responder($st->rowCount() > 0, $st->rowCount() ? 'Recado cancelado.' : 'Esse recado já foi lido pelos noivos 💛', [], $st->rowCount() ? 200 : 404, 'recados');
}

if (!honeypot_ok($in)) {
    responder(true, 'Recado enviado!', ['token' => token_novo()], 200, 'recados');
}
if (!limite_ok('recado', 6, 3600)) {
    responder(false, 'Você já mandou vários recados agora. Espere um pouquinho, por favor 💛', [], 429, 'recados');
}
$nome = texto_limpo($in['nome'] ?? '', 120);
$msg = texto_limpo($in['mensagem'] ?? '', 600);
if (mb_strlen($nome) < 2 || mb_strlen($msg) < 3) {
    responder(false, 'Escreva seu nome e um recadinho, por favor.', [], 422, 'recados');
}
$token = token_novo();
$pdo->prepare('INSERT INTO recados (nome, mensagem, aprovado, token_hash, ip_hash) VALUES (?,?,0,?,?)')
    ->execute([$nome, $msg, token_hash($token), ip_hash()]);
responder(true, 'Recado enviado! Ele aparece no varal depois que os noivos lerem 💛', ['token' => $token], 200, 'recados');
