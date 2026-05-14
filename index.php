<?php
// ============================================================
//  MindCash — index.php | Roteador e Casca Principal (SPA)
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// ── Rotas de API ─────────────────────────────────────────────
// (para requisições AJAX diretas ao index)
if (isset($_GET['api'])) {
    http_response_code(404);
    die(json_encode(['erro' => 'Endpoint não encontrado']));
}

$usuario = usuario_logado();
$csrf    = csrf_gerar();
$aba     = htmlspecialchars($_GET['aba'] ?? 'inicio', ENT_QUOTES, 'UTF-8');

// Abas válidas
$abas_validas = ['inicio', 'comunidade', 'ferramentas', 'mentoria', 'conta'];
if (!in_array($aba, $abas_validas, true)) $aba = 'inicio';

// ── Ícones SVG Inline (reutilizáveis) ────────────────────────
$icones = [
    'inicio'      => '<svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    'comunidade'  => '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>',
    'ferramentas' => '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
    'mentoria'    => '<svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.09 10.9a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .18h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 8a16 16 0 006.29 6.29l1.14-1.14a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>',
    'conta'       => '<svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    'logo'        => '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="lg" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:#7C6FFF"/><stop offset="100%" style="stop-color:#00E5C3"/></linearGradient></defs><path d="M16 2L2 12v18h10v-8h8v8h10V12L16 2z" fill="url(#lg)" opacity="0.9"/><path d="M10 20h4v-4h4v4h4" stroke="white" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>',
];

$labels_nav = [
    'inicio'      => 'Início',
    'comunidade'  => 'Comunidade',
    'ferramentas' => 'Ferramentas',
    'mentoria'    => 'Mentoria',
    'conta'       => 'Conta',
];

// ── Foto do usuário ───────────────────────────────────────────
$foto_usuario = $usuario && $usuario['foto']
    ? esc(UPLOAD_URL . $usuario['foto'])
    : null;

$inicial = $usuario ? strtoupper(mb_substr($usuario['nome'], 0, 1, 'UTF-8')) : 'U';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="<?= SISTEMA_COR ?>">
  <meta name="description"  content="<?= esc(SISTEMA_NOME) ?> — <?= esc(SISTEMA_TAGLINE) ?>">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="<?= esc(SISTEMA_NOME) ?>">

  <title><?= esc(SISTEMA_NOME) ?> — <?= esc(SISTEMA_TAGLINE) ?></title>

  <!-- PWA Manifest -->
  <link rel="manifest" href="/manifest.json">

  <!-- Favicon / Icons -->
  <link rel="icon" type="image/svg+xml" href="/assets/icons/icon.svg">
  <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">

  <!-- CSS -->
  <link rel="stylesheet" href="/css/style.css">

  <!-- Open Graph -->
  <meta property="og:title"       content="<?= esc(SISTEMA_NOME) ?>">
  <meta property="og:description" content="<?= esc(SISTEMA_TAGLINE) ?>">
  <meta property="og:type"        content="website">
</head>
<body>

<div id="app">

  <!-- ═══ SIDEBAR DESKTOP ═══════════════════════════════════ -->
  <aside id="nav-desktop" role="navigation" aria-label="Menu principal">
    <div class="sidebar-logo">
      <?= $icones['logo'] ?>
      <?= esc(SISTEMA_NOME) ?>
    </div>

    <nav class="sidebar-nav">
      <?php foreach ($abas_validas as $item): ?>
        <button
          class="sidebar-item <?= $aba === $item ? 'ativo' : '' ?>"
          data-aba="<?= $item ?>"
          aria-label="<?= esc($labels_nav[$item]) ?>"
          aria-current="<?= $aba === $item ? 'page' : 'false' ?>">
          <?= $icones[$item] ?>
          <?= esc($labels_nav[$item]) ?>
        </button>
      <?php endforeach; ?>
    </nav>

    <!-- Info do usuário na sidebar -->
    <div class="sidebar-usuario" data-aba="conta" style="cursor:pointer">
      <?php if ($foto_usuario): ?>
        <img src="<?= $foto_usuario ?>" alt="Foto" class="avatar avatar-sm">
      <?php else: ?>
        <div class="avatar avatar-sm avatar-placeholder" style="font-size:.75rem">
          <?= $inicial ?>
        </div>
      <?php endif; ?>
      <div class="sidebar-usuario-info">
        <div class="sidebar-usuario-nome">
          <?= $usuario ? esc($usuario['nome']) : 'Visitante' ?>
        </div>
        <div class="sidebar-usuario-nivel">
          <?= $usuario ? esc(ucfirst($usuario['nivel'])) : 'Não autenticado' ?>
        </div>
      </div>
      <svg viewBox="0 0 24 24" width="14" height="14" stroke="var(--texto-terciario)" fill="none" stroke-width="2">
        <polyline points="9 18 15 12 9 6"/>
      </svg>
    </div>
  </aside>

  <!-- ═══ CONTEÚDO PRINCIPAL ═════════════════════════════════ -->
  <main id="conteudo-principal" role="main" aria-live="polite">
    <!-- Carregado via JS / PHP módulos -->
    <div style="display:flex;align-items:center;justify-content:center;height:60vh;flex-direction:column;gap:16px">
      <div class="spinner" style="width:36px;height:36px;border-width:3px"></div>
      <p style="color:var(--texto-terciario);font-size:.9rem">Carregando <?= esc(SISTEMA_NOME) ?>…</p>
    </div>
  </main>

  <!-- ═══ BOTTOM NAV MOBILE ══════════════════════════════════ -->
  <nav id="nav-mobile" role="navigation" aria-label="Navegação inferior">
    <?php foreach ($abas_validas as $item): ?>
      <button
        class="nav-item <?= $aba === $item ? 'ativo' : '' ?>"
        data-aba="<?= $item ?>"
        aria-label="<?= esc($labels_nav[$item]) ?>"
        aria-current="<?= $aba === $item ? 'page' : 'false' ?>">
        <span class="nav-icone"><?= $icones[$item] ?></span>
        <span class="nav-label"><?= esc($labels_nav[$item]) ?></span>
      </button>
    <?php endforeach; ?>
  </nav>

</div><!-- /#app -->

<!-- ═══ TOAST CONTAINER ══════════════════════════════════════ -->
<div id="toast-container" aria-live="polite" aria-atomic="true"></div>

<!-- ═══ DADOS PHP → JS ═══════════════════════════════════════ -->
<script>
  window.MC = {
    nomeSistema: <?= json_encode(SISTEMA_NOME) ?>,
    usuario: <?= json_encode($usuario) ?>,
    csrf: <?= json_encode($csrf) ?>,
    abaInicial: <?= json_encode($aba) ?>,
    uploadUrl: <?= json_encode(UPLOAD_URL) ?>,
  };
</script>

<!-- ═══ SCRIPTS ═══════════════════════════════════════════════ -->
<script src="/js/main.js" defer></script>

</body>
</html>