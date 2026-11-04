# Decisões

Registro das decisões de projeto. Cada uma pode ser revista; o "onde mudar" indica o arquivo ou a tela do admin.

## Conteúdo e dados

| # | Decisão | Por quê | Onde mudar |
|---|---|---|---|
| D1 | **Todo o conteúdo do casal fica em `config_site`** (nomes, data, local, cidade, história, PIX, textos). O código não tem nenhum nome, data ou lugar fixo; o repositório vem com dados fictícios ("Ana & Lucas", "Espaço Exemplo") | Reutilizar a mesma estrutura para outros casais sem mexer em código | admin → Configurações (padrão em `inc/dados.php` e `database/seed_conteudo.sql`) |
| D2 | **História do casal** num campo de texto: uma linha por marco, `quando \| título \| texto` | Editável pelos noivos, sem HTML | admin → Configurações (`historia`) |
| D3 | `data/presentes-base.json` traz **27 itens + 3 cotas de exemplo** (lua de mel, casa nova). `preco_ref` = valor de referência de cada item | Ponto de partida; os noivos ajustam no admin | `data/presentes-base.json` / admin → Presentes |
| D4 | As cotas usam PIX; o botão fica "disponível em breve" até a chave ser preenchida | Sem chave, nada de QR falso | admin → Configurações (`pix_*`) |
| D5 | Recados entram **aguardando aprovação**; nenhum recado vem pré-carregado | Mural só com mensagens reais dos convidados | admin → Recados |
| D6 | Cartão "Prefere loja física?" só aparece se `loja_fisica` estiver preenchida | Nem todo casal tem lista em loja física | admin → Configurações (`loja_fisica*`) |
| D7 | Formulário de presença com **telefone (opcional)** e "mensagem" | Contato extra sem travar quem não quer informar | `public_html/index.php` |
| D8 | Limites padrão: **6 adultos** e **4 crianças** por confirmação | Evita erro de digitação ("66 adultos") | admin → Configurações |
| D9 | Valores `[[PENDENTE: …]]` **nunca aparecem para convidados** (a seção mostra uma frase acolhedora ou some) e aparecem em vermelho no admin | Site pode ir ao ar antes de tudo estar decidido | `inc/util.php: pendente()` |
| D10 | Fotos e rota do mapa (`assets/data/rota.geojson`, opcional) ficam **fora do Git**; sem foto, espaço reservado com ramo de oliveira; sem rota, o mapa 3D mostra só o pin | O repositório é só a estrutura; dados de cada casal ficam no servidor | `config/fotos.php`, `.gitignore` |
| D11 | Coordenadas e `place_id` do local vêm do admin; **conferir no local** | Pin e botões de rota (Google Maps, Waze, Uber) usam esses valores | admin → Configurações |
| D11a | Presenças de outra plataforma: admin → Presenças → **Importar planilha do casar.com** (.xlsx). Uma linha por convite, acompanhantes na mensagem; só insere (pelo e-mail) | Migração sem perder quem já confirmou | `inc/importar_casar.php` |

## Lista de presentes e ofertas

| # | Decisão | Por quê |
|---|---|---|
| D12 | **As ofertas vêm do app Etiqueta** , não de pesquisa manual. O importador `tools/importar-etiqueta.mjs` roda no computador de quem administra, lê a API v1 do Etiqueta (token lido de arquivo, nunca impresso), escolhe 1 principal + até 2 alternativas e envia ao site por `POST /api/admin/importar.php` com uma `IMPORT_KEY` | O Etiqueta só escuta em `127.0.0.1` e barra navegador: a Hostinger não alcança ele. O modelo "empurrar" resolve sem expor o Etiqueta |
| D13 | Item sem oferta confiável fica **pendente** (card mostra o valor de referência e "Pode comprar onde preferir"); **nenhuma URL ou preço inventado** | Regra do prompt |
| D14 | Critério de escolha: título casa com palavras obrigatórias/proibidas, preço na janela do item, nota ≥ 4,3; pontuação = nota bayesiana (5,0 com 5 avaliações não vence 4,6 com milhares) + volume + frete grátis − distância do valor de referência | Melhor custo-benefício com confiança |
| D15 | O botão usa o **link de afiliado** (`meli.la`, Amazon `tag=`, `s.shopee`); a URL canônica limpa fica guardada para conferência. Aviso "alguns links podem gerar comissão" aparece automaticamente quando há afiliado; `rel="noopener sponsored noreferrer"` | Transparência + regra do prompt |
| D16 | Reimportar troca **só** as ofertas de origem `etiqueta`; ofertas manuais e marcações "Já comprei" ficam. Se houver uma oferta manual marcada como principal, ela continua principal | O admin não perde ajuste feito à mão |
| D17 | Imagens: só URLs `https` dos CDNs das lojas (lista em `imagem_externa_ok`), com `referrerpolicy="no-referrer"`; nada é baixado | Regra do prompt + CSP enxuta |
| D18 | Faixa de preço é calculada pelo preço **da oferta principal** (o que o convidado paga), não pelo valor de referência | Filtro "Até R$ 100" tem que bater com o que aparece no card |
| D19 | Chips de categoria só aparecem se houver item (hoje "Cama & Banho" não aparece) | Evita beco sem saída (chip que leva a lista vazia) |
| D19a | **Voltagem:** o padrão do importador é cidade de 127 V. Ele tira 3 pontos de anúncio só 220 V e não usa como "alternativa" o mesmo modelo em outra voltagem nem anúncio só 220 V | Um eletro 220 V numa casa 127 V é presente errado; "Oster 110 V" e "Oster 220 V" não são duas opções |
| D19b | Para cada item sem oferta, cadastre o termo de busca no Etiqueta, deixe coletar e rode o importador de novo | Coletar no Etiqueta em vez de pesquisa manual |
| D19c | O `q` da API do Etiqueta procura o trecho literal no título, por isso os termos do importador são palavras soltas ("bolo", "bowl,tigela"), e a precisão fica nas palavras obrigatórias e proibidas | "forma de bolo" não acha "Forma **para** Bolo" |

## Experiência

| # | Decisão | Por quê |
|---|---|---|
| D20 | **Um único componente de carrossel** (scroll-snap, setas, bolinhas, "1–3 de 12") usado em galeria, avisos, padrinhos, presentes, cotas e recados. Nunca avança sozinho | Consistência + locus de controle + limite de 3–4 blocos visíveis |
| D21 | Presentes no celular: 1 card grande + espiada do próximo (não grid 2×2) | Card com foto, preço, loja e 2 botões fica apertado em 2 colunas de 160 px |
| D22 | Barra inferior no celular: Início · **Presença** (verde, primário) · **Presentes** (dourado, secundário) · Local · Mais | Os dois CTAs ficam sempre a um toque, junto com a navegação, sem duas barras empilhadas |
| D23 | Envelope de abertura só na 1ª visita, com "Pular", Esc, e **desligado** com `prefers-reduced-motion`, com âncora na URL, com link de edição ou se o admin desligar | Encantamento sem bloquear quem já conhece o site |
| D24 | E-mail repetido no RSVP: com JS, pergunta "Atualizar minha resposta?"; sem JS, atualiza direto. Em ambos, o dono do e-mail **recebe um aviso** com o novo resumo e um link de edição novo | Prevenção de duplicata. **Risco aceito:** quem sabe o e-mail de outra pessoa pode sobrescrever a resposta dela; o e-mail de aviso torna isso visível |
| D25 | Mapa: MapLibre 4.7.1 **self-hosted**, estilo OpenFreeMap "liberty", relevo Terrarium (AWS) com exagero 1,6, rota pré-calculada (OSRM, 2,9 km, ~5 min) salva em `assets/data/rota.geojson`; `cooperativeGestures` (dois dedos no celular, Ctrl+rolagem no desktop) | Sem chave de API, sem sequestrar o scroll, sem chamada de rota em tempo real |
| D26 | Sem tema escuro | A identidade marfim/oliva/dourado é o convite; dark mode descaracterizaria |
| D27 | Fonte cursiva: **Pinyon Script**; texto: **Quicksand** 400–700, WOFF2 latin self-hosted (~100 KB, só 2 pré-carregadas) | As duas indicadas no prompt |
| D28 | Cores de texto ajustadas para WCAG AA: o oliva original `#979459` (3:1) ficou só decorativo; texto/botões usam `#5F5C2B` (6,9:1); dourado de texto `#7A5C22` (5,6:1) e `#634A17` sobre dourado claro (5,8:1) | Critério 9 do prompt |

## Técnica e segurança

| # | Decisão | Por quê |
|---|---|---|
| D29 | Pastas privadas (`inc/`, `config/`, `data/`, `database/`, `tools/`) **fora do `public_html`** (na Hostinger: `domains/<site>/`). Alternativa documentada: `public_html/_privado/` bloqueada por `.htaccess` | Config fora da raiz web |
| D30 | Sem Composer/PHPMailer: cliente SMTP mínimo próprio (SSL 465, STARTTLS 587) com fallback para `mail()` | Nada de `vendor/` para subir; Hostinger tem SMTP |
| D31 | Aviso pós-envio sem JS vai pela **sessão**, nunca pela URL | Impede links com mensagens forjadas ("o PIX mudou…") |
| D32 | Tokens ("Já comprei", recado, edição do RSVP) e IPs guardados como **HMAC-SHA256** com `APP_SECRET` | LGPD + token vazado do banco não serve para nada |
| D33 | Rate limit por IP (hash) em janela fixa: RSVP 12/h, "Já comprei" 15/h, desfazer 40/h, recado 6/h, login 8/15 min, importação com chave errada 10/h | Anti-spam sem captcha |
| D34 | JS em módulos sem `?v=` entre si → `Cache-Control: no-cache` (revalida com 304); fontes, imagens e bibliotecas com cache de 1 ano | Nunca misturar versões de módulos depois de um deploy |
| D35 | CSP sem `unsafe-inline` em scripts (o único script "inline" é JSON de dados, que não executa) | Defesa contra XSS |
| D36 | Recados ficam "aguardando" até aprovação no admin; quem escreveu pode desfazer enquanto não foi lido (até 24 h) | Moderação + reversão |
