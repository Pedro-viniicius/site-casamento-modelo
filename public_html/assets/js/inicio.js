// Roda no <head>, antes de pintar: marca que há JS e decide se mostra o envelope.
(function () {
  var html = document.documentElement;
  html.classList.remove('no-js');
  html.classList.add('js');
  try {
    var script = document.currentScript;
    var ligado = script && script.getAttribute('data-abertura') === '1';
    var reduzido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var temAncora = location.hash && location.hash.length > 1;
    var params = new URLSearchParams(location.search);
    var jaViu = localStorage.getItem('cmv_envelope_visto') === '1';
    if (ligado && !reduzido && !temAncora && !jaViu && !params.has('editar')) {
      html.classList.add('com-envelope');
    }
  } catch (e) { /* sem localStorage: não mostra o envelope */ }
})();
