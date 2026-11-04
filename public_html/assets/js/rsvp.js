// Confirmação de presença em 3 passos, validação inline, detecção de e-mail repetido e tela de celebração.
// Sem JS, o mesmo formulário funciona com envio tradicional (todos os campos visíveis).
import { $, $$, carregando, movimentoReduzido, postar, toast, vibrar } from './util.js';

const EMAIL_OK = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

export function iniciarRsvp() {
  const form = $('[data-rsvp]');
  if (!form) return;
  form.dataset.wizard = '1';
  const passos = $$('.rsvp__passo', form);
  const indicadores = $$('[data-passo-ind]', form);
  const barra = $('[data-progresso-barra]', form);
  const progresso = $('.progresso', form);
  const btnVoltar = $('[data-voltar]', form);
  const btnContinuar = $('[data-continuar]', form);
  const btnEnviar = $('[data-enviar]', form);
  const fim = $('[data-rsvp-fim]');
  const tocados = new Set();
  let atual = 1;
  let atualizar = false;

  const campos = {
    nome: form.elements.nome, email: form.elements.email, telefone: form.elements.telefone,
    adultos: form.elements.adultos, criancas: form.elements.criancas, mensagem: form.elements.mensagem,
  };
  const vai = () => form.querySelector('input[name="vai"]:checked')?.value ?? null;

  const regras = {
    nome: () => (campos.nome.value.trim().length >= 3 ? '' : 'Conta pra gente seu nome completo, por favor.'),
    email: () => (EMAIL_OK.test(campos.email.value.trim()) ? '' : 'Confira o e-mail: algo como nome@exemplo.com.'),
    vai: () => (vai() !== null ? '' : 'Escolha se você vai ou não.'),
  };
  const doPasso = { 1: ['nome', 'email'], 2: ['vai'], 3: [] };

  function erro(nome, msg, mostrar) {
    const p = $(`[data-erro-de="${nome}"]`, form);
    const el = campos[nome];
    if (p) p.textContent = mostrar ? msg : '';
    if (el) el.setAttribute('aria-invalid', mostrar && msg ? 'true' : 'false');
  }

  function valido(passo, mostrar = false) {
    let ok = true;
    for (const n of doPasso[passo]) {
      const msg = regras[n]();
      if (msg) ok = false;
      erro(n, msg, mostrar || tocados.has(n));
    }
    return ok;
  }

  function atualizarBotoes() {
    const ok = valido(atual);
    btnContinuar.disabled = !ok;
    btnEnviar.disabled = !(valido(1) && valido(2));
  }

  function mostrar(n, foco = true) {
    atual = n;
    passos.forEach((p) => p.classList.toggle('ativo', +p.dataset.passo === n));
    indicadores.forEach((li) => {
      const k = +li.dataset.passoInd;
      li.classList.toggle('feito', k < n);
      if (k === n) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
    });
    barra.style.width = `${(n / 3) * 100}%`;
    progresso.setAttribute('aria-valuenow', n);
    btnVoltar.hidden = n === 1;
    btnContinuar.hidden = n === 3;
    if (n === 3) montarRevisao($('[data-revisao]', form));
    atualizarBotoes();
    if (foco) {
      const alvo = $(`.rsvp__passo[data-passo="${n}"] legend`, form);
      alvo?.setAttribute('tabindex', '-1');
      alvo?.focus({ preventScroll: true });
      form.scrollIntoView({ block: 'nearest', behavior: movimentoReduzido() ? 'auto' : 'smooth' });
    }
  }

  function linha(dl, rotulo, valor) {
    const d = document.createElement('div');
    d.append(Object.assign(document.createElement('dt'), { textContent: rotulo }), Object.assign(document.createElement('dd'), { textContent: valor }));
    dl.append(d);
  }

  function montarRevisao(dl) {
    dl.replaceChildren();
    linha(dl, 'Nome', campos.nome.value.trim());
    linha(dl, 'E-mail', campos.email.value.trim());
    if (campos.telefone.value.trim()) linha(dl, 'Telefone', campos.telefone.value.trim());
    linha(dl, 'Vai ao casamento?', vai() === '1' ? 'Sim, vou! 🥂' : vai() === '0' ? 'Não poderei ir' : '—');
    if (vai() === '1') {
      linha(dl, 'Adultos', campos.adultos.value);
      linha(dl, 'Crianças', campos.criancas.value);
    }
    if (campos.mensagem.value.trim()) linha(dl, 'Mensagem', campos.mensagem.value.trim());
  }

  function alternarQtd() {
    const so = $('[data-so-se-vai]', form);
    so.hidden = vai() === '0';
  }

  form.addEventListener('input', (ev) => {
    if (ev.target.name === 'vai') { tocados.add('vai'); alternarQtd(); }
    atualizarBotoes();
  });
  form.addEventListener('change', atualizarBotoes);
  form.addEventListener('focusout', (ev) => {
    if (ev.target.name in regras) { tocados.add(ev.target.name); atualizarBotoes(); }
  });
  btnContinuar.addEventListener('click', () => {
    if (!valido(atual, true)) { $('[aria-invalid="true"]', form)?.focus(); return; }
    mostrar(atual + 1);
  });
  btnVoltar.addEventListener('click', () => mostrar(atual - 1));
  // Enter nos passos 1 e 2 avança em vez de enviar.
  form.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter' && atual < 3 && ev.target.tagName !== 'TEXTAREA') { ev.preventDefault(); btnContinuar.click(); }
  });

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    if (!valido(1, true)) { mostrar(1); return; }
    if (!valido(2, true)) { mostrar(2); return; }
    carregando(btnEnviar, true);
    const corpo = {
      nome: campos.nome.value.trim(), email: campos.email.value.trim(), telefone: campos.telefone.value.trim(),
      vai: vai(), adultos: +campos.adultos.value, criancas: +campos.criancas.value, mensagem: campos.mensagem.value.trim(),
      site: form.elements.site.value, token_edicao: form.elements.token_edicao?.value || '', atualizar: atualizar ? 1 : 0,
    };
    const r = await postar('/api/rsvp.php', corpo);
    carregando(btnEnviar, false);
    atualizarBotoes();
    if (r.status === 409 && r.dados.duplicado) { perguntarAtualizar(); return; }
    if (!r.ok) { toast(r.dados.mensagem || 'Não conseguimos registrar agora. Tente de novo em instantes, por favor.', { tipo: 'erro' }); return; }
    celebrar(r.dados);
  });

  function perguntarAtualizar() {
    const passo3 = $('.rsvp__passo[data-passo="3"]', form);
    $('.rsvp__duplicado', passo3)?.remove();
    const caixa = document.createElement('div');
    caixa.className = 'rsvp__duplicado faixa-aviso faixa-aviso--ok';
    caixa.setAttribute('role', 'alert');
    caixa.innerHTML = '<p>Já temos uma confirmação com este e-mail. Quer <strong>atualizar</strong> com estes dados?</p>';
    const sim = Object.assign(document.createElement('button'), { type: 'button', className: 'btn btn--primario btn--pequeno', textContent: 'Atualizar minha resposta' });
    const outro = Object.assign(document.createElement('button'), { type: 'button', className: 'btn btn--texto btn--pequeno', textContent: 'Usar outro e-mail' });
    sim.addEventListener('click', () => { atualizar = true; caixa.remove(); form.requestSubmit(btnEnviar); });
    outro.addEventListener('click', () => { caixa.remove(); mostrar(1); campos.email.select(); });
    const acoes = document.createElement('div'); acoes.className = 'acoes-linha'; acoes.append(sim, outro);
    caixa.append(acoes);
    passo3.prepend(caixa);
    sim.focus();
  }

  async function celebrar(d) {
    form.hidden = true;
    fim.hidden = false;
    const indo = vai() === '1';
    $('[data-fim-titulo]', fim).textContent = d.atualizado ? 'Resposta atualizada!' : indo ? 'Presença confirmada! 🥂' : 'Obrigado por avisar 💌';
    montarRevisao($('[data-fim-resumo]', fim));
    $('[data-fim-email]', fim).textContent = d.email_enviado
      ? `Enviamos um e-mail para ${campos.email.value.trim()} com o resumo e um link para editar.`
      : 'Guardamos sua resposta. (O e-mail de confirmação pode demorar um pouquinho.)';
    fim.focus();
    fim.scrollIntoView({ block: 'center', behavior: movimentoReduzido() ? 'auto' : 'smooth' });
    vibrar(20);
    if (indo && !movimentoReduzido()) {
      try {
        const { default: confetti } = await import('/assets/vendor/confetti.module.mjs');
        confetti({ particleCount: 90, spread: 70, origin: { y: 0.7 }, colors: ['#B8934A', '#979459', '#E9D6A8', '#5F5C2B', '#FFFFFF'], disableForReducedMotion: true });
      } catch { /* confete é só enfeite */ }
    }
  }

  $('[data-rsvp-editar]', fim)?.addEventListener('click', () => {
    atualizar = true;
    fim.hidden = true;
    form.hidden = false;
    mostrar(1);
  });

  alternarQtd();
  mostrar(1, false);
  if (campos.nome.value) { ['nome', 'email', 'vai'].forEach((n) => tocados.add(n)); atualizarBotoes(); }
}

/* ---------- Recados ---------- */
export function iniciarRecados() {
  const form = $('[data-recado-form]');
  if (!form) return;
  const nome = form.elements.nome, msg = form.elements.mensagem;
  const cont = $('[data-caracteres]', form);
  const enviar = $('[data-enviar-recado]', form);
  const checar = () => {
    cont.textContent = msg.value.length;
    enviar.disabled = !(nome.value.trim().length >= 2 && msg.value.trim().length >= 3);
  };
  form.addEventListener('input', checar);
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    if (enviar.disabled) return;
    carregando(enviar, true);
    const r = await postar('/api/recados.php', { acao: 'criar', nome: nome.value.trim(), mensagem: msg.value.trim(), site: form.elements.site.value });
    carregando(enviar, false);
    if (!r.ok) { toast(r.dados.mensagem || 'Não deu para enviar agora. Tente de novo, por favor.', { tipo: 'erro' }); checar(); return; }
    const guardado = { nome: nome.value, mensagem: msg.value };
    form.reset(); checar();
    vibrar(10);
    toast('Recado enviado! Ele aparece no varal depois que os noivos lerem 💛', {
      acao: 'Desfazer',
      aoAgir: async () => {
        const d = await postar('/api/recados.php', { acao: 'desfazer', token: r.dados.token });
        if (d.ok) { nome.value = guardado.nome; msg.value = guardado.mensagem; checar(); toast('Recado cancelado. Seu texto voltou para o campo.'); }
        else toast(d.dados.mensagem || 'Não consegui desfazer.', { tipo: 'erro' });
      },
    });
  });
  checar();
}
