<?php
// GET: arquivo .ics do casamento ("Adicionar ao calendário").
declare(strict_types=1);
require __DIR__ . '/../boot.php';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="casamento.ics"');
header('Cache-Control: public, max-age=3600');
echo ics_evento();
