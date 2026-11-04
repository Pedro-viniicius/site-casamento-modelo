// Utilitários compartilhados: dados do site, toast, feedback, armazenamento e fetch.

export const $ = (sel, raiz = document) => raiz.querySelector(sel);
export const $$ = (sel, raiz = document) => [...raiz.querySelectorAll(sel)];

let _dados = null;
export function dadosSite() {
  if (!_dados) {
    try { _dados = JSON.parse($('#dados-site')?.textContent || '{}'); } catch { _dados = {}; }
  }
  return _dados;
}

export const movimentoReduzido = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

export function vibrar(ms = 10) {
  try { if (!movimentoReduzido()) navigator.vibrate?.(ms); } catch { /* sem suporte */ }
}

export const armazenamento = {
  ler(chave, padrao = null) {
    try { const v = localStorage.getItem(chave); return v === null ? padrao : JSON.parse(v); } catch { return padrao; }
  },
  gravar(chave, valor) {
    try { localStorage.setItem(chave, JSON.stringify(valor)); } catch { /* modo privado */ }
  },
};

/**
 * Toast acessível (a região é aria-live="polite").
 * @param {string} texto
 * @param {{tipo?: 'ok'|'erro', acao?: string, aoAgir?: Function, duracao?: number}} op
 */
export function toast(texto, op = {}) {
  const regiao = $('[data-toasts]');
  if (!regiao) return { fechar() {} };
  const el = document.createElement('div');
  el.className = 'toast' + (op.tipo === 'erro' ? ' toast--erro' : '');
  const span = document.createElement('span');
  span.className = 'toast__texto';
  span.textContent = texto;
  el.append(span);
  let timer;
  const fechar = () => { clearTimeout(timer); el.remove(); };
  if (op.acao) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'toast__acao';
    b.textContent = op.acao;
    b.addEventListener('click', () => { fechar(); op.aoAgir?.(); });
    el.append(b);
  }
  regiao.append(el);
  while (regiao.children.length > 3) regiao.firstElementChild.remove();
  timer = setTimeout(fechar, op.duracao ?? (op.acao ? 10000 : 4000));
  return { fechar };
}

export async function copiar(texto, msg = 'Copiado ✓') {
  try {
    await navigator.clipboard.writeText(texto);
  } catch {
    const t = document.createElement('textarea');
    t.value = texto; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
    document.body.append(t); t.select();
    try { document.execCommand('copy'); } catch { toast('Não consegui copiar. Selecione e copie manualmente, por favor.', { tipo: 'erro' }); t.remove(); return false; }
    t.remove();
  }
  vibrar();
  toast(msg);
  return true;
}

/** POST JSON com CSRF; devolve {ok, status, dados}. Nunca lança. */
export async function postar(url, corpo) {
  try {
    const r = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ csrf: dadosSite().csrf, ...corpo }),
      credentials: 'same-origin',
    });
    let dados = {};
    try { dados = await r.json(); } catch { /* corpo vazio */ }
    return { ok: r.ok && dados.ok !== false, status: r.status, dados };
  } catch {
    return { ok: false, status: 0, dados: { mensagem: 'Sem conexão agora. Confira a internet e tente de novo, por favor.' } };
  }
}

/** Estado de carregamento no botão (feedback em < 100 ms). */
export function carregando(botao, ligado) {
  if (!botao) return;
  botao.classList.toggle('carregando', ligado);
  botao.disabled = ligado;
  botao.setAttribute('aria-busy', ligado ? 'true' : 'false');
}

export const brl = (v) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

/** Usuário está digitando? (atalhos de teclado não devem disparar) */
export const digitando = (el = document.activeElement) =>
  el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));

export function aoAparecer(el, cb, opcoes = { rootMargin: '0px 0px -10% 0px' }) {
  if (!('IntersectionObserver' in window)) { cb(el); return; }
  const io = new IntersectionObserver((entradas) => {
    for (const e of entradas) if (e.isIntersecting) { io.unobserve(e.target); cb(e.target); }
  }, opcoes);
  io.observe(el);
}
