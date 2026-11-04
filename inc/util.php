<?php
declare(strict_types=1);

/** Escapa para HTML. Toda saída dinâmica passa por aqui. */
function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Valor marcado como pendente ([[PENDENTE: ...]]) ou vazio não aparece para os convidados. */
function pendente($v): bool
{
    $v = trim((string) ($v ?? ''));
    return $v === '' || str_starts_with($v, '[[PENDENTE');
}

function brl($v): string
{
    return 'R$ ' . number_format((float) $v, 2, ',', '.');
}

function segredo_app(): string
{
    $s = (string) cfg('APP_SECRET');
    if (strlen($s) < 32) {
        // Sem segredo configurado, ainda funciona, mas registra o problema (sem expor nada ao visitante).
        error_log('[casamento] APP_SECRET ausente ou curto em config/.env.php');
        $s = 'segredo-inseguro-configure-APP_SECRET-' . __FILE__;
    }
    return $s;
}

/** Hash do IP com segredo: nunca guardamos o IP puro (LGPD). */
function ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return hash_hmac('sha256', 'ip|' . $ip, segredo_app());
}

function token_novo(): string
{
    return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
}

function token_hash(string $token): string
{
    return hash_hmac('sha256', 'tk|' . $token, segredo_app());
}

function https_ativo(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function sessao_iniciar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('cmv_sessao');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => https_ativo(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function csrf_token(): string
{
    sessao_iniciar();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_ok(?string $t): bool
{
    sessao_iniciar();
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

/** Corpo do pedido: aceita JSON (fetch) ou formulário tradicional (sem JS). */
function entrada(): array
{
    static $dados = null;
    if ($dados !== null) {
        return $dados;
    }
    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($tipo, 'application/json')) {
        $bruto = file_get_contents('php://input', false, null, 0, 200000) ?: '';
        $j = json_decode($bruto, true);
        $dados = is_array($j) ? $j : [];
    } else {
        $dados = $_POST;
    }
    return $dados;
}

function quer_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
}

function json_out(array $dados, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Resposta para API que serve fetch (JSON) e formulário sem JS (redireciona com aviso). */
function responder(bool $ok, string $msg, array $extra = [], int $status = 200, string $ancora = ''): never
{
    if (quer_json()) {
        json_out(['ok' => $ok, 'mensagem' => $msg] + $extra, $ok ? $status : ($status === 200 ? 400 : $status));
    }
    // Sem JS: o aviso vai pela sessão (nunca pela URL, para ninguém forjar mensagens num link).
    sessao_iniciar();
    $_SESSION['aviso'] = ['msg' => $msg, 'ok' => $ok];
    header('Location: /' . ($ancora ? '#' . $ancora : ''), true, 303);
    exit;
}

function exigir_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        json_out(['ok' => false, 'mensagem' => 'Método não permitido.'], 405);
    }
}

/**
 * Limite por IP (hash) e ação, janela fixa. Sem banco, não limita (o site segue).
 * @return bool true se pode seguir
 */
function limite_ok(string $acao, int $max, int $janelaSeg): bool
{
    $pdo = db();
    if (!$pdo) {
        return true;
    }
    $chave = hash('sha256', $acao . '|' . ip_hash());
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT janela_inicio, contagem FROM limites WHERE chave = ? FOR UPDATE');
        $st->execute([$chave]);
        $l = $st->fetch();
        $agora = time();
        if (!$l || strtotime($l['janela_inicio']) + $janelaSeg < $agora) {
            $pdo->prepare('REPLACE INTO limites (chave, janela_inicio, contagem) VALUES (?, NOW(), 1)')->execute([$chave]);
            $pdo->commit();
            return true;
        }
        if ((int) $l['contagem'] >= $max) {
            $pdo->commit();
            return false;
        }
        $pdo->prepare('UPDATE limites SET contagem = contagem + 1 WHERE chave = ?')->execute([$chave]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[casamento] limite: ' . get_class($e));
        return true;
    }
}

/** Honeypot: campo "site" escondido; robôs preenchem. */
function honeypot_ok(array $in): bool
{
    return trim((string) ($in['site'] ?? '')) === '';
}

function texto_limpo($v, int $max): string
{
    $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) ($v ?? '')) ?? '');
    return mb_substr($v, 0, $max);
}

function url_site(string $caminho = ''): string
{
    $base = rtrim((string) cfg('SITE_URL'), '/');
    if ($base === '' || pendente($base) || str_contains($base, '[[')) {
        $esquema = https_ativo() ? 'https' : 'http';
        $host = preg_replace('/[^a-z0-9.:\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = $esquema . '://' . $host;
    }
    return $base . $caminho;
}

/** Versão de asset para cache longo (?v=mtime). */
function asset(string $caminho): string
{
    $arq = PUBLIC_RAIZ . '/' . ltrim($caminho, '/');
    $v = is_file($arq) ? (string) filemtime($arq) : '1';
    return '/' . ltrim($caminho, '/') . '?v=' . $v;
}

const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const DIAS_SEMANA = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];

function data_extenso(DateTimeInterface $d): string
{
    return $d->format('j') . ' de ' . MESES[(int) $d->format('n') - 1] . ' de ' . $d->format('Y');
}

function data_curta(?string $sql): string
{
    if (!$sql) {
        return '';
    }
    $t = strtotime($sql);
    return $t ? date('d/m', $t) : '';
}
