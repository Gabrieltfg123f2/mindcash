<?php
// ============================================================
//  MindCash — Módulo: Início (Dashboard)
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$usuario = usuario_logado();

// ── Dados para os cards de estatísticas ──────────────────────
$stats = [];

if ($usuario) {
    try {
        $stats['mensagens'] = db_um(
            'SELECT COUNT(*) as total FROM mensagens_comunidade WHERE usuario_id = ?',
            [$usuario['id']]
        )['total'] ?? 0;

        $stats['ferramentas'] = db_um(
            'SELECT COUNT(*) as total FROM ferramentas_dados WHERE usuario_id = ?',
            [$usuario['id']]
        )['total'] ?? 0;

        $stats['acoes'] = db_um(
            'SELECT COUNT(*) as total FROM historico_acoes WHERE usuario_id = ?',
            [$usuario['id']]
        )['total'] ?? 0;

        $stats['dias'] = db_um(
            'SELECT DATEDIFF(NOW(), criado_em) as dias FROM usuarios WHERE id = ? LIMIT 1',
            [$usuario['id']]
        )['dias'] ?? 0;

    } catch (Throwable) {
        $stats = ['mensagens' => 0, 'ferramentas' => 0, 'acoes' => 0, 'dias' => 0];
    }
}
?>

<section class="pagina-header animar-entrada">
  <h1 class="pagina-titulo">
    <?php if ($usuario): ?>
      Olá, <?= esc(explode(' ', $usuario['nome'])[0]) ?> 👋
    <?php else: ?>
      Bem-vindo ao <?= esc(SISTEMA_NOME) ?>
    <?php endif; ?>
  </h1>
  <p class="pagina-subtitulo"><?= esc(SISTEMA_TAGLINE) ?></p>
</section>

<?php if (!$usuario): ?>
<!-- ── CTA para não logados ──────────────────────────────── -->
<div class="card animar-entrada" style="
  background: linear-gradient(135deg, rgba(124,111,255,0.15), rgba(0,229,195,0.08));
  border-color: rgba(124,111,255,0.3);
  text-align: center;
  padding: 40px 24px;
">
  <div style="font-size:3rem;margin-bottom:16px">🚀</div>
  <h2 style="font-family:var(--fonte-display);font-size:1.4rem;margin-bottom:8px">
    Sua jornada financeira começa aqui
  </h2>
  <p style="color:var(--texto-secundario);margin-bottom:24px;font-size:.9rem">
    <?= esc(SISTEMA_NOME) ?> é a plataforma inteligente para gerenciar,
    aprender e crescer financeiramente com uma comunidade ativa.
  </p>
  <button class="btn btn-primario btn-lg" data-aba="conta" onclick="navegarPara('conta')">
    Criar conta gratuita
  </button>
</div>

<!-- ── Features ─────────────────────────────────────────── -->
<div style="display:grid;gap:12px;margin-top:8px">
  <?php
  $features = [
    ['🧠', 'Mentoria Inteligente', 'Aprenda com especialistas em conteúdos exclusivos.'],
    ['💬', 'Comunidade Ativa', 'Chat em tempo real com outros membros da plataforma.'],
    ['📊', 'Ferramentas de Monitoramento', 'Acompanhe metas e alertas em tempo real.'],
    ['🔐', 'Segurança Total', 'Seus dados protegidos com criptografia e PDO.'],
  ];
  foreach ($features as [$icon, $titulo, $desc]):
  ?>
  <div class="card animar-entrada" style="display:flex;align-items:center;gap:16px;padding:16px">
    <div style="font-size:1.8rem;flex-shrink:0"><?= $icon ?></div>
    <div>
      <div style="font-weight:600;font-size:.95rem;margin-bottom:2px"><?= esc($titulo) ?></div>
      <div style="font-size:.82rem;color:var(--texto-secundario)"><?= esc($desc) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php else: ?>
<!-- ── Stats do usuário ──────────────────────────────────── -->
<div class="stat-grid">
  <?php
  $cartoes = [
    ['valor' => $stats['dias'],        'nome' => 'Dias na plataforma', 'cor' => 'var(--cor-primaria)',   'icon' => '📅'],
    ['valor' => $stats['mensagens'],   'nome' => 'Mensagens enviadas', 'cor' => 'var(--cor-secundaria)', 'icon' => '💬'],
    ['valor' => $stats['ferramentas'], 'nome' => 'Ferramentas ativas', 'cor' => 'var(--cor-acento)',    'icon' => '📊'],
    ['valor' => $stats['acoes'],       'nome' => 'Ações registradas',  'cor' => 'var(--cor-aviso)',     'icon' => '⚡'],
  ];
  foreach ($cartoes as $c):
  ?>
  <div class="stat-card animar-entrada" style="--cor-accent-local:<?= $c['cor'] ?>">
    <div class="stat-valor"><?= number_format($c['valor']) ?></div>
    <div class="stat-nome"><?= esc($c['nome']) ?></div>
    <div class="stat-icon"><?= $c['icon'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Boas-vindas / destaque ──────────────────────────── -->
<div class="card animar-entrada" style="
  background: linear-gradient(135deg, rgba(124,111,255,0.12), rgba(0,229,195,0.06));
  border-color: rgba(124,111,255,0.25);
  margin-bottom: 16px;
">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
    <div style="font-size:1.5rem">✨</div>
    <h2 style="font-family:var(--fonte-display);font-size:1.1rem;font-weight:700">
      O que há de novo no <?= esc(SISTEMA_NOME) ?>
    </h2>
  </div>
  <p style="color:var(--texto-secundario);font-size:.875rem;line-height:1.7">
    Explore as ferramentas de monitoramento, participe da comunidade e acesse
    conteúdos exclusivos de mentoria. Sua inteligência financeira cresce a cada dia aqui.
  </p>
</div>

<!-- ── Ações rápidas ─────────────────────────────────── -->
<div class="card animar-entrada">
  <div class="card-titulo">
    <svg viewBox="0 0 24 24" width="18" height="18" stroke="var(--cor-primaria)" fill="none" stroke-width="2">
      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
    </svg>
    Ações Rápidas
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
    <?php
    $acoes = [
      ['aba' => 'comunidade',  'label' => '💬 Comunidade',   'cor' => 'rgba(124,111,255,.15)'],
      ['aba' => 'ferramentas', 'label' => '🔍 Ferramentas',  'cor' => 'rgba(0,229,195,.1)'],
      ['aba' => 'mentoria',    'label' => '📞 Mentoria',     'cor' => 'rgba(255,111,176,.1)'],
      ['aba' => 'conta',       'label' => '👤 Meu Perfil',   'cor' => 'rgba(255,181,71,.1)'],
    ];
    foreach ($acoes as $a):
    ?>
    <button
      class="btn btn-vidro"
      style="background:<?= $a['cor'] ?>;justify-content:flex-start;font-size:.82rem"
      onclick="navegarPara('<?= esc($a['aba']) ?>')">
      <?= $a['label'] ?>
    </button>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── Nível / Badge ─────────────────────────────────── -->
<?php if ($usuario['nivel'] === 'adm'): ?>
<div class="alerta alerta-info animar-entrada" style="display:flex;align-items:center;gap:8px">
  <span>🛡️</span>
  <span>Você é <strong>Administrador</strong> do <?= esc(SISTEMA_NOME) ?>. Acesso total liberado.</span>
</div>
<?php endif; ?>

<?php endif; ?>