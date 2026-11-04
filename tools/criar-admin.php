<?php
// Cria o primeiro usuário do painel /admin.
//
// Pelo terminal (SSH da Hostinger ou local):   php tools/criar-admin.php
// Pelo navegador (sem SSH): copie este arquivo para public_html/criar-admin.php, abra
//   https://seudominio/criar-admin.php, crie o usuário e APAGUE o arquivo em seguida.
// Por segurança, pelo navegador ele só funciona enquanto não existir nenhum admin.
declare(strict_types=1);

$cli = PHP_SAPI === 'cli';
if (!defined('APP_RAIZ')) {
    foreach ([dirname(__DIR__), __DIR__ . '/..', __DIR__ . '/_privado', dirname(__DIR__) . '/_privado'] as $c) {
        if (is_file($c . '/inc/bootstrap.php')) { define('APP_RAIZ', realpath($c)); break; }
    }
    if (!defined('APP_RAIZ')) { exit("Não achei a pasta inc/. Veja o README.\n"); }
    define('PUBLIC_RAIZ', is_dir(APP_RAIZ . '/public_html') ? APP_RAIZ . '/public_html' : __DIR__);
    require APP_RAIZ . '/inc/bootstrap.php';
}
$pdo = db();
if (!$pdo) { exit("Banco indisponível: confira config/.env.php e importe database/schema.sql.\n"); }
$existe = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();

function criar(PDO $pdo, string $usuario, string $senha): string
{
    if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $usuario)) return 'Usuário: 3 a 60 letras, números, ponto, hífen ou _.';
    if (strlen($senha) < 10) return 'Senha: pelo menos 10 caracteres.';
    $st = $pdo->prepare('INSERT INTO admins (usuario, senha_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE senha_hash = VALUES(senha_hash)');
    $st->execute([$usuario, password_hash($senha, PASSWORD_DEFAULT)]);
    return '';
}

if ($cli) {
    $usuario = $argv[1] ?? trim((string) readline('Usuário: '));
    echo 'Senha (mín. 10, não aparece): ';
    system('stty -echo 2>/dev/null');
    $senha = trim((string) fgets(STDIN));
    system('stty echo 2>/dev/null');
    echo "\n";
    $erro = criar($pdo, $usuario, $senha);
    exit($erro ? "Erro: $erro\n" : "Admin \"$usuario\" pronto. Entre em /admin.\n");
}

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
if ($existe > 0) {
    http_response_code(403);
    exit('Já existe um admin. Apague este arquivo do servidor (criar-admin.php).');
}
sessao_iniciar();
$msg = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_ok($_POST['csrf'] ?? null)) {
    $msg = criar($pdo, trim((string) ($_POST['usuario'] ?? '')), (string) ($_POST['senha'] ?? ''));
    if ($msg === '') {
        exit('<p style="font-family:sans-serif">Admin criado! <strong>Agora apague o arquivo criar-admin.php</strong> pelo Gerenciador de Arquivos e entre em <a href="/admin/">/admin</a>.</p>');
    }
}
?><!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Criar admin</title>
<body style="font-family:sans-serif;max-width:420px;margin:10vh auto;padding:16px">
<h1>Criar o primeiro admin</h1>
<?php if ($msg): ?><p style="color:#A1322A"><?= e($msg) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<p><label>Usuário<br><input name="usuario" required pattern="[a-zA-Z0-9._-]{3,60}" style="width:100%;min-height:44px"></label></p>
<p><label>Senha (mín. 10)<br><input name="senha" type="password" required minlength="10" style="width:100%;min-height:44px"></label></p>
<p><button style="min-height:48px;width:100%">Criar</button></p></form></body></html>
