<?php
/**
 * MindCash — Módulo: Mentoria (RESTRITO: apenas ADM)
 */
require_once __DIR__ . '/../.gitignore/config/config.php';
require_once __DIR__ . '/../.gitignore/config/db.php';
require_once __DIR__ . '/../config/auth.php';

iniciarSessao();

// ── Trava de segurança ───────────────────────────────────────
exigirAdmin();  // redireciona para /inicio se não for ADM

$usuario = usuarioAtual();
$action  = $_GET['action'] ?? '';
$erro    = '';
$sucesso = '';

/* ── Criar tópico de mentoria ───────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'criar_topico') {
    exigirCsrf();
    $titulo    = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $publicado = isset($_POST['publicado']) ? 1 : 0;

    if (empty($titulo)) {
        $erro = 'O título é obrigatório.';
    } else {
        dbQuery(
            "INSERT INTO topicos_mentoria (titulo, descricao, adm_id, publicado) VALUES (?, ?, ?, ?)",
            [strip_tags($titulo), strip_tags($descricao), $usuario['id'], $publicado]
        );
        registrarAtividade($usuario['id'], 'topico_criado', ['titulo' => $titulo]);
        $sucesso = 'Tópico criado com sucesso!';
    }
}

/* ── Deletar mensagem (moderação) ──────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'deletar_msg') {
    exigirCsrf();
    $msgId = (int)($_POST['msg_id'] ?? 0);
    if ($msgId > 0) {
        dbQuery("UPDATE mensagens SET deletado = 1 WHERE id = ?", [$msgId]);
        registrarAtividade($usuario['id'], 'msg_deletada', ['msg_id' => $msgId]);
        $sucesso = 'Mensagem removida.';
    }
}

/* ── Dados do painel ─────────────────────────────────────────── */
$statsAdm = dbQuery("
    SELECT
        (SELECT COUNT(*) FROM usuarios WHERE ativo = 1) AS usuarios,
        (SELECT COUNT(*) FROM mensagens WHERE deletado = 0) AS mensagens,
        (SELECT COUNT(*) FROM topicos_mentoria) AS topicos,
        (SELECT COUNT(*) FROM usuarios WHERE nivel = 'adm') AS admins
")->fetch();

$topicos = dbQuery(
    "SELECT t.*, u.nome AS adm_nome
     FROM topicos_mentoria t
     JOIN usuarios u ON u.id = t.adm_id
     ORDER BY t.criado_em DESC
     LIMIT 20"
)->fetchAll();

$ultimasMsgs = dbQuery(
    "SELECT m.id, m.conteudo, m.criado_em, m.deletado, u.nome
     FROM mensagens m
     JOIN usuarios u ON u.id = m.usuario_id
     ORDER BY m.id DESC
     LIMIT 20"
)->fetchAll();
?>

<div class="fade-in">
  <!-- Cabeçalho admin -->
  <div class="admin-header">
    <div class="admin-icon">⭐</div>
    <div>
      <h2><?= xss(NOME_SISTEMA) ?> — Painel de Mentoria</h2>
      <p>Acesso restrito · Você está logado como <strong><?= xss($usuario['nome']) ?></strong></p>
    </div>
  </div>

  <?php if ($erro):    echo "<div class='alert alert-danger'>{$erro}</div>";   endif; ?>
  <?php if ($sucesso): echo "<div class='alert alert-success'>{$sucesso}</div>"; endif; ?>

  <!-- Métricas -->
  <div class="admin-grid" style="margin-bottom:24px">
    <div class="admin-stat">
      <div class="value"><?= number_format((int)($statsAdm['usuarios'] ?? 0)) ?></div>
      <div class="label">Usuários</div>
    </div>
    <div class="admin-stat">
      <div class="value"><?= number_format((int)($statsAdm['mensagens'] ?? 0)) ?></div>
      <div class="label">Mensagens</div>
    </div>
    <div class="admin-stat">
      <div class="value"><?= number_format((int)($statsAdm['topicos'] ?? 0)) ?></div>
      <div class="label">Tópicos</div>
    </div>
    <div class="admin-stat">
      <div class="value"><?= number_format((int)($statsAdm['admins'] ?? 0)) ?></div>
      <div class="label">Admins</div>
    </div>
  </div>

  <!-- Criar tópico -->
  <div class="card" style="margin-bottom:24px">
    <h3 style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:16px">
      ✏️ Criar Tópico de Mentoria
    </h3>
    <form method="POST" action="index.php?mod=mentoria&action=criar_topico">
      <input type="hidden" name="csrf_token" value="<?= xss($csrfToken ?? gerarCsrfToken()) ?>">
      <div class="form-group">
        <label class="form-label">Título *</label>
        <input class="form-input" type="text" name="titulo" maxlength="255" required placeholder="Ex: Como sair das dívidas em 2025">
      </div>
      <div class="form-group">
        <label class="form-label">Descrição</label>
        <textarea class="form-textarea" name="descricao" maxlength="2000" placeholder="Descreva o conteúdo do tópico…"></textarea>
      </div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
        <input type="checkbox" name="publicado" id="publicado" value="1" style="accent-color:var(--accent)">
        <label for="publicado" style="font-size:14px;cursor:pointer">Publicar imediatamente</label>
      </div>
      <button class="btn btn-primary" type="submit">Criar Tópico</button>
    </form>
  </div>

  <!-- Tópicos existentes -->
  <?php if (!empty($topicos)): ?>
  <div class="card" style="margin-bottom:24px">
    <h3 style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:14px">
      📚 Tópicos Cadastrados
    </h3>
    <div class="activity-list">
      <?php foreach ($topicos as $t): ?>
        <div class="activity-item">
          <div class="activity-dot" style="background:<?= $t['publicado'] ? 'var(--success)' : 'var(--text-muted)' ?>"></div>
          <div style="flex:1">
            <strong><?= xss($t['titulo']) ?></strong>
            <?php if ($t['descricao']): ?>
              <p style="font-size:12px;color:var(--text-muted);margin-top:2px">
                <?= xss(mb_strimwidth($t['descricao'], 0, 100, '…')) ?>
              </p>
            <?php endif; ?>
          </div>
          <span style="font-size:11px;color:<?= $t['publicado'] ? 'var(--success)' : 'var(--text-muted)' ?>">
            <?= $t['publicado'] ? 'Publicado' : 'Rascunho' ?>
          </span>
          <span class="activity-time"><?= date('d/m/Y', strtotime($t['criado_em'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Moderação de mensagens -->
  <div class="card">
    <h3 style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:14px">
      🛡️ Moderação de Mensagens
    </h3>
    <div class="activity-list">
      <?php foreach ($ultimasMsgs as $msg): ?>
        <div class="activity-item" style="<?= $msg['deletado'] ? 'opacity:.4' : '' ?>">
          <div class="activity-dot" style="background:<?= $msg['deletado'] ? 'var(--danger)' : 'var(--accent)' ?>"></div>
          <div style="flex:1">
            <strong style="font-size:12px"><?= xss($msg['nome']) ?></strong>
            <p style="font-size:13px"><?= xss(mb_strimwidth($msg['conteudo'], 0, 120, '…')) ?></p>
          </div>
          <?php if (!$msg['deletado']): ?>
            <form method="POST" action="index.php?mod=mentoria&action=deletar_msg"
                  onsubmit="return confirm('Remover esta mensagem?')">
              <input type="hidden" name="csrf_token" value="<?= xss($csrfToken ?? gerarCsrfToken()) ?>">
              <input type="hidden" name="msg_id" value="<?= (int)$msg['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">Remover</button>
            </form>
          <?php else: ?>
            <span style="font-size:11px;color:var(--danger)">Removida</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (empty($ultimasMsgs)): ?>
        <p style="font-size:13px;color:var(--text-muted)">Nenhuma mensagem ainda.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
