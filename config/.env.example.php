<?php
// Copie para config/.env.php e preencha. NUNCA versione o .env.php.
// Na Hostinger, deixe a pasta config/ FORA do public_html (veja o README).
return [
    'APP_ENV'    => 'producao',               // 'dev' mostra erros na tela
    'SITE_URL'   => 'https://[[PENDENTE: dominio]]', // sem barra no fim; usado no Open Graph, .ics, e-mails

    // Gere com: php -r "echo bin2hex(random_bytes(32));"   (ou: openssl rand -hex 32)
    'APP_SECRET' => '',                       // obrigatório: assina hashes de IP e tokens

    // hPanel → Bancos de Dados MySQL
    'DB_HOST' => 'localhost',
    'DB_PORT' => 3306,
    'DB_NAME' => 'u000000000_casamento',
    'DB_USER' => 'u000000000_casamento',
    'DB_PASS' => '',

    // Chave do importador do Etiqueta (tools/importar-etiqueta.mjs --enviar).
    // Mínimo 32 caracteres. Vazio = endpoint de importação desligado.
    // Salve a MESMA chave em config/import_key.txt no seu computador (fora do Git).
    'IMPORT_KEY' => '',

    // E-mail de confirmação. Crie a caixa em hPanel → E-mails.
    'MAIL_FROM'      => 'contato@[[PENDENTE: dominio]]',
    'MAIL_FROM_NOME' => 'Ana & Lucas',
    'MAIL_RESPONDER' => '',                   // reply-to opcional (e-mail dos noivos)

    // SMTP da Hostinger (recomendado). Vazio = usa mail() do PHP.
    'SMTP_HOST'   => 'smtp.hostinger.com',
    'SMTP_PORT'   => 465,
    'SMTP_USER'   => 'contato@[[PENDENTE: dominio]]',
    'SMTP_PASS'   => '',
    'SMTP_SECURE' => 'ssl',                   // 'ssl' (465), 'tls' (587, STARTTLS) ou '' (sem criptografia, só teste local)
];
