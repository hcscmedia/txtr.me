/**
 * Micro News App - Admin JavaScript v4.2 (mit Post-Edit)
 */

let allAnalytics = [];
let allUsers = [];
let allLogs = [];
let selectedPosts = [];
let currentEditPost = null;
let currentEditColor = 'default';

// ==================== ANALYTICS ====================

async function loadAnalytics() {
    const sortBy = document.getElementById('analyticsSort')?.value || 'views';
    
    try {
        const res = await fetch(`api.php?action=get_analytics&sort=${sortBy}`);
        const data = await res.json();
        
        allAnalytics = data.analytics || [];
        renderAnalytics(allAnalytics);
    } catch (e) {
        console.error('Analytics Error:', e);
        document.getElementById('analyticsList').innerHTML = '<div class="error">Fehler beim Laden</div>';
    }
}

function renderAnalytics(analytics) {
    const list = document.getElementById('analyticsList');
    if (!list) return;
    
    if (!analytics.length) {
        list.innerHTML = '<div class="no-data">Keine Posts vorhanden</div>';
        return;
    }
    
    list.innerHTML = analytics.map(post => `
        <div class="analytics-item">
            <div class="analytics-main">
                <div class="analytics-text">${post.text}</div>
                <div class="analytics-meta">
                    <span><i class="fas fa-user"></i> ${post.author}</span>
                    <span><i class="fas fa-clock"></i> ${post.date}</span>
                    ${post.is_pinned ? '<span class="pinned-tag">📌 Angeheftet</span>' : ''}
                </div>
            </div>
            <div class="analytics-stats">
                <div class="stat" title="Aufrufe"><i class="fas fa-eye"></i> ${post.views}</div>
                <div class="stat" title="Likes"><i class="fas fa-heart"></i> ${post.likes}</div>
                <div class="stat" title="Kommentare"><i class="fas fa-comment"></i> ${post.comments}</div>
                <div class="stat" title="Engagement"><i class="fas fa-percent"></i> ${post.engagement_rate}%</div>
            </div>
            <div class="analytics-actions">
                <button class="btn-info" onclick="showPostDetail('${post.id}')" title="Details">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn-primary" onclick="openEditPostModal('${post.id}')" title="Bearbeiten">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-danger" onclick="deletePost('${post.id}')" title="Löschen">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function filterAnalytics() {
    const query = document.getElementById('analyticsSearch')?.value.toLowerCase() || '';
    const filtered = allAnalytics.filter(post => 
        post.text.toLowerCase().includes(query) || 
        post.author.toLowerCase().includes(query)
    );
    renderAnalytics(filtered);
}

// ==================== ✏️ POST EDIT FUNCTIONS ====================

async function openEditPostModal(postId) {
    try {
        const res = await fetch(`api.php?action=get_post_detail&id=${postId}`);
        const data = await res.json();
        
        if (data.success) {
            currentEditPost = data.post;
            currentEditColor = data.post.color || 'default';
            
            // Formulare füllen
            document.getElementById('editPostId').value = data.post.id;
            document.getElementById('editPostText').value = data.post.text.replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
            document.getElementById('editPostLink').value = data.post.link || '';
            document.getElementById('editPostPinned').checked = data.post.pinned || false;
            
            // Info-Felder
            document.getElementById('editPostAuthor').textContent = data.post.author || 'Unknown';
            document.getElementById('editPostDate').textContent = data.post.date || 'Unknown';
            document.getElementById('editPostViews').textContent = data.post.views || 0;
            document.getElementById('editPostLikes').textContent = data.post.likes || 0;
            
            // Farbe setzen
            selectEditColor(currentEditColor);
            
            document.getElementById('editPostModal').classList.add('open');
        } else {
            showToast('❌ Post nicht gefunden');
        }
    } catch (e) {
        console.error('Edit Post Error:', e);
        showToast('❌ Fehler beim Laden');
    }
}

function closeEditPostModal() {
    document.getElementById('editPostModal').classList.remove('open');
    currentEditPost = null;
}

function selectEditColor(color) {
    currentEditColor = color;
    const picker = document.getElementById('editColorPicker');
    if (picker) {
        picker.querySelectorAll('.color-option').forEach(opt => {
            opt.classList.toggle('selected', opt.dataset.color === color);
        });
    }
}

async function saveEditedPost() {
    const postId = document.getElementById('editPostId').value;
    const text = document.getElementById('editPostText').value.trim();
    const link = document.getElementById('editPostLink').value.trim();
    const pinned = document.getElementById('editPostPinned').checked;
    
    if (!text) {
        showToast('❌ Text ist erforderlich');
        return;
    }
    
    if (text.length > 2000) {
        showToast('❌ Text zu lang (max. 2000 Zeichen)');
        return;
    }
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'edit_post',
                id: postId,
                text: text,
                link: link,
                color: currentEditColor,
                pinned: pinned
            })
        });
        
        const data = await res.json();
        if (data.success) {
            showToast('✅ Post aktualisiert');
            closeEditPostModal();
            loadAnalytics(); // Liste aktualisieren
        } else {
            showToast('❌ ' + (data.message || 'Fehler'));
        }
    } catch (e) {
        console.error('Save Edit Error:', e);
        showToast('❌ Verbindungsfehler');
    }
}

async function deletePost(postId) {
    if (!confirm('Diesen Post wirklich löschen?')) return;
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'delete',
                id: postId
            })
        });
        
        const data = await res.json();
        if (data.success) {
            showToast('🗑️ Post gelöscht');
            loadAnalytics();
        } else {
            showToast('❌ ' + (data.message || 'Fehler'));
        }
    } catch (e) {
        showToast('❌ Verbindungsfehler');
    }
}

// ==================== POST DETAIL ====================

async function showPostDetail(postId) {
    try {
        const res = await fetch(`api.php?action=get_post_detail&id=${postId}`);
        const data = await res.json();
        
        if (data.success) {
            const post = data.post;
            const content = document.getElementById('postDetailContent');
            content.innerHTML = `
                <div class="post-detail">
                    <div class="detail-row"><strong>Author:</strong> ${post.author || 'Unknown'}</div>
                    <div class="detail-row"><strong>Datum:</strong> ${post.date || 'Unknown'}</div>
                    <div class="detail-row"><strong>Text:</strong><br>${post.text || ''}</div>
                    <div class="detail-stats">
                        <div class="detail-stat"><i class="fas fa-eye"></i> ${post.views || 0} Aufrufe</div>
                        <div class="detail-stat"><i class="fas fa-heart"></i> ${post.likes || 0} Likes</div>
                        <div class="detail-stat"><i class="fas fa-comment"></i> ${post.comments?.length || 0} Kommentare</div>
                        <div class="detail-stat"><i class="fas fa-flag"></i> ${post.reports?.length || 0} Meldungen</div>
                    </div>
                    ${post.image ? `<img src="uploads/${post.image}" class="detail-image">` : ''}
                    ${post.link ? `<a href="${post.link}" target="_blank" class="detail-link">${post.link}</a>` : ''}
                    ${post.color && post.color !== 'default' ? `<div class="detail-row"><strong>Farbe:</strong> ${post.color}</div>` : ''}
                    ${post.pinned ? `<div class="detail-row"><strong>Status:</strong> 📌 Angeheftet</div>` : ''}
                </div>
            `;
            document.getElementById('postDetailModal').classList.add('open');
        }
    } catch (e) {
        showToast('❌ Fehler beim Laden');
    }
}

function closePostDetailModal() {
    document.getElementById('postDetailModal').classList.remove('open');
}

// ==================== USERS ====================

async function loadUsers() {
    const filter = document.getElementById('userFilter')?.value || 'all';
    
    try {
        const res = await fetch(`api.php?action=get_users&filter=${filter}`);
        const data = await res.json();
        
        allUsers = data.users || [];
        renderUsers(allUsers);
    } catch (e) {
        console.error('Users Error:', e);
        document.getElementById('usersList').innerHTML = '<div class="error">Fehler beim Laden</div>';
    }
}

function renderUsers(users) {
    const list = document.getElementById('usersList');
    if (!list) return;
    
    if (!users.length) {
        list.innerHTML = '<div class="no-data">Keine User gefunden</div>';
        return;
    }
    
    list.innerHTML = users.map(user => `
        <div class="user-item ${user.is_banned ? 'banned' : ''}">
            <div class="user-avatar" style="background:${user.avatar_color || '#1877f2'}">
                ${(user.display_name || 'U').charAt(0)}
            </div>
            <div class="user-info">
                <div class="user-name">${user.display_name} ${user.is_banned ? '<span class="banned-badge">🚫 Gesperrt</span>' : ''}</div>
                <div class="user-username">@${user.username}</div>
                <div class="user-stats">
                    <span>📝 ${user.posts_count || 0} Posts</span>
                    <span>👥 ${user.followers_count || 0} Follower</span>
                </div>
                ${user.banned_at ? `<div class="ban-info">Gesperrt: ${new Date(user.banned_at * 1000).toLocaleDateString()}</div>` : ''}
            </div>
            <div class="user-actions">
                ${user.is_banned 
                    ? `<button class="btn-success" onclick="unbanUser('${user.id}')"><i class="fas fa-check"></i> Entsperren</button>`
                    : `<button class="btn-warning" onclick="openBanModal('${user.id}', '${user.display_name}')"><i class="fas fa-ban"></i> Sperren</button>`
                }
                <button class="btn-info" onclick="showUserPosts('${user.id}')"><i class="fas fa-newspaper"></i> Posts</button>
                <button class="btn-danger" onclick="deleteUser('${user.id}')"><i class="fas fa-trash"></i> Löschen</button>
            </div>
        </div>
    `).join('');
}

function searchUsers() {
    const query = document.getElementById('userSearch')?.value.toLowerCase() || '';
    const filtered = allUsers.filter(user => 
        user.username.toLowerCase().includes(query) || 
        user.display_name.toLowerCase().includes(query)
    );
    renderUsers(filtered);
}

function openBanModal(userId, username) {
    document.getElementById('banUserId').value = userId;
    document.getElementById('banUsername').textContent = username;
    document.getElementById('banReason').value = '';
    document.getElementById('banModal').classList.add('open');
}

function closeBanModal() {
    document.getElementById('banModal').classList.remove('open');
}

async function confirmBan() {
    const userId = document.getElementById('banUserId').value;
    const reason = document.getElementById('banReason').value.trim() || 'Kein Grund angegeben';
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'ban_user',
                user_id: userId,
                reason: reason
            })
        });
        
        const data = await res.json();
        if (data.success) {
            showToast('✅ User gesperrt');
            closeBanModal();
            loadUsers();
        } else {
            showToast('❌ ' + (data.message || 'Fehler'));
        }
    } catch (e) {
        showToast('❌ Verbindungsfehler');
    }
}

async function unbanUser(userId) {
    if (!confirm('User wirklich entsperren?')) return;
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'unban_user',
                user_id: userId
            })
        });
        
        const data = await res.json();
        if (data.success) {
            showToast('✅ User entsperrt');
            loadUsers();
        } else {
            showToast('❌ ' + (data.message || 'Fehler'));
        }
    } catch (e) {
        showToast('❌ Verbindungsfehler');
    }
}

async function deleteUser(userId) {
    if (!confirm('User wirklich löschen? Alle Posts werden ebenfalls gelöscht!')) return;
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'delete_user',
                user_id: userId
            })
        });
        
        const data = await res.json();
        if (data.success) {
            showToast('✅ User gelöscht');
            loadUsers();
        } else {
            showToast('❌ ' + (data.message || 'Fehler'));
        }
    } catch (e) {
        showToast('❌ Verbindungsfehler');
    }
}

function showUserPosts(userId) {
    window.location.href = `profile.php?user_id=${userId}`;
}

// ==================== BULK ACTIONS ====================

async function loadBulkPosts() {
    try {
        const res = await fetch('api.php?action=get_posts');
        const data = await res.json();
        
        allPosts = data.posts || [];
        renderBulkPosts(allPosts);
    } catch (e) {
        console.error('Bulk Posts Error:', e);
        document.getElementById('bulkPostsList').innerHTML = '<div class="error">Fehler beim Laden</div>';
    }
}

function renderBulkPosts(posts) {
    const list = document.getElementById('bulkPostsList');
    if (!list) return;
    
    if (!posts.length) {
        list.innerHTML = '<div class="no-data">Keine Posts vorhanden</div>';
        return;
    }
    
    list.innerHTML = posts.map(post => `
        <div class="bulk-post-item">
            <input type="checkbox" class="bulk-select" value="${post.id}" onchange="updateSelectedCount()">
            <div class="bulk-post-content">
                <div class="bulk-post-text">${post.text.substring(0, 100)}...</div>
                <div class="bulk-post-meta">
                    <span><i class="fas fa-user"></i> ${post.author}</span>
                    <span><i class="fas fa-eye"></i> ${post.views || 0}</span>
                    <span><i class="fas fa-heart"></i> ${post.likes || 0}</span>
                </div>
            </div>
        </div>
    `).join('');
}

function updateSelectedCount() {
    selectedPosts = Array.from(document.querySelectorAll('.bulk-select:checked')).map(cb => cb.value);
}

function selectAllPosts() {
    document.querySelectorAll('.bulk-select').forEach(cb => cb.checked = true);
    updateSelectedCount();
}

function deselectAllPosts() {
    document.querySelectorAll('.bulk-select').forEach(cb => cb.checked = false);
    updateSelectedCount();
}

async function bulkDeleteSelected() {
    if (selectedPosts.length === 0) {
        showToast('❌ Keine Posts ausgewählt');
        return;
    }
    
    if (!confirm(`${selectedPosts.length} Posts wirklich löschen?`)) return;
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'bulk_delete',
                post_ids: selectedPosts
            })
        });
        
        const data = await res.json();
        if (data.success) {
            showToast(`✅ ${data.deleted_count || selectedPosts.length} Posts gelöscht`);
            selectedPosts = [];
            loadBulkPosts();
        } else {
            showToast('❌ ' + (data.message || 'Fehler'));
        }
    } catch (e) {
        showToast('❌ Verbindungsfehler');
    }
}

async function bulkReportSelected() {
    if (selectedPosts.length === 0) {
        showToast('❌ Keine Posts ausgewählt');
        return;
    }
    
    const reason = prompt('Grund für die Meldung:', 'Bulk-Report durch Admin');
    if (!reason) return;
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'bulk_report',
                post_ids: selectedPosts,
                reason: reason
            })
        });
        
        const data = await res.json();
        if (data.success) {
            showToast(`✅ ${data.reported_count || selectedPosts.length} Posts gemeldet`);
            selectedPosts = [];
            loadBulkPosts();
        } else {
            showToast('❌ ' + (data.message || 'Fehler'));
        }
    } catch (e) {
        showToast('❌ Verbindungsfehler');
    }
}

// ==================== ACTIVITY LOG ====================

async function loadActivityLog() {
    const filter = document.getElementById('logFilter')?.value || 'all';
    
    try {
        const res = await fetch(`api.php?action=get_activity_log&filter=${filter}`);
        const data = await res.json();
        
        allLogs = data.logs || [];
        renderActivityLog(allLogs);
    } catch (e) {
        console.error('Activity Log Error:', e);
        document.getElementById('activityLog').innerHTML = '<div class="error">Fehler beim Laden</div>';
    }
}

function renderActivityLog(logs) {
    const list = document.getElementById('activityLog');
    if (!list) return;
    
    if (!logs.length) {
        list.innerHTML = '<div class="no-data">Keine Log-Einträge</div>';
        return;
    }
    
    list.innerHTML = logs.map(log => `
        <div class="log-item">
            <div class="log-icon ${getLogIconClass(log.action)}">
                <i class="${getLogIcon(log.action)}"></i>
            </div>
            <div class="log-content">
                <div class="log-action">${formatAction(log.action)}</div>
                <div class="log-details">
                    <span><i class="fas fa-user"></i> ${log.username}</span>
                    <span><i class="fas fa-clock"></i> ${log.date}</span>
                    ${log.ip_address ? `<span><i class="fas fa-globe"></i> ${log.ip_address}</span>` : ''}
                </div>
                ${log.details ? `<div class="log-meta">${JSON.stringify(log.details)}</div>` : ''}
            </div>
        </div>
    `).join('');
}

function getLogIcon(action) {
    const icons = {
        'user_banned': 'fas fa-ban',
        'user_unbanned': 'fas fa-check-circle',
        'bulk_delete': 'fas fa-trash',
        'posts_deleted': 'fas fa-trash-alt',
        'bulk_report': 'fas fa-flag',
        'post_edited': 'fas fa-edit',
        'default': 'fas fa-info-circle'
    };
    return icons[action] || icons['default'];
}

function getLogIconClass(action) {
    if (action.includes('ban')) return 'log-danger';
    if (action.includes('unban')) return 'log-success';
    if (action.includes('delete')) return 'log-warning';
    if (action.includes('edit')) return 'log-info';
    return 'log-info';
}

function formatAction(action) {
    return action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function filterLogs() {
    const query = document.getElementById('logSearch')?.value.toLowerCase() || '';
    const filtered = allLogs.filter(log => 
        log.action.toLowerCase().includes(query) || 
        log.username.toLowerCase().includes(query) ||
        JSON.stringify(log.details).toLowerCase().includes(query)
    );
    renderActivityLog(filtered);
}

// ==================== INIT ====================

document.addEventListener('DOMContentLoaded', () => {
    console.log('Admin Dashboard loaded', adminStats);
});