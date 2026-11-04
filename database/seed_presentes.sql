-- Lista de presentes de EXEMPLO (de data/presentes-base.json), sem ofertas. Para trazer ofertas, rode tools/importar-etiqueta.mjs;
-- ele regera este arquivo. Pode ser reimportado: atualiza os presentes e não mexe em ofertas cadastradas à mão.
SET NAMES utf8mb4;

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('balanca-cozinha', 'item', 'Balança de cozinha', NULL, 'Cozinha', 'ate-100', 50, 'https://m.media-amazon.com/images/I/51LW-+BBopL._AC_UL320_.jpg', 1, 0, 'balança de cozinha,balança digital cozinha', 1, 10)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('manta-sofa', 'item', 'Manta para sofá', NULL, 'Casa & Conforto', 'ate-100', 50.82, 'https://m.media-amazon.com/images/I/71PrSa--LGL._AC_UL320_.jpg', 1, 0, 'manta sofá,manta para sofá,manta decorativa', 1, 20)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('assadeira', 'item', 'Assadeira', NULL, 'Cozinha', 'ate-100', 70, 'https://m.media-amazon.com/images/I/715PiXN1c7L._AC_UL320_.jpg', 1, 0, 'assadeira,forma retangular', 1, 30)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('jogo-tacas', 'item', 'Jogo de taças', NULL, 'Mesa & Bar', 'ate-100', 70, 'https://m.media-amazon.com/images/I/41NSskOxHYL._AC_UL320_.jpg', 1, 0, 'jogo de taças,taças vinho,taças cristal', 1, 40)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('assadeira-bolo', 'item', 'Assadeira de bolo', NULL, 'Cozinha', 'ate-100', 90, 'https://m.media-amazon.com/images/I/51KTUNj4i6L._AC_UL320_.jpg', 1, 0, 'bolo', 1, 50)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('jogo-fondue', 'item', 'Jogo de fondue', NULL, 'Mesa & Bar', '100-250', 100, 'https://m.media-amazon.com/images/I/51FMJp5eYOL._AC_UL320_.jpg', 1, 0, 'fondue,aparelho de fondue', 1, 60)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('tacas-sobremesa', 'item', 'Jogo de taças para sobremesa (8 peças)', NULL, 'Mesa & Bar', 'ate-100', 100, 'https://m.media-amazon.com/images/I/61PKMjrQZvL._AC_UL320_.jpg', 1, 0, 'taças sobremesa,taça sobremesa', 1, 70)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('suporte-bolo', 'item', 'Suporte para bolo', NULL, 'Mesa & Bar', '100-250', 100, 'https://m.media-amazon.com/images/I/616nEwiucQL._AC_UL320_.jpg', 1, 0, 'boleira,suporte para bolo,prato para bolo', 1, 80)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('frigideira-antiaderente', 'item', 'Frigideira antiaderente', NULL, 'Cozinha', '100-250', 110, 'https://m.media-amazon.com/images/I/31jbK-NtErL._AC_UL320_.jpg', 1, 1, 'frigideira antiaderente,frigideira', 1, 90)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('bowls-ceramica', 'item', 'Conjunto de bowls em cerâmica (6 peças)', NULL, 'Mesa & Bar', '100-250', 150, 'https://m.media-amazon.com/images/I/41KOq+qyuzL._AC_UL320_.jpg', 1, 0, 'bowl,tigela', 1, 100)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('torradeira-eletrica', 'item', 'Torradeira elétrica', NULL, 'Eletroportáteis', '100-250', 160, 'https://m.media-amazon.com/images/I/51IRloWQneL._AC_UL320_.jpg', 1, 0, 'torradeira elétrica,torradeira electrolux,torradeira', 1, 110)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('jogo-talheres', 'item', 'Jogo de talheres (24 peças)', NULL, 'Mesa & Bar', 'ate-100', 180, 'https://m.media-amazon.com/images/I/51OPNgIAJYL._AC_UL320_.jpg', 1, 1, 'faqueiro 24 peças,jogo de talheres 24,faqueiro tramontina', 1, 120)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('xicaras-cafe', 'item', 'Jogo de xícaras de café com pires (12 peças)', NULL, 'Mesa & Bar', 'ate-100', 180, 'https://m.media-amazon.com/images/I/41MkhU0q9mL._AC_UL320_.jpg', 1, 0, 'xícaras de café com pires,jogo de xícaras,xícara cafezinho', 1, 130)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('panela-pressao', 'item', 'Panela de pressão 4,5 L', NULL, 'Cozinha', '100-250', 180, 'https://m.media-amazon.com/images/I/81ubjq2DfxL._AC_UL320_.jpg', 1, 1, 'panela de pressão 4,5,panela de pressão', 1, 140)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('abajur-chao', 'item', 'Abajur de chão', NULL, 'Casa & Conforto', '100-250', 200, 'https://m.media-amazon.com/images/I/31dM9kOWSsL._AC_UL320_.jpg', 1, 0, 'abajur de chão,luminária de chão,luminária coluna', 1, 150)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('potes-cucina', 'item', 'Conjunto de 3 potes (linha Cucina)', NULL, 'Cozinha', '100-250', 200, 'https://http2.mlstatic.com/D_Q_NP_2X_946277-MLA116768315131_082026-E.webp', 1, 0, 'potes herméticos,potes de vidro,porta mantimentos', 1, 160)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('umidificador-ar', 'item', 'Umidificador de ar', NULL, 'Casa & Conforto', '100-250', 200, 'https://m.media-amazon.com/images/I/71a+5LPb9EL._AC_UL320_.jpg', 1, 0, 'umidificador de ar,umidificador ultrassônico', 1, 170)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('ventilador', 'item', 'Ventilador', NULL, 'Eletroportáteis', 'ate-100', 200, 'https://m.media-amazon.com/images/I/61o7Q8hq2zL._AC_UL320_.jpg', 1, 0, 'ventilador', 1, 180)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('aspirador-vertical', 'item', 'Aspirador de pó vertical', NULL, 'Eletroportáteis', '100-250', 240, 'https://m.media-amazon.com/images/I/61wBRlZjDQL._AC_UL320_.jpg', 1, 0, 'aspirador de pó vertical,aspirador vertical', 1, 190)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('grill-eletrico', 'item', 'Grill elétrico antiaderente', NULL, 'Eletroportáteis', '100-250', 290, 'https://m.media-amazon.com/images/I/5199OwhuFwL._AC_UL320_.jpg', 1, 0, 'grill elétrico,grill antiaderente', 1, 200)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('aparelho-jantar-polka', 'item', 'Aparelho de jantar Polka Dots (20 peças)', NULL, 'Mesa & Bar', 'acima-250', 300, 'https://http2.mlstatic.com/D_Q_NP_2X_916799-MLA95743649176_102025-E.webp', 1, 0, 'aparelho de jantar 20 peças', 1, 210)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('batedeira-planetaria', 'item', 'Batedeira planetária', NULL, 'Eletroportáteis', 'acima-250', 300, 'https://m.media-amazon.com/images/I/51aQCAmwRLL._AC_UL320_.jpg', 1, 1, 'batedeira planetária', 1, 220)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('aparelho-jantar', 'item', 'Aparelho de jantar', NULL, 'Mesa & Bar', 'acima-250', 350, 'https://http2.mlstatic.com/D_Q_NP_2X_998805-MLA95694378074_102025-E.webp', 1, 0, 'aparelho de jantar', 1, 230)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('air-fryer', 'item', 'Fritadeira Air Fryer (3,2 L ou maior)', NULL, 'Eletroportáteis', '100-250', 350, 'https://m.media-amazon.com/images/I/51QUCHOZtGL._AC_UL320_.jpg', 1, 1, 'air fryer,fritadeira elétrica,airfryer', 1, 240)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('lavadora-alta-pressao', 'item', 'Lavadora de alta pressão', NULL, 'Eletroportáteis', 'acima-250', 400, 'https://m.media-amazon.com/images/I/711W3v-nNpL._AC_UL320_.jpg', 1, 0, 'lavadora de alta pressão,lavadora alta pressão', 1, 250)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('aparelho-jantar-30', 'item', 'Aparelho de jantar branco (30 peças)', NULL, 'Mesa & Bar', 'acima-250', 500, 'https://http2.mlstatic.com/D_Q_NP_2X_618763-MLA96104647385_102025-E.webp', 1, 0, 'aparelho de jantar 30 peças', 1, 260)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, imagem_url, qtd_desejada, prioridade, busca_etiqueta, ativo, ordem) VALUES ('climatizador-ar', 'item', 'Climatizador de ar', NULL, 'Eletroportáteis', 'acima-250', 700, 'https://m.media-amazon.com/images/I/61SrntjhhlL._AC_UL320_.jpg', 1, 0, 'climatizador de ar,climatizador', 1, 270)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), faixa=VALUES(faixa), preco_ref=VALUES(preco_ref), imagem_url=COALESCE(VALUES(imagem_url), imagem_url), busca_etiqueta=VALUES(busca_etiqueta);

INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, qtd_desejada, prioridade, ativo, ordem) VALUES ('cota-jantar-lua-de-mel', 'cota', 'Jantar da lua de mel', 'Um jantar especial para brindarmos o começo da nossa vida juntos.', 'Cotas', '100-250', 150, 10, 0, 1, 280)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), descricao=VALUES(descricao), preco_ref=VALUES(preco_ref), faixa=VALUES(faixa);
INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, qtd_desejada, prioridade, ativo, ordem) VALUES ('cota-passeio-lua-de-mel', 'cota', 'Passeio na lua de mel', 'Um passeio para guardar na memória (e no álbum).', 'Cotas', '100-250', 250, 6, 0, 1, 290)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), descricao=VALUES(descricao), preco_ref=VALUES(preco_ref), faixa=VALUES(faixa);
INSERT INTO presentes (slug, tipo, nome, descricao, categoria, faixa, preco_ref, qtd_desejada, prioridade, ativo, ordem) VALUES ('cota-casa-nova', 'cota', 'Ajuda na casa nova', 'Cada cantinho do nosso lar vai lembrar de você.', 'Cotas', 'ate-100', 100, 20, 0, 1, 300)
  ON DUPLICATE KEY UPDATE nome=VALUES(nome), descricao=VALUES(descricao), preco_ref=VALUES(preco_ref), faixa=VALUES(faixa);
