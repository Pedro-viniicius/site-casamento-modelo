<?php
declare(strict_types=1);

/**
 * Conexão PDO única. Devolve null se o banco não estiver configurado ou fora do ar:
 * a página continua no ar com o conteúdo padrão (fallback) e os formulários avisam.
 */
function db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }
    $pdo = null;
    if (!cfg('DB_NAME')) {
        return null;
    }
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', cfg('DB_HOST'), (int) cfg('DB_PORT'), cfg('DB_NAME'));
        $pdo = new PDO($dsn, (string) cfg('DB_USER'), (string) cfg('DB_PASS'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        $pdo->exec("SET time_zone = '-03:00'");
    } catch (Throwable $e) {
        error_log('[casamento] banco indisponível: ' . get_class($e));
        $pdo = null;
    }
    return $pdo;
}

function db_exigir(): PDO
{
    $pdo = db();
    if (!$pdo) {
        responder(false, 'Estamos com uma instabilidade agora. Tente de novo em alguns minutos, por favor 💛', [], 503);
    }
    return $pdo;
}
