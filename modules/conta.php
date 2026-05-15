<?php
// ============================================================
//  MindCash — Módulo: Conta (Perfil e Definições)
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$usuario = usuario_logado();
if (!$usuario) {
    die('Acesso negado.');
}

$erro = '';
$sucesso = '';

// ── Atualizar o Perfil ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar_perfil') {
    if (!csrf_validar($_POST['csrf_token'] ?? '')) {
        $erro = 'Sessão expirada. Por favor, tente novamente.';
    } else {
        $novo_nome = sanitizar_texto($_POST['nome'] ?? '', 100);
        $nova_bio  = sanitizar_texto($_POST['bio'] ?? '', 500);

        if (empty($novo_nome)) {
            $erro = 'O campo "Nome" não pode estar vazio.';
        } else {
            $foto_path = $usuario['foto'];

            // Lógica de Upload da Foto
            if (!empty($_FILES['foto']['tmp_name'])) {
                $file = $_FILES['foto'];
                if ($file['size'] > UPLOAD_MAX_SIZE) {
                    $erro = 'A imagem excede o limite de 2MB.';
                } elseif (!in_array($file['type'], UPLOAD_TIPOS)) {
                    $erro = 'Formato inválido. Utilize JPG, PNG ou WEBP.';
                } else {
                    if (!is_dir(UPLOAD_DIR)) {
                        mkdir(UPLOAD_DIR, 0777, true);
                    }
                    $extensao = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $nome_arquivo = 'avatar_' . $usuario['id'] . '_' . time() . '.' . $extensao;
                    $caminho_final = UPLOAD_DIR . $nome_arquivo;
                    
                    if (move_uploaded_file($file['tmp_name'], $caminho_final)) {
                        $foto_path = UPLOAD_URL . $nome_arquivo;
                    }
                }
            }

            if (empty($erro)) {
                db_executar(
                    'UPDATE usuarios SET nome = ?, bio = ?, foto = ? WHERE id = ?',
                    [$novo_nome, $nova_bio, $foto_path, $usuario['id']]
                );
                registrar_acao($usuario['id'], 'atualizou_perfil');
                $sucesso = 'Perfil guardado com sucesso!';
                
                // Atualiza a variável para refletir na interface imediatamente
                $usuario['nome'] = $novo_nome;
                $usuario['bio'] = $nova_bio;
                $usuario['foto'] = $foto_path;
            }
        }
    }
}

// ── Buscar Histórico do Utilizador ──────────────────────────
$historico = [];
try {
    $historico = db_buscar(
        'SELECT acao, criado_em, ip FROM historico_acoes WHERE usuario_id = ? ORDER BY criado_em DESC LIMIT 8',
        [$usuario['id']]
    );
} catch (Throwable $e) {}
?>

<div class="pagina-header">
  <h2 class="pagina-titulo">👤 A Minha Conta</h2>
</div>

<?php if ($erro): ?>
  <div class="toast erro" style="margin-bottom:15px; border-left: 3px solid var(--cor-perigo); padding:10px; background:var(--fundo-nivel2); border-radius:var(--raio-sm);"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>
<?php if ($sucesso): ?>
  <div class="toast sucesso" style="margin-bottom:15px; border-left: 3px solid var(--cor-sucesso); padding:10px; background:var(--fundo-nivel2); border-radius:var(--raio-sm);"><?= htmlspecialchars($sucesso) ?></div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
  
  <div class="card">
    <h3 style="margin-bottom: 15px;">Editar Informações</h3>
    <form method="POST" action="index.php?aba=conta" enctype="multipart/form-data">
        <input type="hidden" name="acao" value="atualizar_perfil">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_gerar()) ?>">
        
        <div style="display:flex; align-items:center; gap: 15px; margin-bottom: 20px;">
            <img id="preview-foto" src="<?= htmlspecialchars($usuario['foto'] ?? 'assets/images/logo.png') ?>" alt="Avatar" style="width:70px; height:70px; border-radius:50%; object-fit:cover; border:2px solid var(--cor-primaria);">
            <div>
                <label class="btn btn-secundario" style="cursor:pointer; display:inline-block; padding:8px 12px; font-size:0.85rem;">
                    Alterar Imagem
                    <input type="file" name="foto" id="input-foto" accept="image/png, image/jpeg, image/webp" style="display:none;" onchange="previewAvatar(event)">
                </label>
            </div>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block; margin-bottom:5px; font-size:0.85rem; color:var(--texto-secundario);">Nome Completo</label>
            <input type="text" name="nome" class="input-base" value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>" required style="width:100%;">
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display:block; margin-bottom:5px; font-size:0.85rem; color:var(--texto-secundario);">Biografia</label>
            <textarea name="bio" class="input-base" rows="3" style="width:100%; resize:vertical;"><?= htmlspecialchars($usuario['bio'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primario" style="width:100%;">Guardar Alterações</button>
    </form>
  </div>

  <div>
      <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 15px;">Registo de Atividades</h3>
        <?php if (empty($historico)): ?>
            <p style="color:var(--texto-terciario); font-size:0.85rem;">Nenhuma atividade registada recentemente.</p>
        <?php else: ?>
            <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:12px;">
                <?php foreach ($historico as $h): ?>
                    <li style="font-size:0.85rem; padding-bottom:10px; border-bottom:1px solid var(--vidro-borda);">
                        <strong style="color:var(--cor-acento);"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $h['acao']))) ?></strong><br>
                        <span style="color:var(--texto-terciario); font-size:0.75rem;">
                            <?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?> • IP: <?= htmlspecialchars($h['ip'] ?? 'Desconhecido') ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
      </div>

      <form method="POST" action="config/auth.php?action=logout">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_gerar()) ?>">
          <button type="submit" class="btn" style="width:100%; background:var(--cor-perigo); color:#fff; font-weight:bold;">
            Terminar Sessão
          </button>
      </form>
  </div>
</div>

<script>
// Pré-visualização da imagem do perfil
function previewAvatar(event) {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 2 * 1024 * 1024) { // 2MB
            alert('Erro: O ficheiro excede 2MB.');
            event.target.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-foto').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
}
</script>