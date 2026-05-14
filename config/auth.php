<?php
// ============================================================
//  MindCash — Autenticação, CSRF e Segurança
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ── Iniciar Sessão Segura ─────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NOME);
    session_set_cookie_params([
        'lifetime' => SESSION_DURACAO,
        'path'     => '/',
        'secure'   => !DEBUG_MODE,   // HTTPS only em produção
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ── CSRF Token ────────────────────────────────────────────
function csrf_gerar(): string
{
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

function csrf_validar(string $token): bool
{
    $valido = isset($_SESSION[CSRF_TOKEN_KEY])
        && hash_equals($_SESSION[CSRF_TOKEN_KEY], $token);
    // Rotaciona o token após uso
    if ($valido) {
        unset($_SESSION[CSRF_TOKEN_KEY]);
    }
    return $valido;
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_gerar() . '">';
}

// ── XSS — Saída Segura ────────────────────────────────────
function esc(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Autenticação ─────────────────────────────────────────
function auth_login(string $email, string $senha): array|false
{
    $usuario = db_um(
        'SELECT id, nome, email, senha, nivel, foto, ativo FROM usuarios WHERE email = ? LIMIT 1',
        [strtolower(trim($email))]
    );

    if (!$usuario || !$usuario['ativo'] || !password_verify($senha, $usuario['senha'])) {
        return false;
    }

    // Regenera sessão para prevenir session fixation
    session_regenerate_id(true);

    $_SESSION['usuario_id']    = $usuario['id'];
    $_SESSION['usuario_nome']  = $usuario['nome'];
    $_SESSION['usuario_nivel'] = $usuario['nivel'];
    $_SESSION['usuario_foto']  = $usuario['foto'];
    $_SESSION['login_em']      = time();

    // Registra histórico
    registrar_acao($usuario['id'], 'login', ['ip' => ip_cliente()]);

    return $usuario;
}

function auth_logout(): void
{
    if (isset($_SESSION['usuario_id'])) {
        registrar_acao($_SESSION['usuario_id'], 'logout');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

function auth_verificar(): bool
{
    return isset($_SESSION['usuario_id'])
        && (time() - ($_SESSION['login_em'] ?? 0)) < SESSION_DURACAO;
}

function auth_requer(): void
{
    if (!auth_verificar()) {
        if (is_ajax()) {
            http_response_code(401);
            die(json_encode(['erro' => 'Sessão expirada. Faça login novamente.']));
        }
        header('Location: /?aba=conta&acao=login');
        exit;
    }
}

function auth_requer_adm(): void
{
    auth_requer();
    if (($_SESSION['usuario_nivel'] ?? '') !== 'adm') {
        if (is_ajax()) {
            http_response_code(403);
            die(json_encode(['erro' => 'Acesso restrito a administradores.']));
        }
        header('Location: /?aba=inicio&aviso=acesso_negado');
        exit;
    }
}

function usuario_logado(): array|null
{
    if (!auth_verificar()) return null;
    return [
        'id'    => $_SESSION['usuario_id'],
        'nome'  => $_SESSION['usuario_nome'],
        'nivel' => $_SESSION['usuario_nivel'],
        'foto'  => $_SESSION['usuario_foto'],
    ];
}

function eh_adm(): bool
{
    return ($_SESSION['usuario_nivel'] ?? '') === 'adm';
}

// ── Utilitários ───────────────────────────────────────────
function ip_cliente(): string
{
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $chave) {
        if (!empty($_SERVER[$chave])) {
            return filter_var(explode(',', $_SERVER[$chave])[0], FILTER_VALIDATE_IP) ?: 'desconhecido';
        }
    }
    return 'desconhecido';
}

function is_ajax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function registrar_acao(int $usuario_id, string $acao, array $detalhes = []): void
{
    try {
        db_executar(
            'INSERT INTO historico_acoes (usuario_id, acao, detalhes, ip) VALUES (?, ?, ?, ?)',
            [$usuario_id, $acao, json_encode($detalhes) ?: null, ip_cliente()]
        );
    } catch (Throwable) { /* não bloqueia a execução */ }
}

// ── Sanitização de Input ──────────────────────────────────
function sanitizar_texto(string $input, int $max = 500): string
{
    $limpo = trim(strip_tags($input));
    return mb_substr($limpo, 0, $max, 'UTF-8');
}

function sanitizar_email(string $email): string|false
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
}

// ── Resposta JSON ─────────────────────────────────────────
function json_resposta(mixed $dados, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}