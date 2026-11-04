// Admin: confirmação antes de ações destrutivas.
document.addEventListener('submit', (ev) => {
  const form = ev.target;
  const botao = ev.submitter;
  const msg = botao?.dataset.confirmarBotao || form.dataset.confirmar;
  if (msg && !window.confirm(msg)) ev.preventDefault();
});
