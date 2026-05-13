<?php
/**
 * MindCash — Módulo: Ferramentas / Pesquisa
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

iniciarSessao();
$usuario = usuarioAtual();
$ehAjax  = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
$action  = $_GET['action'] ?? '';

/* ══════════════════════════════════════════════════════════
   AJAX: Busca fulltext de mensagens
   ══════════════════════════════════════════════════════════ */
if ($ehAjax && $action === 'buscar') {
    $q = trim($_GET['q'] ?? '');

    if (strlen($q) < 2) {
        echo json_encode(['resultados' => []]);
        exit;
    }

    // Busca fulltext + fallback LIKE para termos curtos
    $resultados = dbQuery(
        "SELECT m.id, m.conteudo, m.criado_em, u.nome
         FROM mensagens m
         JOIN usuarios u ON u.id = m.usuario_id
         WHERE m.deletado = 0
           AND (MATCH(m.conteudo) AGAINST(? IN BOOLEAN MODE)
                OR m.conteudo LIKE ?)
         ORDER BY m.criado_em DESC
         LIMIT 20",
        [$q . '*', '%' . $q . '%']
    )->fetchAll();

    echo json_encode(['resultados' => $resultados]);
    exit;
}

/* ══════════════════════════════════════════════════════════
   Dados de monitoria
   ══════════════════════════════════════════════════════════ */
$stats = dbQuery("
    SELECT
        (SELECT COUNT(*) FROM usuarios WHERE ativo = 1) AS total_usuarios,
        (SELECT COUNT(*) FROM usuarios WHERE nivel = 'anonimo') AS anonimos,
        (SELECT COUNT(*) FROM mensagens WHERE deletado = 0) AS total_msgs,
        (SELECT COUNT(*) FROM mensagens WHERE deletado = 0 AND DATE(criado_em) = CURDATE()) AS msgs_hoje
")->fetch();

// Membros mais ativos (top 5)
$top_usuarios = dbQuery("
    SELECT u.nome, u.avatar_url, u.nivel, COUNT(m.id) AS qtd_msgs
    FROM usuarios u
    LEFT JOIN mensagens m ON m.usuario_id = u.id AND m.deletado = 0
    GROUP BY u.id
    ORDER BY qtd_msgs DESC
    LIMIT 5
")->fetchAll();

// Atividade por hora hoje
$atividade_hora = dbQuery("
    SELECT HOUR(criado_em) AS hora, COUNT(*) AS qtd
    FROM mensagens
    WHERE deletado = 0 AND DATE(criado_em) = CURDATE()
    GROUP BY hora
    ORDER BY hora
")->fetchAll();
?>

<div class="fade-in">
  <div style="margin-bottom:24px">
    <h2 style="font-family:var(--font-display);font-size:22px;font-weight:800">
      🔧 Ferramentas
    </h2>
    <p style="font-size:13px;color:var(--text-muted)">
      Pesquise mensagens e acompanhe os dados do <?= xss(NOME_SISTEMA) ?>
    </p>
  </div>

  <!-- Busca de mensagens -->
  <div class="card" style="margin-bottom:20px">
    <h3 style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:14px">
      🔍 Buscar mensagens
    </h3>
    <div class="search-bar">
      <svg class="search-icon" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input
        class="form-input"
        id="busca-input"
        type="search"
        placeholder="Digite para pesquisar nas mensagens…"
        autocomplete="off">
    </div>
    <div id="busca-resultados" class="activity-list"></div>
  </div>

  <!-- Stats de monitoria -->
  <h3 style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:12px">
    📊 Monitoria
  </h3>
  <div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card">
      <div class="stat-label">Total Usuários</div>
      <div class="stat-value accent"><?= number_format((int)($stats['total_usuarios'] ?? 0)) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Anônimos</div>
      <div class="stat-value"><?= number_format((int)($stats['anonimos'] ?? 0)) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Mensagens</div>
      <div class="stat-value accent"><?= number_format((int)($stats['total_msgs'] ?? 0)) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Mensagens Hoje</div>
      <div class="stat-value success"><?= number_format((int)($stats['msgs_hoje'] ?? 0)) ?></div>
    </div>
  </div>

  <!-- Top usuários -->
  <div class="card" style="margin-bottom:20px">
    <h3 style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:14px">
      🏆 Membros mais ativos
    </h3>
    <div class="activity-list">
      <?php foreach ($top_usuarios as $i => $u): ?>
        <div class="activity-item">
          <div style="font-size:16px;min-width:24px;text-align:center">
            <?= ['🥇','🥈','🥉','4️⃣','5️⃣'][$i] ?? ($i + 1) . 'º' ?>
          </div>
          <div style="flex:1">
            <strong><?= xss($u['nome']) ?></strong>
            <span style="font-size:11px;color:var(--text-muted);margin-left:8px"><?= xss($u['nivel']) ?></span>
          </div>
          <span style="font-size:13px;color:var(--accent);font-weight:600">
            <?= (int)$u['qtd_msgs'] ?> msg<?= $u['qtd_msgs'] != 1 ? 's' : '' ?>
          </span>
        </div>
      <?php endforeach; ?>
      <?php if (empty($top_usuarios)): ?>
        <p style="font-size:13px;color:var(--text-muted)">Nenhuma atividade registrada ainda.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Atividade por hora (bar simples) -->
  <?php if (!empty($atividade_hora)): ?>
  <div class="card">
    <h3 style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:16px">
      ⏱ Mensagens por hora hoje
    </h3>
    <?php
    $maxQtd = max(array_column($atividade_hora, 'qtd'));
    foreach ($atividade_hora as $h):
        $pct = $maxQtd > 0 ? round(($h['qtd'] / $maxQtd) * 100) : 0;
    ?>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
      <span style="font-size:12px;color:var(--text-muted);min-width:32px"><?= sprintf('%02d', $h['hora']) ?>h</span>
      <div style="flex:1;background:var(--bg-input);border-radius:99px;height:8px;overflow:hidden">
        <div style="width:<?= $pct ?>%;background:var(--accent);height:100%;border-radius:99px;transition:.4s"></div>
      </div>
      <span style="font-size:12px;color:var(--text-muted);min-width:30px;text-align:right"><?= (int)$h['qtd'] ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
