<?php
/**
 * MindCash — Módulo: Conta
 * Edição de perfil, upload de avatar, histórico de atividades.
 */
require_once __DIR__ . '/../.gitignore/config/config.php';
require_once __DIR__ . '/../.gitignore/config/db.php';
require_once __DIR__ . '/../config/auth.php';

iniciarSessao();
$usuario = usuarioAtual();
$action  = $_GET['action'] ?? '';
$erro    = '';
$sucesso = '';

/* ── Atualizar perfil ───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'atualizar') {
    exigirCsrf();

    $nome  = trim($_POST['nome'] ?? '');
    $bio   = trim($_POST['bio'] ?? '');
    $meta  = (float)($_POST['meta_mensal'] ?? 0);
    $tema  = in_array($_POST['tema'] ?? '', ['dark', 'light']) ? $_POST['tema'] : 'dark';

    if (empty($nome)) {
        $erro = 'O nome não pode estar vazio.';
    } elseif (mb_strlen($nome) > 120) {
        $erro = 'Nome muito longo (máx. 120 caracteres).';
    } else {
        // Upload de avatar
        $avatarUrl = $usuario['avatar_url'] ?? null;
        if (!empty($_FILES['avatar']['tmp_name'])) {
            $file     = $_FILES['avatar'];
            $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
            $mime     = mime_content_type($file['tmp_name']);

            if (!in_array($mime, $allowed)) {
                $erro = 'Tipo de imagem inválido. Use JPG, PNG ou WebP.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $erro = 'Avatar deve ter no máximo 2 MB.';
            } else {
                $ext       = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
                $filename  = 'avatar_' . $usuario['id'] . '_' . time() . '.' . $ext;
                $destDir   = BASE_PATH . '/assets/images/avatars/';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                $destPath  = $destDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $avatarUrl = BASE_URL . '/assets/images/avatars/' . $filename;
                } else {
                    $erro = 'Não foi possível salvar o avatar.';
                }
            }
        }

        if (empty($erro)) {
            dbQuery(
                "UPDATE usuarios SET nome = ?, avatar_url = ? WHERE id = ?",
                [strip_tags($nome), $avatarUrl, $usuario['id']]
            );
            dbQuery(
                "INSERT INTO perfis (usuario_id, bio, meta_mensal, tema)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE bio = VALUES(bio), meta_mensal = VALUES(meta_mensal), tema = VALUES(tema)",
                [$usuario['id'], strip_tags($bio), $meta, $tema]
            );

            // Atualiza sessão
            $_SESSION['usuario']['nome']       = strip_tags($nome);
            $_SESSION['usuario']['avatar_url'] = $avatarUrl;
            $usuario = usuarioAtual();

            registrarAtividade($usuario['id'], 'perfil_atualizado');
            $sucesso = 'Perfil atualizado com sucesso!';
        }
    }
}

/* ── Carregar perfil e atividades ────────────────────────────── */
if ($usuario) {
    $perfil = dbQuery(
        "SELECT * FROM perfis WHERE usuario_id = ?",
        [$usuario['id']]
    )->fetch();

    $atividades = dbQuery(
        "SELECT acao, criado_em, ip FROM atividades
         WHERE usuario_id = ?
         ORDER BY criado_em DESC
         LIMIT 20",
        [$usuario['id']]
    )->fetchAll();

    $totalMsgs = dbQuery(
        "SELECT COUNT(*) AS qtd FROM mensagens WHERE usuario_id = ? AND deletado = 0",
        [$usuario['id']]
    )->fetchColumn();
} else {
    $perfil = [];
    $atividades = [];
    $totalMsgs = 0;
}

$nomeInicial = strtoupper(mb_substr($usuario['nome'] ?? 'A', 0, 1));
?>

<div class="fade-in">

  <?php if ($erro):    echo "<div class='alert alert-danger'>{$erro}</div>";   endif; ?>
  <?php if ($sucesso): echo "<div class='alert alert-success'>{$sucesso}</div>"; endif; ?>

  <?php if ($usuario): ?>
  <!-- Cabeçalho do perfil -->
  <div class="profile-header">
    <div class="profile-avatar-wrap">
      <div class="profile-avatar">
        <?php if ($usuario['avatar_url']): ?>
          <img id="avatar-preview" src="<?= xss($usuario['avatar_url']) ?>" alt="Avatar">
        <?php else: ?>
          <span id="avatar-initials"><?= xss($nomeInicial) ?></span>
          <img id="avatar-preview" src="" alt="" style="display:none">
        <?php endif; ?>
      </div>
      <label class="avatar-edit-btn" for="avatar-input" title="Alterar foto">
        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      </label>
    </div>
    <div class="profile-info">
      <h2><?= xss($usuario['nome']) ?></h2>
      <?php if (!empty($usuario['email'])): ?>
        <p style="font-size:13px;color:var(--text-muted)"><?= xss($usuario['email']) ?></p>
      <?php endif; ?>
      <span class="nivel-badge <?= $usuario['nivel'] === 'adm' ? 'adm' : '' ?>">
        <?= $usuario['nivel'] === 'adm' ? '⭐ Administrador' : xss(ucfirst($usuario['nivel'])) ?>
      </span>
    </div>
  </div>

  <!-- Mini stats -->
  <div class="stats-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:24px">
    <div class="stat-card">
      <div class="stat-label">Mensagens</div>
      <div class="stat-value accent"><?= number_format((int)$totalMsgs) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Meta Mensal</div>
      <div class="stat-value success">
        R$ <?= number_format((float)($perfil['meta_mensal'] ?? 0), 2, ',', '.') ?>
      </div>
    </div>
  </div>

  <!-- Formulário de edição -->
  <div class="card" style="margin-bottom:24px">
    <h3 style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:16px">
      ✏️ Editar Perfil
    </h3>
    <form method="POST" action="index.php?mod=conta&action=atualizar" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= xss($csrfToken ?? gerarCsrfToken()) ?>">
      <input type="file" name="avatar" id="avatar-input" accept="image/*" style="display:none">

      <div class="form-group">
        <label class="form-label">Nome *</label>
        <input class="form-input" type="text" name="nome" maxlength="120" required
               value="<?= xss($usuario['nome']) ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Bio</label>
        <textarea class="form-textarea" name="bio" maxlength="500"
                  placeholder="Conte um pouco sobre você…"><?= xss($perfil['bio'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Meta Mensal (R$)</label>
        <input class="form-input" type="number" name="meta_mensal" min="0" step="0.01"
               value="<?= number_format((float)($perfil['meta_mensal'] ?? 0), 2, '.', '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Tema</label>
        <select class="form-select" name="tema">
          <option value="dark"  <?= ($perfil['tema'] ?? 'dark') === 'dark'  ? 'selected' : '' ?>>🌑 Dark</option>
          <option value="light" <?= ($perfil['tema'] ?? 'dark') === 'light' ? 'selected' : '' ?>>☀️ Light</option>
        </select>
      </div>

      <button class="btn btn-primary btn-full" type="submit">Salvar alterações</button>
    </form>
  </div>

  <!-- Login social -->
  <?php if (($usuario['nivel'] ?? '') === 'anonimo'): ?>
  <div class="card" style="margin-bottom:24px">
    <h3 style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:6px">🔐 Criar conta completa</h3>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
      Você está como anônimo. Crie uma conta para salvar seu histórico.
    </p>
    <?php if (GOOGLE_CLIENT_ID): ?>
      <a href="<?= xss(urlGoogleOAuth()) ?>" class="btn btn-google">
        <svg width="18" height="18" viewBox="0 0 48 48">
          <path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.4 29.3 35 24 35c-6.1 0-11-4.9-11-11s4.9-11 11-11c2.8 0 5.3 1 7.2 2.8l5.7-5.7C33.5 7.1 29 5 24 5 12.9 5 4 13.9 4 25s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.5-.4-3.9z"/>
          <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 16.2 19 13 24 13c2.8 0 5.3 1 7.2 2.8l5.7-5.7C33.5 7.1 29 5 24 5 16.3 5 9.7 9 6.3 14.7z"/>
          <path fill="#4CAF50" d="M24 45c4.9 0 9.3-1.9 12.7-4.9l-5.9-5c-1.7 1.2-3.9 2-6.8 2-5.2 0-9.6-3.5-11.2-8.3l-6.6 5.1C9.5 41 16.2 45 24 45z"/>
          <path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.1-4.1 5.4l5.9 5C41.2 35.5 44 30.7 44 25c0-1.3-.1-2.5-.4-3.9z"/>
        </svg>
        Continuar com Google
      </a>
    <?php else: ?>
      <div class="alert alert-info">Configure as credenciais do Google OAuth em <code>config/config.php</code>.</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Histórico de atividades -->
  <div class="card">
    <h3 style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:14px">
      📋 Histórico de Atividades
    </h3>
    <?php if (!empty($atividades)): ?>
      <div class="activity-list">
        <?php foreach ($atividades as $a): ?>
          <div class="activity-item">
            <div class="activity-dot"></div>
            <span><?= xss($a['acao']) ?></span>
            <?php if ($a['ip']): ?>
              <span style="font-size:11px;color:var(--text-muted)"><?= xss($a['ip']) ?></span>
            <?php endif; ?>
            <span class="activity-time"><?= date('d/m H:i', strtotime($a['criado_em'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="font-size:13px;color:var(--text-muted)">Nenhuma atividade registrada ainda.</p>
    <?php endif; ?>
  </div>

  <!-- Logout -->
  <div style="margin-top:24px;text-align:center">
    <form method="POST" action="index.php?mod=conta&action=logout">
      <input type="hidden" name="csrf_token" value="<?= xss($csrfToken ?? gerarCsrfToken()) ?>">
      <button class="btn btn-outline" type="submit" style="color:var(--danger);border-color:var(--danger)">
        Sair da conta
      </button>
    </form>
  </div>

  <?php else: ?>
    <div class="alert alert-info">Você não está logado.</div>
  <?php endif; ?>
</div>
