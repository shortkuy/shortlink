<?php
/**
 * ============================================================
 *  SafeLink Shared — PHP API Endpoint
 * ------------------------------------------------------------
 *  Handles all link operations via RESTful JSON API.
 *  Uses prepared statements, rate limiting, input validation.
 *  No direct DB access from client JavaScript — proxy through here.
 * ============================================================
 */

// Bootstrap config
require_once __DIR__ . '/config.php';

// === CORS Headers ===
$allowed_origins = array_map('trim', explode(',', ALLOWED_ORIGINS));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins) || in_array('*', $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
header('Access-Control-Max-Age: 3600');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// === DB Connection ===
function db() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            error_log('SafeLink DB connection failed: ' . $conn->connect_error);
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
        $conn->set_charset(DB_CHARSET);
    }
    return $conn;
}

// === Get setting value from DB ===
function get_setting($key, $default = '') {
    $stmt = db()->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $res = $stmt->get_result();
    $val = $res->fetch_assoc();
    $stmt->close();
    return $val ? $val['value'] : $default;
}

// === Get client IP (handles proxies) ===
function get_client_ip() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // Take first IP if comma-separated
    $ip = explode(',', $ip)[0];
    $ip = trim($ip);
    // Validate IPv4 or IPv6
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }
    return $ip;
}

// === Rate limiting (per IP, per endpoint) ===
// Returns true if request is allowed, false if rate limit exceeded
function check_rate_limit($endpoint, $max_per_window, $window_minutes) {
    $ip = get_client_ip();
    $window_seconds = $window_minutes * 60;
    $window_start = date('Y-m-d H:i:s', time() - $window_seconds);

    $conn = db();
    // Clean old rate limit entries (older than window)
    $stmt = $conn->prepare('DELETE FROM rate_limits WHERE window_start < ?');
    $stmt->bind_param('s', $window_start);
    $stmt->execute();
    $stmt->close();

    // Get or create rate limit record
    $stmt = $conn->prepare('SELECT count, window_start FROM rate_limits WHERE ip = ? AND endpoint = ? FOR UPDATE');
    $stmt->bind_param('ss', $ip, $endpoint);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    $now = date('Y-m-d H:i:s');
    if ($row) {
        // Check if window expired
        $window_ts = strtotime($row['window_start']);
        if ((time() - $window_ts) > $window_seconds) {
            // Reset window
            $stmt = $conn->prepare('UPDATE rate_limits SET count = 1, window_start = ? WHERE ip = ? AND endpoint = ?');
            $stmt->bind_param('sss', $now, $ip, $endpoint);
            $stmt->execute();
            $stmt->close();
            return true;
        }
        // Check if count exceeded
        if ($row['count'] >= $max_per_window) {
            return false;
        }
        // Increment count
        $stmt = $conn->prepare('UPDATE rate_limits SET count = count + 1 WHERE ip = ? AND endpoint = ?');
        $stmt->bind_param('ss', $ip, $endpoint);
        $stmt->execute();
        $stmt->close();
        return true;
    } else {
        // Create new record
        $stmt = $conn->prepare('INSERT INTO rate_limits (ip, endpoint, count, window_start) VALUES (?, ?, 1, ?)');
        $stmt->bind_param('sss', $ip, $endpoint, $now);
        $stmt->execute();
        $stmt->close();
        return true;
    }
}

// === Validate URL ===
function validate_url($url) {
    if (empty($url) || strlen($url) > 2000) return false;
    if (!preg_match('/^https?:\/\//i', $url)) return false;
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// === Generate random short ID ===
function generate_id($length = 6) {
    $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $charset[random_int(0, strlen($charset) - 1)];
    }
    return $out;
}

// === Verify API key (for admin operations) ===
function verify_api_key() {
    $key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['key'] ?? '';
    return hash_equals(API_KEY, $key);
}

// === Send JSON response ===
function send_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// === Send error response ===
function send_error($msg, $code = 400) {
    send_json(['error' => $msg], $code);
}

// === Parse action from query string or POST body ===
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// === Route ===
switch ($action) {
    case 'create':
        if ($method !== 'POST') send_error('Method not allowed', 405);

        // Rate limit: max 20 creates per hour per IP
        $max_create = (int)get_setting('rate_limit_max_create', '20');
        $window_mins = (int)get_setting('rate_limit_window_minutes', '60');
        if (!check_rate_limit('create', $max_create, $window_mins)) {
            send_error('Rate limit exceeded. Try again later.', 429);
        }

        // Parse JSON body
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) $body = $_POST;

        $dest = trim($body['dest'] ?? '');
        $timer = (int)($body['timer'] ?? 5);
        $mode = $body['mode'] ?? 'safelink';
        $title = !empty($body['title']) ? trim(substr($body['title'], 0, 255)) : null;
        $article_title = !empty($body['articleTitle']) ? trim(substr($body['articleTitle'], 0, 500)) : null;
        $article_intro = !empty($body['articleIntro']) ? trim(substr($body['articleIntro'], 0, 2000)) : null;
        $id_length = max(5, min(10, (int)($body['idLength'] ?? 6)));

        // Validate inputs
        if (!validate_url($dest)) send_error('Invalid destination URL', 400);
        if ($timer < 0 || $timer > 10) send_error('Timer must be 0-10', 400);
        if (!in_array($mode, ['shortlink', 'safelink'])) send_error('Invalid mode', 400);

        // Generate unique ID (retry up to 5 times on collision)
        $conn = db();
        $id = '';
        $attempts = 0;
        do {
            $id = generate_id($id_length);
            $stmt = $conn->prepare('SELECT id FROM links WHERE id = ?');
            $stmt->bind_param('s', $id);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();
            $attempts++;
        } while ($exists && $attempts < 5);

        if ($exists) send_error('Failed to generate unique ID', 500);

        // Insert with prepared statement
        $ip = get_client_ip();
        $stmt = $conn->prepare('INSERT INTO links (id, dest, timer, mode, title, article_title, article_intro, clicks, creator_ip) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)');
        $stmt->bind_param('ssisssss', $id, $dest, $timer, $mode, $title, $article_title, $article_intro, $ip);
        if (!$stmt->execute()) {
            error_log('Insert failed: ' . $stmt->error);
            send_error('Failed to create link', 500);
        }
        $stmt->close();

        // Build the short URL — points to the `go` action for proper link previews
        $go_url = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?action=go&id=' . $id;

        send_json([
            'ok' => true,
            'id' => $id,
            'dest' => $dest,
            'timer' => $timer,
            'mode' => $mode,
            'title' => $title,
            'shortUrl' => $go_url,
            'created_at' => date('c'),
        ], 201);
        break;

    case 'get':
        // Public read — no API key needed
        $id = $_GET['id'] ?? '';
        if (empty($id) || strlen($id) > 20 || !preg_match('/^[a-zA-Z0-9]+$/', $id)) {
            send_error('Invalid link ID', 400);
        }

        // Rate limit: max 120 gets per hour per IP
        $max_get = (int)get_setting('rate_limit_max_get', '120');
        $window_mins = (int)get_setting('rate_limit_window_minutes', '60');
        if (!check_rate_limit('get', $max_get, $window_mins)) {
            send_error('Rate limit exceeded', 429);
        }

        $stmt = db()->prepare('SELECT id, dest, timer, mode, title, clicks, created_at, last_click_at FROM links WHERE id = ? LIMIT 1');
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $link = $res->fetch_assoc();
        $stmt->close();

        if (!$link) send_error('Link not found', 404);
        send_json(['link' => $link]);
        break;

    case 'increment':
        // Public — increments click counter (called from safelink page)
        $id = $_GET['id'] ?? '';
        if (empty($id) || strlen($id) > 20 || !preg_match('/^[a-zA-Z0-9]+$/', $id)) {
            send_error('Invalid link ID', 400);
        }

        // Rate limit: max 120 increments per hour per IP
        $max_inc = (int)get_setting('rate_limit_max_increment', '120');
        $window_mins = (int)get_setting('rate_limit_window_minutes', '60');
        if (!check_rate_limit('increment', $max_inc, $window_mins)) {
            send_error('Rate limit exceeded', 429);
        }

        $stmt = db()->prepare('UPDATE links SET clicks = clicks + 1, last_click_at = NOW() WHERE id = ?');
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        send_json(['ok' => true, 'affected' => $affected]);
        break;

    case 'list':
        // Admin only — requires API key
        if (!verify_api_key()) send_error('Unauthorized', 401);

        // Rate limit: max 30 list calls per hour
        $max_list = (int)get_setting('rate_limit_max_list', '30');
        $window_mins = (int)get_setting('rate_limit_window_minutes', '60');
        if (!check_rate_limit('list', $max_list, $window_mins)) {
            send_error('Rate limit exceeded', 429);
        }

        $limit = min((int)($_GET['limit'] ?? 100), 200);
        $stmt = db()->prepare('SELECT id, dest, timer, mode, title, clicks, created_at, last_click_at FROM links ORDER BY created_at DESC LIMIT ?');
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $links = [];
        while ($row = $res->fetch_assoc()) $links[] = $row;
        $stmt->close();

        send_json(['links' => $links]);
        break;

    case 'delete':
        // Admin only — requires API key
        if (!verify_api_key()) send_error('Unauthorized', 401);

        $id = $_GET['id'] ?? '';
        if (empty($id) || strlen($id) > 20 || !preg_match('/^[a-zA-Z0-9]+$/', $id)) {
            send_error('Invalid link ID', 400);
        }

        $stmt = db()->prepare('DELETE FROM links WHERE id = ?');
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        send_json(['ok' => true, 'affected' => $affected]);
        break;

    case 'stats':
        // Admin only — requires API key
        if (!verify_api_key()) send_error('Unauthorized', 401);

        $stmt = db()->prepare('SELECT COUNT(*) as total, COALESCE(SUM(clicks), 0) as total_clicks, SUM(CASE WHEN mode = "safelink" THEN 1 ELSE 0 END) as safelink_count, SUM(CASE WHEN mode = "shortlink" THEN 1 ELSE 0 END) as shortlink_count FROM links');
        $stmt->execute();
        $res = $stmt->get_result();
        $stats = $res->fetch_assoc();
        $stmt->close();

        send_json(['stats' => $stats]);
        break;

    case 'health':
        // Public health check (no rate limit)
        $stmt = db()->prepare('SELECT 1 as ok FROM links LIMIT 1');
        $stmt->execute();
        $stmt->close();
        send_json(['ok' => true, 'time' => date('c')]);
        break;

    case 'go':
        // === FULL HTML PAGE for social media link previews ===
        // Returns a complete HTML page with proper <title> and OG meta tags
        // so WhatsApp, Facebook, Twitter, Telegram etc. show the article title.
        $id = $_GET['id'] ?? '';
        if (empty($id) || strlen($id) > 20 || !preg_match('/^[a-zA-Z0-9]+$/', $id)) {
            http_response_code(404);
            echo '<html><body><h1>Invalid link ID</h1></body></html>';
            exit;
        }

        // Fetch link from DB (including article_title and article_intro)
        $stmt = db()->prepare('SELECT id, dest, timer, mode, title, article_title, article_intro, clicks FROM links WHERE id = ? LIMIT 1');
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $link = $res->fetch_assoc();
        $stmt->close();

        if (!$link) {
            http_response_code(404);
            echo '<html><head><title>Link Not Found</title></head><body><h1>Link not found</h1><p>The short link does not exist.</p></body></html>';
            exit;
        }

        // Increment click counter
        $stmt = db()->prepare('UPDATE links SET clicks = clicks + 1, last_click_at = NOW() WHERE id = ?');
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $stmt->close();

        // Determine the display title (custom title > article title > default)
        $display_title = $link['title'] ?: ($link['article_title'] ?: 'SafeLink');
        $display_intro = $link['article_intro'] ?: 'Click to continue to your destination.';
        $dest_url = $link['dest'];
        $timer = (int)$link['timer'];
        $mode = $link['mode'];

        // Build static site URL prefix for loading CSS/JS
        $static_base = defined('STATIC_SITE_URL') ? rtrim(STATIC_SITE_URL, '/') : '';
        $css_url = $static_base ? $static_base . '/css/style.css' : '/css/style.css';

        // Output full HTML page
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>' . "\n";
        echo '<html lang="en">' . "\n";
        echo '<head>' . "\n";
        echo '<meta charset="UTF-8">' . "\n";
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        // KEY: Title shows the article title, not "Redirecting..."
        echo '<title>' . htmlspecialchars($display_title, ENT_QUOTES, 'UTF-8') . '</title>' . "\n";
        echo '<meta name="description" content="' . htmlspecialchars($display_intro, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        // Open Graph tags for WhatsApp, Facebook, Twitter link previews
        echo '<meta property="og:title" content="' . htmlspecialchars($display_title, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta property="og:description" content="' . htmlspecialchars($display_intro, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta name="twitter:card" content="summary">' . "\n";
        echo '<meta name="twitter:title" content="' . htmlspecialchars($display_title, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta name="twitter:description" content="' . htmlspecialchars($display_intro, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        echo '<link rel="stylesheet" href="' . htmlspecialchars($css_url, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 100 100%27%3E%3Cdefs%3E%3ClinearGradient id=%27g%27 x1=%270%25%27 y1=%270%25%27 x2=%27100%25%27 y2=%27100%25%27%3E%3Cstop offset=%270%25%27 stop-color=%27%236366f1%27/%3E%3Cstop offset=%27100%25%27 stop-color=%27%23ec4899%27/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width=%27100%27 height=%27100%27 rx=%2722%27 fill=%27url(%23g)%27/%3E%3Ctext x=%2750%27 y=%2768%27 font-size=%2758%27 font-family=%27Arial%27 font-weight=%27bold%27 fill=%27white%27 text-anchor=%27middle%27%3ES%3C/text%3E%3C/svg%3E">' . "\n";
        echo '</head>' . "\n";
        echo '<body>' . "\n";

        // Include inline CSS for critical styles (in case static site is slow)
        echo '<style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0a0a14;color:#e5e7eb;line-height:1.6;min-height:100vh;display:flex;flex-direction:column}
        .container{max-width:740px;width:100%;margin:0 auto;padding:0 20px;flex:1}
        .safelink-page{padding:30px 0 60px}
        .article-header{text-align:center;margin-bottom:24px}
        .article-badge{display:inline-block;padding:4px 12px;border-radius:100px;background:linear-gradient(135deg,rgba(99,102,241,0.15),rgba(236,72,153,0.15));border:1px solid rgba(99,102,241,0.3);color:#a5b4fc;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px}
        .article-title{font-size:28px;font-weight:800;line-height:1.2;letter-spacing:-0.02em;margin-bottom:8px;background:linear-gradient(135deg,#fff,#c7d2fe 50%,#fbcfe8);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}
        .article-meta{font-size:11px;color:#6b6b85}
        .article-intro{text-align:justify;hyphens:auto;line-height:1.75;font-size:1.05rem;color:#cbd5e1;margin-bottom:20px}
        .card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:24px;padding:28px;box-shadow:0 8px 32px -8px rgba(0,0,0,0.5);margin-bottom:24px;text-align:center}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:14px 28px;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;border:1px solid transparent;transition:all 0.2s;text-decoration:none;user-select:none}
        .btn-primary{background:linear-gradient(135deg,#6366f1,#ec4899);color:#fff;box-shadow:0 20px 60px -20px rgba(99,102,241,0.4)}
        .btn-primary:hover{transform:translateY(-2px)}
        .btn:disabled{opacity:0.5;cursor:not-allowed;transform:none!important}
        .timer-circle{width:130px;height:130px;margin:0 auto 18px;position:relative;display:grid;place-items:center}
        .timer-number{font-size:40px;font-weight:800;background:linear-gradient(135deg,#6366f1,#ec4899);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
        .timer-label{font-size:11px;color:#6b6b85;text-transform:uppercase;letter-spacing:0.1em;margin-top:4px}
        .ad-slot{margin:16px 0;min-height:50px;display:flex;align-items:center;justify-content:center;border:1px dashed rgba(255,255,255,0.14);border-radius:10px;background:rgba(255,255,255,0.04);padding:8px}
        .ad-slot:empty{display:none}
        .text-muted{color:#9b9bb5}
        .text-sm{font-size:13px}
        .text-xs{font-size:11px}
        .mb-4{margin-bottom:16px}
        .mt-4{margin-top:16px}
        .spinner{display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.2);border-top-color:#fff;border-radius:50%;animation:spin 0.8s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        </style>' . "\n";

        // Build page content
        echo '<div class="container safelink-page">' . "\n";

        // Article header
        echo '<div class="article-header">' . "\n";
        echo '<span class="article-badge">SafeLink</span>' . "\n";
        echo '<h1 class="article-title">' . htmlspecialchars($display_title, ENT_QUOTES, 'UTF-8') . '</h1>' . "\n";
        echo '<p class="article-meta">SafeLink</p>' . "\n";
        echo '</div>' . "\n";

        // Article intro
        echo '<div class="article-intro">' . htmlspecialchars($display_intro, ENT_QUOTES, 'UTF-8') . '</div>' . "\n";

        // Ad slot top (empty if no ad code configured)
        echo '<div class="ad-slot" id="adTop"></div>' . "\n";

        // Timer + redirect button
        echo '<div class="card" id="actionBox">' . "\n";

        $is_safelink = ($mode === 'safelink');

        if ($is_safelink) {
            // Safelink mode: manual click, no auto-redirect
            echo '<p style="color:#10b981;font-weight:600;margin-bottom:12px">✅ Link ready</p>' . "\n";
            echo '<a href="' . htmlspecialchars($dest_url, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary" rel="nofollow noopener noreferrer">↗ Click here to continue</a>' . "\n";
            echo '<p class="text-muted text-xs mt-4">By clicking the button above, you will be redirected to the destination URL.</p>' . "\n";
        } else {
            // Shortlink mode: auto-redirect after timer
            if ($timer <= 1) {
                // Timer 0 or 1: redirect almost immediately
                echo '<p class="text-muted text-sm mb-4">⏱ Redirecting...</p>' . "\n";
                echo '<div class="timer-circle"><div class="timer-number">' . max(1, $timer) . '</div><div class="timer-label">seconds</div></div>' . "\n";
                echo '<button class="btn btn-primary" disabled><span class="spinner"></span> Continue</button>' . "\n";
                echo '<script>setTimeout(function(){window.location.href=' . json_encode($dest_url) . ';}, ' . max(1, $timer) * 1000 . ');</script>' . "\n";
            } else {
                // Timer 2+: countdown then redirect
                echo '<p class="text-muted text-sm mb-4">⏱ Redirecting in</p>' . "\n";
                echo '<div class="timer-circle"><div class="timer-number" id="timerNum">' . $timer . '</div><div class="timer-label">seconds</div></div>' . "\n";
                echo '<button class="btn btn-primary" disabled><span class="spinner"></span> Continue</button>' . "\n";
                echo '<script>
                (function(){
                    var t=' . $timer . ';var el=document.getElementById("timerNum");
                    var iv=setInterval(function(){
                        t--;if(el)el.textContent=t;
                        if(t<=0){clearInterval(iv);window.location.href=' . json_encode($dest_url) . ';}
                    },1000);
                })();
                </script>' . "\n";
            }
        }

        echo '</div>' . "\n";

        // Ad slot bottom
        echo '<div class="ad-slot" id="adBottom"></div>' . "\n";

        echo '</div>' . "\n"; // container

        // Footer
        echo '<footer style="border-top:1px solid rgba(255,255,255,0.08);padding:20px;text-align:center;color:#6b6b85;font-size:11px">© ' . date('Y') . ' SafeLink · Built for monetization</footer>' . "\n";

        echo '</body>' . "\n";
        echo '</html>';
        exit;
        break;

    default:
        send_error('Unknown action. Available: create, get, go, increment, list, delete, stats, health', 404);
}
