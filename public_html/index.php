<?php
declare(strict_types=1);
require __DIR__ . '/boot.php';

$c = config_site();
$csrf = csrf_token();
$data = data_evento();
$agora = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$faltamDias = max(0, (int) ceil(($data->getTimestamp() - $agora->getTimestamp()) / 86400));
$presentes = presentes_lista();
$itens = array_values(array_filter($presentes, fn($p) => $p['tipo'] === 'item'));
$cotas = array_values(array_filter($presentes, fn($p) => $p['tipo'] === 'cota'));
$recados = recados_aprovados();
$padrinhos = padrinhos_lista();
$pixOk = !pendente($c['pix_chave']);
$temAfiliado = (bool) array_filter($presentes, fn($p) => array_filter($p['ofertas'], fn($o) => $o['afiliado']));
$catsUsadas = array_values(array_unique(array_map(fn($p) => $p['cat'], $itens)));
$temBanco = db() !== null;
$nomes = $c['noiva_nome'] . ' & ' . $c['noivo_nome'];
$dataTxt = data_extenso($data);
$horaTxt = $data->format('H\hi');
$enderecoCompleto = $c['local_nome'] . ', ' . $c['local_endereco'];
$lat = (float) $c['local_lat'];
$lng = (float) $c['local_lng'];
$gmaps = 'https://www.google.com/maps/search/?api=1&' . http_build_query(array_filter(['query' => $c['local_nome'] . ' ' . $c['local_cidade'], 'query_place_id' => $c['local_place_id']]));
$waze = 'https://waze.com/ul?' . http_build_query(['ll' => "$lat,$lng", 'navigate' => 'yes']);
$uber = 'https://m.uber.com/ul/?' . http_build_query(['action' => 'setPickup', 'pickup' => 'my_location', 'dropoff' => ['latitude' => $lat, 'longitude' => $lng, 'nickname' => $c['local_nome']]]);
$whats = 'https://wa.me/?text=' . rawurlencode($c['whatsapp_texto'] . ' ' . url_site('/'));
$prazoOk = !pendente($c['rsvp_prazo']);
$prazoTxt = '';
if ($prazoOk && ($t = strtotime($c['rsvp_prazo']))) {
    $prazoTxt = date('j', $t) . ' de ' . MESES[(int) date('n', $t) - 1];
}
$maxAdultos = max(1, min(20, (int) $c['max_adultos']));
$maxCriancas = max(0, min(20, (int) $c['max_criancas']));

// Edição de presença pelo link do e-mail.
$editando = null;
if (!empty($_GET['editar']) && is_string($_GET['editar']) && $temBanco) {
    $st = db()->prepare('SELECT nome, email, telefone, vai, adultos, criancas, mensagem FROM rsvps WHERE token_edicao_hash = ?');
    $st->execute([token_hash($_GET['editar'])]);
    $editando = $st->fetch() ?: null;
}
$avisoSessao = $_SESSION['aviso'] ?? null; // csrf_token() já iniciou a sessão
unset($_SESSION['aviso']);
$aviso = $avisoSessao ? mb_substr((string) $avisoSessao['msg'], 0, 200) : '';
$avisoOk = (bool) ($avisoSessao['ok'] ?? true);

$ogImagem = is_file(PUBLIC_RAIZ . '/assets/img/fotos/og-image.jpg') ? url_site(asset('assets/img/fotos/og-image.jpg')) : url_site(asset('assets/img/og-padrao.jpg'));
$titulo = $nomes . ' · ' . $data->format('d.m.Y');
$descricao = 'Você é nosso convidado! ' . $dataTxt . ', às ' . $horaTxt . ', em ' . $c['local_nome'] . ' (' . $c['local_cidade'] . '). Confirme sua presença e veja a lista de presentes.';
[$ini1, $ini2] = iniciais_casal();
$temRota = is_file(PUBLIC_RAIZ . '/assets/data/rota.geojson'); // opcional: traçado do centro da cidade até o local

header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https://m.media-amazon.com https://images-na.ssl-images-amazon.com https://http2.mlstatic.com https://down-br.img.susercontent.com https://a-static.mlcdn.com.br https://*.openfreemap.org; connect-src 'self' https://tiles.openfreemap.org https://s3.amazonaws.com; worker-src 'self' blob:; child-src blob:; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
header('Cache-Control: no-cache');

$avisos = [
    ['👗', 'Branco é da noiva', 'Não use branco nem off-white: essas cores são da noiva!'],
    ['💌', 'Convite pessoal', 'Lembre-se: convidado não convida.'],
    ['🕓', 'Chegue cedo', 'Chegue com antecedência para não perder nenhum momento da cerimônia.'],
    ['👟', 'Sapato confortável', 'Mulheres: levem um sapato confortável para trocar e aproveitar a festa até o fim!'],
    ['💃', 'Dance com a gente', 'Aproveite, dance e se divirta muito com a gente!'],
    ['📸', 'Registre tudo', 'Tire fotos, faça vídeos e guarde cada momento da nossa recepção.'],
    ['🎁', 'Presentes', 'Se quiser nos presentear, temos uma lista com sugestões e algumas cotas.'],
];
$historia = historia_casal(); // admin → Configurações → historia
?><!doctype html>
<html lang="pt-BR" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titulo) ?></title>
<meta name="description" content="<?= e($descricao) ?>">
<meta name="theme-color" content="#F5F4EF">
<link rel="canonical" href="<?= e(url_site('/')) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:title" content="<?= e($nomes . ' · vamos nos casar! 💍') ?>">
<meta property="og:description" content="<?= e($descricao) ?>">
<meta property="og:url" content="<?= e(url_site('/')) ?>">
<meta property="og:image" content="<?= e($ogImagem) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="<?= e(asset('assets/icons/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/pinyon-script-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/quicksand-500.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<script src="<?= e(asset('assets/js/inicio.js')) ?>" data-abertura="<?= $c['abertura_animada'] === '1' ? '1' : '0' ?>"></script>
<script type="module" src="<?= e(asset('assets/js/app.js')) ?>"></script>
</head>
<body>
<?php include APP_RAIZ . '/inc/sprite.svg'; ?>
<a class="pular-link" href="#conteudo">Pular para o conteúdo</a>
<div class="envelope-cortina" id="envelope" hidden></div>

<header class="topo" id="topo">
  <a class="topo__marca" href="#inicio" aria-label="<?= e($nomes) ?>, voltar ao início">
    <span class="topo__iniciais"><?= e($ini1) ?> <span class="e">&amp;</span> <?= e($ini2) ?></span>
  </a>
  <nav class="topo__nav" aria-label="Seções">
    <ul>
      <li><a href="#inicio">Início</a></li>
      <li><a href="#casal">O Casal</a></li>
      <li><a href="#cerimonia">Cerimônia</a></li>
      <li><a href="#recepcao">Recepção</a></li>
      <li><a href="#local">Como chegar</a></li>
      <li><a href="#padrinhos">Padrinhos</a></li>
      <li><a href="#presentes">Presentes</a></li>
      <li><a href="#recados">Recados</a></li>
    </ul>
  </nav>
  <a class="btn btn--primario btn--pequeno topo__cta" href="#presenca">Confirmar presença</a>
</header>

<?php if ($aviso !== ''): ?>
<div class="faixa-aviso <?= $avisoOk ? 'faixa-aviso--ok' : 'faixa-aviso--erro' ?>" role="status"><?= e($aviso) ?></div>
<?php endif; ?>

<main id="conteudo">

<!-- ============ INÍCIO ============ -->
<section class="hero" id="inicio" aria-labelledby="hero-titulo" data-secao>
  <div class="hero__foto">
    <div class="moldura-dourada">
      <?= foto('hero', '(min-width: 900px) 40vw, 90vw', false, 'hero__img') ?>
    </div>
    <svg class="hero__ramo hero__ramo--a" viewBox="0 0 120 60" aria-hidden="true" data-parallax="0.12"><use href="#ramo"/></svg>
    <svg class="hero__ramo hero__ramo--b" viewBox="0 0 120 60" aria-hidden="true" data-parallax="-0.08"><use href="#ramo"/></svg>
  </div>
  <div class="hero__texto">
    <p class="hero__pre">Vamos nos casar!</p>
    <h1 class="hero__nomes" id="hero-titulo">
      <span><?= e($c['noiva_nome']) ?></span>
      <span class="hero__e" aria-label="e">&amp;</span>
      <span><?= e($c['noivo_nome']) ?></span>
    </h1>
    <p class="hero__data">
      <time datetime="<?= e($data->format('c')) ?>"><span class="hero__dia"><?= e($data->format('d')) ?></span><span class="hero__mes"><?= e(MESES[(int) $data->format('n') - 1]) ?></span><span class="hero__ano"><?= e($data->format('Y')) ?></span></time>
    </p>
    <p class="hero__onde"><?= icone('relogio') ?> <?= e(DIAS_SEMANA[(int) $data->format('w')]) ?>, às <?= e($horaTxt) ?> · <?= icone('pin') ?> <?= e($c['local_nome']) ?>, <?= e($c['local_cidade']) ?></p>
    <p class="hero__frase"><?= e($c['boas_vindas']) ?></p>
    <div class="hero__ctas">
      <a class="btn btn--primario btn--grande" href="#presenca"><?= icone('check') ?> Confirmar presença</a>
      <a class="btn btn--secundario btn--grande" href="#presentes"><?= icone('presente') ?> Ver lista de presentes</a>
    </div>
    <?php if ($prazoTxt): ?><p class="hero__prazo">Confirme até <strong><?= e($prazoTxt) ?></strong>, por favor.</p><?php endif; ?>
  </div>
</section>

<!-- ============ CONTAGEM ============ -->
<section class="contagem" aria-labelledby="contagem-titulo">
  <h2 class="sr-only" id="contagem-titulo">Contagem regressiva</h2>
  <div class="contagem__relogio" data-contagem="<?= e($data->format('c')) ?>" role="timer" aria-live="off">
    <?php foreach (['dias' => $faltamDias, 'horas' => 0, 'min' => 0, 'seg' => 0] as $u => $v): ?>
    <div class="flip" data-unidade="<?= $u ?>">
      <span class="flip__valor" data-valor><?= str_pad((string) $v, 2, '0', STR_PAD_LEFT) ?></span>
      <span class="flip__rotulo"><?= ['dias' => 'dias', 'horas' => 'horas', 'min' => 'min', 'seg' => 'seg'][$u] ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="contagem__msg" data-contagem-msg>Faltam <?= $faltamDias ?> dias para o nosso “sim”.</p>
</section>

<!-- ============ O CASAL ============ -->
<section class="secao casal" id="casal" aria-labelledby="casal-titulo" data-secao>
  <header class="secao__cabeca">
    <p class="secao__pre">Nossa história</p>
    <h2 class="secao__titulo" id="casal-titulo">O Casal</h2>
    <svg class="divisor" viewBox="0 0 120 60" aria-hidden="true"><use href="#ramo"/></svg>
  </header>
  <div class="casal__retratos">
    <figure class="retrato">
      <?= foto('noiva', '(min-width: 700px) 30vw, 45vw') ?>
      <figcaption><?= e($c['noiva_completo']) ?></figcaption>
    </figure>
    <span class="casal__e" aria-hidden="true">&amp;</span>
    <figure class="retrato">
      <?= foto('noivo', '(min-width: 700px) 30vw, 45vw') ?>
      <figcaption><?= e($c['noivo_completo']) ?></figcaption>
    </figure>
  </div>

  <ol class="linha-tempo" data-linha-tempo aria-label="Linha do tempo da nossa história">
    <svg class="linha-tempo__traco" aria-hidden="true" preserveAspectRatio="none" viewBox="0 0 10 100"><path d="M5 0 V100" pathLength="1"/></svg>
    <?php foreach ($historia as $i => [$quando, $tit, $txt]): ?>
    <li class="marco" data-marco>
      <span class="marco__ponto" aria-hidden="true"><?= $i === count($historia) - 1 ? '💍' : '' ?></span>
      <div class="marco__cartao card">
        <?php if ($quando): ?><p class="marco__quando"><?= e($quando) ?></p><?php endif; ?>
        <h3 class="marco__titulo"><?= e($tit) ?></h3>
        <p><?= e($txt) ?></p>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>

  <blockquote class="versiculo">
    <p>“<?= e($c['versiculo']) ?>”</p>
    <cite><?= e($c['versiculo_ref']) ?></cite>
  </blockquote>

  <div class="carrossel" data-carrossel role="region" aria-roledescription="carrossel" aria-label="Galeria de fotos do casal">
    <div class="carrossel__trilho" tabindex="0">
      <?php for ($i = 1; $i <= 16; $i++): $slot = 'galeria-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT); ?>
      <div class="carrossel__item galeria__item" role="group" aria-roledescription="slide" aria-label="Foto <?= $i ?> de 16">
        <?= foto($slot, '(min-width: 1100px) 25vw, (min-width: 700px) 33vw, 80vw') ?>
      </div>
      <?php endfor; ?>
    </div>
    <?php include APP_RAIZ . '/inc/controles-carrossel.php'; ?>
  </div>
</section>

<!-- ============ CERIMÔNIA ============ -->
<section class="secao cerimonia" id="cerimonia" aria-labelledby="cerimonia-titulo" data-secao>
  <header class="secao__cabeca">
    <p class="secao__pre">O momento do “sim”</p>
    <h2 class="secao__titulo" id="cerimonia-titulo">Cerimônia</h2>
    <svg class="divisor" viewBox="0 0 120 60" aria-hidden="true"><use href="#ramo"/></svg>
  </header>
  <div class="duas-colunas">
    <?= foto('cerimonia', '(min-width: 900px) 45vw, 100vw', true, 'cartao-foto') ?>
    <div class="card info-evento">
      <p>Queremos muito você com a gente no momento em que nossa união será abençoada diante de Deus. Vai ser lindo e emocionante, e prometemos ser <strong>pontuais</strong>!</p>
      <dl class="fatos">
        <div><dt><?= icone('calendario') ?> Quando</dt><dd><?= e(ucfirst(DIAS_SEMANA[(int) $data->format('w')])) ?>, <?= e($dataTxt) ?></dd></div>
        <div><dt><?= icone('relogio') ?> Horário</dt><dd><?= e($horaTxt) ?> <span class="dica">(chegue 30 min antes)</span></dd></div>
        <div><dt><?= icone('pin') ?> Onde</dt><dd><?= e($c['local_nome']) ?><br><span class="dica"><?= e($c['local_endereco']) ?></span></dd></div>
      </dl>
      <div class="acoes-linha">
        <a class="btn btn--secundario" href="/api/ics.php" download="casamento.ics"><?= icone('calendario') ?> Adicionar ao calendário</a>
        <?= link_externo(link_google_agenda(), 'Google Agenda', 'btn btn--texto') ?>
        <a class="btn btn--texto" href="#local"><?= icone('pin') ?> Como chegar</a>
      </div>
    </div>
  </div>
</section>

<!-- ============ RECEPÇÃO ============ -->
<section class="secao recepcao" id="recepcao" aria-labelledby="recepcao-titulo" data-secao>
  <div class="luzinhas" aria-hidden="true"></div>
  <header class="secao__cabeca">
    <p class="secao__pre">Depois do “sim”, a festa</p>
    <h2 class="secao__titulo" id="recepcao-titulo">Recepção</h2>
    <svg class="divisor" viewBox="0 0 120 60" aria-hidden="true"><use href="#ramo"/></svg>
  </header>
  <div class="duas-colunas duas-colunas--inverte">
    <?= foto('recepcao', '(min-width: 900px) 45vw, 100vw', true, 'cartao-foto') ?>
    <div class="card info-evento">
      <p>A recepção é para todos os convidados, logo depois da cerimônia, <strong><?= e(mb_strtolower($c['recepcao_local'])) ?></strong>. Estamos preparando tudo com muito amor e carinho: queremos todo mundo curtindo e se divertindo muito!</p>
      <dl class="fatos">
        <div><dt><?= icone('relogio') ?> Quando</dt><dd><?= e($c['recepcao_horario']) ?></dd></div>
        <div><dt><?= icone('pin') ?> Onde</dt><dd><?= e($c['recepcao_local']) ?></dd></div>
      </dl>
    </div>
  </div>
</section>

<!-- ============ AVISOS ============ -->
<section class="secao avisos" id="avisos" aria-labelledby="avisos-titulo" data-secao>
  <header class="secao__cabeca">
    <p class="secao__pre">Para o dia ser perfeito</p>
    <h2 class="secao__titulo" id="avisos-titulo">Avisos importantes</h2>
    <p class="secao__sub">Toque em um cartão para virar.</p>
  </header>
  <div class="carrossel" data-carrossel role="region" aria-roledescription="carrossel" aria-label="Avisos importantes">
    <div class="carrossel__trilho" tabindex="0">
      <?php foreach ($avisos as $i => [$ic, $tit, $txt]): ?>
      <div class="carrossel__item" role="group" aria-roledescription="slide" aria-label="Aviso <?= $i + 1 ?> de <?= count($avisos) ?>">
        <button type="button" class="aviso" aria-pressed="false" data-virar>
          <span class="aviso__face aviso__frente"><span class="aviso__icone" aria-hidden="true"><?= $ic ?></span><span class="aviso__titulo"><?= e($tit) ?></span><span class="aviso__dica" aria-hidden="true">toque para ler ↻</span></span>
          <span class="aviso__face aviso__verso"><span class="aviso__texto"><?= e($txt) ?></span></span>
        </button>
      </div>
      <?php endforeach; ?>
    </div>
    <?php include APP_RAIZ . '/inc/controles-carrossel.php'; ?>
  </div>
</section>

<!-- ============ COMO CHEGAR ============ -->
<section class="secao local" id="local" aria-labelledby="local-titulo" data-secao>
  <header class="secao__cabeca">
    <p class="secao__pre"><?= e($c['local_nome']) ?> · <?= e($c['local_cidade']) ?></p>
    <h2 class="secao__titulo" id="local-titulo">Como chegar</h2>
    <svg class="divisor" viewBox="0 0 120 60" aria-hidden="true"><use href="#ramo"/></svg>
  </header>
  <div class="local__grade">
    <div class="mapa" data-mapa data-lat="<?= e((string) $lat) ?>" data-lng="<?= e((string) $lng) ?>" <?= $temRota ? 'data-rota="' . e(asset('assets/data/rota.geojson')) . '"' : '' ?>>
      <div class="mapa__estatico" data-mapa-estatico>
        <?php include APP_RAIZ . '/inc/mapa-ilustrado.svg'; ?>
        <button type="button" class="btn btn--primario mapa__explorar" data-mapa-abrir><?= icone('mapa') ?> Explorar em 3D</button>
      </div>
      <div class="mapa__canvas" data-mapa-canvas hidden role="region" aria-label="Mapa 3D interativo da região de <?= e($c['local_nome']) ?>"></div>
      <div class="mapa__controles" data-mapa-controles hidden>
        <button type="button" class="btn btn--icone-texto" data-mapa-acao="girar"><?= icone('girar') ?> Girar</button>
        <button type="button" class="btn btn--icone-texto" data-mapa-acao="centro"><?= icone('alvo') ?> Recentralizar</button>
        <button type="button" class="btn btn--icone-texto" data-mapa-acao="topo"><?= icone('mapa') ?> Ver de cima</button>
      </div>
      <p class="mapa__dica" data-mapa-dica hidden>Use dois dedos para mover o mapa.</p>
    </div>
    <div class="card local__info">
      <p class="local__endereco"><strong><?= e($c['local_nome']) ?></strong><br><?= e($c['local_endereco']) ?></p>
      <div class="local__botoes">
        <?= link_externo($gmaps, 'Abrir no Google Maps', 'btn btn--primario') ?>
        <?= link_externo($waze, 'Abrir no Waze', 'btn btn--secundario') ?>
        <?= link_externo($uber, 'Chamar Uber', 'btn btn--secundario') ?>
        <button type="button" class="btn btn--texto" data-copiar="<?= e($enderecoCompleto) ?>" data-copiar-msg="Endereço copiado ✓"><?= icone('copiar') ?> Copiar endereço</button>
      </div>
      <ul class="local__dicas">
        <li><?= icone('relogio') ?> <span>Cerimônia às <strong><?= e($horaTxt) ?></strong>; chegue com antecedência.</span></li>
        <li><?= icone('sinal') ?> <span>Zona rural: o sinal de celular pode falhar. <strong>Salve a rota antes de sair.</strong></span></li>
        <?php if (!pendente($c['estacionamento'])): ?><li><?= icone('carro') ?> <span><?= e($c['estacionamento']) ?></span></li><?php endif; ?>
      </ul>
    </div>
  </div>
</section>

<!-- ============ PADRINHOS ============ -->
<section class="secao padrinhos" id="padrinhos" aria-labelledby="padrinhos-titulo" data-secao>
  <header class="secao__cabeca">
    <p class="secao__pre">Ao nosso lado no altar</p>
    <h2 class="secao__titulo" id="padrinhos-titulo">Padrinhos</h2>
    <svg class="divisor" viewBox="0 0 120 60" aria-hidden="true"><use href="#ramo"/></svg>
  </header>
  <?php if (!$padrinhos): ?>
    <p class="vazio card">Em breve vamos apresentar aqui as pessoas especiais que vão estar ao nosso lado no altar 💛</p>
  <?php else: ?>
  <div class="carrossel" data-carrossel role="region" aria-roledescription="carrossel" aria-label="Padrinhos">
    <div class="carrossel__trilho" tabindex="0">
      <?php foreach ($padrinhos as $i => $pd): ?>
      <figure class="carrossel__item padrinho card" role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> de <?= count($padrinhos) ?>">
        <?= $pd['foto'] ? foto_arquivo('fotos/padrinhos/' . basename($pd['foto']), 600, 600, 'Foto de ' . $pd['nome'], 'padrinho', '(min-width: 900px) 22vw, 70vw') : '<div class="foto foto-slot padrinho__inicial" style="aspect-ratio:1" aria-hidden="true">' . e(mb_substr($pd['nome'], 0, 1)) . '</div>' ?>
        <figcaption><strong><?= e($pd['nome']) ?></strong><span><?= $pd['lado'] === 'noiva' ? 'da noiva' : 'do noivo' ?></span></figcaption>
      </figure>
      <?php endforeach; ?>
    </div>
    <?php include APP_RAIZ . '/inc/controles-carrossel.php'; ?>
  </div>
  <?php endif; ?>
</section>

<!-- ============ PRESENTES ============ -->
<section class="secao presentes" id="presentes" aria-labelledby="presentes-titulo" data-secao>
  <header class="secao__cabeca">
    <p class="secao__pre">Lista de presentes</p>
    <h2 class="secao__titulo" id="presentes-titulo"><?= e($c['frase_presentes']) ?></h2>
    <p class="secao__sub">Se quiser nos presentear, escolhemos ofertas em lojas confiáveis para facilitar. Depois de comprar, toque em <strong>“Já comprei”</strong> para ninguém repetir o presente.</p>
  </header>

  <div class="filtros js-only" data-filtros>
    <div class="filtros__busca">
      <label for="busca-presente" class="sr-only">Buscar presente</label>
      <?= icone('lupa') ?>
      <input id="busca-presente" type="search" placeholder="Buscar presente…  (atalho: /)" autocomplete="off" data-busca>
    </div>
    <div class="chips" role="group" aria-label="Faixa de preço">
      <?php $n = 1; foreach (FAIXAS as $k => $rot): ?>
      <button type="button" class="chip" data-faixa="<?= e($k) ?>" aria-pressed="false" title="Atalho: <?= $n ?>"><?= e($rot) ?></button>
      <?php $n++; endforeach; ?>
    </div>
    <div class="chips chips--rolagem" role="group" aria-label="Categoria">
      <?php foreach (CATEGORIAS as $k => $rot): if ($k === 'cotas' || !in_array($k, $catsUsadas, true)) continue; ?>
      <button type="button" class="chip" data-cat="<?= e($k) ?>" aria-pressed="false"><?= ICONE_CATEGORIA[$k] ?> <?= e($rot) ?></button>
      <?php endforeach; ?>
      <?php if ($cotas): ?><a class="chip" href="#cotas"><?= ICONE_CATEGORIA['cotas'] ?> Cotas com PIX ↓</a><?php endif; ?>
    </div>
    <div class="filtros__linha">
      <p class="filtros__contador" data-contador aria-live="polite">Mostrando <?= count($itens) ?> de <?= count($itens) ?></p>
      <label class="filtros__ordem"><span class="filtros__ordem-rotulo">Ordenar</span>
        <select data-ordem>
          <option value="padrao">Sugestão dos noivos</option>
          <option value="menor">Menor preço</option>
          <option value="maior">Maior preço</option>
          <option value="desejados">Mais desejados</option>
        </select>
      </label>
      <button type="button" class="btn btn--texto btn--pequeno" data-limpar hidden>Limpar filtros ✕</button>
    </div>
  </div>

  <div class="carrossel carrossel--presentes" data-carrossel data-carrossel-presentes role="region" aria-roledescription="carrossel" aria-label="Presentes">
    <div class="carrossel__trilho" tabindex="0" data-lista-presentes>
      <?php foreach ($itens as $p) echo card_presente($p, $csrf, $pixOk); ?>
    </div>
    <p class="vazio" data-vazio hidden>Nenhum presente com esses filtros… mas o melhor presente continua sendo você aqui com a gente 💛 <button type="button" class="btn btn--texto" data-limpar>Limpar filtros</button></p>
    <?php include APP_RAIZ . '/inc/controles-carrossel.php'; ?>
  </div>

  <?php if ($cotas): ?>
  <div class="cotas" id="cotas">
    <h3 class="subtitulo">Cotas com PIX</h3>
    <p class="secao__sub">Prefere contribuir com um momento? Escolha uma cota: o PIX já vai com o valor sugerido (pode mudar no app do banco).</p>
    <div class="carrossel" data-carrossel role="region" aria-roledescription="carrossel" aria-label="Cotas">
      <div class="carrossel__trilho" tabindex="0">
        <?php foreach ($cotas as $p) echo card_presente($p, $csrf, $pixOk); ?>
      </div>
      <?php include APP_RAIZ . '/inc/controles-carrossel.php'; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!pendente($c['loja_fisica'])): ?>
  <aside class="card loja-fisica">
    <p><?= icone('loja') ?> <strong>Prefere loja física?</strong> Nossa lista também está na <?= e($c['loja_fisica']) ?>.</p>
    <?php if (!pendente($c['loja_fisica_url'])): ?><?= link_externo($c['loja_fisica_url'], 'Ver a loja', 'btn btn--texto btn--pequeno') ?><?php endif; ?>
  </aside>
  <?php endif; ?>
  <?php if ($temAfiliado): ?><p class="nota-afiliado">Transparência: alguns links podem gerar comissão para nós, sem custo extra para você.</p><?php endif; ?>
</section>

<!-- ============ PRESENÇA ============ -->
<section class="secao presenca" id="presenca" aria-labelledby="presenca-titulo" data-secao>
  <header class="secao__cabeca">
    <p class="secao__pre">Contamos com você</p>
    <h2 class="secao__titulo" id="presenca-titulo">Confirme sua presença</h2>
    <svg class="divisor" viewBox="0 0 120 60" aria-hidden="true"><use href="#ramo"/></svg>
  </header>
  <div class="presenca__grade">
    <aside class="card presenca__resumo" aria-label="Resumo do evento">
      <p class="presenca__data"><?= e($data->format('d')) ?><span><?= e(mb_substr(MESES[(int) $data->format('n') - 1], 0, 3)) ?> · <?= e($data->format('Y')) ?></span></p>
      <p><?= icone('relogio') ?> <?= e(ucfirst(DIAS_SEMANA[(int) $data->format('w')])) ?>, às <?= e($horaTxt) ?></p>
      <p><?= icone('pin') ?> <?= e($c['local_nome']) ?>, <?= e($c['local_cidade']) ?></p>
      <?php if ($prazoTxt): ?><p class="presenca__prazo">Confirme até <strong><?= e($prazoTxt) ?></strong></p><?php endif; ?>
    </aside>

    <div class="card presenca__form-caixa">
      <?php if (!$temBanco): ?>
        <p class="vazio">A confirmação de presença abre em instantes. Volte daqui a pouquinho, por favor 💛</p>
      <?php else: ?>
      <?php if ($editando): ?><p class="faixa-aviso faixa-aviso--ok" role="status">Você está editando sua confirmação. É só ajustar e enviar de novo.</p><?php endif; ?>
      <form id="form-rsvp" class="rsvp" method="post" action="/api/rsvp.php" novalidate data-rsvp data-max-adultos="<?= $maxAdultos ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <?php if ($editando): ?><input type="hidden" name="token_edicao" value="<?= e((string) $_GET['editar']) ?>"><?php endif; ?>
        <div class="hp" aria-hidden="true"><label>Site <input type="text" name="site" tabindex="-1" autocomplete="off"></label></div>

        <div class="passos js-only" data-passos>
          <ol>
            <li data-passo-ind="1" aria-current="step"><span>1</span> Quem é você</li>
            <li data-passo-ind="2"><span>2</span> Quantas pessoas</li>
            <li data-passo-ind="3"><span>3</span> Revisar e enviar</li>
          </ol>
          <div class="progresso" role="progressbar" aria-label="Progresso da confirmação" aria-valuemin="1" aria-valuemax="3" aria-valuenow="1"><span data-progresso-barra></span></div>
        </div>

        <fieldset class="rsvp__passo" data-passo="1">
          <legend>Quem é você</legend>
          <div class="campo">
            <label for="rsvp-nome">Nome completo</label>
            <input id="rsvp-nome" name="nome" type="text" required minlength="3" maxlength="160" autocomplete="name" value="<?= e($editando['nome'] ?? '') ?>" aria-describedby="rsvp-nome-erro">
            <p class="campo__erro" id="rsvp-nome-erro" data-erro-de="nome"></p>
          </div>
          <div class="campo">
            <label for="rsvp-email">E-mail</label>
            <input id="rsvp-email" name="email" type="email" required maxlength="190" autocomplete="email" inputmode="email" value="<?= e($editando['email'] ?? '') ?>" aria-describedby="rsvp-email-ajuda rsvp-email-erro">
            <p class="campo__ajuda" id="rsvp-email-ajuda">Você recebe a confirmação (e um link para editar) neste e-mail.</p>
            <p class="campo__erro" id="rsvp-email-erro" data-erro-de="email"></p>
          </div>
          <div class="campo">
            <label for="rsvp-tel">Telefone <span class="opcional">(opcional)</span></label>
            <input id="rsvp-tel" name="telefone" type="tel" maxlength="30" autocomplete="tel" inputmode="tel" value="<?= e($editando['telefone'] ?? '') ?>">
          </div>
        </fieldset>

        <fieldset class="rsvp__passo" data-passo="2">
          <legend>Você vai ao casamento?</legend>
          <div class="opcoes-vai" role="radiogroup" aria-label="Você irá ao evento?">
            <label class="opcao"><input type="radio" name="vai" value="1" required <?= ($editando['vai'] ?? null) === 1 || ($editando['vai'] ?? null) === '1' ? 'checked' : '' ?>><span>🥂 Sim, vou!</span></label>
            <label class="opcao"><input type="radio" name="vai" value="0" <?= isset($editando['vai']) && (string) $editando['vai'] === '0' ? 'checked' : '' ?>><span>💌 Não poderei ir</span></label>
          </div>
          <p class="campo__erro" data-erro-de="vai"></p>
          <div class="rsvp__qtd" data-so-se-vai>
            <div class="campo">
              <label for="rsvp-adultos">Adultos, incluindo você</label>
              <select id="rsvp-adultos" name="adultos">
                <?php for ($i = 1; $i <= $maxAdultos; $i++): ?><option value="<?= $i ?>" <?= (int) ($editando['adultos'] ?? 1) === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?>
              </select>
            </div>
            <div class="campo">
              <label for="rsvp-criancas">Crianças</label>
              <select id="rsvp-criancas" name="criancas">
                <?php for ($i = 0; $i <= $maxCriancas; $i++): ?><option value="<?= $i ?>" <?= (int) ($editando['criancas'] ?? 0) === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?>
              </select>
            </div>
          </div>
          <div class="campo">
            <label for="rsvp-msg">Quer deixar uma mensagem? <span class="opcional">(opcional)</span></label>
            <textarea id="rsvp-msg" name="mensagem" rows="3" maxlength="1000"><?= e($editando['mensagem'] ?? '') ?></textarea>
          </div>
        </fieldset>

        <fieldset class="rsvp__passo" data-passo="3">
          <legend>Revisar e enviar</legend>
          <dl class="rsvp__revisao js-only" data-revisao></dl>
          <p class="lgpd"><?= icone('cadeado') ?> Seus dados são usados só para organizar o casamento e enviar a confirmação. Não compartilhamos com ninguém.</p>
          <button type="submit" class="btn btn--primario btn--grande btn--bloco" data-enviar><?= icone('check') ?> Confirmar presença</button>
        </fieldset>

        <div class="rsvp__nav js-only" data-nav>
          <button type="button" class="btn btn--texto" data-voltar hidden>← Voltar</button>
          <button type="button" class="btn btn--primario" data-continuar disabled>Continuar →</button>
        </div>
      </form>

      <div class="rsvp-fim" data-rsvp-fim hidden tabindex="-1">
        <p class="rsvp-fim__emoji" aria-hidden="true">🎉</p>
        <h3 class="subtitulo" data-fim-titulo>Presença confirmada!</h3>
        <dl class="rsvp__revisao" data-fim-resumo></dl>
        <p data-fim-email class="dica"></p>
        <div class="acoes-linha">
          <a class="btn btn--secundario" href="/api/ics.php" download="casamento.ics"><?= icone('calendario') ?> Adicionar ao calendário (.ics)</a>
          <?= link_externo(link_google_agenda(), 'Google Agenda', 'btn btn--texto') ?>
          <a class="btn btn--primario" href="#presentes"><?= icone('presente') ?> Ver lista de presentes</a>
        </div>
        <button type="button" class="btn btn--texto btn--pequeno" data-rsvp-editar>Editar minha resposta</button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ============ RECADOS ============ -->
<section class="secao recados" id="recados" aria-labelledby="recados-titulo" data-secao>
  <header class="secao__cabeca">
    <p class="secao__pre">Bilhetes no varal</p>
    <h2 class="secao__titulo" id="recados-titulo">Recados</h2>
    <svg class="divisor" viewBox="0 0 120 60" aria-hidden="true"><use href="#ramo"/></svg>
  </header>
  <div class="varal">
    <div class="varal__fio" aria-hidden="true"></div>
    <div class="carrossel" data-carrossel role="region" aria-roledescription="carrossel" aria-label="Recados dos convidados">
      <div class="carrossel__trilho" tabindex="0">
        <?php foreach ($recados as $i => $r): ?>
        <figure class="carrossel__item bilhete" role="group" aria-roledescription="slide" aria-label="Recado <?= $i + 1 ?> de <?= count($recados) ?>" style="--giro: <?= [-2, 1.5, -1, 2.2][$i % 4] ?>deg">
          <span class="bilhete__pregador" aria-hidden="true"></span>
          <blockquote><p><?= nl2br(e($r['mensagem'])) ?></p></blockquote>
          <figcaption>— <?= e($r['nome']) ?></figcaption>
        </figure>
        <?php endforeach; ?>
      </div>
      <?php include APP_RAIZ . '/inc/controles-carrossel.php'; ?>
    </div>
  </div>

  <div class="card recado-form-caixa">
    <h3 class="subtitulo">Deixe seu recado</h3>
    <?php if (!$temBanco): ?>
      <p class="vazio">Os recados abrem em instantes 💛</p>
    <?php else: ?>
    <form class="recado-form" method="post" action="/api/recados.php" data-recado-form novalidate>
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <div class="hp" aria-hidden="true"><label>Site <input type="text" name="site" tabindex="-1" autocomplete="off"></label></div>
      <div class="campo">
        <label for="recado-nome">Seu nome</label>
        <input id="recado-nome" name="nome" type="text" required minlength="2" maxlength="120" autocomplete="name">
      </div>
      <div class="campo">
        <label for="recado-msg">Recado</label>
        <textarea id="recado-msg" name="mensagem" rows="3" required minlength="3" maxlength="600" aria-describedby="recado-ajuda"></textarea>
        <p class="campo__ajuda" id="recado-ajuda"><span data-caracteres>0</span>/600 · Os noivos leem antes de o recado aparecer no varal.</p>
      </div>
      <button type="submit" class="btn btn--primario" data-enviar-recado disabled><?= icone('coracao') ?> Enviar recado</button>
    </form>
    <?php endif; ?>
  </div>
</section>
</main>

<footer class="rodape">
  <svg class="divisor" viewBox="0 0 120 60" aria-hidden="true"><use href="#ramo"/></svg>
  <p class="rodape__nomes"><?= e($c['noiva_completo']) ?> &amp; <?= e($c['noivo_completo']) ?></p>
  <p class="rodape__data"><?= e($dataTxt) ?> · <?= e($c['local_nome']) ?></p>
  <p class="rodape__versiculo">“<?= e($c['versiculo']) ?>” <cite><?= e($c['versiculo_ref']) ?></cite></p>
  <div class="acoes-linha acoes-linha--centro">
    <?= link_externo($whats, 'Compartilhar no WhatsApp', 'btn btn--secundario') ?>
  </div>
  <p class="rodape__privacidade">Privacidade: os dados de presença e recados servem só para organizar o casamento e são apagados depois da festa. Não usamos rastreadores.<?php if ($temAfiliado): ?> Alguns links da lista podem gerar comissão.<?php endif; ?></p>
</footer>

<!-- Barra fixa no celular: os dois CTAs + navegação -->
<nav class="barra-inferior" aria-label="Atalhos">
  <a href="#inicio" data-barra="inicio"><?= icone('casa') ?><span>Início</span></a>
  <a href="#presenca" data-barra="presenca" class="barra-inferior__cta"><?= icone('check') ?><span>Presença</span></a>
  <a href="#presentes" data-barra="presentes" class="barra-inferior__cta barra-inferior__cta--2"><?= icone('presente') ?><span>Presentes</span></a>
  <a href="#local" data-barra="local"><?= icone('pin') ?><span>Local</span></a>
  <button type="button" data-abrir-mais aria-haspopup="dialog"><?= icone('mais') ?><span>Mais</span></button>
</nav>

<dialog class="folha" id="folha-mais" aria-labelledby="folha-mais-titulo">
  <div class="folha__alca" aria-hidden="true"></div>
  <h2 class="folha__titulo" id="folha-mais-titulo">Ir para…</h2>
  <ul class="folha__links">
    <li><a href="#casal">💑 O Casal</a></li>
    <li><a href="#cerimonia">⛪ Cerimônia</a></li>
    <li><a href="#recepcao">🥂 Recepção</a></li>
    <li><a href="#avisos">📌 Avisos importantes</a></li>
    <li><a href="#padrinhos">🤵 Padrinhos</a></li>
    <li><a href="#recados">💌 Recados</a></li>
  </ul>
  <form method="dialog"><button class="btn btn--texto btn--bloco">Fechar</button></form>
</dialog>

<dialog class="folha" id="folha-ofertas" aria-labelledby="folha-ofertas-titulo">
  <div class="folha__alca" aria-hidden="true"></div>
  <h2 class="folha__titulo" id="folha-ofertas-titulo">Outras lojas</h2>
  <div data-folha-conteudo></div>
  <form method="dialog"><button class="btn btn--texto btn--bloco">Fechar</button></form>
</dialog>

<dialog class="modal" id="modal-confirmar" aria-labelledby="modal-confirmar-titulo">
  <h2 class="modal__titulo" id="modal-confirmar-titulo">Confirma que comprou?</h2>
  <p data-modal-texto></p>
  <div class="campo">
    <label for="comprei-nome">Seu nome <span class="opcional">(opcional, só os noivos veem)</span></label>
    <input id="comprei-nome" type="text" maxlength="120" autocomplete="name">
  </div>
  <div class="acoes-linha">
    <button type="button" class="btn btn--texto" data-modal-cancelar>Ainda não</button>
    <button type="button" class="btn btn--primario" data-modal-ok>Sim, já comprei</button>
  </div>
</dialog>

<dialog class="modal" id="modal-pix" aria-labelledby="modal-pix-titulo">
  <h2 class="modal__titulo" id="modal-pix-titulo">Contribuir com PIX</h2>
  <p data-pix-cota></p>
  <div class="pix__qr" data-pix-qr role="img" aria-label="QR Code do PIX"></div>
  <div class="campo">
    <label for="pix-valor">Valor (R$)</label>
    <input id="pix-valor" type="text" inputmode="decimal" data-pix-valor>
  </div>
  <p class="pix__chave">Chave PIX: <strong data-pix-chave><?= $pixOk ? e($c['pix_chave']) : '' ?></strong></p>
  <div class="acoes-linha">
    <button type="button" class="btn btn--primario" data-pix-copiar-codigo><?= icone('copiar') ?> Copiar código PIX</button>
    <button type="button" class="btn btn--secundario" data-pix-copiar-chave><?= icone('copiar') ?> Copiar chave</button>
  </div>
  <p class="dica">No app do banco: PIX → “Pix copia e cola” (ou leia o QR Code).</p>
  <form method="dialog"><button class="btn btn--texto btn--bloco">Fechar</button></form>
</dialog>

<button type="button" class="voltar-topo" data-voltar-topo aria-label="Voltar ao topo" hidden><?= icone('seta-cima') ?></button>
<div class="toasts" data-toasts role="status" aria-live="polite"></div>

<script type="application/json" id="dados-site"><?= json_encode([
    'csrf'        => $csrf,
    'evento'      => $data->format('c'),
    'pix'         => $pixOk ? ['chave' => $c['pix_chave'], 'nome' => pendente($c['pix_nome']) ? '' : $c['pix_nome'], 'cidade' => pendente($c['pix_cidade']) ? '' : $c['pix_cidade']] : null,
    'local'       => ['lat' => $lat, 'lng' => $lng, 'nome' => $c['local_nome']],
    'casal'       => ['noiva' => $c['noiva_nome'], 'noivo' => $c['noivo_nome'], 'iniciais' => $ini1 . '&' . $ini2, 'data' => $data->format('d · m · Y')],
    'editando'    => (bool) $editando,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</body>
</html>
