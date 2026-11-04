<?php
// POST: cria ou atualiza a confirmação de presença. Aceita JSON (fetch) e formulário (sem JS).
declare(strict_types=1);
require __DIR__ . '/../boot.php';

exigir_post();
$in = entrada();
if (!csrf_ok($in['csrf'] ?? null)) {
    responder(false, 'Sua sessão expirou. Recarregue a página e tente de novo, por favor.', [], 419, 'presenca');
}
if (!honeypot_ok($in)) {
    responder(true, 'Presença registrada!', ['email_enviado' => false], 200, 'presenca'); // robô: finge sucesso
}
if (!limite_ok('rsvp', 12, 3600)) {
    responder(false, 'Muitas tentativas seguidas. Espere alguns minutos e tente de novo, por favor.', [], 429, 'presenca');
}

$c = config_site();
$nome = texto_limpo($in['nome'] ?? '', 160);
$email = mb_strtolower(texto_limpo($in['email'] ?? '', 190));
$telefone = texto_limpo($in['telefone'] ?? '', 30);
$vaiBruto = (string) ($in['vai'] ?? '');
$mensagem = texto_limpo($in['mensagem'] ?? '', 1000);
$maxA = max(1, (int) $c['max_adultos']);
$maxC = max(0, (int) $c['max_criancas']);

$erros = [];
if (mb_strlen($nome) < 3) $erros['nome'] = 'Conta pra gente seu nome completo, por favor.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros['email'] = 'Confira o e-mail: algo como nome@exemplo.com.';
if (!in_array($vaiBruto, ['0', '1'], true)) $erros['vai'] = 'Escolha se você vai ou não.';
$vai = $vaiBruto === '1';
$adultos = $vai ? (int) ($in['adultos'] ?? 1) : 0;
$criancas = $vai ? (int) ($in['criancas'] ?? 0) : 0;
if ($vai && ($adultos < 1 || $adultos > $maxA)) $erros['adultos'] = "Adultos: de 1 a $maxA.";
if ($vai && ($criancas < 0 || $criancas > $maxC)) $erros['criancas'] = "Crianças: de 0 a $maxC.";
if ($telefone !== '' && !preg_match('/^[0-9 ()+\-.]{8,30}$/', $telefone)) $erros['telefone'] = 'Telefone com números, por favor.';
if ($erros) {
    responder(false, reset($erros), ['erros' => $erros], 422, 'presenca');
}

$pdo = db_exigir();
$tokenEdicao = is_string($in['token_edicao'] ?? null) ? $in['token_edicao'] : '';
$querAtualizar = !empty($in['atualizar']) || $tokenEdicao !== '';

$existente = null;
if ($tokenEdicao !== '') {
    $st = $pdo->prepare('SELECT id, email FROM rsvps WHERE token_edicao_hash = ?');
    $st->execute([token_hash($tokenEdicao)]);
    $existente = $st->fetch() ?: null;
    if (!$existente) {
        responder(false, 'Esse link de edição não vale mais. Confirme de novo pelo formulário, por favor.', [], 404, 'presenca');
    }
}
if (!$existente) {
    $st = $pdo->prepare('SELECT id, email FROM rsvps WHERE email = ?');
    $st->execute([$email]);
    $existente = $st->fetch() ?: null;
    if ($existente && !$querAtualizar) {
        // Prevenção de erro: oferece atualizar em vez de duplicar.
        if (quer_json()) {
            json_out(['ok' => false, 'duplicado' => true, 'mensagem' => 'Já temos uma confirmação com este e-mail.'], 409);
        }
        $querAtualizar = true; // sem JS: atualiza (e avisa por e-mail)
    }
}

$token = token_novo();
if ($existente) {
    if ($tokenEdicao !== '' && $email !== $existente['email']) {
        $dup = $pdo->prepare('SELECT 1 FROM rsvps WHERE email = ? AND id <> ?');
        $dup->execute([$email, $existente['id']]);
        if ($dup->fetch()) {
            responder(false, 'Este e-mail já tem outra confirmação. Use o e-mail original, por favor.', [], 409, 'presenca');
        }
    }
    $pdo->prepare('UPDATE rsvps SET nome=?, email=?, telefone=?, vai=?, adultos=?, criancas=?, mensagem=?, token_edicao_hash=?, ip_hash=? WHERE id=?')
        ->execute([$nome, $email, $telefone ?: null, $vai ? 1 : 0, $adultos, $criancas, $mensagem ?: null, token_hash($token), ip_hash(), $existente['id']]);
    $atualizado = true;
} else {
    $pdo->prepare('INSERT INTO rsvps (nome, email, telefone, vai, adultos, criancas, mensagem, token_edicao_hash, ip_hash) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$nome, $email, $telefone ?: null, $vai ? 1 : 0, $adultos, $criancas, $mensagem ?: null, token_hash($token), ip_hash()]);
    $atualizado = false;
}

// E-mail de confirmação com resumo, link de edição e .ics.
$data = data_evento();
$link = url_site('/?editar=' . rawurlencode($token) . '#presenca');
$nomes = $c['noiva_nome'] . ' & ' . $c['noivo_nome'];
$resumo = $vai
    ? "Presença confirmada: $adultos adulto(s)" . ($criancas ? " e $criancas criança(s)" : '') . '.'
    : 'Você avisou que não poderá ir. Obrigado pelo carinho!';
$quando = data_extenso($data) . ', às ' . $data->format('H\hi');
$texto = "Olá, $nome!\n\n$resumo\n\nQuando: $quando\nOnde: {$c['local_nome']}, {$c['local_endereco']}\n\n"
    . "Precisa mudar algo? Use este link (é só seu): $link\n\nCom carinho,\n$nomes";
$html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#2F2E22">'
    . '<p style="font-size:28px;color:#5F5C2B;margin:0 0 8px">' . e($nomes) . '</p>'
    . '<p>Olá, <strong>' . e($nome) . '</strong>!</p><p>' . e($resumo) . '</p>'
    . '<p><strong>Quando:</strong> ' . e($quando) . '<br><strong>Onde:</strong> ' . e($c['local_nome'] . ', ' . $c['local_endereco']) . '</p>'
    . '<p><a href="' . e($link) . '" style="display:inline-block;background:#5F5C2B;color:#fff;padding:12px 20px;border-radius:999px;text-decoration:none">Editar minha resposta</a></p>'
    . '<p style="color:#5A5848;font-size:13px">Este link é pessoal. Seu e-mail é usado só para a confirmação do casamento.</p></div>';
$enviado = enviar_email($email, ($atualizado ? 'Resposta atualizada · ' : 'Presença registrada · ') . $nomes, $texto, $html, $vai ? ['nome' => 'casamento.ics', 'conteudo' => ics_evento()] : null);

responder(true, $vai ? 'Presença confirmada! Que alegria 🥂' : 'Obrigado por avisar 💌', [
    'atualizado'    => $atualizado,
    'email_enviado' => $enviado,
    'vai'           => $vai,
], 200, 'presenca');
