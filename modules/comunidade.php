<?php
// ============================================================
//  MindCash — Módulo: Comunidade (Chat em tempo real)
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$usuario = usuario_logado();
if (!$usuario) {
    die('Acesso negado.');
}

// ── API AJAX: Receber e Enviar Mensagens ─────────────────────
if (is_ajax()) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = $input['acao'] ?? '';

    // Enviar Mensagem
    if ($acao === 'enviar') {
        $msg = sanitizar_texto($input['mensagem'] ?? '', 1000);
        if (empty($msg)) {
            echo json_encode(['erro' => 'A mensagem não pode estar vazia.']);
            exit;
        }
        db_executar(
            'INSERT INTO mensagens_comunidade (usuario_id, conteudo) VALUES (?, ?)',
            [$usuario['id'], $msg]
        );
        registrar_acao($usuario['id'], 'enviou_mensagem');
        echo json_encode(['sucesso' => true]);
        exit;
    }

    // Carregar Mensagens (Polling)
    if ($acao === 'carregar') {
        $ultimo_id = (int)($input['ultimo_id'] ?? 0);
        $msgs = db_buscar(
            'SELECT m.id, m.conteudo, m.criado_em, u.nome, u.foto 
             FROM mensagens_comunidade m 
             JOIN usuarios u ON m.usuario_id = u.id 
             WHERE m.id > ? 
             ORDER BY m.id ASC LIMIT 50',
            [$ultimo_id]
        );
        echo json_encode(['sucesso' => true, 'mensagens' => $msgs]);
        exit;
    }
    exit;
}
?>

<div class="pagina-header">
  <h2 class="pagina-titulo">💬 Comunidade <?= htmlspecialchars(SISTEMA_NOME) ?></h2>
  <p style="color:var(--texto-terciario)">Partilhe ideias e dúvidas com outros utilizadores.</p>
</div>

<div class="card" style="display:flex; flex-direction:column; height: 60vh; padding:0; overflow:hidden;">
  <div id="chat-mensagens" style="flex:1; overflow-y:auto; padding:var(--esp-md); display:flex; flex-direction:column; gap:12px;">
     </div>
  
  <div style="padding:var(--esp-md); border-top:1px solid var(--vidro-borda); background:var(--fundo-nivel2);">
    <form id="form-chat" onsubmit="enviarMensagemChat(event)" style="display:flex; gap:8px;">
      <input type="text" id="input-chat" class="input-base" placeholder="Escreva a sua mensagem..." required style="flex:1;" autocomplete="off">
      <button type="submit" class="btn btn-primario" id="btn-enviar-chat">Enviar</button>
    </form>
  </div>
</div>

<script>
// Lógica do Chat SPA
let ultimoMsgId = 0;

async function carregarMensagens() {
  try {
    const resp = await fetchJSON('modules/comunidade.php', {
      method: 'POST',
      body: JSON.stringify({ acao: 'carregar', ultimo_id: ultimoMsgId })
    });
    
    if (resp.sucesso && resp.mensagens.length > 0) {
      const container = document.getElementById('chat-mensagens');
      
      resp.mensagens.forEach(m => {
        const ehMeu = m.nome === '<?= htmlspecialchars($usuario['nome']) ?>';
        const align = ehMeu ? 'flex-end' : 'flex-start';
        const bg = ehMeu ? 'var(--cor-primaria)' : 'var(--vidro-bg)';
        
        const div = document.createElement('div');
        div.style.alignSelf = align;
        div.style.maxWidth = '85%';
        div.style.background = bg;
        div.style.padding = '10px 14px';
        div.style.borderRadius = 'var(--raio-md)';
        div.innerHTML = `
          <div style="font-size:0.75rem; color:${ehMeu ? '#fff' : 'var(--texto-secundario)'}; margin-bottom:4px; opacity:0.8;">
            <strong>${esc(m.nome)}</strong> • ${formatarHora(m.criado_em)}
          </div>
          <div style="font-size:0.9rem; line-height:1.4;">${esc(m.conteudo)}</div>
        `;
        container.appendChild(div);
        ultimoMsgId = Math.max(ultimoMsgId, m.id);
      });
      container.scrollTop = container.scrollHeight; // Desce o scroll automaticamente
    }
  } catch (e) {
    console.error('Erro ao atualizar chat:', e);
  }
}

async function enviarMensagemChat(e) {
  e.preventDefault();
  const input = document.getElementById('input-chat');
  const btn = document.getElementById('btn-enviar-chat');
  const msg = input.value.trim();
  
  if (!msg) return;
  btn.disabled = true;
  
  try {
    const resp = await fetchJSON('modules/comunidade.php', {
      method: 'POST',
      body: JSON.stringify({ acao: 'enviar', mensagem: msg })
    });
    if (resp.sucesso) {
      input.value = '';
      await carregarMensagens(); // Atualiza instantaneamente ao enviar
    }
  } finally {
    btn.disabled = false;
    input.focus();
  }
}

// Inicia o carregamento e o loop (Polling)
carregarMensagens();
if (App) {
    clearInterval(App.chatInterval); // Limpa loops antigos para não sobrecarregar
    App.chatInterval = setInterval(carregarMensagens, 3000); // Procura mensagens a cada 3 seg
}
</script>