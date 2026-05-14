<?php
// ============================================================
//  MindCash — Configuração Global do Sistema
//  Para renomear o app inteiro, altere APENAS esta variável:
// ============================================================

$NOME_SISTEMA = 'MindCash';

// ── Meta / SEO ────────────────────────────────────────────
define('SISTEMA_NOME',      $NOME_SISTEMA);
define('SISTEMA_TAGLINE',   'Sua inteligência financeira, amplificada.');
define('SISTEMA_VERSAO',    '1.0.0');
define('SISTEMA_AUTOR',     'MindCash Team');
define('SISTEMA_COR',       '#6C63FF');   // cor primária usada no manifest.json
define('SISTEMA_URL',       'https://mindcash.app');

// ── Ambiente ─────────────────────────────────────────────
define('AMBIENTE',   'desenvolvimento');  // 'producao' desativa erros visíveis
define('DEBUG_MODE', AMBIENTE === 'desenvolvimento');

// ── Sessão ───────────────────────────────────────────────
define('SESSION_NOME',    'mc_sessao');
define('SESSION_DURACAO', 60 * 60 * 8);  // 8 horas em segundos
define('CSRF_TOKEN_KEY',  'mc_csrf');

// ── Upload de Fotos ───────────────────────────────────────
define('UPLOAD_DIR',      __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL',      '/assets/uploads/');
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024);   // 2 MB
define('UPLOAD_TIPOS',    ['image/jpeg', 'image/png', 'image/webp']);

// ── Paginação ─────────────────────────────────────────────
define('MSGS_POR_PAGINA', 30);
define('ITENS_POR_PAGINA', 20);

// ── Erros ────────────────────────────────────────────────
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}