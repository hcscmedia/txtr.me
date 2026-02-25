<?php
/**
 * Micro News App - API Endpoint v4.1 (ERROR-FREE)
 */

// Error-Reporting ausschalten
error_reporting(0);
ini_set('display_errors', 0);

// Output Buffering starten
ob_start();

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Config laden
require_once 'config.php';

// Headers setzen BEVOR anything else
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache');

// CORS nur same-origin erlauben
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $serverHost = $_SERVER['HTTP_HOST'] ?? '';
    $serverHost = explode(':', $serverHost)[0];
    if (!empty($originHost) && $originHost === $serverHost) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS Request handling
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Input lesen
$inputRaw = file_get_contents('php://input');
$input = @json_decode($inputRaw, true);
if ($input === null && !empty($inputRaw)) {
    jsonResponse(['error' => 'Ungültiges JSON', 'raw' => substr($inputRaw, 0, 100)], 400);
}
$input = $input ?? [];

$action = $input['action'] ?? ($_GET['action'] ?? '');
$posts = getPosts();
$currentUser = getCurrentUser();

$adminProtectedPostActions = [
    'ban_user',
    'unban_user',
    'bulk_delete',
    'bulk_report',
    'delete_user',
    'edit_post',
    'delete',
    'logout'
];

// ==================== GET REQUESTS ====================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'get_posts':
            $feedType = $_GET['feed'] ?? 'all';
            
            usort($posts, function($a, $b) {
                $pinnedA = $a['pinned'] ?? false;
                $pinnedB = $b['pinned'] ?? false;
                if ($pinnedA && !$pinnedB) return -1;
                if (!$pinnedA && $pinnedB) return 1;
                return ($b['timestamp'] ?? $b['id']) - ($a['timestamp'] ?? $a['id']);
            });
            
            if ($feedType === 'following' && !empty($currentUser['id'])) {
                $following = getFollowing($currentUser['id']);
                $followingIds = array_column($following, 'id');
                $posts = array_filter($posts, fn($p) => isset($p['author_id']) && in_array($p['author_id'], $followingIds));
            }
            
            $allHashtags = [];
            foreach ($posts as $post) {
                if (!empty($post['hashtags'])) {
                    foreach ($post['hashtags'] as $tag) {
                        $allHashtags[$tag] = ($allHashtags[$tag] ?? 0) + 1;
                    }
                }
            }
            arsort($allHashtags);
            
            $bookmarks = [];
            $bookmarkFile = 'bookmarks_' . getUserId() . '.json';
            if (file_exists($bookmarkFile)) {
                $bookmarks = @json_decode(@file_get_contents($bookmarkFile), true) ?? [];
            }
            
            jsonResponse([
                'posts' => array_values($posts),
                'hashtags' => $allHashtags,
                'isAdmin' => isAdmin(),
                'username' => getUsername(),
                'user' => $currentUser,
                'bookmarks' => $bookmarks,
                'colors' => getPostColors()
            ]);
            break;
            
        case 'get_stats':
            if (!isAdmin()) {
                jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
            }
            jsonResponse(['success' => true, 'stats' => getStats()]);
            break;
            
        case 'get_link_preview':
            $url = $_GET['url'] ?? '';
            if (empty($url)) {
                jsonResponse(['success' => false, 'message' => 'URL erforderlich'], 400);
            }
            
            $preview = fetchLinkPreview($url);
            if ($preview) {
                jsonResponse(['success' => true, 'preview' => $preview]);
            } else {
                jsonResponse(['success' => false, 'message' => 'Keine Vorschau', 'preview' => null]);
            }
            break;
            
        case 'get_analytics':
            if (!isAdmin()) {
                jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
            }
            
            $sortBy = $_GET['sort'] ?? 'views';
            $analytics = getPostAnalytics();
            
            usort($analytics, function($a, $b) use ($sortBy) {
                return ($b[$sortBy] ?? 0) - ($a[$sortBy] ?? 0);
            });
            
            jsonResponse(['success' => true, 'analytics' => $analytics]);
            break;
            
        case 'get_users':
            if (!isAdmin()) {
                jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
            }
            
            $filter = $_GET['filter'] ?? 'all';
            $users = getAllUsers(100, 0);
            
            if ($filter === 'banned') {
                $users = array_filter($users, fn($u) => !empty($u['is_banned']));
            } elseif ($filter === 'active') {
                $users = array_filter($users, fn($u) => empty($u['is_banned']));
            }
            
            jsonResponse(['success' => true, 'users' => array_values($users)]);
            break;
            
        case 'get_activity_log':
            if (!isAdmin()) {
                jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
            }
            
            $filter = $_GET['filter'] ?? 'all';
            $logs = getActivityLog();
            
            if ($filter !== 'all') {
                $logs = array_filter($logs, fn($l) => $l['action'] === $filter);
            }
            
            jsonResponse(['success' => true, 'logs' => array_values($logs)]);
            break;
            
        case 'get_conversations':
            $conversations = getUserConversations($currentUser['id']);
            jsonResponse(['success' => true, 'conversations' => $conversations]);
            break;
            
        case 'get_conversation':
            $otherUserId = $_GET['user_id'] ?? '';
            if (empty($otherUserId)) {
                jsonResponse(['success' => false, 'message' => 'User-ID erforderlich'], 400);
            }
            
            $conversation = getConversation($currentUser['id'], $otherUserId);
            markMessagesAsRead($currentUser['id'], $otherUserId);
            
            jsonResponse(['success' => true, 'messages' => $conversation]);
            break;
            
        case 'get_unread_count':
            $count = getUnreadCount($currentUser['id']);
            jsonResponse(['success' => true, 'count' => $count]);
            break;

        case 'get_notifications':
            $limit = (int)($_GET['limit'] ?? 30);
            if ($limit < 1) $limit = 30;
            if ($limit > 100) $limit = 100;

            $notifications = getUserNotifications($currentUser['id'], $limit);
            $unread = getUnreadNotificationsCount($currentUser['id']);

            jsonResponse([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unread
            ]);
            break;

        case 'get_followers':
            $targetUserId = $_GET['user_id'] ?? ($currentUser['id'] ?? '');
            if (empty($targetUserId)) {
                jsonResponse(['success' => false, 'message' => 'User-ID erforderlich'], 400);
            }
            jsonResponse(['success' => true, 'followers' => array_values(getFollowers($targetUserId))]);
            break;

        case 'get_following':
            $targetUserId = $_GET['user_id'] ?? ($currentUser['id'] ?? '');
            if (empty($targetUserId)) {
                jsonResponse(['success' => false, 'message' => 'User-ID erforderlich'], 400);
            }
            jsonResponse(['success' => true, 'following' => array_values(getFollowing($targetUserId))]);
            break;

        case 'load_draft':
            $draftFile = 'draft_' . getUserId() . '.json';
            if (file_exists($draftFile)) {
                $draft = @json_decode(@file_get_contents($draftFile), true);
                if ($draft) {
                    jsonResponse(['success' => true, 'draft' => $draft]);
                }
            }
            jsonResponse(['success' => false, 'message' => 'Kein Entwurf']);
            break;

        case 'get_post_detail':
            if (!isAdmin()) {
                jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
            }

            $postId = $_GET['id'] ?? '';
            if (empty($postId)) {
                jsonResponse(['success' => false, 'message' => 'Post-ID erforderlich'], 400);
            }

            foreach ($posts as $post) {
                if ($post['id'] === $postId) {
                    jsonResponse(['success' => true, 'post' => $post]);
                }
            }

            jsonResponse(['success' => false, 'message' => 'Post nicht gefunden'], 404);
            break;
            
        case 'export_data':
            if (!isAdmin()) {
                jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
            }
            
            $data = [
                'posts' => $posts,
                'exported_at' => date('Y-m-d H:i:s'),
                'version' => '4.1',
                'total_posts' => count($posts)
            ];
            
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="micronews_backup_' . date('Y-m-d_H-i-s') . '.json"');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
            
        default:
            jsonResponse(['error' => 'Unbekannte Aktion: ' . ($action ?? 'none')], 400);
    }
}

// ==================== POST REQUESTS ====================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Methode nicht erlaubt'], 405);
}

if (in_array($action, $adminProtectedPostActions, true)) {
    if (!isAdmin()) {
        jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
    }

    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfHeader)) {
        jsonResponse(['success' => false, 'message' => 'Ungültiger CSRF-Token'], 403);
    }
}

switch ($action) {
    case 'login':
        $password = $input['password'] ?? '';
        $username = $input['username'] ?? '';
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!checkRateLimit('login', 5, 300, $clientIp)) {
            jsonResponse(['success' => false, 'message' => 'Zu viele Login-Versuche. Bitte später erneut versuchen.'], 429);
        }

        if (empty(ADMIN_PASSWORD)) {
            jsonResponse(['success' => false, 'message' => 'Admin-Passwort ist nicht konfiguriert'], 503);
        }
        
        if (empty($password)) {
            jsonResponse(['success' => false, 'message' => 'Passwort erforderlich'], 400);
        }
        
        if ($password === ADMIN_PASSWORD) {
            $_SESSION['isAdmin'] = true;
            $_SESSION['admin_logged_in'] = time();
            if (!empty($username)) {
                $_SESSION['username'] = htmlspecialchars(trim($username));
            }
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Falsches Passwort'], 401);
        }
        break;
        
    case 'logout':
        $_SESSION['isAdmin'] = false;
        unset($_SESSION['admin_logged_in']);
        jsonResponse(['success' => true]);
        break;

    case 'set_username':
        $displayName = trim($input['username'] ?? '');
        if (empty($displayName)) {
            jsonResponse(['success' => false, 'message' => 'Name erforderlich'], 400);
        }
        if (mb_strlen($displayName) > 30) {
            jsonResponse(['success' => false, 'message' => 'Name zu lang'], 400);
        }

        $safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $updated = updateUser($currentUser['id'], ['display_name' => $safeDisplayName]);
        if (!$updated) {
            jsonResponse(['success' => false, 'message' => 'Fehler beim Speichern'], 500);
        }

        $_SESSION['display_name'] = $safeDisplayName;
        jsonResponse(['success' => true, 'username' => $safeDisplayName, 'user' => $updated]);
        break;

    case 'update_profile':
        $displayName = trim($input['display_name'] ?? '');
        $bio = trim($input['bio'] ?? '');

        if (empty($displayName)) {
            jsonResponse(['success' => false, 'message' => 'Name erforderlich'], 400);
        }
        if (mb_strlen($displayName) > 30) {
            jsonResponse(['success' => false, 'message' => 'Name zu lang'], 400);
        }
        if (mb_strlen($bio) > 200) {
            jsonResponse(['success' => false, 'message' => 'Bio zu lang'], 400);
        }

        $safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $safeBio = htmlspecialchars($bio, ENT_QUOTES, 'UTF-8');

        $updated = updateUser($currentUser['id'], [
            'display_name' => $safeDisplayName,
            'bio' => $safeBio
        ]);

        if (!$updated) {
            jsonResponse(['success' => false, 'message' => 'Fehler beim Speichern'], 500);
        }

        $_SESSION['display_name'] = $safeDisplayName;
        jsonResponse(['success' => true, 'user' => $updated]);
        break;

    case 'follow':
        $targetUserId = $input['user_id'] ?? '';
        if (empty($targetUserId)) {
            jsonResponse(['success' => false, 'message' => 'User-ID erforderlich'], 400);
        }
        if (!followUser($currentUser['id'], $targetUserId)) {
            jsonResponse(['success' => false, 'message' => 'Fehler beim Folgen'], 500);
        }

        if ($targetUserId !== ($currentUser['id'] ?? '')) {
            addNotification(
                $targetUserId,
                'follow',
                $currentUser['id'] ?? null,
                $currentUser['display_name'] ?? getUsername(),
                ['follower_username' => $currentUser['username'] ?? '']
            );
        }

        jsonResponse(['success' => true]);
        break;

    case 'unfollow':
        $targetUserId = $input['user_id'] ?? '';
        if (empty($targetUserId)) {
            jsonResponse(['success' => false, 'message' => 'User-ID erforderlich'], 400);
        }
        if (!unfollowUser($currentUser['id'], $targetUserId)) {
            jsonResponse(['success' => false, 'message' => 'Fehler beim Entfolgen'], 500);
        }
        jsonResponse(['success' => true]);
        break;

    case 'view':
        $postId = $input['id'] ?? '';
        if (empty($postId)) {
            jsonResponse(['success' => false, 'message' => 'Post-ID erforderlich'], 400);
        }

        foreach ($posts as &$post) {
            if ($post['id'] === $postId) {
                $post['views'] = (int)($post['views'] ?? 0) + 1;
                savePosts($posts);
                jsonResponse(['success' => true, 'views' => $post['views']]);
            }
        }
        jsonResponse(['success' => false, 'message' => 'Post nicht gefunden'], 404);
        break;
        
    case 'create':
        // User auf Ban prüfen
        if (isUserBanned($currentUser['id'])) {
            jsonResponse(['success' => false, 'message' => 'Dein Account ist gesperrt'], 403);
        }
        
        $text = trim($input['text'] ?? '');
        $link = trim($input['link'] ?? '');
        $image = $input['image'] ?? '';
        $pinned = $input['pinned'] ?? false;
        $color = $input['color'] ?? 'default';
        
        if (empty($text)) {
            jsonResponse(['success' => false, 'message' => 'Text ist erforderlich'], 400);
        }
        if (strlen($text) > 2000) {
            jsonResponse(['success' => false, 'message' => 'Text zu lang'], 400);
        }
        if (!empty($image) && !validateImage($image)) {
            jsonResponse(['success' => false, 'message' => 'Ungültiges Bild'], 400);
        }
        
        $imageName = '';
        if (!empty($image)) {
            try {
                $imageData = explode(',', $image)[1] ?? '';
                if (!empty($imageData)) {
                    $decoded = @base64_decode($imageData);
                    if ($decoded !== false) {
                        $extension = 'jpg';
                        if (function_exists('finfo_open')) {
                            $finfo = @new finfo(FILEINFO_MIME_TYPE);
                            if ($finfo) {
                                $mimeType = $finfo->buffer($decoded);
                                $extensionMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                                $extension = $extensionMap[$mimeType] ?? 'jpg';
                            }
                        }
                        $imageName = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                        @file_put_contents(UPLOAD_DIR . $imageName, $decoded);
                    }
                }
            } catch (Exception $e) {
                // Bild-Fehler ignorieren, Post trotzdem erstellen
            }
        }
        
        $linkPreview = null;
        if (!empty($link) && filter_var($link, FILTER_VALIDATE_URL)) {
            $linkPreview = fetchLinkPreview($link);
        }
        
        $newPost = [
            'id' => time() . '_' . bin2hex(random_bytes(4)),
            'text' => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
            'link' => filter_var($link, FILTER_VALIDATE_URL) ? $link : '',
            'link_preview' => $linkPreview,
            'image' => $imageName,
            'hashtags' => extractHashtags($text),
            'pinned' => isAdmin() && $pinned,
            'color' => $color,
            'date' => date('d.m.Y H:i'),
            'timestamp' => time(),
            'views' => 0,
            'likes' => 0,
            'comments' => [],
            'reports' => [],
            'author' => $currentUser['display_name'],
            'author_id' => $currentUser['id'],
            'author_username' => $currentUser['username']
        ];
        
        array_unshift($posts, $newPost);
        if (count($posts) > MAX_POSTS) array_pop($posts);
        
        if (!savePosts($posts)) {
            jsonResponse(['success' => false, 'message' => 'Fehler beim Speichern'], 500);
        }
        
        jsonResponse(['success' => true, 'post' => $newPost]);
        break;
        
    case 'like':
        $postId = $input['id'] ?? '';
        foreach ($posts as &$post) {
            if ($post['id'] === $postId) {
                $post['likes'] = (int)($post['likes'] ?? 0) + 1;

                $postAuthorId = $post['author_id'] ?? '';
                if (!empty($postAuthorId) && $postAuthorId !== ($currentUser['id'] ?? '')) {
                    addNotification(
                        $postAuthorId,
                        'like',
                        $currentUser['id'] ?? null,
                        $currentUser['display_name'] ?? getUsername(),
                        [
                            'post_id' => $post['id'],
                            'post_preview' => mb_substr(strip_tags($post['text'] ?? ''), 0, 80)
                        ]
                    );
                }

                savePosts($posts);
                jsonResponse(['success' => true, 'likes' => $post['likes']]);
            }
        }
        jsonResponse(['success' => false, 'message' => 'Post nicht gefunden'], 404);
        break;
        
    case 'comment':
        $postId = $input['id'] ?? '';
        $commentText = trim($input['comment'] ?? '');
        
        if (empty($postId)) {
            jsonResponse(['success' => false, 'message' => 'Post-ID erforderlich'], 400);
        }
        if (empty($commentText)) {
            jsonResponse(['success' => false, 'message' => 'Kommentar erforderlich'], 400);
        }
        if (strlen($commentText) > 500) {
            jsonResponse(['success' => false, 'message' => 'Kommentar zu lang'], 400);
        }
        
        foreach ($posts as &$post) {
            if ($post['id'] === $postId) {
                $newComment = [
                    'id' => time() . '_' . bin2hex(random_bytes(4)),
                    'text' => htmlspecialchars($commentText, ENT_QUOTES, 'UTF-8'),
                    'timestamp' => time(),
                    'author' => $currentUser['display_name'],
                    'author_id' => $currentUser['id']
                ];
                $post['comments'][] = $newComment;

                $postAuthorId = $post['author_id'] ?? '';
                if (!empty($postAuthorId) && $postAuthorId !== ($currentUser['id'] ?? '')) {
                    addNotification(
                        $postAuthorId,
                        'comment',
                        $currentUser['id'] ?? null,
                        $currentUser['display_name'] ?? getUsername(),
                        [
                            'post_id' => $post['id'],
                            'comment_preview' => mb_substr(strip_tags($commentText), 0, 80)
                        ]
                    );
                }

                savePosts($posts);
                jsonResponse(['success' => true, 'comments' => $post['comments']]);
            }
        }
        
        jsonResponse(['success' => false, 'message' => 'Post nicht gefunden'], 404);
        break;
        
    case 'bookmark':
        $postId = $input['id'] ?? '';
        $bookmarkFile = 'bookmarks_' . getUserId() . '.json';
        
        $bookmarks = file_exists($bookmarkFile) ? @json_decode(@file_get_contents($bookmarkFile), true) ?? [] : [];
        
        $index = array_search($postId, $bookmarks);
        if ($index !== false) {
            unset($bookmarks[$index]);
            $bookmarks = array_values($bookmarks);
            $message = 'Lesezeichen entfernt';
        } else {
            $bookmarks[] = $postId;
            $message = 'Zu Lesezeichen hinzugefügt';
        }
        
        writeJsonFile($bookmarkFile, $bookmarks);
        jsonResponse(['success' => true, 'bookmarked' => $index === false, 'message' => $message]);
        break;
        
    case 'report':
        $postId = $input['id'] ?? '';
        $reason = trim($input['reason'] ?? 'Spam');
        $userId = getUserId();
        $reportRateId = ($currentUser['id'] ?? $userId) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (!checkRateLimit('report', 10, 300, $reportRateId)) {
            jsonResponse(['success' => false, 'message' => 'Zu viele Meldungen. Bitte später erneut versuchen.'], 429);
        }
        
        foreach ($posts as &$post) {
            if ($post['id'] === $postId) {
                $alreadyReported = false;
                foreach ($post['reports'] ?? [] as $report) {
                    if (isset($report['user_id']) && $report['user_id'] === $userId) {
                        $alreadyReported = true;
                        break;
                    }
                }
                
                if (!$alreadyReported) {
                    $post['reports'][] = [
                        'user_id' => $userId,
                        'reason' => htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'),
                        'timestamp' => time(),
                        'date' => date('d.m.Y H:i')
                    ];
                    savePosts($posts);
                    jsonResponse(['success' => true, 'message' => 'Post gemeldet']);
                } else {
                    jsonResponse(['success' => false, 'message' => 'Bereits gemeldet'], 400);
                }
            }
        }
        
        jsonResponse(['success' => false, 'message' => 'Post nicht gefunden'], 404);
        break;
        
    case 'save_draft':
        $draft = [
            'text' => trim($input['text'] ?? ''),
            'link' => trim($input['link'] ?? ''),
            'color' => $input['color'] ?? 'default',
            'saved_at' => time()
        ];
        
        $draftFile = 'draft_' . getUserId() . '.json';
        writeJsonFile($draftFile, $draft);
        jsonResponse(['success' => true]);
        break;
        
    // Admin Actions
    case 'ban_user':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
        }
        
        $userId = $input['user_id'] ?? '';
        $reason = $input['reason'] ?? 'Kein Grund';
        
        if (banUser($userId, $reason)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Fehler'], 500);
        }
        break;
        
    case 'unban_user':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
        }
        
        $userId = $input['user_id'] ?? '';
        
        if (unbanUser($userId)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Fehler'], 500);
        }
        break;
        
    case 'bulk_delete':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
        }
        
        $postIds = $input['post_ids'] ?? [];
        
        if (empty($postIds)) {
            jsonResponse(['success' => false, 'message' => 'Keine Posts'], 400);
        }
        
        $deletedCount = bulkDeletePosts($postIds);
        jsonResponse(['success' => true, 'deleted_count' => $deletedCount]);
        break;
        
    case 'bulk_report':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
        }
        
        $postIds = $input['post_ids'] ?? [];
        $reason = $input['reason'] ?? 'Bulk-Report';
        
        if (empty($postIds)) {
            jsonResponse(['success' => false, 'message' => 'Keine Posts'], 400);
        }
        
        $reportedCount = bulkReportPosts($postIds, $reason);
        jsonResponse(['success' => true, 'reported_count' => $reportedCount]);
        break;
        
    case 'send_message':
        $toId = $input['to_id'] ?? '';
        $text = trim($input['text'] ?? '');
        
        if (empty($toId)) {
            jsonResponse(['success' => false, 'message' => 'Empfänger erforderlich'], 400);
        }
        if (empty($text)) {
            jsonResponse(['success' => false, 'message' => 'Nachricht erforderlich'], 400);
        }
        
        $message = sendMessage($currentUser['id'], $toId, $text);
        if ($message) {
            if ($toId !== ($currentUser['id'] ?? '')) {
                addNotification(
                    $toId,
                    'message',
                    $currentUser['id'] ?? null,
                    $currentUser['display_name'] ?? getUsername(),
                    ['message_preview' => mb_substr(strip_tags($text), 0, 80)]
                );
            }
            jsonResponse(['success' => true, 'message' => $message]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Fehler'], 500);
        }
        break;

    case 'mark_notifications_read':
        if (markNotificationsAsRead($currentUser['id'])) {
            jsonResponse(['success' => true]);
        }
        jsonResponse(['success' => false, 'message' => 'Fehler beim Aktualisieren'], 500);
        break;

    case 'delete_user':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
        }

        $userId = $input['user_id'] ?? '';
        if (empty($userId)) {
            jsonResponse(['success' => false, 'message' => 'User-ID erforderlich'], 400);
        }

        $user = getUserById($userId);
        if (!$user) {
            jsonResponse(['success' => false, 'message' => 'User nicht gefunden'], 404);
        }

        $users = getUsers();
        $users = array_values(array_filter($users, fn($u) => ($u['id'] ?? '') !== $userId));
        if (!saveUsers($users)) {
            jsonResponse(['success' => false, 'message' => 'User konnte nicht gespeichert werden'], 500);
        }

        $follows = getFollows();
        $follows = array_values(array_filter($follows, fn($f) =>
            ($f['follower_id'] ?? '') !== $userId && ($f['following_id'] ?? '') !== $userId
        ));
        saveFollows($follows);
        refreshAllUserCounts();

        $messages = getMessages();
        $messages = array_values(array_filter($messages, fn($m) =>
            ($m['from_id'] ?? '') !== $userId && ($m['to_id'] ?? '') !== $userId
        ));
        saveMessages($messages);

        deleteUserNotifications($userId);

        $deletedPosts = deleteUserPosts($userId);

        logActivity('user_deleted', [
            'deleted_user_id' => $userId,
            'deleted_username' => $user['username'] ?? 'unknown',
            'deleted_posts' => $deletedPosts
        ]);

        jsonResponse(['success' => true, 'deleted_posts' => $deletedPosts]);
        break;

    case 'edit_post':
    if (!isAdmin()) {
        jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
    }
    
    $postId = $input['id'] ?? '';
    $text = trim($input['text'] ?? '');
    $link = trim($input['link'] ?? '');
    $color = $input['color'] ?? 'default';
    $pinned = $input['pinned'] ?? false;
    
    if (empty($postId)) {
        jsonResponse(['success' => false, 'message' => 'Post-ID erforderlich'], 400);
    }
    
    if (empty($text)) {
        jsonResponse(['success' => false, 'message' => 'Text ist erforderlich'], 400);
    }
    
    if (strlen($text) > 2000) {
        jsonResponse(['success' => false, 'message' => 'Text zu lang'], 400);
    }
    
    $found = false;
    foreach ($posts as &$post) {
        if ($post['id'] === $postId) {
            $post['text'] = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            $post['link'] = filter_var($link, FILTER_VALIDATE_URL) ? $link : '';
            $post['color'] = $color;
            $post['pinned'] = $pinned;
            $post['hashtags'] = extractHashtags($text);
            $post['edited'] = true;
            $post['editedAt'] = date('Y-m-d H:i:s');
            $post['editedBy'] = $currentUser['display_name'];
            
            $found = true;
            
            // Activity Log
            logActivity('post_edited', [
                'post_id' => $postId,
                'edited_by' => $currentUser['display_name'],
                'changes' => [
                    'text' => substr($text, 0, 50) . '...',
                    'color' => $color,
                    'pinned' => $pinned
                ]
            ]);
            
            break;
        }
    }
    
    if (!$found) {
        jsonResponse(['success' => false, 'message' => 'Post nicht gefunden'], 404);
    }
    
    if (!savePosts($posts)) {
        jsonResponse(['success' => false, 'message' => 'Fehler beim Speichern'], 500);
    }
    
    jsonResponse(['success' => true, 'message' => 'Post aktualisiert']);
    break;

    case 'delete':
    if (!isAdmin()) {
        jsonResponse(['success' => false, 'message' => 'Nicht autorisiert'], 403);
    }
    
    $postId = $input['id'] ?? '';
    if (empty($postId)) {
        jsonResponse(['success' => false, 'message' => 'Post-ID erforderlich'], 400);
    }
    
    $newPosts = [];
    $deletedImage = '';
    
    foreach ($posts as $post) {
        if ($post['id'] === $postId) {
            $deletedImage = $post['image'] ?? '';
        } else {
            $newPosts[] = $post;
        }
    }
    
    if (!empty($deletedImage) && file_exists(UPLOAD_DIR . $deletedImage)) {
        @unlink(UPLOAD_DIR . $deletedImage);
    }
    
    if (!savePosts($newPosts)) {
        jsonResponse(['success' => false, 'message' => 'Fehler beim Löschen'], 500);
    }
    
    logActivity('post_deleted', [
        'post_id' => $postId,
        'deleted_by' => $currentUser['display_name']
    ]);
    
    jsonResponse(['success' => true]);
    break;
    
    default:
        jsonResponse(['error' => 'Unbekannte Aktion: ' . ($action ?? 'none')], 400);
}
?>