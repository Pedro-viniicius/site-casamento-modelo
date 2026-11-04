#!/usr/bin/env node
// Importador da lista de presentes a partir do app Etiqueta (API v1, só leitura).
// Roda no SEU computador (onde o Etiqueta está ligado), nunca no servidor da Hostinger.
//
// Uso:
//   node tools/importar-etiqueta.mjs                 # consulta o Etiqueta e gera os arquivos locais
//   node tools/importar-etiqueta.mjs --enviar https://seudominio.com.br
//                                                    # ...e envia as ofertas para o site (admin/importar)
//   node tools/importar-etiqueta.mjs --so-seeds      # só regenera seed/OFERTAS.md a partir do JSON salvo
//
// Opções:
//   --etiqueta <url>        padrão http://127.0.0.1:8765
//   --token-arquivo <path>  padrão $ETIQUETA_TOKEN_ARQUIVO ou ~/etiqueta/api_token.txt
//   --chave-arquivo <path>  arquivo com a IMPORT_KEY do site (padrão config/import_key.txt)
//
// Gera: data/ofertas-etiqueta.json, database/seed_presentes.sql, OFERTAS.md
// O token e a chave são lidos de arquivo e NUNCA impressos, logados ou postos em URL.

import { readFile, writeFile } from 'node:fs/promises';
import { homedir } from 'node:os';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const RAIZ = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2);
const opt = (nome, padrao) => {
  const i = args.indexOf(`--${nome}`);
  return i >= 0 && args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : padrao;
};
const flag = (nome) => args.includes(`--${nome}`);
const expandir = (p) => (p.startsWith('~/') ? resolve(homedir(), p.slice(2)) : resolve(p));

const ETIQUETA = opt('etiqueta', 'http://127.0.0.1:8765').replace(/\/+$/, '');
const TOKEN_ARQ = expandir(opt('token-arquivo', process.env.ETIQUETA_TOKEN_ARQUIVO || '~/etiqueta/api_token.txt'));
const CHAVE_ARQ = expandir(opt('chave-arquivo', resolve(RAIZ, 'config/import_key.txt')));
const SITE = opt('enviar', null);

const NOTA_MIN = 4.3;
// Padrão para cidades de 127 V: eletro só 220 V perde pontos, e variações que só mudam a voltagem não viram "alternativa".
const RE_VOLT = /\b(110|127|220|240)\s?v(olts?)?\b|\bbivolt\b/gi;
const so220 = (t) => /\b220\s?v/i.test(t) && !/\b(110|127)\s?v|\bbivolt\b/i.test(t);
const chaveModelo = (t) => semAcento(t).replace(RE_VOLT, '').replace(/[^a-z0-9]+/g, ' ').trim().split(' ').slice(0, 7).join(' ');
const LOJA_NOME = { amazon: 'Amazon', ml: 'Mercado Livre', shopee: 'Shopee' };
const CDN_OK = /^https:\/\/(m\.media-amazon\.com|http2\.mlstatic\.com|down-br\.img\.susercontent\.com)\//;

const semAcento = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

// Confere que o link é de afiliado e da própria loja (mesma regra do contrato do Etiqueta).
function linkValido(o) {
  let u;
  try { u = new URL(o.link); } catch { return false; }
  if (u.protocol !== 'https:') return false;
  if (o.loja === 'amazon') return /(^|\.)amazon\.com\.br$/.test(u.hostname) && u.searchParams.has('tag');
  if (o.loja === 'ml') return u.hostname === 'meli.la';
  if (o.loja === 'shopee') return u.hostname === 's.shopee.com.br';
  return false;
}

// URL canônica e limpa (sem rastreio) para conferência; o botão usa o link de afiliado.
function urlCanonica(o) {
  if (o.loja === 'amazon' && /^[A-Z0-9]{10}$/.test(o.item_id)) return `https://www.amazon.com.br/dp/${o.item_id}`;
  if (o.loja === 'ml') {
    const m = /^MLB-?(\d{6,})$/.exec(o.item_id || '');
    if (m) return `https://produto.mercadolivre.com.br/MLB-${m[1]}`;
  }
  if (o.loja === 'shopee') {
    const m = /^(\d+)\.(\d+)$/.exec(o.item_id || '');
    if (m) return `https://shopee.com.br/product/${m[1]}/${m[2]}`;
  }
  return o.link;
}

function casa(titulo, regra) {
  const t = semAcento(titulo);
  const deve = (regra.deve || []).every((padrao) => new RegExp(`(^|[^a-z])(${semAcento(padrao)})`, 'i').test(t));
  const nao = (regra.nao || []).some((p) => t.includes(semAcento(p)));
  return deve && !nao;
}

// Nota "bayesiana": puxa notas com poucas avaliações para perto de 4,3,
// para que um 5,0 com 5 avaliações não vença um 4,6 com milhares.
function notaAjustada(o) {
  if (o.avaliacao == null) return 4.1;
  const n = o.num_avaliacoes > 0 ? o.num_avaliacoes : Math.min(o.vendas || 0, 200) || 10;
  const m = 30;
  return (n * o.avaliacao + m * NOTA_MIN) / (n + m);
}

function pontuar(o, ref) {
  const nota = notaAjustada(o);
  const volume = Math.log10(1 + (o.num_avaliacoes || 0) + (o.vendas || 0));
  const distancia = Math.abs(o.preco - ref) / ref;
  return nota * 2 + volume + (o.frete_gratis ? 0.5 : 0) - distancia * 1.5 - (so220(o.titulo) ? 3 : 0);
}

async function lerSegredo(caminho, oQue) {
  try {
    const v = (await readFile(caminho, 'utf8')).trim();
    if (!v) throw new Error('vazio');
    return v;
  } catch {
    console.error(`Não consegui ler ${oQue} em ${caminho}. Confira o caminho (o conteúdo nunca é exibido).`);
    process.exit(2);
  }
}

async function consultarEtiqueta(base) {
  const token = await lerSegredo(TOKEN_ARQ, 'o token do Etiqueta');
  const get = async (params) => {
    const u = new URL(`${ETIQUETA}/api/v1/ofertas`);
    for (const [k, v] of params) u.searchParams.append(k, v);
    const r = await fetch(u, { headers: { Authorization: `Bearer ${token}` } });
    if (r.status === 401) throw new Error('o Etiqueta recusou o token (401)');
    if (r.status === 503) throw new Error('a API do Etiqueta está desligada (503)');
    if (!r.ok) throw new Error(`o Etiqueta respondeu ${r.status}`);
    return r.json();
  };

  const agora = new Date().toISOString();
  const resultado = { gerado_em: agora, fonte: 'etiqueta', presentes: [], pendentes: [] };
  for (const p of base.presentes) {
    const regra = p.busca;
    // Um pedido por vez (o contrato pede para não paralelizar).
    const d = await get([['q', regra.q], ['ordem', 'relevancia'], ['limite', '200']]);
    const vistos = new Set();
    const candidatos = d.ofertas
      .filter((o) => !vistos.has(o.chave) && vistos.add(o.chave))
      .filter((o) => linkValido(o) && o.preco >= regra.min && o.preco <= regra.max)
      .filter((o) => o.avaliacao == null || o.avaliacao >= NOTA_MIN)
      .filter((o) => casa(o.titulo, regra))
      .map((o) => ({ o, score: pontuar(o, p.preco_ref) }))
      .sort((a, b) => b.score - a.score);

    if (!candidatos.length) {
      resultado.pendentes.push({ slug: p.slug, nome: p.nome, motivo: `nenhuma oferta no Etiqueta para "${regra.q}" entre R$ ${regra.min} e R$ ${regra.max}` });
      continue;
    }
    // Principal = melhor pontuação; alternativas preferem outra loja e nunca repetem o mesmo modelo
    // em outra voltagem (o anúncio 220 V do mesmo produto não é "outra opção" para quem mora em 127 V).
    const escolhidas = [candidatos[0]];
    const lojas = new Set([candidatos[0].o.loja]);
    const modelos = new Set([chaveModelo(candidatos[0].o.titulo)]);
    const pode = (c) => !escolhidas.includes(c) && !modelos.has(chaveModelo(c.o.titulo)) && !so220(c.o.titulo);
    const pegar = (c) => { escolhidas.push(c); lojas.add(c.o.loja); modelos.add(chaveModelo(c.o.titulo)); };
    for (const c of candidatos.slice(1)) {
      if (escolhidas.length >= 3) break;
      if (!lojas.has(c.o.loja) && pode(c)) pegar(c);
    }
    for (const c of candidatos.slice(1)) {
      if (escolhidas.length >= 3) break;
      if (pode(c)) pegar(c);
    }
    resultado.presentes.push({
      slug: p.slug,
      ofertas: escolhidas.map(({ o }, i) => ({
        loja: o.loja,
        titulo: o.titulo.slice(0, 300),
        preco: o.preco,
        url: urlCanonica(o),
        link_afiliado: o.link,
        imagem_url: o.imagem && CDN_OK.test(o.imagem) ? o.imagem : null,
        avaliacao: o.avaliacao,
        num_avaliacoes: o.num_avaliacoes || 0,
        frete_gratis: !!o.frete_gratis,
        principal: i === 0,
        chave_origem: o.chave,
        verificado_em: (o.atualizado_em || agora).slice(0, 19).replace('T', ' '),
      })),
    });
    process.stdout.write('.');
  }
  process.stdout.write('\n');
  return resultado;
}

// ---------- geração do seed SQL ----------
const sq = (v) => (v == null ? 'NULL' : typeof v === 'number' ? String(v) : typeof v === 'boolean' ? (v ? '1' : '0') : `'${String(v).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`);
const faixaDe = (preco) => (preco <= 100 ? 'ate-100' : preco <= 250 ? '100-250' : 'acima-250');

function gerarSeed(base, ofertas) {
  const porSlug = new Map(ofertas.presentes.map((p) => [p.slug, p.ofertas]));
  const linhas = [
    '-- GERADO por tools/importar-etiqueta.mjs a partir de data/presentes-base.json e data/ofertas-etiqueta.json.',
    `-- Gerado em ${new Date().toISOString()}. Pode ser reimportado: atualiza presentes e troca só as ofertas vindas do Etiqueta.`,
    'SET NAMES utf8mb4;',
    '',
  ];
  let ordem = 0;
  for (const p of base.presentes) {
    ordem += 10;
    const ofs = porSlug.get(p.slug) || [];
    const precoEfetivo = ofs.find((o) => o.principal)?.preco ?? p.preco_ref;
    linhas.push(
      `INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES (${[p.slug, 'item', p.nome, p.descricao ?? null, p.categoria, faixaDe(precoEfetivo), p.preco_ref, ofs[0]?.imagem_url ?? null, p.qtd_desejada ?? 1, p.prioridade ?? 0, p.busca?.q ?? null, 1, ordem].map(sq).join(', ')})`,
      '  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);',
    );
    linhas.push(`DELETE FROM ofertas WHERE origem='etiqueta' AND presente_id=(SELECT id FROM presentes WHERE slug=${sq(p.slug)});`);
    for (const o of ofs) {
      linhas.push(
        `INSERT INTO ofertas (presente_id, loja, titulo, preco, url, link_afiliado, imagem_url, avaliacao, num_avaliacoes, frete_gratis, principal, origem, chave_origem, verificado_em) SELECT id, ${[o.loja, o.titulo, o.preco, o.url, o.link_afiliado, o.imagem_url, o.avaliacao, o.num_avaliacoes, o.frete_gratis, o.principal, 'etiqueta', o.chave_origem, o.verificado_em].map(sq).join(', ')} FROM presentes WHERE slug=${sq(p.slug)};`,
      );
    }
    linhas.push('');
  }
  for (const c of base.cotas) {
    ordem += 10;
    linhas.push(
      `INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, qtd_desejada, prioridade, ativo, ordem) VALUES (${[c.slug, 'cota', c.nome, c.descricao, 'Cotas', faixaDe(c.preco_ref), c.preco_ref, c.qtd_desejada ?? 1, 0, 1, ordem].map(sq).join(', ')})`,
      '  ON DUPLICATE KEY UPDATE nome=VALUES(nome), descricao=VALUES(descricao), preco_ref=VALUES(preco_ref), faixa=VALUES(faixa);',
    );
  }
  return linhas.join('\n') + '\n';
}

// ---------- relatório OFERTAS.md ----------
const brl = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
function gerarRelatorio(base, ofertas) {
  const porSlug = new Map(ofertas.presentes.map((p) => [p.slug, p.ofertas]));
  const l = [
    '# Ofertas da lista de presentes',
    '',
    `Fonte: app **Etiqueta** (API v1, links de afiliado da própria loja). Consulta em ${new Date(ofertas.gerado_em).toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' })}.`,
    '',
    'Critério: título precisa casar com o item (palavras obrigatórias e proibidas em `data/presentes-base.json`), preço dentro da janela do item, nota ≥ 4,3 quando há nota; depois pontua nota, volume de avaliações/vendas, frete grátis e proximidade do valor de referência. A principal é a de maior pontuação; as alternativas preferem outra loja.',
    '',
    '> **Revise antes de publicar.** Os preços mudam; o site mostra "Preço verificado em dd/mm; pode variar na loja". Nada aqui foi inventado: item sem oferta fica **pendente**.',
    '',
    '| Item | Ref. (casar.com) | Loja | Preço | Nota | Por que | Link |',
    '|---|---|---|---|---|---|---|',
  ];
  for (const p of base.presentes) {
    const ofs = porSlug.get(p.slug);
    if (!ofs) { l.push(`| ${p.nome} | ${brl(p.preco_ref)} | — | — | — | **pendente** | — |`); continue; }
    ofs.forEach((o, i) => {
      const por = [
        i === 0 ? '**principal**' : 'alternativa',
        o.avaliacao ? `nota ${o.avaliacao}${o.num_avaliacoes ? ` (${o.num_avaliacoes.toLocaleString('pt-BR')})` : ''}` : 'sem nota',
        o.frete_gratis ? 'frete grátis' : null,
        `${o.preco <= p.preco_ref ? 'abaixo' : 'acima'} da ref.`,
      ].filter(Boolean).join(', ');
      l.push(`| ${i === 0 ? p.nome : '↳'} | ${i === 0 ? brl(p.preco_ref) : ''} | ${LOJA_NOME[o.loja] || o.loja} | ${brl(o.preco)} | ${o.avaliacao ?? '—'} | ${por} | [ver](${o.url}) — ${o.titulo.replace(/\|/g, '/').slice(0, 60)} |`);
    });
  }
  if (ofertas.pendentes.length) {
    l.push('', '## Pendentes (sem oferta confiável no Etiqueta)', '', 'Para preencher: cadastre a busca sugerida no Etiqueta, deixe ele coletar e rode o importador de novo. Ou cadastre a oferta à mão no admin (Presentes → Ofertas).', '');
    for (const p of ofertas.pendentes) l.push(`- **${p.nome}** (\`${p.slug}\`): ${p.motivo}.`);
  }
  l.push('', '## Cotas', '', 'Cotas não têm loja: o convidado contribui por PIX (chave em Configurações do admin).', '');
  return l.join('\n') + '\n';
}

// ---------- principal ----------
const base = JSON.parse(await readFile(resolve(RAIZ, 'data/presentes-base.json'), 'utf8'));
const arqOfertas = resolve(RAIZ, 'data/ofertas-etiqueta.json');
let ofertas;
if (flag('so-seeds')) {
  ofertas = JSON.parse(await readFile(arqOfertas, 'utf8'));
} else {
  try {
    ofertas = await consultarEtiqueta(base);
  } catch (e) {
    console.error(`Etiqueta indisponível: ${e.message}. Nada foi alterado.`);
    process.exit(1);
  }
  await writeFile(arqOfertas, JSON.stringify(ofertas, null, 2) + '\n');
}
await writeFile(resolve(RAIZ, 'database/seed_presentes.sql'), gerarSeed(base, ofertas));
await writeFile(resolve(RAIZ, 'OFERTAS.md'), gerarRelatorio(base, ofertas));
const total = ofertas.presentes.reduce((s, p) => s + p.ofertas.length, 0);
console.log(`${ofertas.presentes.length} presentes com oferta (${total} ofertas), ${ofertas.pendentes.length} pendentes.`);
console.log('Arquivos: data/ofertas-etiqueta.json, database/seed_presentes.sql, OFERTAS.md');

if (SITE) {
  const chave = await lerSegredo(CHAVE_ARQ, 'a IMPORT_KEY do site');
  const destino = new URL('/api/admin/importar.php', SITE);
  if (destino.protocol !== 'https:' && !['localhost', '127.0.0.1'].includes(destino.hostname)) {
    console.error('Recusado: use https para enviar ao site.');
    process.exit(3);
  }
  const r = await fetch(destino, {
    method: 'POST',
    headers: { Authorization: `Bearer ${chave}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({ presentes: ofertas.presentes }),
  });
  const corpo = await r.json().catch(() => ({}));
  if (!r.ok) { console.error(`O site recusou a importação (HTTP ${r.status}): ${corpo.erro || 'sem detalhe'}`); process.exit(4); }
  console.log(`Site atualizado: ${corpo.atualizados ?? '?'} presentes, ${corpo.ofertas ?? '?'} ofertas. Ignorados: ${(corpo.ignorados || []).join(', ') || 'nenhum'}.`);
}
