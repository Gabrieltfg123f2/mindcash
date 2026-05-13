<?php
/**
 * MindCash — Módulo: Início
 */
if (!defined('NOME_SISTEMA')) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/auth.php';
    iniciarSessao();
}

$usuario = usuarioAtual();
$nomeUsuario = xss($usuario['nome'] ?? 'Visitante');
?>

<section class="hero fade-in">
  <div class="hero-badge">✦ Inteligência Financeira</div>
  <h1><?= xss(NOME_SISTEMA) ?></h1>
  <p><?= xss($DESCRICAO ?? 'Inteligência financeira para sua mente e bolso.') ?></p>

  <?php if (!estaLogado() || ($usuario['nivel'] ?? '') === 'anonimo'): ?>
    <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center">
      <a href="index.php?mod=conta" class="btn btn-primary">
        <?= svgIcon('user') ?> Criar conta
      </a>
      <a href="index.php?mod=comunidade" class="btn btn-outline">
        <?= svgIcon('users') ?> Ver comunidade
      </a>
    </div>
  <?php else: ?>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <a href="index.php?mod=comunidade" class="btn btn-primary">
        <?= svgIcon('users') ?> Ir para comunidade
      </a>
      <a href="index.php?mod=ferramentas" class="btn btn-outline">
        <?= svgIcon('tool') ?> Ferramentas
      </a>
    </div>
  <?php endif; ?>
</section>

<!-- Features -->
<div class="features-grid">
  <?php
  $features = [
    ['icon' => 'trending-up', 'title' => 'Finanças inteligentes', 'desc' => 'Monitore gastos e metas com clareza e simplicidade.'],
    ['icon' => 'users',       'title' => 'Comunidade ativa',      'desc' => 'Compartilhe experiências e aprenda com outros membros.'],
    ['icon' => 'tool',        'title' => 'Ferramentas práticas',  'desc' => 'Calculadoras, buscas e monitoria de dados em tempo real.'],
    ['icon' => 'star',        'title' => 'Mentoria exclusiva',    'desc' => 'Conteúdo e suporte direto com especialistas da plataforma.'],
  ];
  foreach ($features as $f): ?>
    <div class="feature-card fade-in">
      <div class="feature-icon"><?= svgIcon($f['icon']) ?></div>
      <h3><?= xss($f['title']) ?></h3>
      <p><?= xss($f['desc']) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<!-- Boas-vindas personalizada -->
<?php if ($usuario): ?>
<div class="card card-glow" style="margin-top:24px">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:4px">
    <div style="font-size:28px">👋</div>
    <div>
      <div style="font-family:var(--font-display);font-size:18px;font-weight:700">
        Olá, <?= $nomeUsuario ?>!
      </div>
      <div style="font-size:13px;color:var(--text-muted)">
        Você está logado como
        <span style="color:var(--accent)"><?= xss($usuario['nivel']) ?></span>
      </div>
    </div>
  </div>
  <p style="font-size:14px;color:var(--text-muted);margin-top:8px">
    Use o menu abaixo para navegar entre as seções do <?= xss(NOME_SISTEMA) ?>.
  </p>
</div>
<?php endif; ?>

<?php
// SVG helper local para este módulo
function svgIcon(string $name): string {
    $icons = [
        'user'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'users'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
        'tool'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>',
        'star'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        'trending-up'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
    ];
    return $icons[$name] ?? '';
}
?>
