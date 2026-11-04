<?php
// Localiza a pasta privada do app (inc/, config/, data/).
// Layout recomendado na Hostinger:  domains/<site>/inc  (fora do public_html).
// Alternativa (tudo dentro do public_html): public_html/_privado/inc, protegida por .htaccess.
declare(strict_types=1);

if (!defined('APP_RAIZ')) {
    $candidatas = [dirname(__DIR__), __DIR__ . '/_privado'];
    foreach ($candidatas as $c) {
        if (is_file($c . '/inc/bootstrap.php')) {
            define('APP_RAIZ', $c);
            break;
        }
    }
    if (!defined('APP_RAIZ')) {
        http_response_code(500);
        exit('Configuração incompleta: pasta inc/ não encontrada. Veja o README (seção Deploy).');
    }
    define('PUBLIC_RAIZ', __DIR__);
    require APP_RAIZ . '/inc/bootstrap.php';
}
