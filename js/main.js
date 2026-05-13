// Banco de dados simulado no navegador
const db = JSON.parse(localStorage.getItem('mindcash_v2_db')) || { posts: [] };
const viewTitles = {
    feed: 'Comunidade',
    ferramentas: 'Ferramentas',
    mentoria: 'Ajuda & Mentoria'
};

window.onload = () => {
    const user = localStorage.getItem('mindcash_user');
    if (user) showApp(user);
    document.querySelectorAll('.nav-link').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            button.classList.add('active');
        });
    });
    window.addEventListener('resize', handleResize);
    handleResize();
};

function handleLocalLogin() {
    const name = document.getElementById('user-name-input').value.trim();
    if (!name) return alert('Por favor, insira um nome.');
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

function criarPost() {
    const text = document.getElementById('post-input').value.trim();
    const user = localStorage.getItem('mindcash_user');
    if (!text) return alert('Escreva algo antes de publicar.');

    const novoPost = {
        id: Date.now(),
        autor: user,
        conteudo: text,
        data: new Date().toLocaleDateString('pt-BR'),
        likes: 0,
        replies: []
    };

    db.posts.unshift(novoPost);
    saveDB();
    renderFeed();
    document.getElementById('post-input').value = '';
}

function renderFeed() {
    const container = document.getElementById('feed-container');
    if (!db.posts.length) {
        container.innerHTML = `
            <div class="empty-state glass">
                <strong>Ainda não há publicações.</strong>
                <p>Seja o primeiro a compartilhar uma ideia ou pedir ajuda.</p>
            </div>
        `;
        return;
    }

    const currentUser = localStorage.getItem('mindcash_user');
    container.innerHTML = db.posts.map(post => `
        <div class="post-card glass">
            <div class="post-header">
                <div>
                    <strong>${post.autor}</strong>
                    <span>${post.data}</span>
                </div>
                <div class="post-meta">
                    <span>${post.likes} curtidas</span>
                    ${post.autor === currentUser ? `<button class="btn-secondary" type="button" onclick="deletePost(${post.id})">Excluir</button>` : ''}
                </div>
            </div>
            <p>${post.conteudo}</p>
            <div class="post-actions">
                <button type="button" onclick="likePost(${post.id})"><i class="fa fa-thumbs-up"></i> Útil</button>
                <button type="button" onclick="toggleReplyForm(${post.id})"><i class="fa fa-comment"></i> Responder</button>
            </div>
            <div class="comment-section" id="comment-section-${post.id}">
                ${post.replies.length ? post.replies.map(reply => `
                    <div class="reply-item">
                        <strong>${reply.autor}</strong>
                        <span>${reply.data}</span>
                        <p>${reply.conteudo}</p>
                    </div>
                `).join('') : '<p class="empty-state">Nenhuma resposta ainda. Seja o primeiro a comentar.</p>'}
                <form class="reply-form hidden" id="reply-form-${post.id}" onsubmit="submitReply(event, ${post.id})">
                    <textarea id="reply-input-${post.id}" placeholder="Escreva sua resposta..."></textarea>
                    <button type="submit" class="btn-primary">Enviar resposta</button>
                </form>
            </div>
        </div>
    `).join('');
}

function saveDB() {
    localStorage.setItem('mindcash_v2_db', JSON.stringify(db));
}

function likePost(postId) {
    const post = db.posts.find(item => item.id === postId);
    if (!post) return;
    post.likes += 1;
    saveDB();
    renderFeed();
}

function deletePost(postId) {
    const postIndex = db.posts.findIndex(item => item.id === postId);
    if (postIndex === -1) return;
    db.posts.splice(postIndex, 1);
    saveDB();
    renderFeed();
}

function toggleReplyForm(postId) {
    const form = document.getElementById(`reply-form-${postId}`);
    if (!form) return;
    form.classList.toggle('hidden');
    if (!form.classList.contains('hidden')) {
        form.querySelector('textarea').focus();
    }
}

function submitReply(event, postId) {
    event.preventDefault();
    const input = document.getElementById(`reply-input-${postId}`);
    if (!input) return;

    const text = input.value.trim();
    if (!text) return alert('Escreva algo antes de responder.');

    const post = db.posts.find(item => item.id === postId);
    if (!post) return;

    post.replies.push({
        autor: localStorage.getItem('mindcash_user'),
        conteudo: text,
        data: new Date().toLocaleDateString('pt-BR')
    });
    saveDB();
    renderFeed();
}

function switchView(view) {
    document.querySelectorAll('.sub-view').forEach(v => v.classList.remove('active'));
    const selected = document.getElementById(`view-${view}`);
    if (!selected) return;
    selected.classList.add('active');
    document.getElementById('view-title').innerText = viewTitles[view] || 'MindCash';
    if (window.innerWidth <= 920) toggleMobileSidebar(false);
}

function openTool(tool) {
    const modal = document.getElementById('tool-modal');
    const content = document.getElementById('modal-content');

    if (tool === 'canvas') {
        content.innerHTML = `
            <h2>Business Model Canvas</h2>
            <p>Use este modelo para mapear valor, clientes e canais de entrega.</p>
            <div class="tool-result">
                <ul>
                    <li><strong>Proposta de valor:</strong> O que você resolve?</li>
                    <li><strong>Clientes:</strong> Para quem?</li>
                    <li><strong>Canais:</strong> Como entregar?</li>
                    <li><strong>Receitas:</strong> Como ganhar?</li>
                    <li><strong>Recursos:</strong> O que você precisa?</li>
                </ul>
            </div>
        `;
    } else if (tool === 'mei') {
        content.innerHTML = `
            <h2>Simulador de MEI</h2>
            <p>Veja quanto você pode receber líquido após os custos básicos.</p>
            <div class="tool-form">
                <label for="mei-income">Renda mensal esperada (R$)</label>
                <input id="mei-income" type="number" min="0" placeholder="Ex: 3500" />
                <button class="btn-primary" onclick="calculateMei()">Calcular MEI</button>
            </div>
            <div id="mei-result" class="tool-result"></div>
        `;
    }

    modal.classList.remove('hidden');
}

function closeModal() {
    document.getElementById('tool-modal').classList.add('hidden');
}

function calculateMei() {
    const incomeInput = document.getElementById('mei-income');
    const resultBox = document.getElementById('mei-result');
    const income = Number(incomeInput.value.trim());

    if (!income || income <= 0) {
        resultBox.innerHTML = `<p>Digite uma renda mensal válida para calcular.</p>`;
        return;
    }

    const custoMEI = 75.00;
    const lucroEstimado = income - custoMEI;

    resultBox.innerHTML = `
        <p><strong>Renda mensal:</strong> R$ ${income.toFixed(2)}</p>
        <p><strong>Custo MEI:</strong> R$ ${custoMEI.toFixed(2)}</p>
        <p><strong>Renda líquida estimada:</strong> R$ ${lucroEstimado.toFixed(2)}</p>
        <p>Este cálculo é uma estimativa simples para você ter uma ideia dos valores.</p>
    `;
}

function toggleMobileSidebar(open) {
    const sidebar = document.getElementById('sidebar');
    if (open) {
        sidebar.classList.add('open');
    } else {
        sidebar.classList.remove('open');
    }
}

function handleResize() {
    if (window.innerWidth > 920) {
        document.getElementById('sidebar').classList.remove('open');
    }
}
