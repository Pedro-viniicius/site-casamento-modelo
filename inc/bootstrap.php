<?php
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Sao_Paulo');

require APP_RAIZ . '/config/config.php';
require APP_RAIZ . '/inc/util.php';
require APP_RAIZ . '/inc/db.php';
require APP_RAIZ . '/inc/dados.php';
require APP_RAIZ . '/inc/lojas.php';
require APP_RAIZ . '/inc/fotos.php';
require APP_RAIZ . '/inc/email.php';
require APP_RAIZ . '/inc/render.php';

ini_set('display_errors', cfg('APP_ENV') === 'dev' ? '1' : '0');
error_reporting(E_ALL);

// Cabeçalhos de segurança comuns (o .htaccess também define, isto cobre o php -S de teste).
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
}
