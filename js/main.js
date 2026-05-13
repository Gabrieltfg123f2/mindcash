// Banco de dados simulado no navegador
let db = JSON.parse(localStorage.getItem('mindcash_v2_db')) || { posts: [] };

window.onload = () => {
    const user = localStorage.getItem('mindcash_user');
    if (user) showApp(user);
};

function handleLocalLogin() {
    const name = document.getElementById('user-name-input').value;
    if (!name) return alert("Por favor, insira um nome.");
    localStorage.setItem('mindcash_user', name);
    showApp(name);
}

function showApp(name) {
    document.getElementById('auth-screen').classList.add('hidden');
    document.getElementById('app-screen').classList.remove('hidden');
    document.getElementById('display-user-name').innerText = name;
    renderFeed();
}

function handleLogout() {
    localStorage.removeItem('mindcash_user');
    location.reload();
}

// LÓGICA DE COMUNICAÇÃO (FEED)
function criarPost() {
    const text = document.getElementById('post-input').value;
    const user = localStorage.getItem('mindcash_user');
    
    if (!text) return;

    const novoPost = {
        id: Date.now(),
        autor: user,
        conteudo: text,
        data: new Date().toLocaleDateString('pt-BR')
    };

    db.posts.unshift(novoPost); // Adiciona no topo
    saveDB();
    renderFeed();
    document.getElementById('post-input').value = "";
}

function renderFeed() {
    const container = document.getElementById('feed-container');
    container.innerHTML = db.posts.map(post => `
        <div class="post-card glass">
            <div class="post-header">
                <strong>${post.autor}</strong>
                <span>${post.data}</span>
            </div>
            <p>${post.conteudo}</p>
            <div class="post-actions">
                <button><i class="fa fa-thumbs-up"></i> Útil</button>
                <button><i class="fa fa-comment"></i> Responder</button>
            </div>
        </div>
    `).join('');
}

function saveDB() {
    localStorage.setItem('mindcash_v2_db', JSON.stringify(db));
}

function switchView(view) {
    document.querySelectorAll('.sub-view').forEach(v => v.classList.remove('active'));
    document.getElementById(`view-${view}`).classList.add('active');
    document.getElementById('view-title').innerText = view.charAt(0).toUpperCase() + view.slice(1);
}