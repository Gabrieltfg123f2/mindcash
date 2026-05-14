<?php
// ============================================================
//  MindCash — Módulo: Mentoria (Área Restrita ADM)
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// ── API AJAX ──────────────────────────────────────────────────
if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    auth_requer_adm();

    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao   = sanitizar_texto($input['acao'] ?? '');
    $usuario = usuario_logado();

    if ($acao === 'criar_mentoria') {
        $titulo    = sanitizar_texto($input['titulo'] ?? '', 200);
        $descricao = sanitizar_texto($input['descricao'] ?? '', 500);
        $conteudo  = strip_tags($input['conteudo'] ?? '', '<p><b><strong><em><ul><ol><li><h2><h3><br>');
        $publicado = isset($input['publicado']) && $input['publicado'] ? 1 : 0;

        if (empty($titulo)) json_resposta(['erro' => 'Título obrigatório.'], 422);

        $id = db_executar(
            'INSERT INTO mentorias (titulo, descricao, conteudo, autor_id, publicado) VALUES (?,?,?,?,?)',
            [$titulo, $descricao, $conteudo, $usuario['id'], $publicado]
        );

        registrar_acao($usuario['id'], 'mentoria_criada', ['id' => $id]);
        json_resposta(['sucesso' => true, 'id' => $id]);
    }

    if ($acao === 'toggle_publicado') {
        $id = (int)($input['id'] ?? 0);
        db_executar('UPDATE mentorias SET publicado = NOT publicado WHERE id = ?', [$id]);
        json_resposta(['sucesso' => true]);
    }

    if ($acao === 'excluir') {
        $id = (int)($input['id'] ?? 0);
        db_executar('DELETE FROM mentorias WHERE id = ?', [$id]);
        json_resposta(['sucesso' => true]);
    }

    json_resposta(['erro' => 'Ação inválida.'], 400);
}

// ── RENDER HTML ───────────────────────────────────────────────
$usuario = usuario_logado();

// ══════════════════════════════════════════════════════════════
//  🔐 TRAVA DE ACESSO — Verifica nivel == 'adm'
//  Intrusos (membros comuns ou não logados) são bloqueados aqui.
// ══════════════════════════════════════════════════════════════
if (!$usuario) {
    // Não logado → tela de aviso com CTA
    ?>
    <div class="acesso-negado animar-entrada">
      <div class="acesso-negado-icon">
        <svg viewBox="0 0 24 24" width="32" height="32" stroke="currentColor" fill="none" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0110 0v4"/>
        </svg>
      </div>
      <h2 style="font-family:var(--fonte-display);font-size:1.3rem;font-weight:700">Área Restrita</h2>
      <p style="color:var(--texto-secundario);max-width:280px;font-size:.875rem">
        Você precisa estar logado e ter permissão de <strong>administrador</strong> para acessar a Mentoria.
      </p>
      <button class="btn btn-primario" onclick="navegarPara('conta')">Fazer Login</button>
    </div>
    <?php
    return;
}

if ($usuario['nivel'] !== 'adm') {
    // Logado, mas não é admin → acesso negado com redirect implícito
    registrar_acao($usuario['id'], 'acesso_negado_mentoria');
    ?>
    <div class="acesso-negado animar-entrada">
      <div class="acesso-negado-icon">
        <svg viewBox="0 0 24 24" width="32" height="32" stroke="currentColor" fill="none" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
        </svg>
      </div>
      <h2 style="font-family:var(--fonte-display);font-size:1.3rem;font-weight:700">Acesso Negado</h2>
      <p style="color:var(--texto-secundario);max-width:300px;font-size:.875rem">
        Esta área é exclusiva para <strong>administradores</strong> do <?= esc(SISTEMA_NOME) ?>.
        Sua tentativa de acesso foi registrada.
      </p>
      <div style="display:flex;gap:8px">
        <button class="btn btn-vidro" onclick="navegarPara('inicio')">← Voltar ao Início</button>
      </div>
      <p style="font-size:.72rem;color:var(--texto-terciario)">
        Código de erro: 403 — Forbidden
      </p>
    </div>
    <?php
    return;
}

// ══════════════════════════════════════════════════════════════
//  ✅ ÁREA DO ADMINISTRADOR — nivel == 'adm'
// ══════════════════════════════════════════════════════════════

try {
    $mentorias = db_buscar(
        'SELECT m.*, u.nome AS autor_nome
         FROM mentorias m
         JOIN usuarios u ON u.id = m.autor_id
         ORDER BY m.criado_em DESC',
        []
    );
} catch (Throwable) {
    $mentorias = [];
}

$csrf = csrf_gerar();
?>

<section class="pagina-header animar-entrada">
  <h1 class="pagina-titulo">Mentoria</h1>
  <p class="pagina-subtitulo" style="display:flex;align-items:center;gap:8px">
    <span class="badge badge-adm">🛡️ Painel ADM</span>
    Gerencie conteúdos de mentoria
  </p>
</section>

<!-- ── Criar nova mentoria ───────────────────────────────── -->
<div class="card animar-entrada">
  <div class="card-titulo">
    <svg viewBox="0 0 24 24" width="18" height="18" stroke="var(--cor-secundaria)" fill="none" stroke-width="2">
      <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
      <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
    </svg>
    Nova Mentoria
  </div>

  <form id="form-mentoria" onsubmit="criarMentoria(event)">
    <div class="campo-grupo">
      <label class="campo-label">Título</label>
      <input type="text" name="titulo" class="campo-input" placeholder="Título do conteúdo" required maxlength="200">
    </div>

    <div class="campo-grupo">
      <label class="campo-label">Descrição curta</label>
      <input type="text" name="descricao" class="campo-input" placeholder="Resumo em uma linha" maxlength="500">
    </div>

    <div class="campo-grupo">
      <label class="campo-label">Conteúdo</label>
      <textarea name="conteudo" class="campo-input" rows="5" placeholder="Escreva o conteúdo da mentoria…"></textarea>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.875rem;color:var(--texto-secundario)">
        <input type="checkbox" name="publicado" value="1" style="accent-color:var(--cor-primaria)">
        Publicar imediatamente
      </label>
    </div>

    <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
    <button type="submit" class="btn btn-primario">
      Publicar Mentoria
    </button>
  </form>
</div>

<!-- ── Lista de mentorias ────────────────────────────────── -->
<div class="card-titulo" style="margin-bottom:12px;padding:0 4px">
  <svg viewBox="0 0 24 24" width="18" height="18" stroke="var(--cor-primaria)" fill="none" stroke-width="2">
    <path d="M22 16.92v3a2 2 0 01-2.18 2A19.79 19.79 0 018.63 18.9 19.45 19.45 0 01.4 10.68a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .18h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 8a16 16 0 006.29 6.29l1.14-1.14a2 2 0 012.11-.45c.9.34 1.84.57 2.81.7a2 2 0 011.72 2z"/>
  </svg>
  Conteúdos Publicados (<?= count($mentorias) ?>)
</div>

<?php if (empty($mentorias)): ?>
  <div class="card animar-entrada" style="text-align:center;padding:40px;color:var(--texto-terciario)">
    Nenhuma mentoria criada ainda.
  </div>
<?php else: ?>
  <div class="mentoria-grid">
    <?php foreach ($mentorias as $m): ?>
      <div class="mentoria-card animar-entrada">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
          <span class="badge <?= $m['publicado'] ? 'badge-acento' : 'badge-aviso' ?>">
            <?= $m['publicado'] ? '● Publicado' : '○ Rascunho' ?>
          </span>
          <div style="display:flex;gap:6px">
            <button
              class="btn btn-sm btn-vidro"
              onclick="toggleMentoria(<?= $m['id'] ?>, this)"
              title="<?= $m['publicado'] ? 'Despublicar' : 'Publicar' ?>">
              <?= $m['publicado'] ? '⊘' : '✓' ?>
            </button>
            <button
              class="btn btn-sm btn-perigo"
              onclick="excluirMentoria(<?= $m['id'] ?>, this)"
              title="Excluir">
              🗑
            </button>
          </div>
        </div>

        <h3 style="font-family:var(--fonte-display);font-size:1rem;font-weight:700;margin-bottom:4px">
          <?= esc($m['titulo']) ?>
        </h3>

        <?php if ($m['descricao']): ?>
          <p style="font-size:.8rem;color:var(--texto-secundario);margin-bottom:8px;line-height:1.5">
            <?= esc($m['descricao']) ?>
          </p>
        <?php endif; ?>

        <div style="font-size:.7rem;color:var(--texto-terciario);display:flex;justify-content:space-between">
          <span>Por <?= esc($m['autor_nome']) ?></span>
          <span><?= date('d/m/Y', strtotime($m['criado_em'])) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
async function criarMentoria(e) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Publicando…';

  const dados = new FormData(form);
  try {
    const resp = await fetchJSON('/modules/mentoria.php', {
      method: 'POST',
      body: JSON.stringify({
        acao: 'criar_mentoria',
        titulo: dados.get('titulo'),
        descricao: dados.get('descricao'),
        conteudo: dados.get('conteudo'),
        publicado: dados.has('publicado') ? 1 : 0,
        csrf_token: dados.get('csrf_token'),
      })
    });

    if (resp.sucesso) {
      mostrarToast('Mentoria publicada!', 'sucesso');
      form.reset();
      setTimeout(() => navegarPara('mentoria'), 600);
    } else {
      mostrarToast(resp.erro || 'Erro.', 'erro');
    }
  } catch {
    mostrarToast('Erro de conexão.', 'erro');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Publicar Mentoria';
  }
}

async function toggleMentoria(id, btn) {
  btn.disabled = true;
  try {
    await fetchJSON('/modules/mentoria.php', {
      method: 'POST',
      body: JSON.stringify({ acao: 'toggle_publicado', id })
    });
    mostrarToast('Status atualizado.', 'sucesso');
    setTimeout(() => navegarPara('mentoria'), 400);
  } catch {
    mostrarToast('Erro.', 'erro');
    btn.disabled = false;
  }
}

async function excluirMentoria(id, btn) {
  if (!confirm('Excluir esta mentoria permanentemente?')) return;
  btn.disabled = true;
  try {
    const resp = await fetchJSON('/modules/mentoria.php', {
      method: 'POST',
      body: JSON.stringify({ acao: 'excluir', id })
    });
    if (resp.sucesso) {
      btn.closest('.mentoria-card').remove();
      mostrarToast('Excluída.', 'sucesso');
    }
  } catch {
    mostrarToast('Erro.', 'erro');
    btn.disabled = false;
  }
}
</script>