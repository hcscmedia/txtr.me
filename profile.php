<?php
require_once 'config.php';

$username = $_GET['user'] ?? '';
if (empty($username)) {
    header('Location: index.php');
    exit;
}

$user = getUserByUsername($username);
if (!$user) {
    header('Location: index.php');
    exit;
}

$currentUser = getCurrentUser();
$posts = getPosts();

// FIXED: Filter posts with proper null checks
$userPosts = array_values(array_filter($posts, function($p) use ($user) {
    // Check author_id first (new posts)
    if (isset($p['author_id']) && $p['author_id'] === $user['id']) {
        return true;
    }
    // Fallback: Check author name (old posts)
    if (isset($p['author']) && $p['author'] === $user['display_name']) {
        return true;
    }
    return false;
}));

// Sort by timestamp
usort($userPosts, fn($a, $b) => ($b['timestamp'] ?? $b['id'] ?? 0) - ($a['timestamp'] ?? $a['id'] ?? 0));

$isFollowing = isFollowing($currentUser['id'], $user['id']);
$isOwnProfile = $currentUser['id'] === $user['id'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1877f2">
    <title>@<?php echo htmlspecialchars($user['username']); ?> - MicroNews</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Bottom Navigation -->
<nav class="bottom-nav">
    <a href="index.php" class="nav-item">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="index.php?feed=following" class="nav-item">
        <i class="fas fa-user-friends"></i>
        <span>Folge ich</span>
    </a>
    <a href="messages.php" class="nav-item">
        <i class="fas fa-envelope"></i>
        <span>Nachrichten</span>
        <?php 
        $unread = getUnreadCount($currentUser['id']);
        if ($unread > 0): ?>
            <span class="nav-badge"><?php echo min($unread, 99); ?></span>
        <?php endif; ?>
    </a>
    <a href="profile.php?user=<?php echo urlencode($currentUser['username']); ?>" class="nav-item <?php echo $isOwnProfile ? 'active' : ''; ?>">
        <i class="fas fa-user"></i>
        <span>Profil</span>
    </a>
</nav>

<main class="profile-page">
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-avatar" style="background:<?php echo htmlspecialchars($user['avatar_color']); ?>;">
            <?php echo htmlspecialchars(strtoupper(substr($user['display_name'], 0, 1))); ?>
        </div>
        <div class="profile-info">
            <h2><?php echo htmlspecialchars($user['display_name']); ?></h2>
            <p class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></p>
            <?php if (!empty($user['bio'])): ?>
                <p class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
            <?php endif; ?>
            <div class="profile-stats">
                <div class="stat">
                    <strong><?php echo count($userPosts); ?></strong>
                    <span>Posts</span>
                </div>
                <div class="stat" onclick="showFollowers()" style="cursor:pointer;">
                    <strong><?php echo $user['followers_count'] ?? 0; ?></strong>
                    <span>Follower</span>
                </div>
                <div class="stat" onclick="showFollowing()" style="cursor:pointer;">
                    <strong><?php echo $user['following_count'] ?? 0; ?></strong>
                    <span>Folgt</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="profile-actions">
        <?php if ($isOwnProfile): ?>
            <button class="btn-primary" onclick="openEditProfileModal()">
                <i class="fas fa-pen"></i> Profil bearbeiten
            </button>
        <?php else: ?>
            <button class="btn-primary" id="followBtn" onclick="toggleFollow('<?php echo htmlspecialchars($user['id']); ?>')">
                <i class="<?php echo $isFollowing ? 'fas' : 'far'; ?> fa-user-plus"></i>
                <span><?php echo $isFollowing ? 'Folgt' : 'Folgen'; ?></span>
            </button>
            <button class="btn-secondary" onclick="openMessageModal('<?php echo htmlspecialchars($user['id']); ?>', '<?php echo htmlspecialchars($user['display_name']); ?>')">
                <i class="fas fa-envelope"></i> Nachricht
            </button>
        <?php endif; ?>
    </div>
    
    <!-- User Posts -->
    <div class="profile-posts">
        <h3><i class="fas fa-newspaper"></i> Posts</h3>
        <div id="feed">
            <?php if (empty($userPosts)): ?>
                <div class="no-posts">
                    <i class="fas fa-inbox"></i>
                    <p>Noch keine Posts</p>
                </div>
            <?php else: ?>
                <?php foreach ($userPosts as $post): ?>
                    <div class="news-card <?php echo !empty($post['pinned']) ? 'pinned' : ''; ?> <?php echo !empty($post['color']) && $post['color'] !== 'default' ? 'color-' . htmlspecialchars($post['color']) : ''; ?>">
                        <div class="card-header">
                            <div class="avatar" style="background:<?php echo htmlspecialchars($user['avatar_color']); ?>;">
                                <?php echo htmlspecialchars(strtoupper(substr($user['display_name'], 0, 1))); ?>
                            </div>
                            <div class="meta-info">
                                <h3><?php echo htmlspecialchars($post['author'] ?? $user['display_name']); ?></h3>
                                <span><?php echo timeAgo($post['timestamp'] ?? $post['id'] ?? time()); ?></span>
                            </div>
                        </div>
                        <div class="card-body"><?php echo nl2br(htmlspecialchars($post['text'])); ?></div>
                        <?php if (!empty($post['image'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($post['image']); ?>" class="card-image" loading="lazy" onerror="this.style.display='none'">
                        <?php endif; ?>
                        <div class="card-actions">
                            <span class="action-stat"><i class="far fa-heart"></i> <?php echo $post['likes'] ?? 0; ?></span>
                            <span class="action-stat"><i class="far fa-comment"></i> <?php echo count($post['comments'] ?? []); ?></span>
                            <span class="action-stat"><i class="fas fa-eye"></i> <?php echo $post['views'] ?? 0; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Edit Profile Modal -->
<div class="modal" id="editProfileModal">
    <div class="modal-content">
        <h2>Profil bearbeiten</h2>
        <label>Anzeigename</label>
        <input type="text" id="editDisplayName" maxlength="30" value="<?php echo htmlspecialchars($user['display_name']); ?>">
        <label>Bio</label>
        <textarea id="editBio" maxlength="200" rows="3" placeholder="Schreibe etwas über dich..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
        <button onclick="saveProfile()">Speichern</button>
        <button class="secondary" onclick="closeEditProfileModal()">Abbrechen</button>
    </div>
</div>

<!-- Message Modal -->
<div class="modal" id="messageModal">
    <div class="modal-content">
        <h2>Nachricht senden</h2>
        <input type="hidden" id="messageToId">
        <textarea id="messageText" maxlength="1000" rows="4" placeholder="Deine Nachricht..."></textarea>
        <button onclick="sendMessage()">Senden</button>
        <button class="secondary" onclick="closeMessageModal()">Abbrechen</button>
    </div>
</div>

<!-- Followers/Following Modal -->
<div class="modal" id="followModal">
    <div class="modal-content">
        <h2 id="followModalTitle">Follower</h2>
        <div id="followList" class="follow-list"></div>
        <button class="secondary" onclick="closeFollowModal()">Schließen</button>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const profileUser = <?php echo json_encode($user); ?>;
const isFollowing = <?php echo $isFollowing ? 'true' : 'false'; ?>;

function toggleFollow(userId) {
    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: isFollowing ? 'unfollow' : 'follow',
            user_id: userId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showToast('❌ ' + (data.message || 'Fehler'));
        }
    })
    .catch(() => showToast('❌ Verbindungsfehler'));
}

function openEditProfileModal() {
    document.getElementById('editProfileModal').classList.add('open');
}

function closeEditProfileModal() {
    document.getElementById('editProfileModal').classList.remove('open');
}

function saveProfile() {
    const displayName = document.getElementById('editDisplayName').value.trim();
    const bio = document.getElementById('editBio').value.trim();
    
    if (!displayName) {
        showToast('❌ Name erforderlich');
        return;
    }
    
    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'update_profile',
            display_name: displayName,
            bio: bio
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('✅ Profil aktualisiert');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('❌ ' + (data.message || 'Fehler'));
        }
    })
    .catch(() => showToast('❌ Verbindungsfehler'));
}

function openMessageModal(toId, name) {
    document.getElementById('messageToId').value = toId;
    document.getElementById('messageModal').classList.add('open');
}

function closeMessageModal() {
    document.getElementById('messageModal').classList.remove('open');
}

function sendMessage() {
    const toId = document.getElementById('messageToId').value;
    const text = document.getElementById('messageText').value.trim();
    
    if (!text) {
        showToast('❌ Nachricht erforderlich');
        return;
    }
    
    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'send_message',
            to_id: toId,
            text: text
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('✅ Nachricht gesendet');
            closeMessageModal();
        } else {
            showToast('❌ ' + (data.message || 'Fehler'));
        }
    })
    .catch(() => showToast('❌ Verbindungsfehler'));
}

function showFollowers() {
    document.getElementById('followModalTitle').textContent = 'Follower';
    loadFollowList('followers');
    document.getElementById('followModal').classList.add('open');
}

function showFollowing() {
    document.getElementById('followModalTitle').textContent = 'Folgt';
    loadFollowList('following');
    document.getElementById('followModal').classList.add('open');
}

function loadFollowList(type) {
    const list = document.getElementById('followList');
    list.innerHTML = '<div class="loading">Lädt...</div>';
    
    fetch(`api.php?action=get_${type}&user_id=${profileUser.id}`)
    .then(res => res.json())
    .then(data => {
        if (data.success && data[type].length > 0) {
            list.innerHTML = data[type].map(user => `
                <div class="follow-item" onclick="window.location.href='profile.php?user=${encodeURIComponent(user.username)}'">
                    <div class="follow-avatar" style="background:${user.avatar_color}">${user.display_name.charAt(0)}</div>
                    <div class="follow-info">
                        <div class="follow-name">${user.display_name}</div>
                        <div class="follow-username">@${user.username}</div>
                    </div>
                </div>
            `).join('');
        } else {
            list.innerHTML = '<div class="no-data">Keine Einträge</div>';
        }
    })
    .catch(() => {
        list.innerHTML = '<div class="error">Fehler beim Laden</div>';
    });
}

function closeFollowModal() {
    document.getElementById('followModal').classList.remove('open');
}

function showToast(message) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// Theme from localStorage
if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
}
</script>

</body>
</html>