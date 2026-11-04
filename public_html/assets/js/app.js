// Ponto de entrada (ES module, sem build). Cada parte é independente: se uma falhar, o resto segue.
import { iniciarCarrosseis } from './carrossel.js';
import { iniciarNavegacao, iniciarEnvelope, iniciarContagem, iniciarLinhaTempo, iniciarAvisos } from './secoes.js';
import { iniciarPresentes } from './presentes.js';
import { iniciarPix } from './pix.js';
import { iniciarRsvp, iniciarRecados } from './rsvp.js';
import { iniciarMapa } from './mapa.js';

const partes = [iniciarEnvelope, iniciarNavegacao, iniciarCarrosseis, iniciarContagem, iniciarLinhaTempo, iniciarAvisos, iniciarPresentes, iniciarPix, iniciarRsvp, iniciarRecados, iniciarMapa];
for (const iniciar of partes) {
  try { iniciar(); } catch (e) { console.error(`[casamento] ${iniciar.name}:`, e); }
}

// Esc fecha qualquer folha/modal aberto (o <dialog> já faz isso; aqui cobre navegadores antigos).
document.addEventListener('keydown', (ev) => {
  if (ev.key === 'Escape') document.querySelectorAll('dialog[open]').forEach((d) => d.close());
});
