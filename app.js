/**
 * Micro News App - JavaScript v3.2
 */

// ==================== STATE ====================
let allPosts = [];
let currentFilter = null;
let filterValue = '';
let allHashtags = {};
let isAdmin = false;
let currentUser = 'User';
let bookmarks = [];
let selectedColor = 'default';
let selectedImageBase64 = '';
let currentLinkPreview = null;
let autoSaveTimer = null;
let linkPreviewTimer = null;
let notifications = [];
let unreadNotifications = 0;
let notificationsPollTimer = null;

// ==================== THEME ====================
const themeToggle = document.querySelector('.theme-toggle i');

if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
    themeToggle?.classList.replace('fa-moon', 'fa-sun');
}

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    const icon = document.querySelector('.theme-toggle i');
    
    if (current === 'dark') {
        document.documentElement.setAttribute('data-theme', 'light');
        localStorage.setItem('theme', 'light');
        icon?.classList.replace('fa-sun', 'fa-moon');
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
        icon?.classList.replace('fa-moon', 'fa-sun');
    }
}

// ==================== SEARCH ====================
function handleSearch() {
    const input = document.getElementById('searchInput');
    const clearBtn = document.getElementById('searchClear');
    const filterValueEl = document.getElementById('filterValue');
    const activeFilterEl = document.getElementById('activeFilter');
    
    if (!input) return;
    
    const query = input.value.toLowerCase().trim();
    
    if (query.length > 0) {
        clearBtn?.classList.add('show');
        currentFilter = 'search';
        filterValue = query;
        if (filterValueEl) filterValueEl.textContent = `"${query}"`;
        activeFilterEl?.classList.add('show');
    } else {
        clearBtn?.classList.remove('show');
        clearFilter();
    }
    
    renderPosts();
}

function clearSearch() {
    const input = document.getElementById('searchInput');
    const clearBtn = document.getElementById('searchClear');
    if (input) input.value = '';
    if (clearBtn) clearBtn.classList.remove('show');
    clearFilter();
}

function filterByHashtag(hashtag) {
    currentFilter = 'hashtag';
    filterValue = hashtag;
    const filterValueEl = document.getElementById('filterValue');
    const activeFilterEl = document.getElementById('activeFilter');
    
    if (filterValueEl) filterValueEl.textContent = hashtag;
    activeFilterEl?.classList.add('show');
    
    document.querySelectorAll('.hashtag-chip').forEach(chip => {
        chip.classList.toggle('active', chip.textContent.trim() === hashtag);
    });
    
    renderPosts();
}

function clearFilter() {
    currentFilter = null;
    filterValue = '';
    document.getElementById('activeFilter')?.classList.remove('show');
    document.querySelectorAll('.hashtag-chip').forEach(chip => {
        chip.classList.remove('active');
    });
    renderPosts();
}

function filterByType(type) {
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.toggle('active', tab === event.target);
    });
    
    if (type === 'all') {
        currentFilter = null;
    } else if (type === 'bookmarks') {
        currentFilter = 'bookmarks';
    } else if (type === 'following') {
        currentFilter = 'following';
    }
    
    renderPosts();
}

function matchesFilter(post) {
    if (currentFilter === 'bookmarks') {
        return bookmarks.includes(post.id);
    }
    if (currentFilter === 'following') {
        // Wird server-seitig gefiltert
        return true;
    }
    if (!currentFilter || currentFilter === 'all') return true;
    
    if (currentFilter === 'search') {
        const rawQuery = (filterValue || '').trim();
        const query = rawQuery.replace(/^[@#]/, '');
        if (!query) return true;

        const username = (post.username || '').toLowerCase();
        const text = (post.text || '').toLowerCase();
        const hashtags = (post.hashtags || []).map(tag => String(tag).toLowerCase());
        const hashtagsAsText = hashtags.join(' ');
        const linkUrl = (post.link || '').toLowerCase();

        if (rawQuery.startsWith('@')) {
            return username.includes(query);
        }

        if (rawQuery.startsWith('#')) {
            return hashtags.some(tag => tag.includes(query));
        }

        const searchText = [username, text, hashtagsAsText, linkUrl].join(' ');
        return searchText.includes(query);
    }
    
    if (currentFilter === 'hashtag') {
        return post.hashtags && post.hashtags.includes(filterValue);
    }
    
    return true;
}

// ==================== TIME AGO ====================
function timeAgo(timestamp) {
    const now = Math.floor(Date.now() / 1000);
    const diff = now - timestamp;
    
    if (diff < 1) return 'gerade eben';
    if (diff < 60) return `vor ${diff} Sek.`;
    if (diff < 3600) return `vor ${Math.round(diff / 60)} Min.`;
    if (diff < 86400) return `vor ${Math.round(diff / 3600)} Std.`;
    if (diff < 604800) return `vor ${Math.round(diff / 86400)} Tagen`;
    
    return new Date(timestamp * 1000).toLocaleDateString('de-DE');
}

// ==================== MODALS ====================
function openLoginModal() {
    document.getElementById('loginModal')?.classList.add('open');
    setTimeout(() => document.getElementById('adminPassword')?.focus(), 100);
}

function closeLoginModal() {
    document.getElementById('loginModal')?.classList.remove('open');
    const pwd = document.getElementById('adminPassword');
    if (pwd) pwd.value = '';
}

function openEditModal(post) {
    if (!post) return;
    
    document.getElementById('editPostId').value = post.id;
    document.getElementById('editPostText').value = post.text.replace(/<[^>]*>/g, '');
    document.getElementById('editPostLink').value = post.link || '';
    document.getElementById('editPostPinned').checked = post.pinned || false;
    const colorSelect = document.getElementById('editPostColor');
    if (colorSelect) colorSelect.value = post.color || 'default';
    document.getElementById('editModal')?.classList.add('open');
}

function closeEditModal() {
    document.getElementById('editModal')?.classList.remove('open');
}

function openUsernameModal() {
    document.getElementById('usernameInput').value = currentUser;
    document.getElementById('usernameModal')?.classList.add('open');
}

function closeUsernameModal() {
    document.getElementById('usernameModal')?.classList.remove('open');
}

function openReportModal(postId) {
    document.getElementById('reportPostId').value = postId;
    document.getElementById('reportModal')?.classList.add('open');
}

function closeReportModal() {
    document.getElementById('reportModal')?.classList.remove('open');
    document.getElementById('reportPostId').value = '';
}

function openDashboardModal() {
    loadDashboardStats();
    document.getElementById('dashboardModal')?.classList.add('open');
}

function closeDashboardModal() {
    document.getElementById('dashboardModal')?.classList.remove('open');
}

// ==================== TOAST ====================
function showToast(message) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

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

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function notificationText(notification) {
    const actor = notification?.actor_name || 'Jemand';
    const meta = notification?.meta || {};

    switch (notification?.type) {
        case 'follow':
            return `${actor} folgt dir jetzt`;
        case 'like':
            return `${actor} hat deinen Post geliked`;
        case 'comment':
            return `${actor} hat deinen Post kommentiert`;
        case 'message':
            return `${actor} hat dir eine Nachricht gesendet`;
        default:
            return `${actor} hat eine neue Aktivität ausgelöst`;
    }
}

function notificationMetaText(notification) {
    const meta = notification?.meta || {};
    if (notification?.type === 'comment' && meta.comment_preview) {
        return `„${meta.comment_preview}“`;
    }
    if (notification?.type === 'message' && meta.message_preview) {
        return `„${meta.message_preview}“`;
    }
    if (notification?.type === 'like' && meta.post_preview) {
        return `Post: „${meta.post_preview}“`;
    }
    return '';
}

function updateNotificationBadge() {
    const badge = document.getElementById('notificationBadge');
    if (!badge) return;

    if (unreadNotifications > 0) {
        badge.textContent = unreadNotifications > 99 ? '99+' : String(unreadNotifications);
        badge.classList.add('show');
    } else {
        badge.textContent = '';
        badge.classList.remove('show');
    }
}

function renderNotifications() {
    const list = document.getElementById('notificationsList');
    if (!list) return;

    if (!notifications.length) {
        list.innerHTML = '<div class="notification-empty">Keine Benachrichtigungen</div>';
        return;
    }

    list.innerHTML = notifications.map(n => {
        const text = escapeHtml(notificationText(n));
        const meta = escapeHtml(notificationMetaText(n));
        const createdAt = Number(n.created_at || 0);
        const timeLabel = createdAt > 0 ? timeAgo(createdAt) : '';
        const unreadClass = n.read ? '' : ' unread';
        const targetUrl = n.type === 'message' ? 'messages.php' : 'index.php';

        return `
            <a href="${targetUrl}" class="notification-item${unreadClass}">
                <div class="notification-text">${text}</div>
                ${meta ? `<div class="notification-meta">${meta}</div>` : ''}
                <div class="notification-time">${escapeHtml(timeLabel)}</div>
            </a>
        `;
    }).join('');
}

async function loadNotifications(showError = false) {
    try {
        const res = await fetch('api.php?action=get_notifications&limit=30');
        const data = await res.json();

        if (data.success) {
            notifications = data.notifications || [];
            unreadNotifications = data.unread_count || 0;
            updateNotificationBadge();
            renderNotifications();
        } else if (showError) {
            showToast(getApiErrorMessage(data));
        }
    } catch (e) {
        if (showError) {
            showToast('❌ Benachrichtigungen konnten nicht geladen werden');
        }
    }
}

function openNotificationsModal() {
    document.getElementById('notificationsModal')?.classList.add('open');
    loadNotifications(true);
}

function closeNotificationsModal() {
    document.getElementById('notificationsModal')?.classList.remove('open');
}

async function markNotificationsRead() {
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'mark_notifications_read' })
        });
        const data = await res.json();
        if (data.success) {
            unreadNotifications = 0;
            notifications = notifications.map(n => ({ ...n, read: true }));
            updateNotificationBadge();
            renderNotifications();
            showToast('✅ Benachrichtigungen als gelesen markiert');
        } else {
            showToast(getApiErrorMessage(data));
        }
    } catch (e) {
        showToast('❌ Verbindungsfehler');
    }
}

// ==================== COLOR PICKER ====================
function selectColor(color) {
    selectedColor = color;
    document.querySelectorAll('.color-option').forEach(opt => {
        opt.classList.toggle('selected', opt.dataset.color === color);
    });
}

// ==================== IMAGE ====================
function handleImageSelect(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    
    if (file.size > 5 * 1024 * 1024) {
        showToast('❌ Bild ist zu groß (max. 5MB)');
        event.target.value = '';
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        selectedImageBase64 = e.target?.result || '';
        const preview = document.getElementById('previewImg');
        const previewContainer = document.getElementById('imagePreview');
        if (preview) preview.src = selectedImageBase64;
        if (previewContainer) previewContainer.classList.add('show');
    };
    reader.onerror = function() {
        showToast('❌ Fehler beim Laden des Bildes');
    };
    reader.readAsDataURL(file);
}

function removeImage() {
    selectedImageBase64 = '';
    const input = document.getElementById('imageInput');
    const preview = document.getElementById('imagePreview');
    if (input) input.value = '';
    if (preview) preview.classList.remove('show');
}

// ==================== LINK PREVIEW ====================
function isValidUrl(string) {
    try {
        new URL(string.startsWith('http') ? string : 'https://' + string);
        return true;
    } catch (_) {
        return false;
    }
}

function handleLinkInput() {
    const input = document.getElementById('postLink');
    if (!input) return;
    
    const url = input.value.trim();
    clearTimeout(linkPreviewTimer);
    
    const previewContainer = document.getElementById('linkPreview');
    
    if (!url || url.length < 10) {
        previewContainer?.classList.remove('show');
        currentLinkPreview = null;
        return;
    }
    
    linkPreviewTimer = setTimeout(async () => {
        await fetchLinkPreview(url);
    }, 1500);
}

async function fetchLinkPreview(url) {
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
        url = 'https://' + url;
    }
    
    try {
        const encodedUrl = encodeURIComponent(url);
        const res = await fetch(`api.php?action=get_link_preview&url=${encodedUrl}`);
        
        if (!res.ok) {
            throw new Error('Network error');
        }
        
        const data = await res.json();
        
        if (data.success && data.preview) {
            currentLinkPreview = data.preview;
            displayLinkPreview(data.preview);
        } else {
            currentLinkPreview = {
                title: 'Link',
                description: url,
                image: '',
                url: url
            };
            displayLinkPreview(currentLinkPreview);
        }
    } catch (e) {
        console.error('Link Preview Error:', e);
        currentLinkPreview = {
            title: 'Link',
            description: url,
            image: '',
            url: url
        };
        displayLinkPreview(currentLinkPreview);
    }
}

function displayLinkPreview(preview) {
    const container = document.getElementById('linkPreview');
    const content = container?.querySelector('.link-preview-content');
    if (!container || !content) return;
    
    const imageHtml = preview.image 
        ? `<img src="${preview.image}" alt="" class="link-preview-image" onerror="this.style.display='none'">`
        : '<div class="link-preview-image" style="display:flex;align-items:center;justify-content:center;background:var(--input-bg);"><i class="fas fa-link" style="color:var(--text-sec);font-size:24px;"></i></div>';
    
    content.innerHTML = `
        ${imageHtml}
        <div class="link-preview-info">
            <div class="link-preview-title">${preview.title || 'Link'}</div>
            <div class="link-preview-description">${preview.description || ''}</div>
            <div class="link-preview-url">${preview.url || ''}</div>
        </div>
    `;
    
    container.classList.add('show');
}

function removeLinkPreview() {
    const container = document.getElementById('linkPreview');
    const linkInput = document.getElementById('postLink');
    
    container?.classList.remove('show');
    if (linkInput) linkInput.value = '';
    currentLinkPreview = null;
}

// ==================== AUTO-SAVE ====================
function initAutoSave() {
    const textarea = document.getElementById('postText');
    const linkInput = document.getElementById('postLink');
    
    if (textarea) {
        textarea.addEventListener('input', () => {
            clearTimeout(autoSaveTimer);
            showAutoSaveStatus('saving');
            
            autoSaveTimer = setTimeout(() => {
                saveDraft();
            }, 2000);
        });
    }
    
    if (linkInput) {
        linkInput.addEventListener('input', () => {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                saveDraft();
            }, 2000);
        });
    }
    
    loadDraft();
}

function showAutoSaveStatus(status) {
    const el = document.getElementById('autoSaveStatus');
    if (!el) return;
    
    if (status === 'saving') {
        el.classList.add('show', 'saving');
        el.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Speichert...</span>';
    } else if (status === 'saved') {
        el.classList.add('show');
        el.classList.remove('saving');
        el.innerHTML = '<i class="fas fa-check"></i> <span>Entwurf gespeichert</span>';
        
        setTimeout(() => {
            el.classList.remove('show');
        }, 3000);
    }
}

async function saveDraft() {
    const text = document.getElementById('postText')?.value || '';
    const link = document.getElementById('postLink')?.value || '';
    const color = selectedColor;
    
    if (!text && !link) return;
    
    try {
        await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'save_draft',
                text,
                link,
                color
            })
        });
        
        showAutoSaveStatus('saved');
    } catch (e) {
        console.error('Auto-Save Error:', e);
    }
}

async function loadDraft() {
    try {
        const res = await fetch('api.php?action=load_draft');
        const data = await res.json();
        
        if (data.success && data.draft) {
            const textarea = document.getElementById('postText');
            const linkInput = document.getElementById('postLink');
            
            if (textarea && data.draft.text) {
                textarea.value = data.draft.text;
            }
            if (linkInput && data.draft.link) {
                linkInput.value = data.draft.link;
                handleLinkInput();
            }
            if (data.draft.color) {
                selectColor(data.draft.color);
            }
        }
    } catch (e) {
        console.error('Draft Load Error:', e);
    }
}

// ==================== EMOJI PICKER ====================
const commonEmojis = [
    '😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂',
    '🙂', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗',
    '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭',
    '👍', '👎', '👏', '🙌', '👋', '🤟', '🤘', '👌',
    '❤️', '🧡', '💛', '💚', '💙', '💜', '🔥', '✨',
    '⭐', '🌟', '💫', '🎉', '🎊', '📱', '💻', '📷'
];

function initEmojiPicker() {
    const grid = document.querySelector('.emoji-grid');
    if (!grid) return;
    
    grid.innerHTML = commonEmojis.map(emoji => 
        `<span class="emoji-item" onclick="insertEmoji('${emoji}')">${emoji}</span>`
    ).join('');
}

function toggleEmojiPicker() {
    const picker = document.getElementById('emojiPicker');
    picker?.classList.toggle('show');
}

function insertEmoji(emoji) {
    const textarea = document.getElementById('postText');
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    
    textarea.value = text.substring(0, start) + emoji + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
    textarea.focus();
    
    document.getElementById('emojiPicker')?.classList.remove('show');
}

// ==================== API CALLS ====================
function buildJsonHeaders(includeCsrf = false) {
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    };
    if (includeCsrf && window.csrfToken) {
        headers['X-CSRF-Token'] = window.csrfToken;
    }
    return headers;
}

async function loadPosts() {
    try {
        const res = await fetch('api.php?action=get_posts');
        
        if (!res.ok) {
            throw new Error('Network error: ' + res.status);
        }
        
        const data = await res.json();
        
        allPosts = data.posts || [];
        allHashtags = data.hashtags || {};
        isAdmin = data.isAdmin || false;
        currentUser = data.username || 'User';
        bookmarks = data.bookmarks || [];
        
        // Admin-Status von window oder API
        if (typeof window.isAdmin !== 'undefined') {
            isAdmin = window.isAdmin;
        }
        
        updateUsernameDisplay();
        renderHashtagCloud();
        renderPosts();
    } catch (e) {
        console.error('Load Posts Error:', e);
        showToast('❌ Fehler beim Laden der Posts');
    }
}

function updateUsernameDisplay() {
    const span = document.getElementById('currentUsername');
    if (span) span.textContent = currentUser;
}

function renderHashtagCloud() {
    const cloud = document.getElementById('hashtagCloud');
    const list = document.getElementById('hashtagList');
    
    if (!cloud || !list) return;
    
    if (Object.keys(allHashtags).length === 0) {
        cloud.classList.remove('show');
        return;
    }
    
    cloud.classList.add('show');
    list.innerHTML = '';
    
    const topHashtags = Object.entries(allHashtags)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 15);
    
    topHashtags.forEach(([tag, count]) => {
        const chip = document.createElement('div');
        chip.className = 'hashtag-chip';
        if (tag === filterValue && currentFilter === 'hashtag') {
            chip.classList.add('active');
        }
        chip.innerHTML = `${tag} <span class="hashtag-count">${count}</span>`;
        chip.onclick = () => filterByHashtag(tag);
        list.appendChild(chip);
    });
}

function renderPosts() {
    const feed = document.getElementById('feed');
    const noResults = document.getElementById('noResults');
    if (!feed) return;
    
    feed.innerHTML = '';
    const filteredPosts = allPosts.filter(matchesFilter);
    
    if (noResults) {
        noResults.classList.toggle('show', filteredPosts.length === 0);
    }
    
    filteredPosts.forEach(post => {
        trackView(post.id);
        
        const imageHtml = post.image 
            ? `<img src="uploads/${post.image}" class="card-image" alt="Post Bild" loading="lazy" onerror="this.style.display='none'">` 
            : '';
        
        const linkHtml = post.link 
            ? `<a href="${post.link}" target="_blank" rel="noopener" class="card-link"><i class="fas fa-link"></i> ${post.link}</a>` 
            : '';
        
        let linkPreviewHtml = '';
        if (post.link_preview) {
            const previewImg = post.link_preview.image 
                ? `<img src="${post.link_preview.image}" alt="" class="link-preview-image" onerror="this.style.display='none'">`
                : '<div class="link-preview-image" style="display:flex;align-items:center;justify-content:center;background:var(--input-bg);"><i class="fas fa-link" style="color:var(--text-sec);font-size:24px;"></i></div>';
            
            linkPreviewHtml = `
                <div class="link-preview show" style="margin:10px 15px;">
                    <div class="link-preview-content">
                        ${previewImg}
                        <div class="link-preview-info">
                            <div class="link-preview-title">${post.link_preview.title || 'Link'}</div>
                            <div class="link-preview-description">${post.link_preview.description || ''}</div>
                            <div class="link-preview-url">${post.link_preview.url || ''}</div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        let textWithHashtags = post.text;
        textWithHashtags = textWithHashtags.replace(/(#\w+)/g, '<span class="hashtag" onclick="filterByHashtag(\'$1\')">$1</span>');
        
        const hashtagsHtml = (post.hashtags && post.hashtags.length > 0)
            ? `<div class="card-hashtags">${post.hashtags.map(tag => `<span class="card-hashtag" onclick="filterByHashtag('${tag}')">${tag}</span>`).join(' ')}</div>`
            : '';
        
        const pinnedBadge = post.pinned ? '<span class="pinned-badge">📌 Angeheftet</span>' : '';
        const editedInfo = post.edited ? `<div class="edited">bearbeitet am ${post.editedAt}</div>` : '';
        const authorInitial = (post.author || 'U').charAt(0).toUpperCase();
        const isBookmarked = bookmarks.includes(post.id);
        const colorClass = (post.color && post.color !== 'default') ? `color-${post.color}` : '';
        
        let commentsHtml = '';
        if (post.comments && post.comments.length > 0) {
            commentsHtml = post.comments.map(c => {
                const commentAuthor = (c.author || 'U').charAt(0).toUpperCase();
                return `
                    <div class="comment-item">
                        <div class="avatar" style="width:30px;height:30px;font-size:12px;">${commentAuthor}</div>
                        <div>
                            <div class="comment-bubble">
                                <div class="comment-meta">${c.author || 'User'} • ${timeAgo(c.timestamp || c.id)}</div>
                                ${c.text}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        const html = `
            <div class="news-card ${isAdmin ? 'admin-mode' : ''} ${post.pinned ? 'pinned' : ''} ${colorClass}" id="post-${post.id}">
                <button class="bookmark-btn ${isBookmarked ? 'active' : ''}" onclick="toggleBookmark('${post.id}', this)" title="Lesezeichen" aria-label="Lesezeichen">
                    <i class="${isBookmarked ? 'fas' : 'far'} fa-bookmark"></i>
                </button>
                
                <button class="pin-btn ${post.pinned ? 'pinned' : ''}" onclick="togglePin('${post.id}')" title="Anheften" aria-label="Anheften">
                    <i class="fas fa-thumbtack"></i>
                </button>
                <button class="edit-btn" onclick="openEditModal(${JSON.stringify(post).replace(/"/g, '&quot;')})" title="Bearbeiten" aria-label="Bearbeiten">
                    <i class="fas fa-pen"></i>
                </button>
                <button class="delete-btn" onclick="deletePost('${post.id}')" title="Löschen" aria-label="Löschen">
                    <i class="fas fa-trash"></i>
                </button>
                
                <div class="card-header">
                    <div class="avatar" style="background:${post.author_color || '#1877f2'};">${authorInitial}</div>
                    <div class="meta-info">
                        <h3>${post.author || 'User'} ${pinnedBadge}</h3>
                        <a href="profile.php?user=${encodeURIComponent(post.author_username || 'user')}" style="color:var(--primary);text-decoration:none;font-size:12px;">@${post.author_username || 'user'}</a>
                        <span>${timeAgo(post.timestamp || post.id)}</span>
                        ${editedInfo}
                    </div>
                </div>
                
                <div class="card-body">${textWithHashtags}</div>
                
                ${imageHtml}
                ${linkPreviewHtml}
                ${linkHtml}
                ${hashtagsHtml}
                
                <div class="card-actions">
                    <button class="action-btn" onclick="likePost('${post.id}', this)" aria-label="Like">
                        <i class="far fa-heart"></i> <span>${post.likes}</span>
                    </button>
                    <button class="action-btn" onclick="toggleComments('${post.id}')" aria-label="Kommentare">
                        <i class="far fa-comment"></i> <span>${post.comments ? post.comments.length : 0}</span>
                    </button>
                    <button class="action-btn" onclick="sharePost('${post.id}')" aria-label="Teilen">
                        <i class="fas fa-share"></i>
                    </button>
                    <button class="action-btn" onclick="openReportModal('${post.id}')" aria-label="Melden">
                        <i class="fas fa-flag"></i>
                    </button>
                </div>
                
                <div style="padding: 5px 15px; display: flex; justify-content: space-between; align-items: center;">
                    <div class="view-counter">
                        <i class="fas fa-eye"></i> ${post.views || 0} Aufrufe
                    </div>
                    ${post.reports && post.reports.length > 0 && isAdmin ? `<span style="font-size:11px;color:var(--danger)">⚠️ ${post.reports.length} Meldungen</span>` : ''}
                </div>

                <div class="comments-section" id="comments-${post.id}">
                    <div class="comments-list">${commentsHtml}</div>
                    <div class="comment-input-area">
                        <input type="text" id="input-${post.id}" placeholder="Kommentar schreiben..." maxlength="500" onkeypress="if(event.key==='Enter')addComment('${post.id}')">
                        <button class="send-btn" onclick="addComment('${post.id}')" aria-label="Senden"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
        `;
        
        feed.innerHTML += html;
    });
}

// View Tracking
const viewTracker = {};
function trackView(postId) {
    if (viewTracker[postId]) return;
    viewTracker[postId] = true;
    
    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'view', id: postId })
    }).catch(() => {});
}

// ==================== AUTH ====================
async function login() {
    const password = document.getElementById('adminPassword')?.value || '';
    const username = document.getElementById('loginUsername')?.value || '';
    
    if (!password) {
        showToast('❌ Passwort erforderlich');
        return;
    }
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'login', password: password, username: username })
        });
        
        const data = await res.json();
        if (data.success) {
            closeLoginModal();
            showToast('✅ Erfolgreich eingeloggt');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(getApiErrorMessage(data));
        }
    } catch (e) {
        showToast('❌ Verbindungsfehler');
        console.error('Login Error:', e);
    }
}

async function logout() {
    try {
        await fetch('api.php', {
            method: 'POST',
            headers: buildJsonHeaders(true),
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'logout' })
        });
        location.reload();
    } catch (e) {
        showToast('❌ Fehler beim Ausloggen');
        console.error('Logout Error:', e);
    }
}

async function setUsername() {
    const username = document.getElementById('usernameInput')?.value.trim() || '';
    if (!username) {
        showToast('❌ Name erforderlich');
        return;
    }
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'set_username', username: username })
        });
        
        const data = await res.json();
        if (data.success) {
            closeUsernameModal();
            currentUser = data.username;
            updateUsernameDisplay();
            showToast('✅ Name aktualisiert');
        } else {
            showToast(getApiErrorMessage(data));
        }
    } catch (e) {
        showToast('❌ Verbindungsfehler');
        console.error('Set Username Error:', e);
    }
}

// ==================== POST ACTIONS ====================
async function submitPost() {
    const text = document.getElementById('postText')?.value.trim() || '';
    const link = document.getElementById('postLink')?.value.trim() || '';
    const pinned = document.getElementById('postPinned')?.checked || false;
    const btn = document.getElementById('submitBtn');
    const btnText = btn?.querySelector('.btn-text');
    const btnLoading = btn?.querySelector('.btn-loading');
    
    if (!text) {
        showToast('❌ Text ist erforderlich');
        return;
    }
    
    if (text.length > 2000) {
        showToast('❌ Text zu lang (max. 2000 Zeichen)');
        return;
    }
    
    if (btn) {
        btn.disabled = true;
        btn.classList.add('loading');
        if (btnText) btnText.style.display = 'none';
        if (btnLoading) btnLoading.style.display = 'inline-flex';
    }
    
    try {
        const requestBody = {
            action: 'create',
            text: text,
            link: link,
            pinned: isAdmin && pinned,
            color: selectedColor
        };
        
        if (selectedImageBase64) {
            requestBody.image = selectedImageBase64;
        }
        
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(requestBody)
        });
        
        const data = await res.json();
        
        if (!res.ok) {
            throw new Error(data.message || 'Fehler beim Erstellen');
        }
        
        if (data.success) {
            document.getElementById('postText').value = '';
            document.getElementById('postLink').value = '';
            removeImage();
            removeLinkPreview();
            selectedColor = 'default';
            document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('selected'));
            
            showToast('✅ Post erstellt');
            loadPosts();
        } else {
            showToast('❌ ' + (data.message || 'Fehler beim Erstellen'));
        }
    } catch (e) {
        console.error('Submit Post Error:', e);
        showToast('❌ Fehler: ' + e.message);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.classList.remove('loading');
            if (btnText) btnText.style.display = 'inline';
            if (btnLoading) btnLoading.style.display = 'none';
        }
    }
}

async function updatePost() {
    const postId = document.getElementById('editPostId')?.value || '';
    const text = document.getElementById('editPostText')?.value.trim() || '';
    const link = document.getElementById('editPostLink')?.value.trim() || '';
    const pinned = document.getElementById('editPostPinned')?.checked || false;
    const color = document.getElementById('editPostColor')?.value || 'default';
    
    if (!text) {
        showToast('❌ Text ist erforderlich');
        return;
    }
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: buildJsonHeaders(true),
            body: JSON.stringify({
                action: 'edit_post',
                id: postId,
                text: text,
                link: link,
                pinned: pinned,
                color: color
            })
        });
        
        const data = await res.json();
        if (data.success) {
            closeEditModal();
            showToast('✅ Post aktualisiert');
            loadPosts();
        } else {
            showToast(getApiErrorMessage(data));
        }
    } catch (e) {
        showToast('❌ Verbindungsfehler');
        console.error('Update Error:', e);
    }
}

async function togglePin(id) {
    if (!isAdmin) return;
    
    const post = allPosts.find(p => p.id === id);
    if (!post) return;
    
    try {
        await fetch('api.php', {
            method: 'POST',
            headers: buildJsonHeaders(true),
            body: JSON.stringify({
                action: 'edit_post',
                id: id,
                text: post.text,
                link: post.link || '',
                pinned: !post.pinned,
                color: post.color || 'default'
            })
        });
        
        showToast(post.pinned ? '📌 Anheftung entfernt' : '📌 Post angeheftet');
        loadPosts();
    } catch (e) {
        showToast('❌ Fehler beim Anheften');
    }
}

async function deletePost(id) {
    if (!confirm('Diesen Post wirklich löschen?')) return;
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: buildJsonHeaders(true),
            body: JSON.stringify({ action: 'delete', id: id })
        });
        
        const data = await res.json();
        if (data.success) {
            showToast('🗑️ Post gelöscht');
            loadPosts();
        } else {
            showToast(getApiErrorMessage(data, 'Nicht autorisiert'));
        }
    } catch (e) {
        showToast('❌ Fehler beim Löschen');
        console.error('Delete Error:', e);
    }
}

async function likePost(id, btn) {
    if (btn.classList.contains('liked')) return;
    
    const icon = btn.querySelector('i');
    const countSpan = btn.querySelector('span');
    
    btn.classList.add('liked');
    icon?.classList.replace('far', 'fas');
    if (countSpan) countSpan.innerText = parseInt(countSpan.innerText) + 1;
    
    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'like', id: id })
    }).catch(() => {});
}

function toggleComments(id) {
    const section = document.getElementById(`comments-${id}`);
    section?.classList.toggle('open');
}

async function addComment(id) {
    const input = document.getElementById(`input-${id}`);
    const text = input?.value.trim() || '';
    
    if (!text) return;
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'comment', id: id, comment: text })
        });
        
        const data = await res.json();
        if (data.success) {
            input.value = '';
            loadPosts();
            setTimeout(() => {
                document.getElementById(`comments-${id}`)?.classList.add('open');
            }, 100);
        } else {
            showToast(getApiErrorMessage(data));
        }
    } catch (e) {
        showToast('❌ Verbindungsfehler');
        console.error('Comment Error:', e);
    }
}

async function toggleBookmark(id, btn) {
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'bookmark', id: id })
        });
        
        const data = await res.json();
        if (data.success) {
            btn?.classList.toggle('active', data.bookmarked);
            const icon = btn?.querySelector('i');
            icon?.classList.toggle('fas', data.bookmarked);
            icon?.classList.toggle('far', !data.bookmarked);
            showToast(data.message || 'OK');
            
            if (data.bookmarked) {
                bookmarks.push(id);
            } else {
                bookmarks = bookmarks.filter(b => b !== id);
            }
        }
    } catch (e) {
        showToast('❌ Fehler bei Lesezeichen');
    }
}

async function submitReport() {
    const postId = document.getElementById('reportPostId')?.value || '';
    const reason = document.querySelector('input[name="reportReason"]:checked')?.value || 'Spam';
    
    if (!postId) return;
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'report', id: postId, reason: reason })
        });
        
        const data = await res.json();
        if (data.success) {
            closeReportModal();
            showToast('✅ Post gemeldet');
        } else {
            showToast(getApiErrorMessage(data));
        }
    } catch (e) {
        showToast('❌ Verbindungsfehler');
    }
}

function sharePost(id) {
    const post = allPosts.find(p => p.id === id);
    if (!post) return;
    
    const shareText = post.text.replace(/<[^>]*>/g, '').substring(0, 100);
    
    if (navigator.share) {
        navigator.share({
            title: 'Micro News',
            text: shareText,
            url: window.location.href
        }).catch(() => {});
    } else {
        navigator.clipboard.writeText(window.location.href);
        showToast('📋 Link kopiert!');
    }
}

async function loadDashboardStats() {
    try {
        const res = await fetch('api.php?action=get_stats');
        const data = await res.json();
        
        if (data.success) {
            document.getElementById('statPosts').textContent = data.stats.totalPosts;
            document.getElementById('statLikes').textContent = data.stats.totalLikes;
            document.getElementById('statComments').textContent = data.stats.totalComments;
            document.getElementById('statImages').textContent = data.stats.totalImages;
            document.getElementById('statPinned').textContent = data.stats.pinnedPosts;
            document.getElementById('statViews').textContent = data.stats.totalViews;
            document.getElementById('statReports').textContent = data.stats.totalReports;
        }
    } catch (e) {
        console.error('Dashboard Error:', e);
    }
}

function exportData() {
    window.location.href = 'api.php?action=export_data';
    showToast('📥 Download startet...');
}

// ==================== INIT ====================
document.addEventListener('DOMContentLoaded', () => {
    loadPosts();
    loadNotifications();
    initAutoSave();
    initEmojiPicker();

    notificationsPollTimer = setInterval(() => {
        loadNotifications();
    }, 30000);
    
    // Admin-Status von window übernehmen
    if (typeof window.isAdmin !== 'undefined') {
        isAdmin = window.isAdmin;
    }
    
    // Event Listeners
    document.getElementById('adminPassword')?.addEventListener('keypress', e => {
        if (e.key === 'Enter') login();
    });
    
    document.getElementById('usernameInput')?.addEventListener('keypress', e => {
        if (e.key === 'Enter') setUsername();
    });
    
    document.getElementById('postLink')?.addEventListener('input', handleLinkInput);
    
    // Modal Close on Outside Click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) {
                modal.classList.remove('open');
            }
        });
    });
    
    // Emoji Picker Close
    document.addEventListener('click', e => {
        const picker = document.getElementById('emojiPicker');
        const btn = document.querySelector('.emoji-btn');
        if (picker && !picker.contains(e.target) && !btn?.contains(e.target)) {
            picker.classList.remove('show');
        }
    });
});