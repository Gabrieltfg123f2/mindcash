<?php
// ============================================================
//  MindCash — Módulo: Ferramentas (Busca + Monitoramento)
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// ── API AJAX ──────────────────────────────────────────────────
if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    auth_requer();
    $input   = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao    = sanitizar_texto($input['acao'] ?? '');
    $usuario = usuario_logado();

    if ($acao === 'criar_item') {
        $titulo     = sanitizar_texto($input['titulo'] ?? '', 200);
        $tipo       = in_array($input['tipo'] ?? '', ['meta','alerta','nota'], true) ? $input['tipo'] : 'nota';
        $valor      = is_numeric($input['valor'] ?? '') ? (float)$input['valor'] : null;
        $meta_valor = is_numeric($input['meta_valor'] ?? '') ? (float)$input['meta_valor'] : null;
        $cor        = preg_match('/^#[0-9A-Fa-f]{6}$/', $input['cor'] ?? '') ? $input['cor'] : '#6C63FF';

        if (empty($titulo)) json_resposta(['erro' => 'Título obrigatório.'], 422);

        $id = db_executar(
            'INSERT INTO ferramentas_dados (usuario_id, tipo, titulo, valor, meta_valor, cor) VALUES (?,?,?,?,?,?)',
            [$usuario['id'], $tipo, $titulo, $valor, $meta_valor, $cor]
        );

        registrar_acao($usuario['id'], 'ferramenta_criada', ['id' => $id, 'tipo' => $tipo]);
        json_resposta(['sucesso' => true, 'id' => $id]);
    }

    if ($acao === 'excluir_item') {
        $id = (int)($input['id'] ?? 0);
        db_executar(
            'DELETE FROM ferramentas_dados WHERE id = ? AND usuario_id = ?',
            [$id, $usuario['id']]
        );
        json_resposta(['sucesso' => true]);
    }

    json_resposta(['erro' => 'Ação inválida.'], 400);
}

// ── RENDER HTML ───────────────────────────────────────────────
$usuario = usuario_logado();
$itens   = [];

if ($usuario) {
    try {
        $itens = db_buscar(
            'SELECT * FROM ferramentas_dados WHERE usuario_id = ? AND ativo = 1 ORDER BY criado_em DESC',
            [$usuario['id']]
        );
    } catch (Throwable) {}
}

$csrf = csrf_gerar();
$tipos_label = ['meta' => '🎯 Meta', 'alerta' => '🔔 Alerta', 'nota' => '📝 Nota'];
?>

<section class="pagina-header animar-entrada">
  <h1 class="pagina-titulo">Ferramentas</h1>
  <p class="pagina-subtitulo">Monitore metas e configure alertas personalizados</p>
</section>

<!-- ── Barra de busca ─────────────────────────────────────── -->
<div class="busca-container animar-entrada">
  <svg class="busca-icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
  </svg>
  <input
    type="text"
    id="busca-input"
    class="campo-input busca-input"
    placeholder="Buscar ferramentas…"
    autocomplete="off">
</div>

<?php if (!$usuario): ?>
  <div class="alerta alerta-aviso">
    <a onclick="navegarPara('conta')" style="cursor:pointer;color:var(--cor-primaria)">Faça login</a>
    para usar as ferramentas de monitoramento.
  </div>
<?php else: ?>

<!-- ── Criar nova ferramenta ─────────────────────────────── -->
<div class="card animar-entrada" id="card-nova-ferramenta">
  <div class="card-titulo" style="cursor:pointer" onclick="toggleFormFerramenta()">
    <svg viewBox="0 0 24 24" width="18" height="18" stroke="var(--cor-acento)" fill="none" stroke-width="2">
      <circle cx="12" cy="12" r="10"/>
      <line x1="12" y1="8" x2="12" y2="16"/>
      <line x1="8" y1="12" x2="16" y2="12"/>
    </svg>
    Nova Ferramenta
    <svg id="seta-form" viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" style="margin-left:auto;transition:transform .2s">
      <polyline points="6 9 12 15 18 9"/>
    </svg>
  </div>

  <form id="form-nova-ferramenta" style="display:none" onsubmit="criarFerramenta(event)">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div class="campo-grupo" style="grid-column:1/-1">
        <label class="campo-label">Título</label>
        <input type="text" name="titulo" class="campo-input" placeholder="Ex: Meta de poupança" required maxlength="200">
      </div>

      <div class="campo-grupo">
        <label class="campo-label">Tipo</label>
        <select name="tipo" class="campo-input">
          <option value="meta">🎯 Meta</option>
          <option value="alerta">🔔 Alerta</option>
          <option value="nota">📝 Nota</option>
        </select>
      </div>

      <div class="campo-grupo">
        <label class="campo-label">Cor</label>
        <input type="color" name="cor" value="#7C6FFF" class="campo-input" style="padding:6px;height:44px;cursor:pointer">
      </div>

      <div class="campo-grupo">
        <label class="campo-label">Valor Atual (R$)</label>
        <input type="number" name="valor" class="campo-input" placeholder="0,00" step="0.01" min="0">
      </div>

      <div class="campo-grupo">
        <label class="campo-label">Meta / Limite (R$)</label>
        <input type="number" name="meta_valor" class="campo-input" placeholder="0,00" step="0.01" min="0">
      </div>
    </div>

    <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">

    <button type="submit" class="btn btn-primario">
      <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      Criar Ferramenta
    </button>
  </form>
</div>

<!-- ── Lista de ferramentas ──────────────────────────────── -->
<?php if (empty($itens)): ?>
  <div class="card animar-entrada" style="text-align:center;padding:40px">
    <div style="font-size:3rem;margin-bottom:12px">📊</div>
    <p style="color:var(--texto-secundario)">Nenhuma ferramenta criada ainda.</p>
  </div>
<?php else: ?>
  <div class="card animar-entrada" style="padding:12px">
    <div class="card-titulo" style="margin-bottom:16px">
      <svg viewBox="0 0 24 24" width="18" height="18" stroke="var(--cor-primaria)" fill="none" stroke-width="2">
        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
      </svg>
      Monitor Ativo (<?= count($itens) ?> item<?= count($itens) !== 1 ? 's' : '' ?>)
    </div>

    <?php foreach ($itens as $item): ?>
      <?php
      $pct = 0;
      if ($item['meta_valor'] > 0 && $item['valor'] !== null) {
          $pct = min(100, round(($item['valor'] / $item['meta_valor']) * 100));
      }
      ?>
      <div class="monitor-item" data-id="<?= $item['id'] ?>">
        <div style="
          width:36px;height:36px;border-radius:8px;
          background:<?= esc($item['cor']) ?>22;
          border:1px solid <?= esc($item['cor']) ?>44;
          display:flex;align-items:center;justify-content:center;
          font-size:1.2rem;flex-shrink:0">
          <?= $tipos_label[$item['tipo']][0] ?>
        </div>

        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:.875rem;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= esc($item['titulo']) ?>
          </div>
          <div style="font-size:.72rem;color:var(--texto-terciario);margin-bottom:6px">
            <?= $tipos_label[$item['tipo']] ?>
            <?php if ($item['valor'] !== null): ?>
              • R$ <?= number_format((float)$item['valor'], 2, ',', '.') ?>
              <?php if ($item['meta_valor']): ?>
                / R$ <?= number_format((float)$item['meta_valor'], 2, ',', '.') ?>
                (<?= $pct ?>%)
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <?php if ($item['meta_valor'] > 0): ?>
          <div class="monitor-barra">
            <div
              class="monitor-progresso"
              data-pct="<?= $pct ?>"
              style="background:linear-gradient(90deg, <?= esc($item['cor']) ?>, <?= esc($item['cor']) ?>88)">
            </div>
          </div>
          <?php endif; ?>
        </div>

        <button
          class="btn btn-sm btn-perigo"
          onclick="excluirFerramenta(<?= $item['id'] ?>, this)"
          title="Excluir">
          <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" fill="none" stroke-width="2">
            <polyline points="3 6 5 6 21 6"/>
            <path d="M19 6l-1 14H6L5 6"/>
            <path d="M10 11v6M14 11v6M9 6V4h6v2"/>
          </svg>
        </button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php endif; ?>

<script>
function toggleFormFerramenta() {
  const form = document.getElementById('form-nova-ferramenta');
  const seta = document.getElementById('seta-form');
  const aberto = form.style.display !== 'none';
  form.style.display = aberto ? 'none' : 'block';
  seta.style.transform = aberto ? '' : 'rotate(180deg)';
}

async function criarFerramenta(e) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>';

  const dados = new FormData(form);
  try {
    const resp = await fetchJSON('/modules/ferramentas.php', {
      method: 'POST',
      body: JSON.stringify({
        acao: 'criar_item',
        titulo: dados.get('titulo'),
        tipo: dados.get('tipo'),
        valor: dados.get('valor') || null,
        meta_valor: dados.get('meta_valor') || null,
        cor: dados.get('cor'),
        csrf_token: dados.get('csrf_token'),
      })
    });

    if (resp.sucesso) {
      mostrarToast('Ferramenta criada!', 'sucesso');
      form.reset();
      setTimeout(() => navegarPara('ferramentas'), 500);
    } else {
      mostrarToast(resp.erro || 'Erro ao criar.', 'erro');
    }
  } catch {
    mostrarToast('Erro de conexão.', 'erro');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Criar Ferramenta';
  }
}

async function excluirFerramenta(id, btn) {
  if (!confirm('Excluir esta ferramenta?')) return;
  btn.disabled = true;
  try {
    const resp = await fetchJSON('/modules/ferramentas.php', {
      method: 'POST',
      body: JSON.stringify({ acao: 'excluir_item', id })
    });
    if (resp.sucesso) {
      btn.closest('.monitor-item').remove();
      mostrarToast('Removida.', 'sucesso');
    } else {
      mostrarToast('Erro ao remover.', 'erro');
    }
  } catch {
    mostrarToast('Erro de conexão.', 'erro');
  } finally {
    btn.disabled = false;
  }
}
</script>