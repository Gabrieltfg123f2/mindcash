<?php
/**
 * MindCash — Módulo: Comunidade
 * Chat em tempo real via Fetch API (polling leve).
 */
require_once __DIR__ . '/../.gitignore/config/config.php';
require_once __DIR__ . '/../.gitignore/config/db.php';
require_once __DIR__ . '/../config/auth.php';

iniciarSessao();

$usuario = usuarioAtual();
if (!$usuario) { loginAnonimo(); $usuario = usuarioAtual(); }

$action  = $_GET['action'] ?? '';
$ehAjax  = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

/* ══════════════════════════════════════════════════════════
   AJAX: Enviar mensagem
   ══════════════════════════════════════════════════════════ */
if ($ehAjax && $action === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $texto = trim($body['mensagem'] ?? '');

    if (empty($texto)) {
        echo json_encode(['erro' => 'Mensagem vazia.']);
        exit;
    }

    if (mb_strlen($texto) > MAX_MSG_LEN) {
        echo json_encode(['erro' => 'Mensagem muito longa.']);
        exit;
    }

    // Sanitiza — texto puro, sem HTML
    $texto = strip_tags($texto);

    dbQuery(
        "INSERT INTO mensagens (usuario_id, conteudo) VALUES (?, ?)",
        [$usuario['id'], $texto]
    );
    $novoId = getDB()->lastInsertId();

    registrarAtividade($usuario['id'], 'mensagem_enviada', ['id' => $novoId]);
    echo json_encode(['ok' => true, 'id' => $novoId]);
    exit;
}

/* ══════════════════════════════════════════════════════════
   AJAX: Listar mensagens (polling)
   ══════════════════════════════════════════════════════════ */
if ($ehAjax && $action === 'listar') {
    $desde = max(0, (int)($_GET['desde'] ?? 0));

    $msgs = dbQuery(
        "SELECT m.id, m.conteudo, m.criado_em,
                u.nome, u.avatar_url,
                (u.id = ?) AS minha
         FROM mensagens m
         JOIN usuarios u ON u.id = m.usuario_id
         WHERE m.deletado = 0 AND m.id > ?
         ORDER BY m.id ASC
         LIMIT 50",
        [$usuario['id'], $desde]
    )->fetchAll();

    echo json_encode(['mensagens' => $msgs]);
    exit;
}

/* ══════════════════════════════════════════════════════════
   VIEW: Interface do Chat
   ══════════════════════════════════════════════════════════ */

// Carrega últimas 60 mensagens para a view inicial
$mensagens = dbQuery(
    "SELECT m.id, m.conteudo, m.criado_em,
            u.nome, u.avatar_url,
            (u.id = ?) AS minha
     FROM mensagens m
     JOIN usuarios u ON u.id = m.usuario_id
     WHERE m.deletado = 0
     ORDER BY m.id DESC
     LIMIT 60",
    [$usuario['id']]
)->fetchAll();

$mensagens   = array_reverse($mensagens);
$ultimoId    = !empty($mensagens) ? (int) end($mensagens)['id'] : 0;
?>

<div class="fade-in">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <div>
      <h2 style="font-family:var(--font-display);font-size:22px;font-weight:800">
        💬 Comunidade
      </h2>
      <p style="font-size:13px;color:var(--text-muted)">
        Chat em tempo real do <?= xss(NOME_SISTEMA) ?>
      </p>
    </div>
    <div style="font-size:12px;color:var(--text-muted);text-align:right">
      Logado como<br>
      <strong style="color:var(--accent)"><?= xss($usuario['nome']) ?></strong>
    </div>
  </div>

  <div class="chat-wrap">
    <!-- Mensagens -->
    <div class="chat-messages" id="chat-messages">
      <?php foreach ($mensagens as $msg): ?>
        <?php
        $mine   = (bool) $msg['minha'];
        $ini    = strtoupper(mb_substr($msg['nome'] ?? 'A', 0, 1));
        ?>
        <div class="msg-bubble <?= $mine ? 'mine' : '' ?>" data-id="<?= (int)$msg['id'] ?>">
          <div class="msg-avatar">
            <?php if ($msg['avatar_url']): ?>
              <img src="<?= xss($msg['avatar_url']) ?>" alt="">
            <?php else: ?>
              <?= $ini ?>
            <?php endif; ?>
          </div>
          <div class="msg-body">
            <div class="msg-meta">
              <strong><?= xss($msg['nome'] ?? 'Anônimo') ?></strong>
              <span><?= xss(date('H:i', strtotime($msg['criado_em']))) ?></span>
            </div>
            <div class="msg-text"><?= xss($msg['conteudo']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Input de envio -->
    <form class="chat-input-bar" id="chat-form" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= xss($csrfToken ?? '') ?>">
      <input
        class="form-input"
        id="chat-input"
        type="text"
        placeholder="Escreva uma mensagem…"
        maxlength="<?= MAX_MSG_LEN ?>"
        autocomplete="off"
        required>
      <button class="btn btn-primary" id="chat-send" type="submit">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="22" y1="2" x2="11" y2="13"/>
          <polygon points="22 2 15 22 11 13 2 9 22 2"/>
        </svg>
      </button>
    </form>
  </div>
</div>

<script>
  // Passa o último ID carregado pelo PHP para o JS
  window.__chatLastId = <?= $ultimoId ?>;
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof chatLastId !== 'undefined') chatLastId = window.__chatLastId;
    const el = document.getElementById('chat-messages');
    if (el) el.scrollTop = el.scrollHeight;
  });
</script>
