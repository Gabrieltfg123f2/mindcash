/* ============================================================
   MindCash — main.js
   SPA Navigation | Fetch API | PWA | Utilitários
   ============================================================ */

'use strict';

/* ── Estado Global ──────────────────────────────────────────── */
const App = {
  abaAtual: 'inicio',
  carregando: false,
  chatInterval: null,
  ultimaMsgId: 0,
};

/* ── DOM Ready ──────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  iniciarNavegacao();
  iniciarServiceWorker();
  // Lê a aba da URL ao carregar
  const params = new URLSearchParams(window.location.search);
  const abaInicial = params.get('aba') || 'inicio';
  navegarPara(abaInicial, false);
});

/* ============================================================
   NAVEGAÇÃO SPA
   ============================================================ */
function iniciarNavegacao() {
  // Cliques nos nav-items (mobile e desktop)
  document.querySelectorAll('[data-aba]').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      navegarPara(el.dataset.aba);
    });
  });

  // Botão Voltar do navegador
  window.addEventListener('popstate', e => {
    const aba = e.state?.aba || 'inicio';
    navegarPara(aba, false);
  });
}

async function navegarPara(aba, pushState = true) {
  if (App.carregando) return;
  App.carregando = true;

  // Para polling do chat se sair da comunidade
  if (App.abaAtual === 'comunidade' && aba !== 'comunidade') {
    pararChatPolling();
  }

  App.abaAtual = aba;
  atualizarNavAtivo(aba);

  if (pushState) {
    const url = new URL(window.location);
    url.searchParams.set('aba', aba);
    history.pushState({ aba }, '', url.toString());
  }

  const container = document.getElementById('conteudo-principal');

  // Fade-out
  container.style.opacity = '0';
  container.style.transform = 'translateY(8px)';

  try {
    const resposta = await fetch(`/modules/${aba}.php`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (resposta.status === 401) {
      mostrarToast('Faça login para acessar esta área.', 'aviso');
      navegarPara('conta');
      return;
    }

    if (resposta.status === 403) {
      mostrarToast('Acesso restrito a administradores.', 'erro');
      navegarPara('inicio');
      return;
    }

    const html = await resposta.text();
    container.innerHTML = html;
    container.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    container.style.opacity = '1';
    container.style.transform = 'translateY(0)';

    // Re-bind de eventos pós-carregamento
    if (aba === 'comunidade') iniciarChat();
    if (aba === 'ferramentas') iniciarFerramentas();
    if (aba === 'conta') iniciarConta();

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });

  } catch (err) {
    container.innerHTML = `
      <div class="alerta alerta-erro">
        Erro ao carregar módulo. Verifique sua conexão.
      </div>`;
    container.style.opacity = '1';
    container.style.transform = 'translateY(0)';
    console.error('Erro de navegação:', err);
  } finally {
    App.carregando = false;
  }
}

function atualizarNavAtivo(aba) {
  document.querySelectorAll('[data-aba]').forEach(el => {
    el.classList.toggle('ativo', el.dataset.aba === aba);
  });
}

/* ============================================================
   CHAT — COMUNIDADE (Fetch API sem refresh)
   ============================================================ */
function iniciarChat() {
  const form  = document.getElementById('chat-form');
  const input = document.getElementById('chat-input');
  if (!form || !input) return;

  carregarMensagens(true);

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const texto = input.value.trim();
    if (!texto) return;

    input.value = '';
    input.disabled = true;

    try {
      const resp = await fetchJSON('/modules/comunidade.php', {
        method: 'POST',
        body: JSON.stringify({ acao: 'enviar', mensagem: texto }),
      });

      if (resp.sucesso) {
        await carregarMensagens();
      } else {
        mostrarToast(resp.erro || 'Erro ao enviar.', 'erro');
      }
    } catch {
      mostrarToast('Erro de conexão.', 'erro');
    } finally {
      input.disabled = false;
      input.focus();
    }
  });

  // Polling a cada 4s
  App.chatInterval = setInterval(() => carregarMensagens(), 4000);
}

async function carregarMensagens(scroll = false) {
  const container = document.getElementById('chat-mensagens');
  if (!container) return pararChatPolling();

  try {
    const resp = await fetchJSON(
      `/modules/comunidade.php?acao=buscar&ultimo_id=${App.ultimaMsgId}`
    );

    if (resp.mensagens?.length) {
      resp.mensagens.forEach(msg => {
        const bolha = criarBolha(msg);
        container.appendChild(bolha);
      });
      App.ultimaMsgId = resp.mensagens.at(-1).id;
      scroll = true;
    }

    if (scroll) {
      container.scrollTop = container.scrollHeight;
    }
  } catch { /* silencioso em polling */ }
}

function criarBolha(msg) {
  const div = document.createElement('div');
  div.className = `msg-bolha animar-entrada ${msg.propria ? 'propria' : 'outra'}`;
  div.innerHTML = `
    ${!msg.propria ? `<div class="msg-meta">${esc(msg.autor)}</div>` : ''}
    <div>${esc(msg.conteudo)}</div>
    <div class="msg-meta" style="margin-top:4px;text-align:${msg.propria?'right':'left'}">
      ${formatarHora(msg.criado_em)}
    </div>
  `;
  return div;
}

function pararChatPolling() {
  if (App.chatInterval) {
    clearInterval(App.chatInterval);
    App.chatInterval = null;
  }
}

/* ============================================================
   FERRAMENTAS
   ============================================================ */
function iniciarFerramentas() {
  const busca = document.getElementById('busca-input');
  if (busca) {
    busca.addEventListener('input', debounce(e => filtrarItens(e.target.value), 300));
  }

  // Anima barras de progresso
  document.querySelectorAll('.monitor-progresso').forEach(barra => {
    const meta = parseInt(barra.dataset.pct || 0);
    barra.style.width = '0%';
    requestAnimationFrame(() => {
      setTimeout(() => { barra.style.width = meta + '%'; }, 100);
    });
  });
}

function filtrarItens(termo) {
  const itens = document.querySelectorAll('.monitor-item');
  itens.forEach(item => {
    const texto = item.textContent.toLowerCase();
    item.style.display = texto.includes(termo.toLowerCase()) ? '' : 'none';
  });
}

/* ============================================================
   CONTA
   ============================================================ */
function iniciarConta() {
  // Auth tabs
  const tabs = document.querySelectorAll('.auth-tab');
  const forms = document.querySelectorAll('.auth-form');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('ativa'));
      tab.classList.add('ativa');
      forms.forEach(f => {
        f.style.display = f.id === tab.dataset.form ? '' : 'none';
      });
    });
  });

  // Upload de foto
  const fotoInput = document.getElementById('foto-input');
  const fotoWrapper = document.querySelector('.upload-foto-wrapper');

  if (fotoWrapper && fotoInput) {
    fotoWrapper.addEventListener('click', () => fotoInput.click());
    fotoInput.addEventListener('change', uploadFoto);
  }

  // Forms de login / cadastro
  const formLogin    = document.getElementById('form-login');
  const formCadastro = document.getElementById('form-cadastro');
  const formPerfil   = document.getElementById('form-perfil');

  formLogin    && formLogin.addEventListener('submit', submitLogin);
  formCadastro && formCadastro.addEventListener('submit', submitCadastro);
  formPerfil   && formPerfil.addEventListener('submit', submitPerfil);
}

async function submitLogin(e) {
  e.preventDefault();
  const btn  = e.target.querySelector('[type=submit]');
  const orig = btn.textContent;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>';

  const dados = new FormData(e.target);

  try {
    const resp = await fetchJSON('/modules/conta.php', {
      method: 'POST',
      body: JSON.stringify({
        acao:        'login',
        email:       dados.get('email'),
        senha:       dados.get('senha'),
        csrf_token:  dados.get('csrf_token'),
      }),
    });

    if (resp.sucesso) {
      mostrarToast('Login realizado! Bem-vindo.', 'sucesso');
      setTimeout(() => navegarPara('inicio'), 800);
    } else {
      mostrarToast(resp.erro || 'Credenciais inválidas.', 'erro');
    }
  } catch {
    mostrarToast('Erro de conexão.', 'erro');
  } finally {
    btn.disabled = false;
    btn.textContent = orig;
  }
}

async function submitCadastro(e) {
  e.preventDefault();
  const dados = new FormData(e.target);
  const btn = e.target.querySelector('[type=submit]');
  btn.disabled = true;

  try {
    const resp = await fetchJSON('/modules/conta.php', {
      method: 'POST',
      body: JSON.stringify({
        acao:       'cadastro',
        nome:       dados.get('nome'),
        email:      dados.get('email'),
        senha:      dados.get('senha'),
        csrf_token: dados.get('csrf_token'),
      }),
    });

    if (resp.sucesso) {
      mostrarToast('Conta criada com sucesso!', 'sucesso');
      setTimeout(() => navegarPara('inicio'), 800);
    } else {
      mostrarToast(resp.erro || 'Erro no cadastro.', 'erro');
    }
  } catch {
    mostrarToast('Erro de conexão.', 'erro');
  } finally {
    btn.disabled = false;
  }
}

async function submitPerfil(e) {
  e.preventDefault();
  const dados = new FormData(e.target);
  const btn = e.target.querySelector('[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Salvando...';

  try {
    const resp = await fetchJSON('/modules/conta.php', {
      method: 'POST',
      body: JSON.stringify({
        acao:       'atualizar_perfil',
        nome:       dados.get('nome'),
        bio:        dados.get('bio'),
        csrf_token: dados.get('csrf_token'),
      }),
    });

    mostrarToast(resp.sucesso ? 'Perfil atualizado!' : (resp.erro || 'Erro.'), resp.sucesso ? 'sucesso' : 'erro');
  } catch {
    mostrarToast('Erro de conexão.', 'erro');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Salvar Alterações';
  }
}

async function uploadFoto(e) {
  const arquivo = e.target.files[0];
  if (!arquivo) return;

  if (arquivo.size > 2 * 1024 * 1024) {
    mostrarToast('Foto muito grande. Máximo: 2MB.', 'aviso');
    return;
  }

  const formData = new FormData();
  formData.append('acao', 'upload_foto');
  formData.append('foto', arquivo);
  formData.append('csrf_token', document.querySelector('[name=csrf_foto]')?.value || '');

  try {
    const resp = await fetch('/modules/conta.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: formData,
    });
    const dados = await resp.json();

    if (dados.sucesso) {
      document.querySelectorAll('.avatar-perfil').forEach(img => {
        img.src = dados.url + '?' + Date.now();
      });
      mostrarToast('Foto atualizada!', 'sucesso');
    } else {
      mostrarToast(dados.erro || 'Erro no upload.', 'erro');
    }
  } catch {
    mostrarToast('Erro de conexão.', 'erro');
  }
}

/* ============================================================
   TOAST NOTIFICATIONS
   ============================================================ */
function mostrarToast(mensagem, tipo = 'info') {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const icones = {
    sucesso: '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
    erro:    '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    aviso:   '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info:    '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
  };

  const cores = {
    sucesso: 'var(--cor-sucesso)',
    erro:    'var(--cor-perigo)',
    aviso:   'var(--cor-aviso)',
    info:    'var(--cor-primaria)',
  };

  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.style.borderLeft = `3px solid ${cores[tipo] || cores.info}`;
  toast.style.color = cores[tipo] || cores.info;
  toast.innerHTML = `${icones[tipo] || icones.info}<span style="color:var(--texto-primario)">${esc(mensagem)}</span>`;

  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.add('toast-saindo');
    toast.addEventListener('animationend', () => toast.remove());
  }, 3500);
}

/* ============================================================
   HELPERS
   ============================================================ */
async function fetchJSON(url, opcoes = {}) {
  const defaults = {
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  };
  const resp = await fetch(url, { ...defaults, ...opcoes,
    headers: { ...defaults.headers, ...(opcoes.headers || {}) }
  });
  return resp.json();
}

function esc(str) {
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str ?? ''));
  return div.innerHTML;
}

function debounce(fn, delay) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

function formatarHora(dataStr) {
  if (!dataStr) return '';
  const d = new Date(dataStr);
  return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

/* ============================================================
   SERVICE WORKER — PWA
   ============================================================ */
function iniciarServiceWorker() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker
      .register('/sw.js')
      .then(reg => console.log('[PWA] SW registrado:', reg.scope))
      .catch(err => console.warn('[PWA] SW falhou:', err));
  }
}