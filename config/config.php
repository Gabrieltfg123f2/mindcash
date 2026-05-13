<?php
/**
 * MindCash — Configuração Global
 * Altere $NOME_SISTEMA aqui para refletir em TODO o sistema.
 */

// ── Identidade ───────────────────────────────────────────────
$NOME_SISTEMA   = 'MindCash';
$VERSAO         = '1.0.0';
$DESCRICAO      = 'Inteligência financeira para sua mente e bolso.';

// ── Ambiente ─────────────────────────────────────────────────
define('ENV',         'development');   // 'production' em produção
define('BASE_URL',    'http://localhost/mindcash');
define('BASE_PATH',   __DIR__ . '/..');

// ── Caminhos ─────────────────────────────────────────────────
define('MODULES_PATH', BASE_PATH . '/modules');
define('ASSETS_URL',   BASE_URL  . '/assets');
define('CSS_URL',      BASE_URL  . '/css');
define('JS_URL',       BASE_URL  . '/js');

// ── Sessão ───────────────────────────────────────────────────
define('SESSION_NAME',     'mc_session');
define('SESSION_LIFETIME', 60 * 60 * 8);   // 8 horas
define('CSRF_LIFETIME',    60 * 60 * 2);   // 2 horas

// ── Google OAuth (preencha com suas credenciais) ─────────────
define('GOOGLE_CLIENT_ID',     '');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI',  BASE_URL . '/index.php?mod=conta&action=google_callback');

// ── Segurança ────────────────────────────────────────────────
define('BCRYPT_COST',  12);
define('MAX_MSG_LEN',  2000);   // caracteres por mensagem

// ── Debug (desative em produção) ─────────────────────────────
if (ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Disponibiliza $NOME_SISTEMA como constante também
define('NOME_SISTEMA', $NOME_SISTEMA);
