<?php
// Lê config/.env.php (FORA do Git). Modelo: config/.env.example.php
declare(strict_types=1);

$__env = [];
// Ordem: CMV_ENV_ARQUIVO (teste local) → config/.env.php → "casamento.env.php" numa pasta ACIMA do projeto
// (até 4 níveis). Na Hostinger com deploy por Git o segredo fica em domains/<site>/casamento.env.php,
// fora do public_html e fora da pasta que o deploy sobrescreve.
$__arquivo = getenv('CMV_ENV_ARQUIVO') ?: __DIR__ . '/.env.php';
if (!is_file($__arquivo)) {
    $__dir = dirname(__DIR__);
    for ($__i = 0; $__i < 4; $__i++) {
        $__dir = dirname($__dir);
        if (is_file($__dir . '/casamento.env.php')) {
            $__arquivo = $__dir . '/casamento.env.php';
            break;
        }
    }
    unset($__dir, $__i);
}
if (is_file($__arquivo)) {
    $__env = require $__arquivo;
}

const CONFIG_PADRAO = [
    'APP_ENV'        => 'producao',
    'SITE_URL'       => '',
    'APP_SECRET'     => '',
    'DB_HOST'        => 'localhost',
    'DB_PORT'        => 3306,
    'DB_NAME'        => '',
    'DB_USER'        => '',
    'DB_PASS'        => '',
    'IMPORT_KEY'     => '',
    'MAIL_FROM'      => '',
    'MAIL_FROM_NOME' => 'Os noivos',
    'MAIL_RESPONDER' => '',
    'SMTP_HOST'      => '',
    'SMTP_PORT'      => 465,
    'SMTP_USER'      => '',
    'SMTP_PASS'      => '',
    'SMTP_SECURE'    => 'ssl',
];

$GLOBALS['__CONFIG'] = array_merge(CONFIG_PADRAO, is_array($__env) ? $__env : []);
unset($__env, $__arquivo);

function cfg(string $chave)
{
    return $GLOBALS['__CONFIG'][$chave] ?? null;
}
