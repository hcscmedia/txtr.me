<?php
require_once 'config.php';
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nachrichten - MicroNews</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="header-top">
        <div class="header-left">
            <a href="index.php" style="color:var(--text-main);text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Zurück
            </a>
            <h1>Nachrichten</h1>
        </div>
        <div class="header-right">
            <button class="theme-toggle" onclick="toggleTheme()">
                <i class="fas fa-moon"></i>
            </button>
        </div>
    </div>
</header>

<div class="container">
    <div class="messages-layout">
        <!-- Konversations-Liste -->
        <div class="conversations-list" id="conversationsList">
            <div class="loading">Lädt...</div>
        </div>
        
        <!-- Chat-Bereich -->
        <div class="chat-area" id="chatArea" style="display:none;">
            <div class="chat-header" id="chatHeader"></div>
            <div class="chat-messages" id="chatMessages"></div>
            <div class="chat-input">
                <input type="text" id="messageInput" placeholder="Nachricht schreiben..." onkeypress="if(event.key==='Enter')sendChatMessage()">
                <button onclick="sendChatMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<style>
.messages-layout {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 20px;
    height: calc(100vh - 150px);
}

.conversations-list, .chat-area {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

.conversation-item {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    gap: 12px;
}

.conversation-item:hover {
    background: var(--input-bg);
}

.conversation-item.active {
    background: var(--input-bg);
}

.conversation-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    flex-shrink: 0;
}

.conversation-info {
    flex: 1;
    min-width: 0;
}

.conversation-name {
    font-weight: 600;
    margin-bottom: 3px;
}

.conversation-last-message {
    font-size: 13px;
    color: var(--text-sec);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.unread-badge {
    background: var(--primary);
    color: white;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
}

.chat-header {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    font-weight: 600;
}

.chat-messages {
    padding: 20px;
    height: calc(100% - 140px);
    overflow-y: auto;
}

.chat-message {
    margin-bottom: 15px;
    display: flex;
    flex-direction: column;
}

.chat-message.own {
    align-items: flex-end;
}

.chat-message-bubble {
    background: var(--input-bg);
    padding: 10px 15px;
    border-radius: 12px;
    max-width: 70%;
}

.chat-message.own .chat-message-bubble {
    background: var(--primary);
    color: white;
}

.chat-message-time {
    font-size: 11px;
    color: var(--text-sec);
    margin-top: 5px;
}

.chat-input {
    padding: 15px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 10px;
}

.chat-input input {
    flex: 1;
    padding: 10px 15px;
    border: 1px solid var(--border);
    border-radius: 20px;
    background: var(--input-bg);
    color: var(--text-main);
    outline: none;
}

.chat-input button {
    background: var(--primary);
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
}

@media (max-width: 768px) {
    .messages-layout {
        grid-template-columns: 1fr;
    }
    .conversations-list {
        display: block;
    }
    .chat-area {
        display: none;
    }
    .chat-area.active {
        display: block;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 100;
    }
}
</style>

<script>
let currentChatUserId = null;
let refreshInterval = null;

function getApiErrorMessage(data, fallback = 'Fehler') {
    const msg = data?.message || fallback;
    if (/zu viele|rate|später erneut/i.test(msg)) {
        return '⏳ ' + msg;
    }
    if (/csrf|nicht autorisiert|unauthorized/i.test(msg)) {
        return '🔒 ' + msg;
    }
    return '❌ ' + msg;
}

document.addEventListener('DOMContentLoaded', () => {
    loadConversations();
    refreshInterval = setInterval(loadConversations, 30000); // Alle 30 Sek. aktualisieren
});

async function loadConversations() {
    try {
        const res = await fetch('api.php?action=get_conversations');
        const data = await res.json();

        if (!res.ok) {
            showToast(getApiErrorMessage(data, 'Fehler beim Laden')); 
            return;
        }
        
        const list = document.getElementById('conversationsList');
        if (!data.success || !data.conversations.length) {
            list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-sec);">Keine Nachrichten</div>';
            return;
        }
        
        list.innerHTML = data.conversations.map(conv => `
            <div class="conversation-item" onclick="openChat('${conv.user.id}', '${conv.user.display_name.replace(/'/g, "\\'")}', '${conv.user.avatar_color}')">
                <div class="conversation-avatar" style="background:${conv.user.avatar_color};">
                    ${conv.user.display_name.charAt(0)}
                </div>
                <div class="conversation-info">
                    <div class="conversation-name">
                        ${conv.user.display_name}
                        ${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
                    </div>
                    <div class="conversation-last-message">
                        ${conv.last_message ? (conv.last_message.from_id === '<?php echo $currentUser['id']; ?>' ? 'Du: ' : '') + conv.last_message.text.substring(0, 50) : 'Keine Nachrichten'}
                    </div>
                </div>
            </div>
        `).join('');
    } catch (e) {
        console.error('Error:', e);
        showToast('❌ Verbindungsfehler');
    }
}

async function openChat(userId, userName, userColor) {
    currentChatUserId = userId;
    document.getElementById('conversationsList').style.display = 'none';
    document.getElementById('chatArea').style.display = 'block';
    document.getElementById('chatHeader').innerHTML = `
        <div style="display:flex;align-items:center;gap:10px;">
            <div class="conversation-avatar" style="background:${userColor};width:35px;height:35px;font-size:14px;">
                ${userName.charAt(0)}
            </div>
            ${userName}
        </div>
        <button onclick="closeChat()" style="background:none;border:none;cursor:pointer;"><i class="fas fa-arrow-left"></i></button>
    `;
    
    loadMessages(userId);
    clearInterval(refreshInterval);
    refreshInterval = setInterval(() => loadMessages(userId), 5000); // Alle 5 Sek. aktualisieren
}

function closeChat() {
    document.getElementById('chatArea').style.display = 'none';
    document.getElementById('conversationsList').style.display = 'block';
    currentChatUserId = null;
    clearInterval(refreshInterval);
    refreshInterval = setInterval(loadConversations, 30000);
}

async function loadMessages(userId) {
    try {
        const res = await fetch(`api.php?action=get_conversation&user_id=${userId}`);
        const data = await res.json();

        if (!res.ok) {
            showToast(getApiErrorMessage(data, 'Fehler beim Laden')); 
            return;
        }
        
        const messagesContainer = document.getElementById('chatMessages');
        if (!data.success) return;
        
        messagesContainer.innerHTML = data.messages.map(msg => `
            <div class="chat-message ${msg.from_id === '<?php echo $currentUser['id']; ?>' ? 'own' : ''}">
                <div class="chat-message-bubble">${msg.text}</div>
                <div class="chat-message-time">${new Date(msg.created_at * 1000).toLocaleTimeString('de-DE', {hour: '2-digit', minute:'2-digit'})}</div>
            </div>
        `).join('');
        
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    } catch (e) {
        console.error('Error:', e);
        showToast('❌ Verbindungsfehler');
    }
}

async function sendChatMessage() {
    const input = document.getElementById('messageInput');
    const text = input.value.trim();
    
    if (!text || !currentChatUserId) return;
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'send_message',
                to_id: currentChatUserId,
                text: text
            })
        });
        
        const data = await res.json();
        if (data.success) {
            input.value = '';
            loadMessages(currentChatUserId);
        } else {
            showToast(getApiErrorMessage(data));
        }
    } catch (e) {
        console.error('Error:', e);
        showToast('❌ Verbindungsfehler');
    }
}

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    document.documentElement.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
    localStorage.setItem('theme', current === 'dark' ? 'light' : 'dark');
}

function showToast(message) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}
</script>

</body>
</html>