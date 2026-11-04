<?php
// Instalador de uso único (alternativa ao phpMyAdmin): importa schema + seeds e cria o primeiro admin.
// Copie para a raiz do site como instalar.php, chame UMA vez por POST (HTTPS) e ele se trava e se apaga:
//   curl -X POST https://SEU-SITE/instalar.php --data-urlencode token=<INSTALL_TOKEN> \
//        --data-urlencode usuario=noivos --data-urlencode senha=<senha>
// Exige INSTALL_TOKEN (32+ caracteres) no .env.php e os .sql em <pasta privada>/database/.
declare(strict_types=1);
require __DIR__ . '/boot.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('Content-Type: text/plain; charset=utf-8');

$trava = APP_RAIZ . '/config/instalado.lock';
if (is_file($trava)) {
    @unlink(__FILE__);
    http_response_code(410);
    exit("Já instalado.\n");
}
$token = (string) (cfg('INSTALL_TOKEN') ?? '');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || strlen($token) < 32 || !hash_equals($token, (string) ($_POST['token'] ?? ''))) {
    http_response_code(403);
    exit("Negado.\n");
}
$usuario = (string) ($_POST['usuario'] ?? '');
$senha = (string) ($_POST['senha'] ?? '');
if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $usuario) || strlen($senha) < 10) {
    http_response_code(422);
    exit("Usuário (3-60, letras/números) e senha (10+) obrigatórios.\n");
}
$pdo = db();
if (!$pdo) {
    http_response_code(503);
    exit("Banco indisponível: confira o .env.php.\n");
}

/** Divide um .sql em comandos, respeitando aspas ('' e \') e ignorando comentários "--". */
function dividir_sql(string $sql): array
{
    $cmds = [];
    $atual = '';
    $n = strlen($sql);
    $aspas = false;
    for ($i = 0; $i < $n; $i++) {
        $ch = $sql[$i];
        if (!$aspas && $ch === '-' && ($sql[$i + 1] ?? '') === '-') {
            while ($i < $n && $sql[$i] !== "\n") $i++;
            continue;
        }
        if ($ch === "'") {
            if ($aspas && ($sql[$i + 1] ?? '') === "'") { $atual .= "''"; $i++; continue; }
            $aspas = !$aspas;
        } elseif ($aspas && $ch === '\\') {
            $atual .= $ch . ($sql[$i + 1] ?? '');
            $i++;
            continue;
        }
        if ($ch === ';' && !$aspas) {
            if (trim($atual) !== '') $cmds[] = trim($atual);
            $atual = '';
            continue;
        }
        $atual .= $ch;
    }
    if (trim($atual) !== '') $cmds[] = trim($atual);
    return $cmds;
}

$feitos = 0;
try {
    foreach (['schema', 'seed_conteudo', 'seed_presentes'] as $arq) {
        foreach (dividir_sql((string) file_get_contents(APP_RAIZ . "/database/$arq.sql")) as $cmd) {
            $pdo->exec($cmd);
            $feitos++;
        }
    }
    $pdo->prepare('INSERT INTO admins (usuario, senha_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE senha_hash = VALUES(senha_hash)')
        ->execute([$usuario, password_hash($senha, PASSWORD_DEFAULT)]);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Falhou no comando ' . ($feitos + 1) . ': ' . get_class($e) . "\n");
}
file_put_contents($trava, date('c') . "\n");
$cont = fn(string $t) => (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
echo "OK: $feitos comandos. presentes={$cont('presentes')} ofertas={$cont('ofertas')} recados={$cont('recados')} config={$cont('config_site')} admins={$cont('admins')}\n";
echo @unlink(__FILE__) ? "Instalador apagado.\n" : "ATENÇÃO: apague instalar.php pelo Gerenciador de Arquivos.\n";
