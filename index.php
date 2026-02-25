<?php
/**
 * Micro News App - Hauptseite v4.1 (ERROR-FREE)
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

// Session prüfen
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$currentUser = getCurrentUser();
if (!$currentUser) {
    $currentUser = createUser('user_' . substr(bin2hex(random_bytes(4)), 0, 8));
}

$currentUserId = $currentUser['id'] ?? 'unknown';
$currentUsername = $currentUser['username'] ?? 'user';
$currentDisplayName = $currentUser['display_name'] ?? 'User';
$currentAvatarColor = $currentUser['avatar_color'] ?? '#1877f2';
$isAdminUser = isAdmin();
$unreadCount = !empty($currentUserId) ? getUnreadCount($currentUserId) : 0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1877f2">
    <title>Micro News App</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Header -->
<header>
    <div class="header-top">
        <div class="header-left">
            <h1>MicroNews</h1>
            <?php if ($isAdminUser): ?>
                <span class="admin-badge">ADMIN</span>
                <a href="admin.php" class="dashboard-btn" title="Dashboard">
                    <i class="fas fa-chart-line"></i>
                </a>
            <?php endif; ?>
        </div>
        <div class="header-right">
            <button class="theme-toggle" onclick="toggleTheme()">
                <i class="fas fa-moon"></i>
            </button>
            <?php if ($isAdminUser): ?>
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            <?php else: ?>
                <button class="login-btn" onclick="openLoginModal()">
                    <i class="fas fa-shield-alt"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="search-container">
        <input type="text" class="search-input" id="searchInput" placeholder="News durchsuchen..." oninput="handleSearch()">
        <i class="fas fa-search search-icon"></i>
        <button class="search-clear" id="searchClear" onclick="clearSearch()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="active-filter" id="activeFilter">
        <span>Filter: <strong id="filterValue"></strong></span>
        <button onclick="clearFilter()">Zurücksetzen</button>
    </div>
</header>

<!-- Bottom Nav -->
<nav class="bottom-nav">
    <a href="index.php" class="nav-item active">
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
        <?php if ($unreadCount > 0): ?>
            <span class="nav-badge"><?php echo min($unreadCount, 99); ?></span>
        <?php endif; ?>
    </a>
    <a href="profile.php?user=<?php echo urlencode($currentUsername); ?>" class="nav-item">
        <i class="fas fa-user"></i>
        <span>Profil</span>
    </a>
</nav>

<div class="container">
    <!-- Feed Tabs -->
    <div class="filter-tabs">
        <div class="filter-tab active" onclick="filterByType('all')">Alle</div>
        <div class="filter-tab" onclick="filterByType('following')">👥 Folgende</div>
        <div class="filter-tab" onclick="filterByType('bookmarks')">🔖 Lesezeichen</div>
    </div>

    <!-- Create Post -->
    <div class="create-post">
        <div class="username-display">
            <div class="avatar" style="background:<?php echo htmlspecialchars($currentAvatarColor); ?>;">
                <?php echo htmlspecialchars(strtoupper(substr($currentDisplayName, 0, 1))); ?>
            </div>
            <span id="currentUsername"><?php echo htmlspecialchars($currentDisplayName); ?></span>
            <button class="edit-username" onclick="openUsernameModal()">
                <i class="fas fa-pen"></i>
            </button>
        </div>
        
        <textarea id="postText" rows="3" placeholder="Was gibt's Neues?" maxlength="2000"></textarea>

        <div class="auto-save-status" id="autoSaveStatus"></div>
        
        <div class="color-picker">
            <div class="color-option color-default selected" data-color="default" onclick="selectColor('default')"></div>
            <div class="color-option color-blue" data-color="blue" onclick="selectColor('blue')"></div>
            <div class="color-option color-green" data-color="green" onclick="selectColor('green')"></div>
            <div class="color-option color-yellow" data-color="yellow" onclick="selectColor('yellow')"></div>
            <div class="color-option color-red" data-color="red" onclick="selectColor('red')"></div>
            <div class="color-option color-purple" data-color="purple" onclick="selectColor('purple')"></div>
        </div>
        
        <div class="image-preview" id="imagePreview">
            <img id="previewImg" src="" alt="Vorschau">
            <button class="image-remove" onclick="removeImage()">×</button>
        </div>
        
        <div class="link-preview" id="linkPreview">
            <div class="link-preview-content"></div>
            <button class="link-preview-remove" onclick="removeLinkPreview()">×</button>
        </div>
        
        <div class="post-actions">
            <div class="action-left">
                <label class="image-input-label">
                    <i class="fas fa-image"></i>
                    <input type="file" id="imageInput" accept="image/*" onchange="handleImageSelect(event)">
                </label>
                <input type="url" id="postLink" class="link-input" placeholder="Link (https://...)" oninput="handleLinkInput()">
            </div>
            <button class="btn-post" id="submitBtn" onclick="submitPost()">Posten</button>
        </div>

        <div class="emoji-picker" id="emojiPicker">
            <div class="emoji-grid"></div>
        </div>
    </div>

    <div class="hashtag-cloud" id="hashtagCloud">
        <h3>Trending Hashtags</h3>
        <div id="hashtagList"></div>
    </div>

    <!-- Feed -->
    <div id="feed"></div>
    
    <div class="no-results" id="noResults">
        <i class="fas fa-search"></i>
        <h3>Keine Ergebnisse</h3>
    </div>
</div>

<!-- Login Modal -->
<div class="modal" id="loginModal">
    <div class="modal-content">
        <h2>🛡️ Admin Login</h2>
        <input type="text" id="loginUsername" placeholder="Username (optional)">
        <input type="password" id="adminPassword" placeholder="Passwort">
        <button onclick="login()">Einloggen</button>
        <button class="secondary" onclick="closeLoginModal()">Abbrechen</button>
    </div>
</div>

<div class="modal" id="usernameModal">
    <div class="modal-content">
        <h2>Anzeigename ändern</h2>
        <input type="text" id="usernameInput" maxlength="30" placeholder="Neuer Anzeigename">
        <button onclick="setUsername()">Speichern</button>
        <button class="secondary" onclick="closeUsernameModal()">Abbrechen</button>
    </div>
</div>

<div class="modal" id="reportModal">
    <div class="modal-content">
        <h2>Post melden</h2>
        <input type="hidden" id="reportPostId">
        <label><input type="radio" name="reportReason" value="Spam" checked> Spam</label>
        <label><input type="radio" name="reportReason" value="Beleidigung"> Beleidigung</label>
        <label><input type="radio" name="reportReason" value="Falsche Informationen"> Falsche Informationen</label>
        <button onclick="submitReport()">Melden</button>
        <button class="secondary" onclick="closeReportModal()">Abbrechen</button>
    </div>
</div>

<div class="modal" id="editModal">
    <div class="modal-content">
        <h2>Post bearbeiten</h2>
        <input type="hidden" id="editPostId">
        <textarea id="editPostText" rows="4" maxlength="2000" placeholder="Post Text"></textarea>
        <input type="url" id="editPostLink" placeholder="Link (https://...)">
        <label for="editPostColor">Farbe</label>
        <select id="editPostColor">
            <option value="default">Standard</option>
            <option value="blue">Blau</option>
            <option value="green">Grün</option>
            <option value="yellow">Gelb</option>
            <option value="red">Rot</option>
            <option value="purple">Lila</option>
        </select>
        <label><input type="checkbox" id="editPostPinned"> Anheften</label>
        <button onclick="updatePost()">Speichern</button>
        <button class="secondary" onclick="closeEditModal()">Abbrechen</button>
    </div>
</div>

<div class="toast" id="toast"></div>

<script src="app.js"></script>
<script>
window.currentUser = {
    id: '<?php echo htmlspecialchars($currentUserId); ?>',
    username: '<?php echo htmlspecialchars($currentUsername); ?>',
    displayName: '<?php echo htmlspecialchars($currentDisplayName); ?>',
    avatarColor: '<?php echo htmlspecialchars($currentAvatarColor); ?>'
};
window.isAdmin = <?php echo $isAdminUser ? 'true' : 'false'; ?>;
window.csrfToken = '<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>';
</script>

</body>
</html>