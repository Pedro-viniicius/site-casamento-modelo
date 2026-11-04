<?php
// CSV para Excel (UTF-8 com BOM, separador ;). Só para admin logado.
declare(strict_types=1);
require __DIR__ . '/../boot.php';
require APP_RAIZ . '/inc/admin.php';

admin_cabecalhos();
if (!admin_atual() || !($pdo = db())) {
    redirecionar('/admin/');
}
$tipo = ($_GET['tipo'] ?? '') === 'presentes' ? 'presentes' : 'presencas';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $tipo . '-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

// Neutraliza fórmulas (=, +, -, @) ao abrir no Excel.
$seguro = fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;

if ($tipo === 'presencas') {
    fputcsv($out, ['Nome', 'E-mail', 'Telefone', 'Vai', 'Adultos', 'Crianças', 'Mensagem', 'Criado em', 'Atualizado em'], ';');
    foreach ($pdo->query('SELECT nome, email, telefone, vai, adultos, criancas, mensagem, criado_em, atualizado_em FROM rsvps ORDER BY nome') as $r) {
        $r['vai'] = $r['vai'] ? 'sim' : 'não';
        fputcsv($out, array_map($seguro, array_values($r)), ';');
    }
} else {
    fputcsv($out, ['Presente', 'Quem marcou', 'Marcado em', 'Desfeito em'], ';');
    foreach ($pdo->query('SELECT p.nome, m.nome_opcional, m.criado_em, m.desfeito_em FROM presentes_marcados m JOIN presentes p ON p.id = m.presente_id ORDER BY p.nome, m.criado_em') as $r) {
        fputcsv($out, array_map($seguro, array_values($r)), ';');
    }
}
fclose($out);
