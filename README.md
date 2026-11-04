# Site de casamento · modelo reutilizável

Landing page de casamento que também funciona como convite: abertura em envelope, confirmação de presença em 3 passos, lista de presentes com ofertas (e links de afiliado) vindas do app **Etiqueta**, cotas com PIX, mapa 3D, recados com moderação e painel admin para os noivos.

Este repositório é **só a estrutura**. Não há dados de nenhum casal: o conteúdo de exemplo é fictício ("Ana & Lucas", "Espaço Exemplo") e tudo se troca pelo painel admin. Fotos, rota do mapa e segredos ficam fora do Git.

- **Stack:** PHP 8.2+ puro com PDO, MySQL/MariaDB (utf8mb4, InnoDB), HTML semântico, CSS moderno e JavaScript vanilla em ES modules. **Não há etapa de build**: o que está aqui sobe direto para a hospedagem (testado na Hostinger compartilhada).
- **Documentos:** [`DECISOES.md`](DECISOES.md) (o que foi decidido e por quê) e [`IHC.md`](IHC.md) (as regras de Shneiderman e onde cada uma está no código).

---

## Usar para um novo casal (checklist)

1. **Hospedagem e banco:** siga "Deploy na Hostinger" abaixo (banco, PHP, arquivos, `.env.php`, primeiro admin).
2. **Admin → Configurações:** troque nomes, data e hora, local, endereço, **cidade**, latitude/longitude (e o `place_id` do Google, opcional), recepção, textos, versículo, **história** (uma linha por marco: `quando | título | texto`), prazo do RSVP e limites, PIX e loja física (opcional).
3. **Admin → Presentes:** ajuste a lista de exemplo (27 itens + 3 cotas) ou importe ofertas do Etiqueta (seção abaixo).
4. **Fotos:** salve em `public_html/assets/img/fotos/` com os nomes da tabela "Fotos" e descreva cada uma no `alt` em `config/fotos.php`.
5. **Prévia do WhatsApp:** crie `assets/img/fotos/og-image.jpg` (1200 × 630) com o casal; sem ela, vai a imagem genérica `assets/img/og-padrao.jpg`.
6. **Mapa (opcional):** para desenhar o caminho do centro da cidade até o local no mapa 3D, salve um GeoJSON (LineString) em `public_html/assets/data/rota.geojson`. Sem ele, o mapa mostra só o pin.
7. **Já tinham site em outra plataforma?** Admin → Presenças → **Importar planilha do casar.com** (o `.xlsx` exportado de lá). Pode reenviar quando chegarem respostas novas: entra só quem ainda não está no painel (pelo e-mail), nada é apagado nem sobrescrito.

## Campos pendentes

Enquanto um valor estiver como `[[PENDENTE: …]]`, ele **não aparece para os convidados**: a seção mostra uma frase acolhedora ou some. No admin, ele aparece com uma etiqueta vermelha.

| Campo | Onde | Efeito enquanto falta |
|---|---|---|
| **Chave PIX** (e nome/cidade do recebedor) | admin → Configurações `pix_*` | Cotas mostram "PIX disponível em breve" |
| **Padrinhos** (nomes, lado e fotos) | admin → Padrinhos | "Em breve vamos apresentar…" |
| **Prazo do RSVP** (AAAA-MM-DD) | admin → Configurações | O prazo não é exibido |
| **Estacionamento** | admin → Configurações | O item some de "Como chegar" |
| **Loja física** | admin → Configurações | O cartão "Prefere loja física?" some |
| **Domínio final** (`SITE_URL`) | `config/.env.php` | Open Graph, `.ics` e e-mails usam o host da requisição |

## Estrutura

```
public_html/            ← raiz do site (vai para o public_html da Hostinger)
  index.php             página única (dados do banco; sem banco, usa data/*.json)
  boot.php              localiza a pasta privada (../inc ou ./_privado/inc)
  .htaccess             HTTPS, compressão, cache, bloqueios, URLs curtas
  api/                  rsvp.php · comprei.php · recados.php · ics.php · presentes.php · admin/importar.php
  admin/                painel (index.php) + exportar.php (CSV)
  assets/css            site.css (design system) · admin.css
  assets/js             módulos ES: app, util, carrossel, secoes, presentes, pix, rsvp, mapa (+ inicio.js no <head>)
  assets/vendor         MapLibre 4.7.1, canvas-confetti 1.9.3, qrcode-generator 1.4.4 (carregados sob demanda)
  assets/fonts          Pinyon Script + Quicksand (WOFF2, latin)
  assets/img/fotos/     ← AS FOTOS DO CASAL VÃO AQUI
inc/                    PHP privado: bootstrap, util, db, dados, render, email, fotos, lojas, admin, sprite.svg
config/                 config.php · .env.example.php · fotos.php  (.env.php e import_key.txt ficam FORA do Git)
data/                   presentes-base.json (fonte da lista) · ofertas-etiqueta.json (gerado pelo importador)
database/               schema.sql · seed_conteudo.sql · seed_presentes.sql (gerado)
tools/                  importar-etiqueta.mjs · criar-admin.php · otimizar-fotos.sh · exportar-csv.php
```

---

## Lista de presentes: como o Etiqueta alimenta o site

O Etiqueta roda só no seu computador (`127.0.0.1:8765`) e a Hostinger não consegue chamá-lo. Por isso o importador roda **no seu computador** e **envia** as ofertas para o site:

```bash
# 1) Só gerar os arquivos locais (OFERTAS.md, seed_presentes.sql, data/ofertas-etiqueta.json) para revisar:
node tools/importar-etiqueta.mjs

# 2) Enviar para o site no ar (usa a IMPORT_KEY salva em config/import_key.txt):
node tools/importar-etiqueta.mjs --enviar https://SEU-DOMINIO
```

- O token do Etiqueta é lido de `~/etiqueta/api_token.txt`. Para usar outro caminho: `--token-arquivo` ou a variável `ETIQUETA_TOKEN_ARQUIVO`. O token **nunca** é impresso.
- Os termos de busca de cada item, com as palavras obrigatórias e proibidas e a janela de preço, estão em `data/presentes-base.json`. Para um item pendente, cadastre a busca no Etiqueta, deixe-o coletar e rode o importador de novo.
- Reimportar troca **só** as ofertas que vieram do Etiqueta. Ofertas cadastradas à mão no admin e as marcações "Já comprei" continuam como estão.
- No admin → Presentes → (item), o botão **"Reverificar preço ↗"** abre a loja. Em seguida, **"Marcar como conferido"** (com o preço novo, se mudou) atualiza o "Preço verificado em dd/mm".

### Atualizar os preços (rotina até o casamento)

```bash
cd ~/etiqueta && python3 servidor.py        # terminal 1: Etiqueta no ar (pasta: variável ETIQUETA_DIR)
bash tools/atualizar-lista.sh                 # terminal 2, na pasta deste projeto (~5 min)
```

O script recoleta na Amazon **só** os termos que alimentam a lista: os dos presentes mais os das ofertas já escolhidas. Ele usa um arquivo de progresso temporário, então o progresso normal do Etiqueta não muda. Depois, envia as ofertas para o site gravado em `config/site.txt` (chave em `config/import_key.txt`; os dois ficam fora do Git). As ofertas do Mercado Livre mantêm o preço da última coleta delas.

Dica: rode antes de datas de promoção (11.11, Black Friday, Cyber Monday) e na semana do casamento.

---

## Fotos: o que mandar e onde colocar

Salve com **exatamente** estes nomes em `public_html/assets/img/fotos/`. A página troca o espaço reservado pela foto sozinha.

| Arquivo | Proporção | Tamanho sugerido | Máx. | Onde aparece |
|---|---|---|---|---|
| `hero.jpg` | 3:4 (retrato) | 1200 × 1600 | 350 KB | Capa, na moldura dourada |
| `noiva.jpg` | 3:4 | 900 × 1200 | 250 KB | O Casal |
| `noivo.jpg` | 3:4 | 900 × 1200 | 250 KB | O Casal |
| `galeria-01.jpg` … `galeria-16.jpg` | 4:5 | 1200 × 1500 | 300 KB cada | Galeria (carrossel) |
| `cerimonia.jpg` | 16:10 | 1600 × 1000 | 300 KB | Cerimônia |
| `recepcao.jpg` | 16:10 | 1600 × 1000 | 300 KB | Recepção |
| `padrinhos/<nome>.jpg` | 1:1 | 600 × 600 | 120 KB | Padrinhos (informe o arquivo no admin) |
| `og-image.jpg` | 1,91:1 | **1200 × 630** | 300 KB | Prévia no WhatsApp (sem ela, usa `assets/img/og-padrao.jpg`) |

**Checklist "como trocar as fotos"**
1. Renomeie as fotos com os nomes da tabela.
2. No seu computador, rode `bash tools/otimizar-fotos.sh`. Ele precisa do ImageMagick (`sudo apt install imagemagick webp`). O script reduz o JPG, remove os metadados (inclusive a localização do GPS) e gera as versões WebP de 480, 960 e 1600 px.
3. Envie a pasta `assets/img/fotos/` inteira (JPG + WebP) pelo Gerenciador de Arquivos ou FTP. As fotos ficam **fora do Git** (`.gitignore`): o repositório é só a estrutura.
4. Abra o site e recarregue a página. Se a foto antiga continuar aparecendo, abra em aba anônima: o navegador guarda as imagens por até 1 ano, e o `?v=` muda sozinho com a data do arquivo.


---

## Deploy na Hostinger (passo a passo)

### 1. Banco de dados
1. **hPanel → Bancos de Dados → Gerenciamento** → crie o banco e o usuário. Anote o nome do banco, o usuário e a senha (a Hostinger prefixa os nomes com `u123456789_`).
2. **phpMyAdmin** → selecione o banco → **Importar**, nesta ordem:
   1. `database/schema.sql`
   2. `database/seed_conteudo.sql`
   3. `database/seed_presentes.sql`

### 2. PHP
**hPanel → Avançado → Configuração do PHP** → selecione **PHP 8.2** ou mais novo. As extensões `pdo_mysql` e `mbstring` já vêm ligadas.

### 3. Arquivos
Na Hostinger, o site fica em `domains/SEU-DOMINIO/public_html`. Envie assim, pelo **Gerenciador de Arquivos**, por **FTP** ou pelo **Git** do hPanel:

```
domains/SEU-DOMINIO/
  public_html/   ← conteúdo da pasta public_html deste projeto
  inc/  config/  data/          ← FORA do public_html (recomendado)
  database/  tools/             ← opcional no servidor (não precisam estar lá)
```

> **Alternativa**, se preferir tudo dentro do `public_html`: crie `public_html/_privado/` e coloque `inc/`, `config/` e `data/` lá dentro. O `boot.php` acha essas pastas sozinho e o `.htaccess` bloqueia o acesso a elas.

### 4. Configuração
1. Copie `config/.env.example.php` para `config/.env.php` e preencha:
   - `SITE_URL` (`https://seudominio.com.br`, sem barra no fim);
   - `APP_SECRET`: gere com `openssl rand -hex 32` ou `php -r "echo bin2hex(random_bytes(32));"`;
   - `DB_*`, com os dados do passo 1;
   - `IMPORT_KEY`: gere com `openssl rand -hex 24`. Salve **a mesma** chave em `config/import_key.txt` **no seu computador**, para o importador;
   - E-mail: crie a caixa em **hPanel → E-mails** (ex.: `contato@seudominio.com.br`) e preencha `MAIL_FROM` e `SMTP_*` (`smtp.hostinger.com`, porta 465, `ssl`).
2. **Nunca** envie o `.env.php` para o Git: ele já está no `.gitignore`.

### 5. Primeiro admin
- **Com SSH** (planos Premium e Business): `php tools/criar-admin.php noivos`. A senha é pedida no terminal e não aparece na tela.
- **Sem SSH:** copie `tools/criar-admin.php` para `public_html/criar-admin.php`, abra `https://seudominio/criar-admin.php`, crie o usuário e **apague o arquivo em seguida**. Ele só funciona enquanto não existe nenhum admin.
- Entre em `https://seudominio/admin` e preencha as **Configurações** (pendências acima).

### 6. SSL e e-mail
1. **hPanel → Segurança → SSL** → instale o certificado gratuito. O `.htaccess` já força HTTPS.
2. Teste: confirme presença com o seu e-mail. A mensagem chega com o resumo, o `.ics` anexo e o link "Editar minha resposta". Se não chegar, confira o `SMTP_PASS` e a caixa de spam.
3. Envie as ofertas: `node tools/importar-etiqueta.mjs --enviar https://seudominio.com.br`.

### 7. Prévia do WhatsApp
Cole o link numa conversa do WhatsApp. Para forçar a atualização da prévia depois de trocar a `og-image.jpg`, use o [Sharing Debugger](https://developers.facebook.com/tools/debug/) da Meta.

---

## Rodar localmente / QA

Não precisa instalar PHP nem MySQL: dá para usar Docker.

```bash
docker network create cmvqa
docker run -d --name cmv-db --network cmvqa -e MARIADB_RANDOM_ROOT_PASSWORD=1 \
  -e MARIADB_DATABASE=casamento -e MARIADB_USER=casamento -e MARIADB_PASSWORD=senha-local mariadb:11.4
printf 'FROM php:8.2-cli\nRUN docker-php-ext-install pdo_mysql\n' | docker build -t cmv-php82 -
for f in schema seed_conteudo seed_presentes; do docker exec -i cmv-db mariadb -ucasamento -psenha-local casamento < database/$f.sql; done
# config/.env.php com DB_HOST=cmv-db, DB_PASS=senha-local, APP_ENV=dev (ou aponte CMV_ENV_ARQUIVO para outro arquivo)
docker run -d --name cmv-php --network cmvqa -p 8000:8000 -v "$PWD":/app -w /app cmv-php82 php -S 0.0.0.0:8000 -t public_html
# Abra http://localhost:8000  (com PHP local: php -S localhost:8000 -t public_html)
```

Sem banco configurado, a página continua no ar com o conteúdo padrão e a lista vinda de `data/*.json`. Os formulários avisam que "abrem em instantes".

### Resultado do QA (04/11/2026)
- **Fluxos da API** (curl): presença nova, e-mail repetido (409), atualização, validação 422, honeypot, envio sem JS (303 com aviso), "Já comprei" + corrida (409) + desfazer, recado + desfazer, importação com e sem chave, login do admin, as 5 telas, exportação CSV e e-mails com `.ics` (capturados no Mailpit).
- **E2E no Chrome** (puppeteer-core): **37/37**. Inclui o envelope, a definição de pronto no celular, o filtro em 2 toques, o deep link, os atalhos, o flip, o scrollspy, o mapa 3D carregado sob demanda (rota, relevo e 195 feições de rua), a ausência de rolagem horizontal e os alvos de toque em **360, 390, 768, 1280 e 1920 px**, e a página sem JS.
- **Lighthouse mobile** (servidor de teste do PHP, **sem compressão**; duas medições iguais): Desempenho **93** · Acessibilidade **100** · Boas práticas **100** · SEO **100**. CLS 0, TBT 0 ms, LCP 2,7 s sem gzip; com a compressão da Hostinger, deve ficar abaixo de 2,5 s.
- **Peso inicial** (JS + CSS, gzip): **~24 KB**, contra o limite de 200 KB. MapLibre (~230 KB gzip), confete e QR só baixam quando são usados.

---

## Definição de pronto: como ficou

| Pedido | Como |
|---|---|
| Abro pelo WhatsApp e vejo os noivos e a data de cara | Hero com nomes, data em destaque e CTAs; prévia Open Graph 1200 × 630 |
| Confirmo presença em menos de 1 minuto | 3 passos, só 3 campos obrigatórios (nome, e-mail, vai?) |
| Acho um presente até R$ 100 em até 3 toques | **Presentes** (barra inferior) → chip **Até R$ 100** → cards na tela (2 toques) |
| Abro a loja certa | "Ver na Amazon ↗" / "Ver no Mercado Livre ↗", em nova aba |
| Marco "Já comprei" e consigo desfazer | Confirmação → toast com **Desfazer** (10 s) → depois, **Marquei por engano** |
| Funciona na Hostinger compartilhada com MySQL | PHP 8.2 puro + PDO, sem Composer e sem build; `.htaccess` para Apache/LiteSpeed |
