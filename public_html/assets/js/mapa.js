// "Como chegar": cartão estático primeiro; MapLibre GL (self-hosted) só baixa quando a seção
// se aproxima da tela ou quando a pessoa toca em "Explorar em 3D". Sem WebGL, fica o cartão.
import { $, dadosSite, movimentoReduzido, toast, aoAparecer } from './util.js';

const ESTILO = 'https://tiles.openfreemap.org/styles/liberty';
const DEM = 'https://s3.amazonaws.com/elevation-tiles-prod/terrarium/{z}/{x}/{y}.png';

function temWebGL() {
  try {
    const c = document.createElement('canvas');
    return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
  } catch { return false; }
}

let carregamento = null;
function carregarLib() {
  if (window.maplibregl) return Promise.resolve(window.maplibregl);
  return carregamento ??= new Promise((ok, falha) => {
    const css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = '/assets/vendor/maplibre/maplibre-gl.css';
    document.head.append(css);
    const s = document.createElement('script');
    s.src = '/assets/vendor/maplibre/maplibre-gl.js';
    s.onload = () => ok(window.maplibregl);
    s.onerror = falha;
    document.head.append(s);
  });
}

export function iniciarMapa() {
  const caixa = $('[data-mapa]');
  if (!caixa) return;
  const botao = $('[data-mapa-abrir]', caixa);
  if (!temWebGL()) { botao.hidden = true; return; } // fallback total: ilustração + botões de rota

  let mapa = null;
  let iniciado = false;
  const { local } = dadosSite();

  async function abrir(interativo) {
    if (iniciado) { if (interativo) liberarGestos(); return; }
    iniciado = true;
    botao.classList.add('carregando');
    try {
      const maplibregl = await carregarLib();
      const canvas = $('[data-mapa-canvas]', caixa);
      canvas.hidden = false;
      mapa = new maplibregl.Map({
        container: canvas,
        style: ESTILO,
        center: [local.lng, local.lat],
        zoom: 13,
        pitch: 45,
        bearing: -15,
        maxPitch: 75,
        attributionControl: { compact: true },
        cooperativeGestures: true, // no celular, só com dois dedos: não sequestra o scroll
        locale: {
          'CooperativeGesturesHandler.WindowsHelpText': 'Use Ctrl + rolagem para dar zoom no mapa',
          'CooperativeGesturesHandler.MacHelpText': 'Use ⌘ + rolagem para dar zoom no mapa',
          'CooperativeGesturesHandler.MobileHelpText': 'Use dois dedos para mover o mapa',
        },
      });
      caixa._mapa = mapa; // útil para depurar no console
      mapa.on('load', async () => {
        mapa.addSource('relevo', { type: 'raster-dem', tiles: [DEM], tileSize: 256, encoding: 'terrarium', maxzoom: 14, attribution: 'Relevo: Mapzen/AWS Terrain Tiles' });
        mapa.setTerrain({ source: 'relevo', exaggeration: 1.6 });
        mapa.addLayer({ id: 'sombras', type: 'hillshade', source: 'relevo', paint: { 'hillshade-shadow-color': '#5F5C2B', 'hillshade-exaggeration': 0.35 } });
        try {
          if (!caixa.dataset.rota) throw new Error('sem rota'); // rota.geojson é opcional
          const rota = await (await fetch(caixa.dataset.rota)).json();
          mapa.addSource('rota', { type: 'geojson', data: rota });
          mapa.addLayer({ id: 'rota-borda', type: 'line', source: 'rota', paint: { 'line-color': '#FFFFFF', 'line-width': 7, 'line-opacity': 0.8 } });
          mapa.addLayer({ id: 'rota', type: 'line', source: 'rota', layout: { 'line-cap': 'round' }, paint: { 'line-color': '#7A5C22', 'line-width': 4, 'line-dasharray': [0.6, 1.8] } });
        } catch { /* sem rota, segue o pin */ }

        const el = document.createElement('div');
        el.className = 'pin-casamento';
        el.innerHTML = '<span aria-hidden="true">💍</span>';
        el.setAttribute('role', 'img');
        el.setAttribute('aria-label', local.nome);
        new maplibregl.Marker({ element: el, anchor: 'bottom' })
          .setLngLat([local.lng, local.lat])
          .setPopup(new maplibregl.Popup({ offset: 30 }).setText(`${local.nome} · cerimônia e recepção`))
          .addTo(mapa);

        $('[data-mapa-estatico]', caixa).hidden = true;
        $('[data-mapa-controles]', caixa).hidden = false;
        botao.classList.remove('carregando');
        voar();
      });
      mapa.on('error', (e) => { if (String(e?.error?.message || '').includes('style')) falhou(); });
    } catch {
      falhou();
    }
  }

  function falhou() {
    botao.classList.remove('carregando');
    botao.hidden = true;
    $('[data-mapa-canvas]', caixa).hidden = true;
    $('[data-mapa-estatico]', caixa).hidden = false;
    toast('O mapa 3D não carregou agora, mas os botões de rota funcionam 😉', { tipo: 'erro' });
  }

  function voar() {
    mapa.flyTo({ center: [local.lng, local.lat], zoom: 15.2, pitch: 62, bearing: 30, duration: movimentoReduzido() ? 0 : 4200, essential: false });
  }
  function liberarGestos() {
    $('[data-mapa-dica]', caixa).hidden = false;
    setTimeout(() => { $('[data-mapa-dica]', caixa).hidden = true; }, 3500);
  }

  $('[data-mapa-controles]', caixa).addEventListener('click', (ev) => {
    const acao = ev.target.closest('[data-mapa-acao]')?.dataset.mapaAcao;
    if (!acao || !mapa) return;
    if (acao === 'girar') mapa.easeTo({ bearing: mapa.getBearing() + 90, duration: movimentoReduzido() ? 0 : 1600 });
    if (acao === 'centro') voar();
    if (acao === 'topo') mapa.easeTo({ pitch: 0, bearing: 0, zoom: 14.5, center: [local.lng, local.lat], duration: movimentoReduzido() ? 0 : 1200 });
  });
  botao.addEventListener('click', () => abrir(true));
  // Carrega sozinho quando a seção chega perto, sem som e sem prender a rolagem.
  aoAparecer(caixa, () => abrir(false), { rootMargin: '200px 0px' });
}
