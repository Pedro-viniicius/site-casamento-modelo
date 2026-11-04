<?php
declare(strict_types=1);

function admin_atual(): ?array
{
    sessao_iniciar();
    $id = $_SESSION['admin_id'] ?? null;
    if (!$id || ($_SESSION['admin_expira'] ?? 0) < time()) {
        return null;
    }
    $_SESSION['admin_expira'] = time() + 4 * 3600; // sessão deslizante de 4 h
    return ['id' => (int) $id, 'usuario' => (string) $_SESSION['admin_usuario']];
}

function admin_login(string $usuario, string $senha): bool
{
    $pdo = db();
    if (!$pdo || !limite_ok('login', 8, 900)) {
        return false;
    }
    $st = $pdo->prepare('SELECT id, usuario, senha_hash FROM admins WHERE usuario = ?');
    $st->execute([$usuario]);
    $a = $st->fetch();
    // Compara mesmo sem usuário (tempo parecido; não revela se o usuário existe).
    $ok = password_verify($senha, $a['senha_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuJ6P9n1c9ZQ0cQ0mY1x0y8o8m9yq9Z5e');
    if (!$a || !$ok) {
        return false;
    }
    if (password_needs_rehash($a['senha_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE admins SET senha_hash = ? WHERE id = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $a['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $a['id'];
    $_SESSION['admin_usuario'] = $a['usuario'];
    $_SESSION['admin_expira'] = time() + 4 * 3600;
    unset($_SESSION['csrf']);
    return true;
}

function admin_sair(): void
{
    sessao_iniciar();
    $_SESSION = [];
    session_regenerate_id(true);
}

function admin_cabecalhos(): void
{
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
}

function flash(?string $msg = null, bool $ok = true): ?array
{
    sessao_iniciar();
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'ok' => $ok];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirecionar(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** Destaca valores pendentes no admin. */
function marcar_pendente(string $v): string
{
    return pendente($v) ? '<span class="pendente-tag">pendente</span>' : '';
}

/**
 * Ordenação de tabela por clique no cabeçalho. $colunas = [chave => [rótulo, direção do 1º clique]].
 * Lê ?ordem=&dir= (só valores conhecidos); $padrao '' = ordem original da tela (nenhuma coluna ativa).
 */
function ordem_pedida(array $colunas, string $padrao = ''): array
{
    $ordem = isset($colunas[$_GET['ordem'] ?? '']) ? (string) $_GET['ordem'] : $padrao;
    if ($ordem === '') {
        return ['', ''];
    }
    $dir = in_array($_GET['dir'] ?? '', ['asc', 'desc'], true) ? (string) $_GET['dir'] : $colunas[$ordem][1];
    return [$ordem, $dir];
}

/** Ordena as linhas pela chave da coluna; empates (e nulos, sempre no fim) desempatam por $desempate. */
function ordenar_linhas(array $linhas, callable $chave, string $dir, callable $desempate): array
{
    usort($linhas, function ($x, $y) use ($chave, $dir, $desempate) {
        [$a, $b] = [$chave($x), $chave($y)];
        if (($a === null) !== ($b === null)) {
            return $a === null ? 1 : -1;
        }
        return (($dir === 'asc' ? 1 : -1) * ($a <=> $b)) ?: ($desempate($x) <=> $desempate($y));
    });
    return $linhas;
}

/** <th> clicável: 1º clique usa a direção padrão da coluna, clicar de novo inverte. */
function th_ordenavel(string $col, string $rotulo, string $padrao, string $ordem, string $dir, callable $url): string
{
    $ativa = $ordem === $col;
    $prox = $ativa ? ($dir === 'asc' ? 'desc' : 'asc') : $padrao;
    $seta = $ativa ? ($dir === 'asc' ? '▲' : '▼') : '↕';
    return '<th aria-sort="' . ($ativa ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none') . '">'
        . '<a class="tabela__ordenar' . ($ativa ? ' tabela__ordenar--ativa' : '') . '" href="' . e($url(['ordem' => $col, 'dir' => $prox])) . '"'
        . ' title="Ordenar por ' . e(mb_strtolower($rotulo)) . '">' . e($rotulo)
        . '<span class="tabela__seta" aria-hidden="true">' . $seta . '</span></a></th>';
}
