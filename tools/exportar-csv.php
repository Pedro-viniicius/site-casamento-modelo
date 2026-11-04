<?php
// Exporta presenças em CSV pelo terminal: php tools/exportar-csv.php > presencas.csv
// (No painel, use Presenças → Exportar CSV.)
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('APP_RAIZ', dirname(__DIR__));
define('PUBLIC_RAIZ', APP_RAIZ . '/public_html');
require APP_RAIZ . '/inc/bootstrap.php';
$pdo = db() ?? exit("Banco indisponível.\n");
$out = fopen('php://stdout', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Nome', 'E-mail', 'Telefone', 'Vai', 'Adultos', 'Crianças', 'Mensagem', 'Atualizado em'], ';');
foreach ($pdo->query('SELECT nome, email, telefone, IF(vai,\'sim\',\'não\'), adultos, criancas, mensagem, atualizado_em FROM rsvps ORDER BY nome', PDO::FETCH_NUM) as $r) {
    fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r), ';');
}
