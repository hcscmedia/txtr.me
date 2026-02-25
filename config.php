<?php
/**
 * Micro News App - Konfiguration v4.1 (ERROR-FREE)
 * ⚠️ PASSWORT UNBEDINGT ÄNDERN!
 */

// Error-Reporting für Produktion ausschalten
error_reporting(0);
ini_set('display_errors', 0);

// Session sicher starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== KONFIGURATION ====================
define('DATA_FILE', 'news_data.json');
define('USERS_FILE', 'users.json');
define('FOLLOWS_FILE', 'follows.json');
define('MESSAGES_FILE', 'messages.json');
define('ACTIVITY_LOG_FILE', 'activity_log.json');
define('MAX_POSTS', 50);
$adminPassword = getenv('ADMIN_PASSWORD');
if ($adminPassword === false || $adminPassword === '') {
    $adminPassword = $_ENV['ADMIN_PASSWORD'] ?? ($_SERVER['ADMIN_PASSWORD'] ?? '');
}
define('ADMIN_PASSWORD', (string)$adminPassword);
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('RATE_LIMIT_FILE', 'rate_limits.json');
define('NOTIFICATIONS_FILE', 'notifications.json');

// Upload-Ordner erstellen
if (!file_exists(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}

function encodeJsonData($data) {
    $json = @json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json === false ? null : $json;
}

function writeJsonFile($filePath, $data) {
    $json = encodeJsonData($data);
    if ($json === null) return false;

    $tmpFile = $filePath . '.tmp';
    if (@file_put_contents($tmpFile, $json, LOCK_EX) === false) {
        return false;
    }

    if (!@rename($tmpFile, $filePath)) {
        @unlink($tmpFile);
        return false;
    }

    return true;
}

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($token)) return false;
    return hash_equals(getCsrfToken(), $token);
}

function getRateLimitData() {
    if (!file_exists(RATE_LIMIT_FILE)) return [];
    $json = @file_get_contents(RATE_LIMIT_FILE);
    if (empty($json)) return [];
    $data = @json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveRateLimitData($data) {
    return writeJsonFile(RATE_LIMIT_FILE, $data);
}

function checkRateLimit($action, $limit, $windowSeconds, $identifier = 'global') {
    $now = time();
    $key = $action . '|' . $identifier;
    $data = getRateLimitData();

    foreach ($data as $entryKey => $entry) {
        $hits = array_filter($entry['hits'] ?? [], fn($ts) => ($now - (int)$ts) <= 86400);
        if (empty($hits)) {
            unset($data[$entryKey]);
        } else {
            $data[$entryKey]['hits'] = array_values($hits);
        }
    }

    $hits = $data[$key]['hits'] ?? [];
    $hits = array_values(array_filter($hits, fn($ts) => ($now - (int)$ts) <= $windowSeconds));

    if (count($hits) >= $limit) {
        $data[$key]['hits'] = $hits;
        saveRateLimitData($data);
        return false;
    }

    $hits[] = $now;
    $data[$key]['hits'] = $hits;
    saveRateLimitData($data);
    return true;
}

// ==================== NOTIFICATIONS ====================

function getNotifications() {
    if (!file_exists(NOTIFICATIONS_FILE)) return [];
    $json = @file_get_contents(NOTIFICATIONS_FILE);
    if (empty($json)) return [];
    $data = @json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveNotifications($notifications) {
    return writeJsonFile(NOTIFICATIONS_FILE, $notifications);
}

function addNotification($userId, $type, $actorId = null, $actorName = null, $meta = []) {
    if (empty($userId) || empty($type)) return false;

    $notifications = getNotifications();
    $notifications[] = [
        'id' => 'notif_' . time() . '_' . bin2hex(random_bytes(4)),
        'user_id' => $userId,
        'type' => $type,
        'actor_id' => $actorId,
        'actor_name' => $actorName,
        'meta' => is_array($meta) ? $meta : [],
        'read' => false,
        'created_at' => time()
    ];

    if (count($notifications) > 5000) {
        $notifications = array_slice($notifications, -5000);
    }

    return saveNotifications($notifications);
}

function getUserNotifications($userId, $limit = 30) {
    if (empty($userId)) return [];
    $notifications = getNotifications();

    $list = array_values(array_filter($notifications, fn($n) =>
        isset($n['user_id']) && $n['user_id'] === $userId
    ));

    usort($list, fn($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));
    return array_slice($list, 0, max(1, (int)$limit));
}

function getUnreadNotificationsCount($userId) {
    if (empty($userId)) return 0;
    $notifications = getNotifications();
    $count = 0;

    foreach ($notifications as $n) {
        if (($n['user_id'] ?? '') === $userId && empty($n['read'])) {
            $count++;
        }
    }

    return $count;
}

function markNotificationsAsRead($userId) {
    if (empty($userId)) return false;
    $notifications = getNotifications();
    $changed = false;

    foreach ($notifications as &$n) {
        if (($n['user_id'] ?? '') === $userId && empty($n['read'])) {
            $n['read'] = true;
            $changed = true;
        }
    }

    if (!$changed) return true;
    return saveNotifications($notifications);
}

function deleteUserNotifications($userId) {
    if (empty($userId)) return false;
    $notifications = getNotifications();

    $notifications = array_values(array_filter($notifications, fn($n) =>
        ($n['user_id'] ?? '') !== $userId && ($n['actor_id'] ?? '') !== $userId
    ));

    return saveNotifications($notifications);
}

// ==================== USER MANAGEMENT ====================

function getUsers() {
    if (!file_exists(USERS_FILE)) return [];
    $json = @file_get_contents(USERS_FILE);
    if (empty($json)) return [];
    $data = @json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveUsers($users) {
    return writeJsonFile(USERS_FILE, $users);
}

function getUserById($userId) {
    if (empty($userId)) return null;
    $users = getUsers();
    foreach ($users as $user) {
        if (isset($user['id']) && $user['id'] === $userId) return $user;
    }
    return null;
}

function getUserByUsername($username) {
    if (empty($username)) return null;
    $users = getUsers();
    foreach ($users as $user) {
        if (isset($user['username']) && strtolower($user['username']) === strtolower($username)) return $user;
    }
    return null;
}

function createUser($username = null, $displayName = null) {
    $users = getUsers();
    
    if (empty($username)) {
        $username = 'user_' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
    
    foreach ($users as $user) {
        if (isset($user['username']) && strtolower($user['username']) === strtolower($username)) {
            return $user;
        }
    }
    
    $newUser = [
        'id' => 'user_' . bin2hex(random_bytes(8)),
        'username' => $username,
        'display_name' => $displayName ?? $username,
        'bio' => '',
        'avatar_color' => getRandomAvatarColor(),
        'created_at' => time(),
        'posts_count' => 0,
        'followers_count' => 0,
        'following_count' => 0,
        'is_banned' => false,
        'banned_at' => null,
        'banned_by' => null,
        'ban_reason' => null
    ];
    
    $users[] = $newUser;
    saveUsers($users);
    return $newUser;
}

function updateUser($userId, $data) {
    if (empty($userId)) return null;
    $users = getUsers();
    foreach ($users as &$user) {
        if (isset($user['id']) && $user['id'] === $userId) {
            $user = array_merge($user, $data);
            saveUsers($users);
            return $user;
        }
    }
    return null;
}

function banUser($userId, $reason = 'Kein Grund angegeben', $adminId = null) {
    $user = getUserById($userId);
    if (!$user) return false;
    
    $updated = updateUser($userId, [
        'is_banned' => true,
        'banned_at' => time(),
        'banned_by' => $adminId ?? getCurrentUserId(),
        'ban_reason' => $reason
    ]);
    
    if ($updated) {
        logActivity('user_banned', [
            'banned_user_id' => $userId,
            'banned_username' => $user['username'],
            'reason' => $reason
        ], $adminId);
    }
    
    return $updated !== null;
}

function unbanUser($userId, $adminId = null) {
    $user = getUserById($userId);
    if (!$user) return false;
    
    $updated = updateUser($userId, [
        'is_banned' => false,
        'banned_at' => null,
        'banned_by' => null,
        'ban_reason' => null
    ]);
    
    if ($updated) {
        logActivity('user_unbanned', [
            'unbanned_user_id' => $userId,
            'unbanned_username' => $user['username']
        ], $adminId);
    }
    
    return $updated !== null;
}

function isUserBanned($userId) {
    $user = getUserById($userId);
    return $user && !empty($user['is_banned']);
}

function getAllUsers($limit = 100, $offset = 0) {
    $users = getUsers();
    usort($users, fn($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));
    return array_slice($users, $offset, $limit);
}

function getRandomAvatarColor() {
    $colors = ['#1877f2', '#e41e3f', '#31a24c', '#f7b928', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'];
    return $colors[array_rand($colors)];
}

// ==================== ACTIVITY LOG ====================

function getActivityLog() {
    if (!file_exists(ACTIVITY_LOG_FILE)) return [];
    $json = @file_get_contents(ACTIVITY_LOG_FILE);
    if (empty($json)) return [];
    return @json_decode($json, true) ?? [];
}

function saveActivityLog($logs) {
    return writeJsonFile(ACTIVITY_LOG_FILE, $logs);
}

function logActivity($action, $details = [], $userId = null) {
    $logs = getActivityLog();
    
    $newLog = [
        'id' => 'log_' . time() . '_' . bin2hex(random_bytes(4)),
        'timestamp' => time(),
        'date' => date('Y-m-d H:i:s'),
        'action' => $action,
        'user_id' => $userId ?? getCurrentUserId(),
        'username' => getUsername(),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'details' => $details
    ];
    
    array_unshift($logs, $newLog);
    if (count($logs) > 500) {
        $logs = array_slice($logs, 0, 500);
    }
    
    saveActivityLog($logs);
    return $newLog;
}

// ==================== FOLLOW SYSTEM ====================

function getFollows() {
    if (!file_exists(FOLLOWS_FILE)) return [];
    $json = @file_get_contents(FOLLOWS_FILE);
    if (empty($json)) return [];
    return @json_decode($json, true) ?? [];
}

function saveFollows($follows) {
    return writeJsonFile(FOLLOWS_FILE, $follows);
}

function followUser($followerId, $followingId) {
    if (empty($followerId) || empty($followingId) || $followerId === $followingId) return false;
    
    $follows = getFollows();
    
    foreach ($follows as $follow) {
        if (isset($follow['follower_id']) && $follow['follower_id'] === $followerId && 
            isset($follow['following_id']) && $follow['following_id'] === $followingId) {
            return true;
        }
    }
    
    $follows[] = [
        'id' => 'follow_' . time() . '_' . bin2hex(random_bytes(4)),
        'follower_id' => $followerId,
        'following_id' => $followingId,
        'created_at' => time()
    ];
    
    saveFollows($follows);
    updateUserCounts($followerId, $followingId);
    
    return true;
}

function unfollowUser($followerId, $followingId) {
    if (empty($followerId) || empty($followingId)) return false;
    
    $follows = getFollows();
    $newFollows = [];
    
    foreach ($follows as $follow) {
        if (!(isset($follow['follower_id']) && $follow['follower_id'] === $followerId && 
              isset($follow['following_id']) && $follow['following_id'] === $followingId)) {
            $newFollows[] = $follow;
        }
    }
    
    saveFollows($newFollows);
    updateUserCounts($followerId, $followingId);
    
    return true;
}

function isFollowing($followerId, $followingId) {
    if (empty($followerId) || empty($followingId)) return false;
    $follows = getFollows();
    foreach ($follows as $follow) {
        if (isset($follow['follower_id']) && $follow['follower_id'] === $followerId && 
            isset($follow['following_id']) && $follow['following_id'] === $followingId) {
            return true;
        }
    }
    return false;
}

function getFollowers($userId) {
    if (empty($userId)) return [];
    $follows = getFollows();
    $followers = [];
    
    foreach ($follows as $follow) {
        if (isset($follow['following_id']) && $follow['following_id'] === $userId) {
            $user = getUserById($follow['follower_id']);
            if ($user) $followers[] = $user;
        }
    }
    
    return $followers;
}

function getFollowing($userId) {
    if (empty($userId)) return [];
    $follows = getFollows();
    $following = [];
    
    foreach ($follows as $follow) {
        if (isset($follow['follower_id']) && $follow['follower_id'] === $userId) {
            $user = getUserById($follow['following_id']);
            if ($user) $following[] = $user;
        }
    }
    
    return $following;
}

function updateUserCounts($followerId, $followingId) {
    $users = getUsers();
    
    foreach ($users as &$user) {
        if (isset($user['id'])) {
            if ($user['id'] === $followerId) {
                $user['following_count'] = count(getFollowing($followerId));
            }
            if ($user['id'] === $followingId) {
                $user['followers_count'] = count(getFollowers($followingId));
            }
        }
    }
    
    saveUsers($users);
}

function refreshAllUserCounts() {
    $users = getUsers();
    foreach ($users as &$user) {
        $userId = $user['id'] ?? null;
        if (empty($userId)) continue;
        $user['followers_count'] = count(getFollowers($userId));
        $user['following_count'] = count(getFollowing($userId));
    }
    return saveUsers($users);
}

// ==================== MESSAGES ====================

function getMessages() {
    if (!file_exists(MESSAGES_FILE)) return [];
    $json = @file_get_contents(MESSAGES_FILE);
    if (empty($json)) return [];
    return @json_decode($json, true) ?? [];
}

function saveMessages($messages) {
    return writeJsonFile(MESSAGES_FILE, $messages);
}

function sendMessage($fromId, $toId, $text) {
    if (empty($fromId) || empty($toId) || empty(trim($text))) return false;
    
    $messages = getMessages();
    
    $newMessage = [
        'id' => 'msg_' . time() . '_' . bin2hex(random_bytes(4)),
        'from_id' => $fromId,
        'to_id' => $toId,
        'text' => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
        'read' => false,
        'created_at' => time()
    ];
    
    $messages[] = $newMessage;
    saveMessages($messages);
    
    return $newMessage;
}

function getConversation($userId1, $userId2) {
    if (empty($userId1) || empty($userId2)) return [];
    $messages = getMessages();
    $conversation = [];
    
    foreach ($messages as $msg) {
        if ((isset($msg['from_id']) && $msg['from_id'] === $userId1 && isset($msg['to_id']) && $msg['to_id'] === $userId2) ||
            (isset($msg['from_id']) && $msg['from_id'] === $userId2 && isset($msg['to_id']) && $msg['to_id'] === $userId1)) {
            $conversation[] = $msg;
        }
    }
    
    usort($conversation, fn($a, $b) => ($a['created_at'] ?? 0) - ($b['created_at'] ?? 0));
    
    return $conversation;
}

function getUnreadCount($userId) {
    if (empty($userId)) return 0;
    $messages = getMessages();
    $count = 0;
    
    foreach ($messages as $msg) {
        if (isset($msg['to_id']) && $msg['to_id'] === $userId && empty($msg['read'])) {
            $count++;
        }
    }
    
    return $count;
}

function markMessagesAsRead($userId1, $userId2) {
    if (empty($userId1) || empty($userId2)) return;
    $messages = getMessages();
    
    foreach ($messages as &$msg) {
        if (isset($msg['from_id']) && $msg['from_id'] === $userId2 && 
            isset($msg['to_id']) && $msg['to_id'] === $userId1) {
            $msg['read'] = true;
        }
    }
    
    saveMessages($messages);
}

function getUserConversations($userId) {
    if (empty($userId)) return [];
    $messages = getMessages();
    $conversations = [];
    $userIds = [];
    
    foreach ($messages as $msg) {
        $otherId = null;
        if (isset($msg['from_id']) && $msg['from_id'] === $userId) {
            $otherId = $msg['to_id'] ?? null;
        } elseif (isset($msg['to_id']) && $msg['to_id'] === $userId) {
            $otherId = $msg['from_id'] ?? null;
        }
        
        if ($otherId && $otherId !== $userId && !in_array($otherId, $userIds)) {
            $userIds[] = $otherId;
            $otherUser = getUserById($otherId);
            
            if ($otherUser) {
                $lastMessage = null;
                $unreadCount = 0;
                
                foreach ($messages as $m) {
                    if ((isset($m['from_id']) && $m['from_id'] === $userId && isset($m['to_id']) && $m['to_id'] === $otherId) ||
                        (isset($m['from_id']) && $m['from_id'] === $otherId && isset($m['to_id']) && $m['to_id'] === $userId)) {
                        $lastMessage = $m;
                        if (isset($m['to_id']) && $m['to_id'] === $userId && empty($m['read'])) {
                            $unreadCount++;
                        }
                    }
                }
                
                $conversations[] = [
                    'user' => $otherUser,
                    'last_message' => $lastMessage,
                    'unread_count' => $unreadCount
                ];
            }
        }
    }
    
    usort($conversations, fn($a, $b) => 
        ($b['last_message']['created_at'] ?? 0) - ($a['last_message']['created_at'] ?? 0)
    );
    
    return $conversations;
}

// ==================== POSTS ====================

function getPosts() {
    if (!file_exists(DATA_FILE)) return [];
    $json = @file_get_contents(DATA_FILE);
    if (empty($json)) return [];
    $data = @json_decode($json, true);
    return is_array($data) ? $data : [];
}

function savePosts($posts) {
    return writeJsonFile(DATA_FILE, $posts);
}

function extractHashtags($text) {
    preg_match_all('/#\w+/', $text, $matches);
    return array_unique($matches[0]);
}

function isAdmin() {
    if (!isset($_SESSION)) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }
    return isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;
}

function getCurrentUserId() {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 'user_' . bin2hex(random_bytes(8));
    }
    return $_SESSION['user_id'];
}

function getUserId() {
    return getCurrentUserId();
}

function getCurrentUser() {
    if (!isset($_SESSION)) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }
    
    $userId = getCurrentUserId();
    $user = getUserById($userId);
    
    if (!$user) {
        $username = 'user_' . substr($userId, -6);
        $user = createUser($username);
        
        $_SESSION['username'] = $user['username'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['user_id'] = $user['id'];
    }
    
    return $user;
}

function getUsername() {
    $user = getCurrentUser();
    return $user['display_name'] ?? 'User';
}

function timeAgo($timestamp) {
    if (empty($timestamp)) return 'unbekannt';
    $time = time() - $timestamp;
    if ($time < 1) return 'gerade eben';
    if ($time < 60) return 'vor ' . $time . ' Sek.';
    if ($time < 3600) return 'vor ' . round($time / 60) . ' Min.';
    if ($time < 86400) return 'vor ' . round($time / 3600) . ' Std.';
    if ($time < 604800) return 'vor ' . round($time / 86400) . ' Tagen';
    return date('d.m.Y', $timestamp);
}

function jsonResponse($data, $statusCode = 200) {
    // Alle vorherigen Outputs löschen
    ob_clean();
    
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-cache');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getStats() {
    $posts = getPosts();
    $users = getUsers();
    $logs = getActivityLog();
    $totalLikes = 0;
    $totalComments = 0;
    $totalImages = 0;
    $totalViews = 0;
    $bannedUsers = 0;
    
    foreach ($posts as $post) {
        $totalLikes += $post['likes'] ?? 0;
        $totalComments += count($post['comments'] ?? []);
        $totalViews += $post['views'] ?? 0;
        if (!empty($post['image'])) $totalImages++;
    }
    
    foreach ($users as $user) {
        if (!empty($user['is_banned'])) $bannedUsers++;
    }
    
    return [
        'totalPosts' => count($posts),
        'totalUsers' => count($users),
        'totalLikes' => $totalLikes,
        'totalComments' => $totalComments,
        'totalImages' => $totalImages,
        'totalViews' => $totalViews,
        'bannedUsers' => $bannedUsers,
        'activityLogs' => count($logs),
        'pinnedPosts' => count(array_filter($posts, fn($p) => !empty($p['pinned'])))
    ];
}

function getPostColors() {
    return [
        'default' => ['bg' => 'transparent', 'text' => 'inherit'],
        'blue' => ['bg' => '#e7f3ff', 'text' => '#0066cc'],
        'green' => ['bg' => '#e7f9ef', 'text' => '#008a4c'],
        'yellow' => ['bg' => '#fff9e7', 'text' => '#b78900'],
        'red' => ['bg' => '#ffe7e7', 'text' => '#cc0000'],
        'purple' => ['bg' => '#f3e7ff', 'text' => '#6600cc']
    ];
}

function validateImage($base64Data) {
    if (empty($base64Data)) return true;
    $data = explode(',', $base64Data)[1] ?? '';
    if (empty($data)) return false;
    $decoded = @base64_decode($data);
    if ($decoded === false) return false;
    if (strlen($decoded) > MAX_FILE_SIZE) return false;
    
    if (function_exists('finfo_open')) {
        $finfo = @new finfo(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = $finfo->buffer($decoded);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            return in_array($mimeType, $allowedMimes);
        }
    }
    
    return true;
}

function fetchLinkPreview($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) return null;
    if (!preg_match('/^https?:\/\//i', $url)) $url = 'https://' . $url;

    $parsedUrl = @parse_url($url);
    $host = strtolower($parsedUrl['host'] ?? '');
    if (empty($host)) return null;
    if (in_array($host, ['localhost', '127.0.0.1', '::1'])) return null;

    $resolvedIp = @gethostbyname($host);
    $isPublicIp = filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if ($isPublicIp === false) return null;
    
    try {
        $ch = @curl_init();
        if (!$ch) return null;
        
        @curl_setopt($ch, CURLOPT_URL, $url);
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        @curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        @curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        @curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        @curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        $html = @curl_exec($ch);
        $httpCode = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);
        
        if (!$html || $httpCode !== 200) return null;
        
        $html = @mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        
        $preview = ['title' => '', 'description' => '', 'image' => '', 'url' => $url];
        
        if (@preg_match('/<meta[^>]*property=["\']og:title["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $matches)) {
            $preview['title'] = trim($matches[1]);
        }
        if (@preg_match('/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $matches)) {
            $preview['description'] = trim($matches[1]);
        }
        if (@preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $matches)) {
            $preview['image'] = trim($matches[1]);
        }
        
        if (empty($preview['title']) && @preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            $preview['title'] = trim($matches[1]);
        }
        
        if (!empty($preview['image']) && strpos($preview['image'], 'http') !== 0) {
            $parsedUrl = @parse_url($url);
            if ($parsedUrl) {
                $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
                $preview['image'] = $baseUrl . (strpos($preview['image'], '/') === 0 ? '' : '/') . $preview['image'];
            }
        }
        
        $preview['title'] = htmlspecialchars(substr($preview['title'], 0, 100), ENT_QUOTES, 'UTF-8');
        $preview['description'] = htmlspecialchars(substr($preview['description'], 0, 200), ENT_QUOTES, 'UTF-8');
        
        return !empty($preview['title']) ? $preview : null;
        
    } catch (Exception $e) {
        return null;
    }
}

function savePushSubscription($subscription) {
    $file = 'push_subscriptions.json';
    $subscriptions = file_exists($file) ? @json_decode(@file_get_contents($file), true) ?? [] : [];
    
    $exists = false;
    foreach ($subscriptions as &$sub) {
        if (isset($sub['endpoint']) && $sub['endpoint'] === $subscription['endpoint']) {
            $sub['updated_at'] = time();
            $exists = true;
            break;
        }
    }
    
    if (!$exists) {
        $subscription['created_at'] = time();
        $subscription['updated_at'] = time();
        $subscriptions[] = $subscription;
    }
    
    writeJsonFile($file, $subscriptions);
}

// Bulk Actions
function bulkDeletePosts($postIds, $adminId = null) {
    $posts = getPosts();
    $deletedCount = 0;
    $newPosts = [];
    
    foreach ($posts as $post) {
        if (in_array($post['id'], $postIds)) {
            if (!empty($post['image']) && file_exists(UPLOAD_DIR . $post['image'])) {
                @unlink(UPLOAD_DIR . $post['image']);
            }
            $deletedCount++;
        } else {
            $newPosts[] = $post;
        }
    }
    
    if ($deletedCount > 0) {
        savePosts($newPosts);
        logActivity('bulk_delete', [
            'posts_count' => $deletedCount,
            'post_ids' => $postIds
        ], $adminId);
    }
    
    return $deletedCount;
}

function bulkReportPosts($postIds, $reason, $userId = null) {
    $posts = getPosts();
    $reportedCount = 0;
    
    foreach ($posts as &$post) {
        if (in_array($post['id'], $postIds)) {
            $post['reports'][] = [
                'user_id' => $userId ?? getCurrentUserId(),
                'reason' => $reason,
                'timestamp' => time(),
                'date' => date('Y-m-d H:i:s'),
                'is_bulk' => true
            ];
            $reportedCount++;
        }
    }
    
    if ($reportedCount > 0) {
        savePosts($posts);
        logActivity('bulk_report', [
            'posts_count' => $reportedCount,
            'reason' => $reason
        ], $userId);
    }
    
    return $reportedCount;
}

function getPostAnalytics($postId = null) {
    $posts = getPosts();
    
    if ($postId) {
        foreach ($posts as $post) {
            if ($post['id'] === $postId) {
                return calculatePostAnalytics($post);
            }
        }
        return null;
    }
    
    $analytics = [];
    foreach ($posts as $post) {
        $analytics[] = calculatePostAnalytics($post);
    }
    
    usort($analytics, fn($a, $b) => $b['views'] - $a['views']);
    
    return $analytics;
}

function calculatePostAnalytics($post) {
    $commentsCount = count($post['comments'] ?? []);
    $reportsCount = count($post['reports'] ?? []);
    
    $totalEngagement = ($post['likes'] ?? 0) + $commentsCount;
    $engagementRate = $post['views'] > 0 ? round(($totalEngagement / $post['views']) * 100, 2) : 0;
    
    return [
        'id' => $post['id'],
        'text' => substr($post['text'], 0, 50) . '...',
        'author' => $post['author'] ?? 'Unknown',
        'author_id' => $post['author_id'] ?? null,
        'date' => $post['date'] ?? 'Unknown',
        'timestamp' => $post['timestamp'] ?? 0,
        'views' => $post['views'] ?? 0,
        'likes' => $post['likes'] ?? 0,
        'comments' => $commentsCount,
        'reports' => $reportsCount,
        'engagement_rate' => $engagementRate,
        'is_pinned' => !empty($post['pinned']),
        'has_image' => !empty($post['image']),
        'has_link' => !empty($post['link'])
    ];
}

function deleteUserPosts($userId) {
    $posts = getPosts();
    $deletedCount = 0;
    $newPosts = [];
    
    foreach ($posts as $post) {
        if (isset($post['author_id']) && $post['author_id'] === $userId) {
            if (!empty($post['image']) && file_exists(UPLOAD_DIR . $post['image'])) {
                @unlink(UPLOAD_DIR . $post['image']);
            }
            $deletedCount++;
        } else {
            $newPosts[] = $post;
        }
    }
    
    if ($deletedCount > 0) {
        savePosts($newPosts);
        logActivity('posts_deleted', [
            'user_id' => $userId,
            'posts_count' => $deletedCount
        ]);
    }
    
    return $deletedCount;
}
?>