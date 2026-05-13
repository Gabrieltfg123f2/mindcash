/**
 * MindCash — main.js
 * Chat via Fetch API | Toast | Navegação | CSRF
 */

'use strict';

/* ── Utilidades ────────────────────────────────────────── */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

function toast(msg, type = 'info') {
  const wrap = document.getElementById('toast-container') || (() => {
    const d = document.createElement('div');
    d.id = 'toast-container';
    document.body.appendChild(d);
    return d;
  })();

  const colors = { info: '#3b82f6', success: '#10b981', danger: '#ef4444', warn: '#f59e0b' };
  const t = document.createElement('div');
  t.className = 'toast';
  t.style.borderLeftColor = colors[type] || colors.info;
  t.style.borderLeftWidth = '3px';
  t.textContent = msg;
  wrap.appendChild(t);
  setTimeout(() => t.remove(), 3200);
}

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/* ── Fetch helper (inclui CSRF automaticamente) ──────── */
async function apiFetch(url, opts = {}) {
  const headers = {
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-Token': csrfToken(),
    ...(opts.headers ?? {}),
  };

  if (!(opts.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
    if (opts.body && typeof opts.body === 'object') {
      opts.body = JSON.stringify(opts.body);
    }
  }

  try {
    const res = await fetch(url, { ...opts, headers });
    const data = await res.json();
    if (!res.ok) throw new Error(data.erro ?? 'Erro na requisição');
    return data;
  } catch (err) {
    toast(err.message, 'danger');
    throw err;
  }
}

/* ── Navegação SPA-light ─────────────────────────────── */
function initNav() {
  const modAtual = new URLSearchParams(location.search).get('mod') || 'inicio';
  $$('.nav-item').forEach(el => {
    const mod = el.dataset.mod;
    if (mod === modAtual) el.classList.add('active');
  });
}

/* ════════════════════════════════════════════════════════
   CHAT — Comunidade
   ════════════════════════════════════════════════════════ */
const CHAT_POLL_MS = 3000;
let chatLastId    = 0;
let chatPollTimer = null;

function initChat() {
  const msgList  = document.getElementById('chat-messages');
  const form     = document.getElementById('chat-form');
  const inputEl  = document.getElementById('chat-input');
  const sendBtn  = document.getElementById('chat-send');

  if (!msgList || !form) return;

  // Primeira carga
  carregarMensagens(true);

  // Polling
  chatPollTimer = setInterval(() => carregarMensagens(false), CHAT_POLL_MS);

  // Envio
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const texto = inputEl.value.trim();
    if (!texto) return;

    sendBtn.disabled = true;
    sendBtn.innerHTML = '<span class="spinner"></span>';

    try {
      const data = await apiFetch('index.php?mod=comunidade&action=enviar', {
        method: 'POST',
        body: { mensagem: texto },
      });

      if (data.ok) {
        inputEl.value = '';
        await carregarMensagens(false);
        scrollChat();
      }
    } finally {
      sendBtn.disabled = false;
      sendBtn.innerHTML = `<svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>`;
      inputEl.focus();
    }
  });

  // Enviar com Enter (Shift+Enter = nova linha)
  inputEl.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit();
    }
  });
}

async function carregarMensagens(inicial) {
  const msgList = document.getElementById('chat-messages');
  if (!msgList) return;

  const url = `index.php?mod=comunidade&action=listar&desde=${chatLastId}`;
  const data = await apiFetch(url);

  if (!data.mensagens?.length) return;

  const atBottom = msgList.scrollHeight - msgList.scrollTop - msgList.clientHeight < 60;

  data.mensagens.forEach(msg => {
    if (msg.id <= chatLastId) return;
    chatLastId = Math.max(chatLastId, msg.id);
    msgList.appendChild(buildMsgBubble(msg));
  });

  if (inicial || atBottom) scrollChat();
}

function buildMsgBubble(msg) {
  const isMine = msg.minha === true || msg.minha === 1;
  const wrap   = document.createElement('div');
  wrap.className = `msg-bubble${isMine ? ' mine' : ''} fade-in`;
  wrap.dataset.id = msg.id;

  const inicial = (msg.nome ?? 'A')[0].toUpperCase();
  const avatarHtml = msg.avatar_url
    ? `<img src="${escHtml(msg.avatar_url)}" alt="">`
    : inicial;

  wrap.innerHTML = `
    <div class="msg-avatar">${avatarHtml}</div>
    <div class="msg-body">
      <div class="msg-meta">
        <strong>${escHtml(msg.nome ?? 'Anônimo')}</strong>
        <span>${formatTime(msg.criado_em)}</span>
      </div>
      <div class="msg-text">${escHtml(msg.conteudo)}</div>
    </div>`;
  return wrap;
}

function scrollChat() {
  const el = document.getElementById('chat-messages');
  if (el) el.scrollTop = el.scrollHeight;
}

/* ════════════════════════════════════════════════════════
   FERRAMENTAS — Busca de mensagens
   ════════════════════════════════════════════════════════ */
function initFerramantas() {
  const searchInput = document.getElementById('busca-input');
  const resultados  = document.getElementById('busca-resultados');
  if (!searchInput || !resultados) return;

  let debounceTimer;

  searchInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    const q = searchInput.value.trim();
    if (q.length < 2) { resultados.innerHTML = ''; return; }

    debounceTimer = setTimeout(async () => {
      resultados.innerHTML = '<div style="color:var(--text-muted);font-size:13px;padding:12px">Buscando…</div>';
      try {
        const data = await apiFetch(`index.php?mod=ferramentas&action=buscar&q=${encodeURIComponent(q)}`);
        renderBusca(data.resultados ?? [], resultados);
      } catch {}
    }, 380);
  });
}

function renderBusca(items, container) {
  if (!items.length) {
    container.innerHTML = '<div style="color:var(--text-muted);font-size:13px;padding:12px">Nenhum resultado.</div>';
    return;
  }
  container.innerHTML = items.map(m => `
    <div class="activity-item fade-in">
      <div class="activity-dot"></div>
      <div>
        <strong style="font-size:12px">${escHtml(m.nome)}</strong>
        <p style="font-size:13px;margin-top:2px">${escHtml(m.conteudo)}</p>
      </div>
      <span class="activity-time">${formatTime(m.criado_em)}</span>
    </div>`).join('');
}

/* ════════════════════════════════════════════════════════
   CONTA — Upload de avatar
   ════════════════════════════════════════════════════════ */
function initConta() {
  const fileInput = document.getElementById('avatar-input');
  const preview   = document.getElementById('avatar-preview');
  if (!fileInput || !preview) return;

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { toast('Avatar deve ter no máximo 2 MB.', 'warn'); return; }
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; };
    reader.readAsDataURL(file);
  });
}

/* ── Helpers ─────────────────────────────────────────── */
function escHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function formatTime(iso) {
  if (!iso) return '';
  try {
    const d = new Date(iso.replace(' ', 'T'));
    const now = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60)   return 'agora';
    if (diff < 3600) return `${Math.floor(diff / 60)}min`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    return d.toLocaleDateString('pt-BR');
  } catch { return ''; }
}

/* ── Boot ──────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initNav();
  initChat();
  initFerramantas();
  initConta();
});

// Limpa polling ao sair da página
window.addEventListener('beforeunload', () => {
  clearInterval(chatPollTimer);
});
