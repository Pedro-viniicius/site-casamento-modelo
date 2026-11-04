# IHC: as 8 Regras de Ouro de Shneiderman no site

Mapa de cada regra para onde ela foi aplicada no código. A prova automatizada (Chrome headless, 37 checagens) está descrita no README, seção "QA".

## 1. Consistência

| O quê | Onde |
|---|---|
| Design tokens (cores, tipografia, espaços, raios, sombras, durações, alvo de toque de 48 px) | `public_html/assets/css/site.css` → `:root` |
| **Um** botão (`.btn` + variantes `--primario`, `--secundario`, `--texto`, `--icone`), **um** card, **um** chip, **um** toast, **um** carrossel, **uma** folha inferior | `site.css` (seções "Botão", "Card, chip, selo", "Toast", "CARROSSEL", "Folhas") |
| Mesmo verbo para a mesma ação: sempre **"Confirmar presença"**, **"Ver lista de presentes"**, **"Ver na/no <Loja>"**, **"Já comprei este presente"**, **"Desfazer"** | `index.php`, `inc/lojas.php: loja_rotulo()` |
| O admin usa o mesmo `site.css` e só acrescenta layout | `assets/css/admin.css` |
| Links externos sempre com ↗ + "(abre em nova aba)" para leitor de tela | `inc/render.php: link_externo()` |

## 2. Atalhos para usuários frequentes

| Atalho | Onde |
|---|---|
| `←` / `→` navegam o carrossel em foco ou sob o mouse | `assets/js/carrossel.js` (listener global no fim) |
| `/` foca a busca de presentes | `assets/js/presentes.js: iniciarPresentes()` |
| `1` / `2` / `3` aplicam as faixas Até R$ 100 / R$ 100–250 / Acima de R$ 250 | idem |
| `Esc` fecha folhas, modais e o envelope | `<dialog>` nativo + `app.js` + `secoes.js: iniciarEnvelope()` |
| Deep link do filtro: `#presentes?faixa=ate-100&cat=cozinha&q=…&ordem=menor` (lido ao abrir e gravado a cada mudança) | `presentes.js: lerDaUrl()` / `gravarNaUrl()` |
| URLs curtas: `/presenca`, `/presentes`, `/como-chegar`, `/calendario.ics` | `public_html/.htaccess` |
| Swipe nativo (scroll-snap) em todo carrossel; botão "Voltar ao topo" | `site.css` `.carrossel__trilho`; `secoes.js: iniciarNavegacao()` |

## 3. Feedback informativo

| O quê | Onde |
|---|---|
| Estado de carregamento no botão em < 100 ms (spinner, `aria-busy`, desabilita) | `util.js: carregando()` + `.btn.carregando` |
| Toasts de sucesso/erro em região `role="status" aria-live="polite"` | `util.js: toast()`, `index.php` `.toasts` |
| Vibração leve (`navigator.vibrate(10)`) ao marcar presente, enviar recado, copiar, abrir envelope (desligada com movimento reduzido) | `util.js: vibrar()` |
| "Mostrando 4 de 12" nos presentes; "1–3 de 12" e bolinhas em cada carrossel | `presentes.js: aplicar()`, `carrossel.js: atualizar()` |
| Cartão do presente atualiza na hora: selo "Já presenteado 💚", "2 de 3 já presenteados", vai para o fim | `presentes.js: atualizarCard()` |
| Erros de validação inline (`aria-invalid` + mensagem embaixo do campo) | `rsvp.js: erro()` |
| "Endereço copiado ✓", "Código PIX copiado ✓" | `util.js: copiar()` |
| Seção ativa destacada no menu (scrollspy) | `secoes.js: iniciarNavegacao()` |

## 4. Diálogos que levam à conclusão

| O quê | Onde |
|---|---|
| Presença em **3 passos** com indicador numerado + barra de progresso (`role="progressbar"`) | `index.php` `.passos`, `rsvp.js: mostrar()` |
| Tela final de celebração: confete leve (só se vai e sem movimento reduzido), resumo, **Adicionar ao calendário (.ics)**, Google Agenda, **Ver lista de presentes** | `index.php` `[data-rsvp-fim]`, `rsvp.js: celebrar()` |
| E-mail de confirmação com resumo, `.ics` anexo e link pessoal de edição | `api/rsvp.php`, `inc/email.php` |
| Fluxo do presente: escolher → "Ver na Amazon ↗" (nova aba, a lista continua aberta) → voltar → "Já comprei" → confirmação → agradecimento | `inc/render.php: card_presente()`, `presentes.js: iniciarMarcacao()` |

## 5. Prevenção de erros

| O quê | Onde |
|---|---|
| "Continuar" e "Confirmar presença" desabilitados até os obrigatórios valerem | `rsvp.js: atualizarBotoes()` |
| Validação inline ao sair do campo (não só no envio) e de novo no servidor | `rsvp.js` (`focusout`) + `api/rsvp.php` |
| "Não poderei ir" oculta adultos/crianças | `rsvp.js: alternarQtd()` |
| Selects com limite (1–6 adultos, 0–4 crianças, configurável) | `index.php`, `config_site.max_*` |
| Confirmação leve antes de "Já comprei": "Confirma que comprou **Jogo de fondue**?" | `index.php #modal-confirmar`, `presentes.js` |
| E-mail repetido: oferece **"Atualizar minha resposta"** ou "Usar outro e-mail" em vez de duplicar | `api/rsvp.php` (409) + `rsvp.js: perguntarAtualizar()` |
| Presente já esgotado: botão desabilitado; corrida entre duas pessoas resolvida no servidor (`SELECT … FOR UPDATE`, 409 "Alguém acabou de marcar") | `api/comprei.php` |
| Enter nos passos 1–2 avança em vez de enviar pela metade | `rsvp.js` (`keydown`) |
| Chips de categoria vazia não aparecem | `index.php` (`$catsUsadas`) |
| `font-size: 16px` nos campos (evita o zoom automático do iOS) | `site.css .campo input` |

## 6. Reversão fácil de ações

| O quê | Onde |
|---|---|
| Toast com **"Desfazer"** por 10 s após marcar presente | `presentes.js` → `toast(..., {acao: 'Desfazer'})` |
| Depois disso, **"Marquei por engano"** no mesmo aparelho (token em `localStorage`, hash no banco) | `presentes.js: guardarToken()` / `desfazer()`, `api/comprei.php` |
| Toast "Desfazer" após enviar recado (o texto volta para o campo) | `rsvp.js: iniciarRecados()`, `api/recados.php` |
| Presença editável pelo link do e-mail (`/?editar=<token>`) e pelo botão "Editar minha resposta" | `index.php` (`$editando`), `rsvp.js` |
| "Voltar" em cada passo do RSVP, sem perder o que foi digitado | `rsvp.js` |
| No admin: "Zerar marcações", "Ocultar" recado, confirmação antes de apagar | `admin/index.php`, `assets/js/admin.js` |

## 7. Locus de controle interno

| O quê | Onde |
|---|---|
| Nenhum som, nenhum autoplay; **carrosséis nunca avançam sozinhos** | `carrossel.js` (sem timers) |
| Envelope com **"Pular"**, Esc, só na 1ª visita, desligável no admin | `secoes.js: iniciarEnvelope()`, `inicio.js` |
| Mapa não sequestra a rolagem: `cooperativeGestures` (dois dedos no celular), controles grandes "Girar / Recentralizar / Ver de cima" | `mapa.js` |
| Nenhum modal surpresa: só abre o que a pessoa toca | — |
| Menu com seção ativa destacada; barra inferior marca onde a pessoa está | `secoes.js` (`aria-current`) |
| `prefers-reduced-motion` desliga animações, flip, parallax, confete e voo do mapa | `site.css` (media query global), checagens em cada módulo |

## 8. Menor carga de memória de curto prazo

| O quê | Onde |
|---|---|
| Data, hora e local repetidos ao lado do formulário de presença | `index.php .presenca__resumo` |
| Passo 3 do RSVP mostra o resumo do que foi preenchido | `rsvp.js: montarRevisao()` |
| Filtro ativo sempre visível (chip marcado ✓) + "Limpar filtros" | `presentes.js: aplicar()` |
| Card do presente mostra preço, loja, nota e "Preço verificado em dd/mm" | `inc/render.php: card_presente()` |
| **Máximo de 3–4 blocos visíveis** em qualquer área de cards (1 + espiada no celular, 2 no tablet, 3–4 no desktop) | `site.css` `--por-vez` no `.carrossel` |
| Cada carrossel diz onde a pessoa está ("4–6 de 12") | `carrossel.js` |
