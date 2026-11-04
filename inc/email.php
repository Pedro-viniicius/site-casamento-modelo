<?php
declare(strict_types=1);

/**
 * Envia e-mail em texto + HTML. Usa SMTP (Hostinger) se SMTP_HOST estiver configurado,
 * senão mail(). Nunca lança: devolve false e registra só o tipo do erro.
 */
function enviar_email(string $para, string $assunto, string $texto, string $html, ?array $anexoIcs = null): bool
{
    $de = (string) cfg('MAIL_FROM');
    if ($de === '' || pendente($de) || str_contains($de, '[[') || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
        error_log('[casamento] e-mail não enviado: MAIL_FROM não configurado ou destino inválido');
        return false;
    }
    $nome = (string) cfg('MAIL_FROM_NOME');
    $fronteira = 'cmv_' . bin2hex(random_bytes(8));
    $alt = 'alt_' . bin2hex(random_bytes(8));
    $cab = [
        'From: ' . mb_encode_mimeheader($nome, 'UTF-8') . ' <' . $de . '>',
        'MIME-Version: 1.0',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $de)[1] ?? 'localhost') . '>',
    ];
    if (filter_var((string) cfg('MAIL_RESPONDER'), FILTER_VALIDATE_EMAIL)) {
        $cab[] = 'Reply-To: ' . cfg('MAIL_RESPONDER');
    }
    $corpoAlt = "--$alt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($texto))
        . "--$alt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
        . "--$alt--\r\n";
    if ($anexoIcs) {
        $cab[] = "Content-Type: multipart/mixed; boundary=\"$fronteira\"";
        $corpo = "--$fronteira\r\nContent-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n" . $corpoAlt
            . "--$fronteira\r\nContent-Type: text/calendar; charset=UTF-8; method=PUBLISH; name=\"{$anexoIcs['nome']}\"\r\nContent-Disposition: attachment; filename=\"{$anexoIcs['nome']}\"\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($anexoIcs['conteudo'])) . "--$fronteira--\r\n";
    } else {
        $cab[] = "Content-Type: multipart/alternative; boundary=\"$alt\"";
        $corpo = $corpoAlt;
    }
    $assuntoCod = mb_encode_mimeheader($assunto, 'UTF-8');

    try {
        if (cfg('SMTP_HOST')) {
            return smtp_enviar($de, $para, "To: <$para>\r\nSubject: $assuntoCod\r\n" . implode("\r\n", $cab) . "\r\n\r\n" . $corpo);
        }
        return mail($para, $assuntoCod, $corpo, implode("\r\n", $cab), '-f' . $de);
    } catch (Throwable $e) {
        error_log('[casamento] e-mail falhou: ' . get_class($e));
        return false;
    }
}

/** Cliente SMTP mínimo (AUTH LOGIN, SSL implícito ou STARTTLS). */
function smtp_enviar(string $de, string $para, string $mensagem): bool
{
    $host = (string) cfg('SMTP_HOST');
    $porta = (int) cfg('SMTP_PORT');
    $seg = (string) cfg('SMTP_SECURE');
    $alvo = ($seg === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $porta;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $s = @stream_socket_client($alvo, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
    if (!$s) {
        error_log('[casamento] SMTP: sem conexão');
        return false;
    }
    stream_set_timeout($s, 15);
    $ler = function () use ($s): string {
        $r = '';
        while (($l = fgets($s, 1024)) !== false) {
            $r .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') {
                break;
            }
        }
        return $r;
    };
    $cmd = function (string $c, array $esperado) use ($s, $ler): bool {
        if ($c !== '') {
            fwrite($s, $c . "\r\n");
        }
        $r = $ler();
        $ok = in_array((int) substr($r, 0, 3), $esperado, true);
        if (!$ok) {
            error_log('[casamento] SMTP respondeu ' . substr($r, 0, 3));
        }
        return $ok;
    };
    $ehlo = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost';
    $ok = $cmd('', [220]) && $cmd("EHLO $ehlo", [250]);
    if ($ok && $seg === 'tls') {
        $ok = $cmd('STARTTLS', [220])
            && stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
            && $cmd("EHLO $ehlo", [250]);
    }
    if ($ok && cfg('SMTP_USER')) {
        $ok = $cmd('AUTH LOGIN', [334]) && $cmd(base64_encode((string) cfg('SMTP_USER')), [334]) && $cmd(base64_encode((string) cfg('SMTP_PASS')), [235]);
    }
    $ok = $ok && $cmd("MAIL FROM:<$de>", [250]) && $cmd("RCPT TO:<$para>", [250, 251]) && $cmd('DATA', [354]);
    if ($ok) {
        // "Dot-stuffing": linhas que começam com ponto ganham outro ponto.
        $mensagem = preg_replace('/^\./m', '..', $mensagem);
        $ok = $cmd($mensagem . "\r\n.", [250]);
    }
    @fwrite($s, "QUIT\r\n");
    fclose($s);
    return $ok;
}

/** Conteúdo .ics do casamento (cerimônia + recepção, 7 h de duração estimada). */
function ics_evento(): string
{
    $ini = data_evento()->setTimezone(new DateTimeZone('UTC'));
    $fim = $ini->modify('+7 hours');
    $esc = fn(string $t) => addcslashes(str_replace(["\r\n", "\n"], '\n', $t), ',;\\');
    $titulo = 'Casamento ' . conf('noiva_nome') . ' & ' . conf('noivo_nome');
    $linhas = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Site de casamento//PT-BR',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:casamento-' . data_evento()->format('Ymd') . '@' . (parse_url(url_site(), PHP_URL_HOST) ?: 'localhost'),
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . $ini->format('Ymd\THis\Z'),
        'DTEND:' . $fim->format('Ymd\THis\Z'),
        'SUMMARY:' . $esc($titulo),
        'LOCATION:' . $esc(conf('local_nome') . ', ' . conf('local_endereco')),
        'GEO:' . conf('local_lat') . ';' . conf('local_lng'),
        'DESCRIPTION:' . $esc('Cerimônia às ' . data_evento()->format('H\hi') . ". Recepção: " . conf('recepcao_horario') . ".\nZona rural: salve a rota antes de sair.\n" . url_site('/')),
        'URL:' . url_site('/'),
        'BEGIN:VALARM',
        'TRIGGER:-P1D',
        'ACTION:DISPLAY',
        'DESCRIPTION:' . $esc('Amanhã é o casamento! Separe o sapato confortável 👟'),
        'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR',
    ];
    // Linhas de no máximo 75 octetos (RFC 5545), dobradas com espaço.
    $saida = [];
    foreach ($linhas as $l) {
        while (strlen($l) > 75) {
            $corte = 75;
            while ($corte > 0 && (ord($l[$corte]) & 0xC0) === 0x80) {
                $corte--;
            }
            $saida[] = substr($l, 0, $corte);
            $l = ' ' . substr($l, $corte);
        }
        $saida[] = $l;
    }
    return implode("\r\n", $saida) . "\r\n";
}

function link_google_agenda(): string
{
    $ini = data_evento()->setTimezone(new DateTimeZone('UTC'));
    $fim = $ini->modify('+7 hours');
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action'   => 'TEMPLATE',
        'text'     => 'Casamento ' . conf('noiva_nome') . ' & ' . conf('noivo_nome'),
        'dates'    => $ini->format('Ymd\THis\Z') . '/' . $fim->format('Ymd\THis\Z'),
        'location' => conf('local_nome') . ', ' . conf('local_endereco'),
        'details'  => 'Cerimônia às ' . data_evento()->format('H\hi') . '. ' . url_site('/'),
    ]);
}
