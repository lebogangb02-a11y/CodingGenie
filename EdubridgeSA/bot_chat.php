<?php
// Bot Chat UI for Admins
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/admin_layout.php';

admin_require_login();

admin_header('Assistant Chat');
?>
<style>
.chat-container { max-width: 900px; margin: 0 auto; }
.chat-window { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
.messages { height: 420px; overflow-y: auto; padding: 1rem; }
.msg { display: flex; margin-bottom: .75rem; }
.msg .bubble { max-width: 70%; padding: .5rem .75rem; border-radius: 12px; }
.msg.admin { justify-content: flex-end; }
.msg.admin .bubble { background: #e7f1ff; border: 1px solid #cfe2ff; }
.msg.bot .bubble { background: #f8f9fa; border: 1px solid #dee2e6; }
.meta { font-size: .75rem; color: #6c757d; margin-top: .25rem; }
.connection { font-size: .85rem; }
.quick-replies .btn { margin-right: .5rem; margin-bottom: .5rem; }
</style>

<div class="chat-container">
  <div class="card chat-window">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <i class="bi bi-robot me-2"></i>Assistant Chat
        <span class="badge bg-secondary ms-2">Real-time</span>
      </div>
      <div class="connection" id="connStatus"><span class="badge bg-success">Connected</span></div>
    </div>
    <div class="messages" id="messages"></div>
    <div class="card-body">
      <div class="quick-replies mb-2">
        <button class="btn btn-outline-secondary btn-sm" data-quick="Next deadline?">Next deadline?</button>
        <button class="btn btn-outline-secondary btn-sm" data-quick="Pending applications count">Pending applications count</button>
        <button class="btn btn-outline-secondary btn-sm" data-quick="Document requirements">Document requirements</button>
        <button class="btn btn-outline-secondary btn-sm" data-quick="How to upload documents?">How to upload documents?</button>
      </div>
      <form id="chatForm" class="d-flex gap-2">
        <input type="text" class="form-control" id="chatInput" placeholder="Type a message..." required />
        <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> Send</button>
      </form>
    </div>
  </div>
</div>

<script>
const messagesEl = document.getElementById('messages');
const chatForm = document.getElementById('chatForm');
const chatInput = document.getElementById('chatInput');
const connStatus = document.getElementById('connStatus');
let lastId = null;
let conversationId = null;

function formatTime(ts) {
  const d = new Date(ts.replace(' ', 'T'));
  return d.toLocaleString();
}

function renderMessage(m) {
  const who = m.sender_type;
  const div = document.createElement('div');
  div.className = 'msg ' + (who === 'admin' ? 'admin' : 'bot');
  div.innerHTML = `<div class="bubble"><div>${escapeHtml(m.content)}</div><div class="meta">${who} • ${formatTime(m.created_at)}</div></div>`;
  messagesEl.appendChild(div);
  messagesEl.scrollTop = messagesEl.scrollHeight;
}

function escapeHtml(str) {
  return str.replace(/[&<>\"]/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]));
}

async function loadMessages() {
  try {
    const url = new URL('/chat_api.php', window.location.origin);
    url.searchParams.set('action', 'list');
    if (conversationId) url.searchParams.set('conversation_id', conversationId);
    if (lastId) url.searchParams.set('after_id', lastId);
    const res = await fetch(url.toString());
    if (!res.ok) throw new Error('Network ' + res.status);
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Failed');
    connStatus.innerHTML = '<span class="badge bg-success">Connected</span>';
    conversationId = data.conversation_id;
    for (const m of data.messages) {
      renderMessage(m);
      lastId = m.id;
    }
  } catch (e) {
    connStatus.innerHTML = '<span class="badge bg-warning text-dark">Reconnecting…</span>';
  }
}

async function sendMessage(text) {
  try {
    const fd = new FormData();
    fd.set('action', 'send');
    if (conversationId) fd.set('conversation_id', conversationId);
    fd.set('content', text);
    const res = await fetch('/chat_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Failed to send');
    connStatus.innerHTML = '<span class="badge bg-success">Connected</span>';
    // Optimistically render user message; bot reply will arrive on next poll
    renderMessage({ sender_type: 'admin', content: text, created_at: new Date().toISOString().slice(0,19).replace('T',' ') });
    lastId = data.user_message_id;
    loadMessages();
  } catch (e) {
    connStatus.innerHTML = '<span class="badge bg-danger">Error</span>';
    const div = document.createElement('div');
    div.className = 'alert alert-danger m-3';
    div.textContent = 'Send failed: ' + (e.message || 'Unknown');
    messagesEl.appendChild(div);
  }
}

chatForm.addEventListener('submit', (ev) => {
  ev.preventDefault();
  const text = chatInput.value.trim();
  if (!text) return;
  sendMessage(text);
  chatInput.value = '';
});

document.querySelectorAll('[data-quick]').forEach(btn => {
  btn.addEventListener('click', () => {
    sendMessage(btn.getAttribute('data-quick'));
  });
});

// Initial load and auto-refresh every 3 seconds
loadMessages();
setInterval(loadMessages, 3000);
</script>

<?php admin_footer(); ?>