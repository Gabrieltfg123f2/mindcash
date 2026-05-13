<?php
/**
 * MindCash — Roteador Principal
 * Ponto de entrada único do sistema.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

iniciarSessao();

// ── Segurança: headers HTTP ──────────────────────────────────
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://accounts.google.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data: https://lh3.googleusercontent.com;");

// ── Módulos permitidos ───────────────────────────────────────
$MODULOS = ['inicio', 'comunidade', 'ferramentas', 'mentoria', 'conta'];
$mod = preg_replace('/[^a-z]/', '', strtolower($_GET['mod'] ?? 'inicio'));
if (!in_array($mod, $MODULOS, true)) $mod = 'inicio';

// ── Ações AJAX (retornam JSON) ───────────────────────────────
$action = $_GET['action'] ?? '';
$ehAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($ehAjax && $action) {
    header('Content-Type: application/json; charset=utf-8');
    require_once MODULES_PATH . "/{$mod}.php";
    exit;
}

// ── Acesso anônimo automático ────────────────────────────────
if (!estaLogado()) {
    loginAnonimo();
}

// ── Ações de autenticação (não-AJAX) ────────────────────────
if ($mod === 'conta') {
    if ($action === 'google_callback') { processarCallbackGoogle(); }
    if ($action === 'logout') { exigirCsrf(); logout(); }
}

// ── Gera token CSRF para a view ──────────────────────────────
$csrfToken = gerarCsrfToken();
$usuario   = usuarioAtual();

// ── Nomes das abas ────────────────────────────────────────────
$ABAS = [
    'inicio'       => ['label' => 'Início',      'icon' => 'home'],
    'comunidade'   => ['label' => 'Comunidade',  'icon' => 'users'],
    'ferramentas'  => ['label' => 'Ferramentas', 'icon' => 'tool'],
    'mentoria'     => ['label' => 'Mentoria',    'icon' => 'star'],
    'conta'        => ['label' => 'Conta',       'icon' => 'user'],
];

// ── Ícones SVG inline ─────────────────────────────────────────
function navIcon(string $name): string {
    $icons = [
        'home'  => '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'users' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
        'tool'  => '<path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/>',
        'star'  => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'user'  => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    ];
    return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' . ($icons[$name] ?? '') . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= xss(NOME_SISTEMA) ?> — <?= xss($ABAS[$mod]['label']) ?></title>
  <meta name="description" content="<?= xss($DESCRICAO) ?>">
  <meta name="csrf-token" content="<?= xss($csrfToken) ?>">
  <meta name="theme-color" content="#0b0f19">

  <!-- Fonts & CSS -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= CSS_URL ?>/style.css">
</head>
<body>

<!-- ── Topbar (desktop) ───────────────────────────────────── -->
<header class="topbar">
  <a href="index.php?mod=inicio" class="topbar-logo"><?= xss(NOME_SISTEMA) ?></a>
  <nav class="topbar-nav">
    <?php foreach ($ABAS as $m => $info): ?>
      <?php if ($m === 'mentoria' && !ehAdmin()) continue; ?>
      <a href="index.php?mod=<?= $m ?>"
         class="nav-item <?= $mod === $m ? 'active' : '' ?>"
         data-mod="<?= $m ?>">
        <?= navIcon($info['icon']) ?>
        <?= xss($info['label']) ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div style="font-size:13px;color:var(--text-muted)">
    <?= xss($usuario['nome'] ?? 'Visitante') ?>
    <?php if (ehAdmin()): ?>
      <span style="color:var(--accent-hot);margin-left:6px">⭐ ADM</span>
    <?php endif; ?>
  </div>
</header>

<!-- ── Conteúdo principal ──────────────────────────────────── -->
<div class="app-shell">
  <main class="main-content">
    <?php require_once MODULES_PATH . "/{$mod}.php"; ?>
  </main>

  <footer>
    &copy; <?= date('Y') ?> <?= xss(NOME_SISTEMA) ?> · Todos os direitos reservados
  </footer>
</div>

<!-- ── Nav inferior (mobile) ──────────────────────────────── -->
<nav class="bottom-nav">
  <?php foreach ($ABAS as $m => $info): ?>
    <?php if ($m === 'mentoria' && !ehAdmin()) continue; ?>
    <a href="index.php?mod=<?= $m ?>"
       class="nav-item <?= $mod === $m ? 'active' : '' ?>"
       data-mod="<?= $m ?>"
       style="position:relative">
      <?= navIcon($info['icon']) ?>
      <?= xss($info['label']) ?>
      <?php if ($m === 'mentoria' && ehAdmin()): ?>
        <span class="badge-adm"></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<div id="toast-container"></div>
<script src="<?= JS_URL ?>/main.js"></script>
</body>
</html>
