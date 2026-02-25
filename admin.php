<?php
/**
 * Micro News App - Admin Dashboard v4.2 (mit Post-Edit)
 */
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$currentUser = getCurrentUser();
$stats = getStats();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1877f2">
    <title>Admin Dashboard - MicroNews</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body">

<!-- Admin Header -->
<header class="admin-header">
    <div class="header-left">
        <h1>🛡️ Admin Dashboard</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Zurück zur Seite</a>
    </div>
    <div class="header-right">
        <button class="theme-toggle" onclick="toggleTheme()">
            <i class="fas fa-moon"></i>
        </button>
        <button class="logout-btn" onclick="logout()">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </div>
</header>

<!-- Admin Navigation -->
<nav class="admin-nav">
    <button class="admin-nav-item active" onclick="showAdminTab('overview')">
        <i class="fas fa-chart-line"></i> Übersicht
    </button>
    <button class="admin-nav-item" onclick="showAdminTab('analytics')">
        <i class="fas fa-analytics"></i> Post-Analytics
    </button>
    <button class="admin-nav-item" onclick="showAdminTab('users')">
        <i class="fas fa-users"></i> User-Management
    </button>
    <button class="admin-nav-item" onclick="showAdminTab('bulk')">
        <i class="fas fa-check-square"></i> Bulk-Actions
    </button>
    <button class="admin-nav-item" onclick="showAdminTab('activity')">
        <i class="fas fa-history"></i> Activity-Log
    </button>
</nav>

<main class="admin-main">
    <!-- OVERVIEW TAB -->
    <div class="admin-tab active" id="tab-overview">
        <h2>📊 Übersicht</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-value"><?php echo $stats['totalPosts']; ?></div>
                <div class="stat-label">Posts</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $stats['totalUsers']; ?></div>
                <div class="stat-label">User</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❤️</div>
                <div class="stat-value"><?php echo $stats['totalLikes']; ?></div>
                <div class="stat-label">Likes</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💬</div>
                <div class="stat-value"><?php echo $stats['totalComments']; ?></div>
                <div class="stat-label">Kommentare</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👁️</div>
                <div class="stat-value"><?php echo number_format($stats['totalViews']); ?></div>
                <div class="stat-label">Aufrufe</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-icon">🚫</div>
                <div class="stat-value"><?php echo $stats['bannedUsers']; ?></div>
                <div class="stat-label">Gesperrte User</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?php echo $stats['activityLogs']; ?></div>
                <div class="stat-label">Log-Einträge</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📌</div>
                <div class="stat-value"><?php echo $stats['pinnedPosts']; ?></div>
                <div class="stat-label">Angeheftet</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3>⚡ Schnellaktionen</h3>
            <div class="action-buttons">
                <button class="action-btn" onclick="exportData()">
                    <i class="fas fa-download"></i> Daten exportieren
                </button>
                <button class="action-btn" onclick="location.href='?tab=users'">
                    <i class="fas fa-user-plus"></i> User verwalten
                </button>
                <button class="action-btn" onclick="location.href='?tab=analytics'">
                    <i class="fas fa-edit"></i> Posts bearbeiten
                </button>
            </div>
        </div>
    </div>
    
    <!-- ANALYTICS TAB -->
    <div class="admin-tab" id="tab-analytics">
        <h2>📈 Post-Analytics & Bearbeiten</h2>
        
        <div class="analytics-controls">
            <select id="analyticsSort" onchange="loadAnalytics()">
                <option value="views">Nach Aufrufen</option>
                <option value="likes">Nach Likes</option>
                <option value="comments">Nach Kommentaren</option>
                <option value="engagement_rate">Nach Engagement</option>
                <option value="timestamp">Nach Datum</option>
            </select>
            <input type="text" id="analyticsSearch" placeholder="Posts durchsuchen..." oninput="filterAnalytics()">
        </div>
        
        <div class="analytics-list" id="analyticsList">
            <div class="loading">Lädt Analytics...</div>
        </div>
    </div>
    
    <!-- USERS TAB -->
    <div class="admin-tab" id="tab-users">
        <h2>👥 User-Management</h2>
        
        <div class="users-controls">
            <input type="text" id="userSearch" placeholder="User suchen..." oninput="searchUsers()">
            <select id="userFilter" onchange="loadUsers()">
                <option value="all">Alle User</option>
                <option value="active">Aktive</option>
                <option value="banned">Gesperrte</option>
            </select>
        </div>
        
        <div class="users-list" id="usersList">
            <div class="loading">Lädt User...</div>
        </div>
    </div>
    
    <!-- BULK ACTIONS TAB -->
    <div class="admin-tab" id="tab-bulk">
        <h2>🗑️ Bulk-Actions</h2>
        
        <div class="bulk-controls">
            <p>Wähle Posts aus für Massenaktionen:</p>
            <div class="bulk-buttons">
                <button class="btn-danger" onclick="bulkDeleteSelected()">
                    <i class="fas fa-trash"></i> Ausgewählte löschen
                </button>
                <button class="btn-warning" onclick="bulkReportSelected()">
                    <i class="fas fa-flag"></i> Ausgewählte melden
                </button>
                <button class="btn-secondary" onclick="selectAllPosts()">
                    <i class="fas fa-check-square"></i> Alle auswählen
                </button>
                <button class="btn-secondary" onclick="deselectAllPosts()">
                    <i class="fas fa-square"></i> Auswahl aufheben
                </button>
            </div>
        </div>
        
        <div class="bulk-posts" id="bulkPostsList">
            <div class="loading">Lädt Posts...</div>
        </div>
    </div>
    
    <!-- ACTIVITY LOG TAB -->
    <div class="admin-tab" id="tab-activity">
        <h2>📋 Activity-Log</h2>
        
        <div class="log-controls">
            <input type="text" id="logSearch" placeholder="Log durchsuchen..." oninput="filterLogs()">
            <select id="logFilter" onchange="loadActivityLog()">
                <option value="all">Alle Aktionen</option>
                <option value="user_banned">User gesperrt</option>
                <option value="user_unbanned">User entsperrt</option>
                <option value="bulk_delete">Bulk Delete</option>
                <option value="posts_deleted">Posts gelöscht</option>
                <option value="post_edited">Post bearbeitet</option>
            </select>
        </div>
        
        <div class="activity-log" id="activityLog">
            <div class="loading">Lädt Activity-Log...</div>
        </div>
    </div>
</main>

<!-- ✏️ POST EDIT MODAL -->
<div class="modal" id="editPostModal">
    <div class="modal-content modal-large">
        <h2>✏️ Post bearbeiten</h2>
        <input type="hidden" id="editPostId">
        
        <div class="edit-form">
            <label>Text:</label>
            <textarea id="editPostText" rows="5" maxlength="2000" placeholder="Post Text"></textarea>
            
            <label>Link (optional):</label>
            <input type="url" id="editPostLink" placeholder="https://...">
            
            <label>Farbe:</label>
            <div class="color-picker" id="editColorPicker">
                <div class="color-option color-default selected" data-color="default" onclick="selectEditColor('default')"></div>
                <div class="color-option color-blue" data-color="blue" onclick="selectEditColor('blue')"></div>
                <div class="color-option color-green" data-color="green" onclick="selectEditColor('green')"></div>
                <div class="color-option color-yellow" data-color="yellow" onclick="selectEditColor('yellow')"></div>
                <div class="color-option color-red" data-color="red" onclick="selectEditColor('red')"></div>
                <div class="color-option color-purple" data-color="purple" onclick="selectEditColor('purple')"></div>
            </div>
            
            <div class="checkbox-wrapper">
                <input type="checkbox" id="editPostPinned">
                <label for="editPostPinned">📌 Als wichtig anheften</label>
            </div>
            
            <div class="edit-info">
                <div class="info-row"><strong>Autor:</strong> <span id="editPostAuthor"></span></div>
                <div class="info-row"><strong>Erstellt:</strong> <span id="editPostDate"></span></div>
                <div class="info-row"><strong>Aufrufe:</strong> <span id="editPostViews"></span></div>
                <div class="info-row"><strong>Likes:</strong> <span id="editPostLikes"></span></div>
            </div>
        </div>
        
        <div class="modal-actions">
            <button class="btn-primary" onclick="saveEditedPost()">
                <i class="fas fa-save"></i> Speichern
            </button>
            <button class="btn-secondary" onclick="closeEditPostModal()">
                <i class="fas fa-times"></i> Abbrechen
            </button>
        </div>
    </div>
</div>

<!-- Post Detail Modal -->
<div class="modal" id="postDetailModal">
    <div class="modal-content modal-large">
        <h2>📊 Post-Details</h2>
        <div id="postDetailContent"></div>
        <button class="secondary" onclick="closePostDetailModal()">Schließen</button>
    </div>
</div>

<!-- Ban User Modal -->
<div class="modal" id="banModal">
    <div class="modal-content">
        <h2>🚫 User sperren</h2>
        <input type="hidden" id="banUserId">
        <p>User: <strong id="banUsername"></strong></p>
        <label>Grund:</label>
        <textarea id="banReason" placeholder="Grund für die Sperre..." rows="3"></textarea>
        <button class="btn-danger" onclick="confirmBan()">User sperren</button>
        <button class="secondary" onclick="closeBanModal()">Abbrechen</button>
    </div>
</div>

<div class="toast" id="toast"></div>

<script src="admin.js"></script>
<script>
const adminStats = <?php echo json_encode($stats); ?>;

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    document.documentElement.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
    localStorage.setItem('theme', current === 'dark' ? 'light' : 'dark');
}

function logout() {
    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'logout' })
    }).then(() => location.href = 'index.php');
}

function showAdminTab(tabName) {
    document.querySelectorAll('.admin-tab').forEach(tab => {
        tab.classList.toggle('active', tab.id === 'tab-' + tabName);
    });
    document.querySelectorAll('.admin-nav-item').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    if (tabName === 'analytics') loadAnalytics();
    if (tabName === 'users') loadUsers();
    if (tabName === 'bulk') loadBulkPosts();
    if (tabName === 'activity') loadActivityLog();
}

function showToast(message) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function exportData() {
    window.location.href = 'api.php?action=export_data';
    showToast('📥 Download startet...');
}
</script>

</body>
</html>