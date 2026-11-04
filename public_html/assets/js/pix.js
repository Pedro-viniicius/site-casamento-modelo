// PIX estático (BR Code / EMV) gerado no cliente + QR Code (biblioteca carregada sob demanda).
import { $, copiar, dadosSite } from './util.js';

const campo = (id, valor) => id + String(valor.length).padStart(2, '0') + valor;

function crc16(str) {
  let crc = 0xffff;
  for (const b of new TextEncoder().encode(str)) {
    crc ^= b << 8;
    for (let i = 0; i < 8; i++) crc = crc & 0x8000 ? ((crc << 1) ^ 0x1021) & 0xffff : (crc << 1) & 0xffff;
  }
  return crc.toString(16).toUpperCase().padStart(4, '0');
}

const semAcento = (s, max) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9 ]/g, '').toUpperCase().slice(0, max);

/** Payload "copia e cola" do PIX estático. */
export function payloadPix({ chave, nome, cidade, valor, txid = '***' }) {
  const conta = campo('00', 'br.gov.bcb.pix') + campo('01', chave.trim());
  let p = campo('00', '01')
    + campo('26', conta)
    + campo('52', '0000')
    + campo('53', '986')
    + (valor > 0 ? campo('54', valor.toFixed(2)) : '')
    + campo('58', 'BR')
    + campo('59', semAcento(nome, 25) || 'NOIVOS')
    + campo('60', semAcento(cidade, 15) || 'BRASIL')
    + campo('62', campo('05', txid));
  p += '6304';
  return p + crc16(p);
}

let libQr = null;
function carregarQr() {
  if (window.qrcode) return Promise.resolve(window.qrcode);
  return libQr ??= new Promise((ok, falha) => {
    const s = document.createElement('script');
    s.src = '/assets/vendor/qrcode.js';
    s.onload = () => ok(window.qrcode);
    s.onerror = falha;
    document.head.append(s);
  });
}

export function iniciarPix() {
  const modal = $('#modal-pix');
  const pix = dadosSite().pix;
  if (!modal || !pix) return;
  const entradaValor = $('[data-pix-valor]', modal);
  const caixaQr = $('[data-pix-qr]', modal);
  let codigo = '';

  async function desenhar() {
    const valor = Math.max(0, parseFloat(String(entradaValor.value).replace(/\./g, '').replace(',', '.')) || 0);
    codigo = payloadPix({ ...pix, valor });
    try {
      const qrcode = await carregarQr();
      const qr = qrcode(0, 'M');
      qr.addData(codigo);
      qr.make();
      caixaQr.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
    } catch {
      caixaQr.textContent = 'QR indisponível agora: use “Copiar código PIX”.';
    }
  }

  document.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-pix]');
    if (!b) return;
    $('[data-pix-cota]', modal).textContent = `Cota: ${b.dataset.nome}`;
    entradaValor.value = Number(b.dataset.valor).toFixed(2).replace('.', ',');
    modal.showModal();
    desenhar();
  });
  let t;
  entradaValor.addEventListener('input', () => { clearTimeout(t); t = setTimeout(desenhar, 250); });
  $('[data-pix-copiar-codigo]', modal).addEventListener('click', () => copiar(codigo, 'Código PIX copiado ✓ Cole no app do banco.'));
  $('[data-pix-copiar-chave]', modal).addEventListener('click', () => copiar(pix.chave, 'Chave PIX copiada ✓'));
  modal.addEventListener('click', (ev) => { if (ev.target === modal) modal.close(); });
}
