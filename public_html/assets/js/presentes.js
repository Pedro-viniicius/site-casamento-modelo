// Lista de presentes: filtros (faixa/categoria/busca/ordem) com deep link,
// atalhos (/, 1, 2, 3), "Já comprei" com confirmação + Desfazer (10 s) + "Marquei por engano".
import { $, $$, armazenamento, carregando, digitando, postar, toast, vibrar } from './util.js';
import { iniciarCarrosseis } from './carrossel.js';

const CHAVE_MARCAS = 'cmv_marcas'; // { [presenteId]: [token, ...] } só deste aparelho
const estado = { faixa: '', cat: '', q: '', ordem: 'padrao' };
let trilho, cards, contador, vazio, filtrosAtivos = false;

export function iniciarPresentes() {
  const secao = $('#presentes');
  if (!secao) return;
  trilho = $('[data-lista-presentes]', secao);
  cards = $$('.presente', trilho);
  contador = $('[data-contador]', secao);
  vazio = $('[data-vazio]', secao);

  $$('[data-faixa].chip', secao).forEach((b) => b.addEventListener('click', () => alternar('faixa', b.dataset.faixa)));
  $$('[data-cat].chip', secao).forEach((b) => b.addEventListener('click', () => alternar('cat', b.dataset.cat)));
  const busca = $('[data-busca]', secao);
  let t;
  busca?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => { estado.q = busca.value.trim(); aplicar(true); }, 120); });
  $('[data-ordem]', secao)?.addEventListener('change', (ev) => { estado.ordem = ev.target.value; aplicar(true); });
  $$('[data-limpar]', secao).forEach((b) => b.addEventListener('click', limpar));

  // Atalhos: "/" foca a busca; 1/2/3 aplicam as faixas.
  document.addEventListener('keydown', (ev) => {
    if (ev.ctrlKey || ev.metaKey || ev.altKey || digitando() || document.querySelector('dialog[open]')) return;
    if (ev.key === '/') { ev.preventDefault(); secao.scrollIntoView({ block: 'start' }); busca?.focus(); }
    const faixas = ['ate-100', '100-250', 'acima-250'];
    if (['1', '2', '3'].includes(ev.key)) { alternar('faixa', faixas[+ev.key - 1]); secao.scrollIntoView({ block: 'start' }); }
  });

  lerDaUrl();
  addEventListener('hashchange', () => { if (location.hash.startsWith('#presentes?')) { lerDaUrl(); } });

  iniciarMarcacao(secao);
  iniciarOutrasLojas(secao);
  restaurarMarcasLocais();
  aplicar(false);
}

function alternar(campo, valor) {
  estado[campo] = estado[campo] === valor ? '' : valor;
  aplicar(true);
  vibrar(5);
}

function limpar() {
  Object.assign(estado, { faixa: '', cat: '', q: '', ordem: 'padrao' });
  const busca = $('[data-busca]'); if (busca) busca.value = '';
  const ordem = $('[data-ordem]'); if (ordem) ordem.value = 'padrao';
  aplicar(true);
}

function lerDaUrl() {
  const h = location.hash;
  if (!h.startsWith('#presentes?')) return;
  const p = new URLSearchParams(h.slice('#presentes?'.length));
  estado.faixa = p.get('faixa') || '';
  estado.cat = p.get('cat') || '';
  estado.q = p.get('q') || '';
  estado.ordem = p.get('ordem') || 'padrao';
  const busca = $('[data-busca]'); if (busca) busca.value = estado.q;
  const ordem = $('[data-ordem]'); if (ordem) ordem.value = estado.ordem;
  aplicar(false);
  requestAnimationFrame(() => $('#presentes')?.scrollIntoView({ block: 'start' }));
}

function gravarNaUrl() {
  const p = new URLSearchParams();
  for (const k of ['faixa', 'cat', 'q']) if (estado[k]) p.set(k, estado[k]);
  if (estado.ordem !== 'padrao') p.set('ordem', estado.ordem);
  const s = p.toString();
  history.replaceState(null, '', s ? `#presentes?${s}` : '#presentes');
}

const normalizar = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

function aplicar(mexeuNaUrl) {
  if (!trilho) return;
  const q = normalizar(estado.q);
  let visiveis = 0;
  for (const c of cards) {
    const ok = (!estado.faixa || c.dataset.faixa === estado.faixa)
      && (!estado.cat || c.dataset.cat === estado.cat)
      && (!q || normalizar(c.dataset.nome).includes(q));
    c.hidden = !ok;
    if (ok) visiveis++;
  }
  // Ordenação: presenteados sempre no fim.
  const chave = {
    padrao: (c) => +c.dataset.ordem,
    menor: (c) => +c.dataset.preco,
    maior: (c) => -c.dataset.preco,
    desejados: (c) => -(+c.dataset.desejado) * 1e6 + +c.dataset.ordem,
  }[estado.ordem] || ((c) => +c.dataset.ordem);
  const ordenados = [...cards].sort((a, b) => (esgotado(a) - esgotado(b)) || (chave(a) - chave(b)));
  ordenados.forEach((c) => trilho.append(c));

  // Chips e "Limpar filtros" sempre refletem o estado (menor carga de memória).
  $$('#presentes [data-faixa].chip').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.faixa === estado.faixa)));
  $$('#presentes [data-cat].chip').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.cat === estado.cat)));
  filtrosAtivos = !!(estado.faixa || estado.cat || estado.q || estado.ordem !== 'padrao');
  $$('#presentes [data-limpar]').forEach((b) => { if (!b.closest('[data-vazio]')) b.hidden = !filtrosAtivos; });
  if (contador) contador.textContent = `Mostrando ${visiveis} de ${cards.length}`;
  if (vazio) vazio.hidden = visiveis > 0;
  trilho.scrollTo({ left: 0 });
  iniciarCarrosseis($('#presentes'));
  if (mexeuNaUrl) gravarNaUrl();
}

const esgotado = (c) => (+c.dataset.marcados >= +c.dataset.qtd ? 1 : 0);

/* ---------- Outras lojas: folha inferior (sem JS, o <details> abre no lugar) ---------- */
function iniciarOutrasLojas(secao) {
  const folha = $('#folha-ofertas');
  if (!folha) return;
  secao.addEventListener('click', (ev) => {
    const sum = ev.target.closest('.presente__outras > summary');
    if (!sum) return;
    ev.preventDefault();
    const card = sum.closest('.presente');
    $('#folha-ofertas-titulo').textContent = `Outras lojas · ${$('.presente__nome', card).textContent}`;
    $('[data-folha-conteudo]', folha).replaceChildren($('.lista-ofertas', card).cloneNode(true));
    folha.showModal();
  });
  folha.addEventListener('click', (ev) => { if (ev.target === folha) folha.close(); });
}

/* ---------- "Já comprei" ---------- */
function iniciarMarcacao(raiz) {
  const modal = $('#modal-confirmar');
  let pendente = null;

  document.addEventListener('submit', (ev) => {
    const form = ev.target.closest('.presente__form');
    if (!form) return;
    ev.preventDefault();
    const card = form.closest('.presente');
    pendente = { card, botao: $('[data-comprei]', form) };
    const cota = card.dataset.tipo === 'cota';
    $('#modal-confirmar-titulo').textContent = cota ? 'Confirma que contribuiu?' : 'Confirma que comprou?';
    const p = $('[data-modal-texto]', modal);
    p.replaceChildren(cota ? 'Vamos marcar a cota ' : 'Vamos marcar ', Object.assign(document.createElement('strong'), { textContent: $('.presente__nome', card).textContent }), ' como presenteado para todos verem. Você pode desfazer depois.');
    $('[data-modal-ok]', modal).textContent = cota ? 'Sim, já contribuí' : 'Sim, já comprei';
    modal.showModal();
  });
  $('[data-modal-cancelar]', modal)?.addEventListener('click', () => modal.close());
  modal?.addEventListener('click', (ev) => { if (ev.target === modal) modal.close(); });
  $('[data-modal-ok]', modal)?.addEventListener('click', async () => {
    if (!pendente) return;
    const { card, botao } = pendente;
    const okBtn = $('[data-modal-ok]', modal);
    carregando(okBtn, true);
    const nome = $('#comprei-nome').value.trim();
    const r = await postar('/api/comprei.php', { acao: 'marcar', presente_id: +card.dataset.id, nome });
    carregando(okBtn, false);
    modal.close();
    if (!r.ok) { toast(r.dados.mensagem || 'Não deu certo agora. Tente de novo, por favor.', { tipo: 'erro' }); return; }
    vibrar(10);
    guardarToken(card.dataset.id, r.dados.token);
    atualizarCard(card, r.dados);
    botao?.blur();
    const nomeP = $('.presente__nome', card).textContent;
    toast(`Obrigado! “${nomeP}” marcado 💚`, { acao: 'Desfazer', aoAgir: () => desfazer(card, r.dados.token) });
    aplicar(false);
    pendente = null;
  });

  raiz.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-desmarcar]');
    if (!b) return;
    const card = b.closest('.presente');
    const tokens = armazenamento.ler(CHAVE_MARCAS, {})[card.dataset.id] || [];
    if (tokens.length) desfazer(card, tokens[tokens.length - 1], b);
  });
}

async function desfazer(card, token, botao) {
  carregando(botao, true);
  const r = await postar('/api/comprei.php', { acao: 'desfazer', token });
  carregando(botao, false);
  if (!r.ok) { toast(r.dados.mensagem || 'Não consegui desfazer agora. Tente de novo.', { tipo: 'erro' }); return; }
  removerToken(card.dataset.id, token);
  atualizarCard(card, r.dados);
  aplicar(false);
  toast('Pronto, desfeito. O presente voltou para a lista.');
}

function atualizarCard(card, d) {
  if (typeof d.marcados === 'number') card.dataset.marcados = d.marcados;
  const qtd = +card.dataset.qtd;
  const marc = +card.dataset.marcados;
  const cheio = marc >= qtd;
  card.classList.toggle('presente--esgotado', cheio);
  $('.selo--presenteado', card).hidden = !cheio;
  const btn = $('[data-comprei]', card);
  if (btn) btn.disabled = cheio;
  const prog = $('[data-progresso]', card);
  if (prog) prog.textContent = `${Math.min(marc, qtd)} de ${qtd}${card.dataset.tipo === 'cota' ? ' cotas recebidas' : ' já presenteados'}`;
  const tokens = armazenamento.ler(CHAVE_MARCAS, {})[card.dataset.id] || [];
  const desm = $('[data-desmarcar]', card);
  if (desm) desm.hidden = tokens.length === 0;
}

function guardarToken(id, token) {
  const m = armazenamento.ler(CHAVE_MARCAS, {});
  (m[id] ||= []).push(token);
  armazenamento.gravar(CHAVE_MARCAS, m);
}
function removerToken(id, token) {
  const m = armazenamento.ler(CHAVE_MARCAS, {});
  m[id] = (m[id] || []).filter((t) => t !== token);
  if (!m[id].length) delete m[id];
  armazenamento.gravar(CHAVE_MARCAS, m);
}
function restaurarMarcasLocais() {
  const m = armazenamento.ler(CHAVE_MARCAS, {});
  $$('.presente').forEach((c) => { if (m[c.dataset.id]?.length) { const d = $('[data-desmarcar]', c); if (d) d.hidden = false; } });
}
