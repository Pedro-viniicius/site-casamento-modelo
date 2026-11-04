<?php
// POST (Bearer IMPORT_KEY): recebe as ofertas do importador do Etiqueta (tools/importar-etiqueta.mjs --enviar).
// Troca só as ofertas de origem "etiqueta"; ofertas manuais e marcações ficam intactas.
declare(strict_types=1);
require __DIR__ . '/../../boot.php';

exigir_post();
$chave = (string) cfg('IMPORT_KEY');
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (strlen($chave) < 32) {
    json_out(['ok' => false, 'erro' => 'importação desligada (IMPORT_KEY ausente ou curta no .env.php)'], 503);
}
if (!preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) || !hash_equals($chave, $m[1])) {
    limite_ok('importar_falha', 10, 3600) || json_out(['ok' => false, 'erro' => 'muitas tentativas'], 429);
    json_out(['ok' => false, 'erro' => 'chave inválida'], 401);
}
$in = entrada();
$pdo = db_exigir();
$lojasOk = ['amazon', 'ml', 'shopee'];
$hostsOk = '#^https://([a-z0-9-]+\.)*(amazon\.com\.br|meli\.la|mercadolivre\.com\.br|shopee\.com\.br)/#i';

$atualizados = 0;
$total = 0;
$ignorados = [];
$pdo->beginTransaction();
try {
    $busca = $pdo->prepare('SELECT id FROM presentes WHERE slug = ?');
    $apaga = $pdo->prepare("DELETE FROM ofertas WHERE presente_id = ? AND origem = 'etiqueta'");
    $temPrincipalManual = $pdo->prepare("SELECT 1 FROM ofertas WHERE presente_id = ? AND origem = 'manual' AND principal = 1");
    $insere = $pdo->prepare("INSERT INTO ofertas (presente_id, loja, titulo, preco, url, link_afiliado, imagem_url, avaliacao, num_avaliacoes, frete_gratis, principal, origem, chave_origem, verificado_em) VALUES (?,?,?,?,?,?,?,?,?,?,?,'etiqueta',?,?)");
    foreach (array_slice((array) ($in['presentes'] ?? []), 0, 300) as $p) {
        $slug = is_string($p['slug'] ?? null) ? $p['slug'] : '';
        $busca->execute([$slug]);
        $pid = (int) $busca->fetchColumn();
        if (!$pid) {
            $ignorados[] = mb_substr($slug, 0, 60);
            continue;
        }
        $apaga->execute([$pid]);
        $temPrincipalManual->execute([$pid]);
        $manualManda = (bool) $temPrincipalManual->fetchColumn();
        foreach (array_slice((array) ($p['ofertas'] ?? []), 0, 5) as $o) {
            $url = (string) ($o['url'] ?? '');
            $af = (string) ($o['link_afiliado'] ?? '');
            if (!in_array($o['loja'] ?? '', $lojasOk, true) || !preg_match($hostsOk, $url) || ($af !== '' && !preg_match($hostsOk, $af))) {
                continue;
            }
            $verif = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($o['verificado_em'] ?? '')) ? $o['verificado_em'] : date('Y-m-d H:i:s');
            $insere->execute([
                $pid, $o['loja'], texto_limpo($o['titulo'] ?? '', 300), max(0, (float) ($o['preco'] ?? 0)), mb_substr($url, 0, 700), $af !== '' ? mb_substr($af, 0, 700) : null,
                imagem_externa_ok($o['imagem_url'] ?? null), isset($o['avaliacao']) && $o['avaliacao'] !== null ? max(0, min(5, (float) $o['avaliacao'])) : null,
                max(0, (int) ($o['num_avaliacoes'] ?? 0)), !empty($o['frete_gratis']) ? 1 : 0, (!$manualManda && !empty($o['principal'])) ? 1 : 0,
                mb_substr((string) ($o['chave_origem'] ?? ''), 0, 120) ?: null, $verif,
            ]);
            $total++;
        }
        $preco = $pdo->prepare('SELECT COALESCE((SELECT preco FROM ofertas WHERE presente_id = p.id ORDER BY principal DESC, preco LIMIT 1), p.preco_ref) FROM presentes p WHERE id = ?');
        $preco->execute([$pid]);
        $pdo->prepare('UPDATE presentes SET faixa = ?, imagem_url = COALESCE(imagem_url, (SELECT imagem_url FROM ofertas WHERE presente_id = ? AND principal = 1 LIMIT 1)) WHERE id = ?')
            ->execute([faixa_de((float) $preco->fetchColumn()), $pid, $pid]);
        $atualizados++;
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[casamento] importar: ' . get_class($e));
    json_out(['ok' => false, 'erro' => 'falha ao gravar; nada foi alterado'], 500);
}
json_out(['ok' => true, 'atualizados' => $atualizados, 'ofertas' => $total, 'ignorados' => $ignorados]);
