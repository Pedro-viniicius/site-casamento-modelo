// Carrossel único do site: scroll-snap + setas grandes + bolinhas + "1–3 de 12".
// Nunca avança sozinho. ←/→ navegam quando o foco (ou o mouse) está no carrossel.
import { $, $$ } from './util.js';

const instancias = new Set();
let emFoco = null;

export function iniciarCarrosseis(raiz = document) {
  $$('[data-carrossel]', raiz).forEach((el) => {
    if (el._carrossel) { el._carrossel.atualizar(); return; }
    el._carrossel = criar(el);
    instancias.add(el._carrossel);
  });
}

function criar(raiz) {
  const trilho = $('.carrossel__trilho', raiz);
  const ant = $('[data-anterior]', raiz);
  const prox = $('[data-proximo]', raiz);
  const pontos = $('[data-pontos]', raiz);
  const contador = $('[data-contador-carrossel]', raiz);
  const itens = () => [...trilho.children].filter((c) => c.classList.contains('carrossel__item') && !c.hidden);

  function porVez() {
    const v = parseFloat(getComputedStyle(raiz).getPropertyValue('--por-vez')) || 1;
    return Math.max(1, Math.floor(v + 0.05));
  }

  function indiceAtual() {
    const lista = itens();
    if (!lista.length) return 0;
    const base = trilho.getBoundingClientRect().left;
    let melhor = 0, dist = Infinity;
    lista.forEach((it, i) => {
      const d = Math.abs(it.getBoundingClientRect().left - base);
      if (d < dist) { dist = d; melhor = i; }
    });
    return melhor;
  }

  function irPara(i) {
    const lista = itens();
    if (!lista.length) return;
    const alvo = lista[Math.max(0, Math.min(i, lista.length - 1))];
    trilho.scrollTo({ left: alvo.offsetLeft - trilho.offsetLeft - 2, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }

  function passo(dir) {
    const lista = itens();
    const n = porVez();
    let i = indiceAtual() + dir * n;
    // No fim, a próxima volta ao começo (e vice-versa): o usuário decide, nada é automático.
    if (dir > 0 && indiceAtual() + n >= lista.length) i = 0;
    if (dir < 0 && indiceAtual() === 0) i = Math.max(0, lista.length - n);
    irPara(i);
  }

  let raf = 0;
  function atualizar() {
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => {
      const lista = itens();
      const total = lista.length;
      const n = Math.min(porVez(), total);
      const i = indiceAtual();
      const fim = Math.min(total, i + n);
      if (contador) contador.textContent = total ? (n > 1 ? `${i + 1}–${fim} de ${total}` : `${i + 1} de ${total}`) : '';
      const paginas = Math.ceil(total / Math.max(1, n));
      if (pontos) {
        if (pontos.children.length !== paginas) {
          pontos.replaceChildren(...Array.from({ length: Math.min(paginas, 12) }, () => document.createElement('span')));
        }
        const pag = Math.min(paginas - 1, Math.round(i / Math.max(1, n)));
        [...pontos.children].forEach((s, k) => s.classList.toggle('ativo', k === pag));
      }
      const semRolagem = trilho.scrollWidth <= trilho.clientWidth + 4;
      raiz.classList.toggle('carrossel--fixo', semRolagem);
      if (ant) ant.disabled = semRolagem;
      if (prox) prox.disabled = semRolagem;
    });
  }

  ant?.addEventListener('click', () => passo(-1));
  prox?.addEventListener('click', () => passo(1));
  trilho.addEventListener('scroll', atualizar, { passive: true });
  raiz.addEventListener('focusin', () => { emFoco = api; });
  raiz.addEventListener('pointerenter', () => { emFoco = api; });
  raiz.addEventListener('pointerleave', () => { if (emFoco === api && !raiz.contains(document.activeElement)) emFoco = null; });
  if ('ResizeObserver' in window) new ResizeObserver(atualizar).observe(trilho);

  const api = { raiz, passo, irPara, atualizar };
  atualizar();
  return api;
}

// Atalho global: ←/→ no carrossel em foco/sob o mouse (fora de campos de texto).
document.addEventListener('keydown', (ev) => {
  if (!emFoco || ev.altKey || ev.ctrlKey || ev.metaKey) return;
  const alvo = document.activeElement;
  if (alvo && /^(INPUT|TEXTAREA|SELECT)$/.test(alvo.tagName)) return;
  if (ev.key === 'ArrowRight') { ev.preventDefault(); emFoco.passo(1); }
  if (ev.key === 'ArrowLeft') { ev.preventDefault(); emFoco.passo(-1); }
});
