<?php
/**
 * MindCash — Lógica de Autenticação
 * Suporta: Login Anônimo | Login com Google OAuth2
 */

require_once __DIR__ . '/../.gitignore/config/config.php';
require_once __DIR__ . '/../.gitignore/config/db.php';

// ── Inicialização de sessão segura ───────────────────────────
function iniciarSessao(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => (ENV === 'production'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Regenera ID a cada 30 minutos para prevenir fixação de sessão
    if (!isset($_SESSION['ultima_regeneracao'])) {
        $_SESSION['ultima_regeneracao'] = time();
    } elseif (time() - $_SESSION['ultima_regeneracao'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['ultima_regeneracao'] = time();
    }
}

// ── CSRF ─────────────────────────────────────────────────────
function gerarCsrfToken(): string {
    iniciarSessao();
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token']  = $token;
    $_SESSION['csrf_expira'] = time() + CSRF_LIFETIME;
    return $token;
}

function validarCsrfToken(string $token): bool {
    iniciarSessao();
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_expira'])) {
        return false;
    }
    if (time() > $_SESSION['csrf_expira']) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function exigirCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validarCsrfToken($token)) {
        http_response_code(403);
        die(json_encode(['erro' => 'Token CSRF inválido.']));
    }
}

// ── XSS ──────────────────────────────────────────────────────
function xss(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Usuário atual ─────────────────────────────────────────────
function usuarioAtual(): ?array {
    iniciarSessao();
    return $_SESSION['usuario'] ?? null;
}

function estaLogado(): bool {
    return usuarioAtual() !== null;
}

function ehAdmin(): bool {
    $u = usuarioAtual();
    return $u && $u['nivel'] === 'adm';
}

function exigirAdmin(): void {
    if (!ehAdmin()) {
        header('Location: ' . BASE_URL . '/index.php?mod=inicio');
        exit;
    }
}

// ── Login Anônimo ─────────────────────────────────────────────
function loginAnonimo(): array {
    iniciarSessao();

    if (isset($_SESSION['usuario'])) {
        return $_SESSION['usuario'];
    }

    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $nome = 'Anônimo_' . strtoupper(substr($uuid, 0, 6));

    dbQuery(
        "INSERT INTO usuarios (uuid, nome, nivel) VALUES (?, ?, 'anonimo')",
        [$uuid, $nome]
    );
    $id = (int) getDB()->lastInsertId();

    dbQuery("INSERT INTO perfis (usuario_id) VALUES (?)", [$id]);

    $usuario = ['id' => $id, 'uuid' => $uuid, 'nome' => $nome, 'nivel' => 'anonimo', 'avatar_url' => null];
    $_SESSION['usuario'] = $usuario;

    registrarAtividade($id, 'login_anonimo');
    return $usuario;
}

// ── Google OAuth — URL de autorização ─────────────────────────
function urlGoogleOAuth(): string {
    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => gerarCsrfToken(),
        'prompt'        => 'select_account',
    ]);
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
}

// ── Google OAuth — Callback ────────────────────────────────────
function processarCallbackGoogle(): void {
    iniciarSessao();

    $code  = $_GET['code']  ?? '';
    $state = $_GET['state'] ?? '';

    if (!validarCsrfToken($state) || empty($code)) {
        header('Location: ' . BASE_URL . '/index.php?mod=inicio&erro=oauth');
        exit;
    }

    // Trocar code por access_token
    $resp = file_get_contents('https://oauth2.googleapis.com/token', false,
        stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'code'          => $code,
                'client_id'     => GOOGLE_CLIENT_ID,
                'client_secret' => GOOGLE_CLIENT_SECRET,
                'redirect_uri'  => GOOGLE_REDIRECT_URI,
                'grant_type'    => 'authorization_code',
            ]),
        ]])
    );

    $token = json_decode($resp, true);
    if (empty($token['id_token'])) {
        header('Location: ' . BASE_URL . '/index.php?mod=inicio&erro=oauth_token');
        exit;
    }

    // Decodificar payload JWT (sem verificar assinatura — use lib em produção)
    $partes   = explode('.', $token['id_token']);
    $payload  = json_decode(base64_decode(str_pad(strtr($partes[1], '-_', '+/'), strlen($partes[1]) % 4 === 0 ? 0 : 4 - strlen($partes[1]) % 4, '=')), true);

    $googleId = $payload['sub']     ?? '';
    $email    = $payload['email']   ?? '';
    $nome     = $payload['name']    ?? 'Usuário Google';
    $avatar   = $payload['picture'] ?? null;

    if (empty($googleId)) {
        header('Location: ' . BASE_URL . '/index.php?mod=inicio&erro=oauth_payload');
        exit;
    }

    // Upsert no banco
    $usuario = dbQuery("SELECT * FROM usuarios WHERE google_id = ? OR email = ? LIMIT 1", [$googleId, $email])->fetch();

    if ($usuario) {
        dbQuery("UPDATE usuarios SET google_id=?, nome=?, avatar_url=?, ultimo_login=NOW() WHERE id=?",
            [$googleId, $nome, $avatar, $usuario['id']]);
        $usuario['nome']       = $nome;
        $usuario['avatar_url'] = $avatar;
    } else {
        $uuid = bin2hex(random_bytes(8));
        dbQuery("INSERT INTO usuarios (uuid,nome,email,google_id,avatar_url,nivel,ultimo_login) VALUES (?,?,?,?,?,'membro',NOW())",
            [$uuid, $nome, $email, $googleId, $avatar]);
        $id      = (int) getDB()->lastInsertId();
        dbQuery("INSERT INTO perfis (usuario_id) VALUES (?)", [$id]);
        $usuario = dbQuery("SELECT * FROM usuarios WHERE id = ?", [$id])->fetch();
    }

    $_SESSION['usuario'] = [
        'id'         => $usuario['id'],
        'uuid'       => $usuario['uuid'],
        'nome'       => $usuario['nome'],
        'email'      => $usuario['email'] ?? '',
        'nivel'      => $usuario['nivel'],
        'avatar_url' => $usuario['avatar_url'],
    ];

    registrarAtividade($usuario['id'], 'login_google');
    header('Location: ' . BASE_URL . '/index.php?mod=inicio');
    exit;
}

// ── Logout ────────────────────────────────────────────────────
function logout(): void {
    iniciarSessao();
    $u = usuarioAtual();
    if ($u) registrarAtividade($u['id'], 'logout');
    $_SESSION = [];
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php?mod=inicio');
    exit;
}

// ── Log de atividades ─────────────────────────────────────────
function registrarAtividade(int $usuarioId, string $acao, array $detalhes = []): void {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    dbQuery(
        "INSERT INTO atividades (usuario_id, acao, detalhes, ip) VALUES (?, ?, ?, ?)",
        [$usuarioId, $acao, empty($detalhes) ? null : json_encode($detalhes), $ip]
    );
}
