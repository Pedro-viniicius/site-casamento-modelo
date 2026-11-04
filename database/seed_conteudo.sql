-- Conteúdo inicial do site (config_site). Igual a CONTEUDO_PADRAO em inc/dados.php.
-- Troque tudo no admin → Configurações. Itens [[PENDENTE: ...]] ficam escondidos dos convidados até serem preenchidos.
SET NAMES utf8mb4;

INSERT IGNORE INTO config_site (chave, valor) VALUES
('noiva_nome', 'Ana'),
('noiva_completo', 'Ana Exemplo'),
('noivo_nome', 'Lucas'),
('noivo_completo', 'Lucas Exemplo'),
('data_evento', '2027-06-12 16:30'),
('local_nome', 'Espaço Exemplo'),
('local_endereco', 'Rua Exemplo, 100, Centro, Cidade (UF), 00000-000'),
('local_cidade', 'Cidade (UF)'),
('local_lat', '-23.5505'),
('local_lng', '-46.6333'),
('local_place_id', ''),
('recepcao_horario', 'Logo após a cerimônia'),
('recepcao_local', 'No mesmo espaço da cerimônia'),
('estacionamento', '[[PENDENTE: estacionamento (tem? é gratuito? há manobrista?)]]'),
('boas_vindas', 'Criamos este cantinho para dividir com vocês os detalhes do nosso casamento. Estamos muito felizes e contamos com a sua presença no nosso grande dia!'),
('frase_presentes', 'O melhor presente é ter você com a gente.'),
('versiculo', 'O amor é paciente, o amor é bondoso.'),
('versiculo_ref', '1 Coríntios 13:4'),
('historia', '2020 | Como nos conhecemos | Conte aqui como vocês se conheceram.\n | Namoro | Vieram os sonhos, os aprendizados e os momentos especiais lado a lado.\n | Noivado | O “sim” que confirmou o que já vinha sendo escrito.\n12 jun 2027 | O grande dia | Um novo capítulo, celebrado ao lado das pessoas que amamos.'),
('rsvp_prazo', '[[PENDENTE: prazo para confirmar presença, ex.: 2027-05-20]]'),
('max_adultos', '6'),
('max_criancas', '4'),
('pix_chave', '[[PENDENTE: chave PIX]]'),
('pix_nome', '[[PENDENTE: nome de quem recebe o PIX]]'),
('pix_cidade', '[[PENDENTE: cidade de quem recebe o PIX]]'),
('loja_fisica', '[[PENDENTE: loja física com lista de presentes (opcional)]]'),
('loja_fisica_url', ''),
('abertura_animada', '1'),
('whatsapp_texto', 'Você é nosso convidado! 💍 Confirme sua presença e veja a lista de presentes:');
