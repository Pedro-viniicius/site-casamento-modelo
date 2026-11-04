// Navegação, abertura em envelope, contagem flip, linha do tempo, avisos e pequenos comportamentos.
import { $, $$, armazenamento, copiar, dadosSite, movimentoReduzido, vibrar } from './util.js';

/* ---------- Navegação: scrollspy (topo e barra inferior), folha "Mais", voltar ao topo ---------- */
export function iniciarNavegacao() {
  const links = $$('.topo__nav a, .barra-inferior a');
  const secoes = $$('[data-secao]');
  const mapaBarra = { inicio: 'inicio', casal: 'inicio', cerimonia: 'local', recepcao: 'local', avisos: 'local', local: 'local', padrinhos: 'presentes', presentes: 'presentes', presenca: 'presenca', recados: null };

  if ('IntersectionObserver' in window) {
    const visiveis = new Map();
    const io = new IntersectionObserver((entradas) => {
      entradas.forEach((e) => visiveis.set(e.target.id, e.intersectionRatio));
      const ativa = [...visiveis.entries()].sort((a, b) => b[1] - a[1])[0];
      if (!ativa || ativa[1] === 0) return;
      const id = ativa[0];
      links.forEach((a) => {
        const alvo = a.getAttribute('href').slice(1);
        const naBarra = a.closest('.barra-inferior');
        const ativo = naBarra ? mapaBarra[id] === alvo : alvo === id || (id === 'avisos' && alvo === 'recepcao');
        if (ativo) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
      });
    }, { threshold: [0, 0.15, 0.35, 0.6], rootMargin: '-64px 0px -30% 0px' });
    secoes.forEach((s) => io.observe(s));
  }

  const folha = $('#folha-mais');
  $('[data-abrir-mais]')?.addEventListener('click', () => folha?.showModal());
  folha?.addEventListener('click', (ev) => {
    if (ev.target.closest('a')) folha.close();
    if (ev.target === folha) folha.close(); // toque fora fecha
  });

  const topo = $('[data-voltar-topo]');
  let ultimo = 0;
  addEventListener('scroll', () => {
    const y = scrollY;
    if (Math.abs(y - ultimo) < 40) return;
    ultimo = y;
    if (topo) topo.hidden = y < innerHeight * 1.5;
  }, { passive: true });
  topo?.addEventListener('click', () => {
    scrollTo({ top: 0, behavior: movimentoReduzido() ? 'auto' : 'smooth' });
    $('#hero-titulo')?.focus({ preventScroll: true });
  });

  // Parallax leve nos ramos do hero (desligado com movimento reduzido).
  if (!movimentoReduzido()) {
    const ramos = $$('[data-parallax]');
    let pend = false;
    addEventListener('scroll', () => {
      if (pend || scrollY > innerHeight) return;
      pend = true;
      requestAnimationFrame(() => {
        ramos.forEach((r) => { r.style.translate = `0 ${scrollY * parseFloat(r.dataset.parallax)}px`; });
        pend = false;
      });
    }, { passive: true });
  }

  $$('[data-copiar]').forEach((b) => b.addEventListener('click', () => copiar(b.dataset.copiar, b.dataset.copiarMsg || 'Copiado ✓')));
}

/* ---------- Abertura: envelope com selo de cera (1,5 s, com "Pular") ---------- */
export function iniciarEnvelope() {
  const html = document.documentElement;
  const cortina = $('#envelope');
  if (!cortina || !html.classList.contains('com-envelope')) return;
  cortina.hidden = false;
  cortina.setAttribute('role', 'dialog');
  cortina.setAttribute('aria-modal', 'true');
  cortina.setAttribute('aria-label', 'Convite de casamento');
  cortina.innerHTML = `
    <button type="button" class="btn btn--texto envelope__pular" data-pular>Pular ✕</button>
    <div class="envelope">
      <div class="envelope__corpo"></div>
      <div class="envelope__carta"><small>Você é nosso convidado</small><p><span data-noiva></span><br>&amp; <span data-noivo></span></p><small data-data></small></div>
      <div class="envelope__frente" aria-hidden="true"><span data-nomes></span></div>
      <div class="envelope__aba" aria-hidden="true"></div>
      <button type="button" class="envelope__selo" data-abrir aria-label="Abrir o convite" data-iniciais></button>
      <p class="envelope__instrucao">Toque no selo para abrir</p>
    </div>`;
  // Nomes e data vêm das Configurações (dados-site), sempre como texto (nunca HTML).
  const casal = dadosSite().casal || {};
  const texto = (sel, v) => { const el = $(sel, cortina); if (el) el.textContent = v || ''; };
  texto('[data-noiva]', casal.noiva);
  texto('[data-noivo]', casal.noivo);
  texto('[data-data]', casal.data);
  texto('[data-nomes]', `${casal.noiva || ''} & ${casal.noivo || ''}`);
  texto('[data-iniciais]', casal.iniciais);
  const envelope = $('.envelope', cortina);
  const selo = $('[data-abrir]', cortina);
  const conteudo = $('main');
  conteudo?.setAttribute('inert', '');
  selo.focus();

  let fechado = false;
  const fechar = () => {
    if (fechado) return;
    fechado = true;
    armazenamento.gravar('cmv_envelope_visto', 1);
    try { localStorage.setItem('cmv_envelope_visto', '1'); } catch { /* ok */ }
    cortina.classList.add('saindo');
    conteudo?.removeAttribute('inert');
    setTimeout(() => { cortina.hidden = true; html.classList.remove('com-envelope'); cortina.innerHTML = ''; $('#hero-titulo')?.focus({ preventScroll: true }); }, 500);
    document.removeEventListener('keydown', aoTeclar);
  };
  const aoTeclar = (ev) => { if (ev.key === 'Escape') fechar(); };
  document.addEventListener('keydown', aoTeclar);
  $('[data-pular]', cortina).addEventListener('click', fechar);
  selo.addEventListener('click', () => {
    vibrar(15);
    envelope.classList.add('aberto');
    $('.envelope__instrucao', cortina).textContent = '';
    setTimeout(fechar, 1500);
  });
}

/* ---------- Contagem regressiva flip ---------- */
export function iniciarContagem() {
  const relogio = $('[data-contagem]');
  if (!relogio) return;
  const alvo = new Date(relogio.dataset.contagem).getTime();
  const msg = $('[data-contagem-msg]');
  const celulas = Object.fromEntries($$('.flip', relogio).map((f) => [f.dataset.unidade, $('[data-valor]', f)]));
  const fimDoDia = alvo + 10 * 3600e3;

  function tique() {
    const agora = Date.now();
    let resto = Math.max(0, alvo - agora);
    if (resto === 0) {
      relogio.hidden = true;
      msg.textContent = agora < fimDoDia ? 'Hoje é o grande dia! 💍' : 'Obrigado por celebrar com a gente! 💛';
      return false;
    }
    const v = {
      dias: Math.floor(resto / 864e5),
      horas: Math.floor((resto % 864e5) / 36e5),
      min: Math.floor((resto % 36e5) / 6e4),
      seg: Math.floor((resto % 6e4) / 1e3),
    };
    for (const [u, el] of Object.entries(celulas)) {
      const txt = String(v[u]).padStart(2, '0');
      if (el.textContent !== txt) {
        el.textContent = txt;
        if (!movimentoReduzido() && u !== 'seg') { el.classList.remove('virando'); void el.offsetWidth; el.classList.add('virando'); }
      }
    }
    msg.textContent = v.dias > 1 ? `Faltam ${v.dias} dias para o nosso “sim”.` : v.dias === 1 ? 'É amanhã! Separe o sapato confortável 👟' : 'É hoje! Nos vemos daqui a pouco 💛';
    return true;
  }
  if (tique()) {
    const id = setInterval(() => { if (!tique()) clearInterval(id); }, 1000);
  }
}

/* ---------- Linha do tempo que se desenha com o scroll ---------- */
export function iniciarLinhaTempo() {
  const ol = $('[data-linha-tempo]');
  if (!ol) return;
  const marcos = $$('[data-marco]', ol);
  if (movimentoReduzido() || !('IntersectionObserver' in window)) {
    marcos.forEach((m) => m.classList.add('visivel'));
    ol.style.setProperty('--progresso', 1);
    return;
  }
  const io = new IntersectionObserver((es) => es.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('visivel'); io.unobserve(e.target); } }), { rootMargin: '0px 0px -15% 0px' });
  marcos.forEach((m) => io.observe(m));

  let ativo = false, pend = false;
  const desenhar = () => {
    pend = false;
    const r = ol.getBoundingClientRect();
    const p = Math.min(1, Math.max(0, (innerHeight * 0.75 - r.top) / r.height));
    ol.style.setProperty('--progresso', p.toFixed(3));
  };
  new IntersectionObserver(([e]) => { ativo = e.isIntersecting; if (ativo) desenhar(); }).observe(ol);
  ol.style.setProperty('--progresso', 0);
  addEventListener('scroll', () => { if (ativo && !pend) { pend = true; requestAnimationFrame(desenhar); } }, { passive: true });
}

/* ---------- Avisos: cartões que viram ---------- */
export function iniciarAvisos() {
  $$('[data-virar]').forEach((b) => b.addEventListener('click', () => {
    const virado = b.getAttribute('aria-pressed') === 'true';
    b.setAttribute('aria-pressed', String(!virado));
  }));
}
