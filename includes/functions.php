<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* --------------------------------------------------------------------------
   Utilitaires généraux
   -------------------------------------------------------------------------- */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Renvoie une URL de retour interne sûre (ex: "detail.php?id=3"). */
function safe_redirect_target(?string $target, string $default = 'index.php'): string
{
    if ($target && preg_match('/^[a-z\-]+\.php(\?[\w=&%+.\-]*)?(#[\w\-]*)?$/i', $target)) {
        return $target;
    }
    return $default;
}

/* --------------------------------------------------------------------------
   Protection CSRF
   -------------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Jeton de sécurité invalide. Rechargez la page et réessayez.');
    }
}

/* --------------------------------------------------------------------------
   Authentification
   -------------------------------------------------------------------------- */

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }
    return $user;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('info', 'Connectez-vous pour accéder à cette page.');
        redirect('login.php?redirect=' . urlencode(basename($_SERVER['REQUEST_URI'])));
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        flash('danger', 'Accès réservé aux administrateurs du campus.');
        redirect('index.php');
    }
    return $user;
}

/* --------------------------------------------------------------------------
   Affichage
   -------------------------------------------------------------------------- */

function initials(string $first, string $last): string
{
    return mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
}

function short_name(array $user): string
{
    return $user['first_name'] . ' ' . mb_substr($user['last_name'], 0, 1) . '.';
}

function request_ref(int $id): string
{
    return '#REQ-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
}

function format_date_fr(?string $datetime, bool $withTime = false): string
{
    if (!$datetime) {
        return '';
    }
    $months = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    $ts = strtotime($datetime);
    $out = date('d', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    if ($withTime) {
        $out .= ', ' . date('H\hi', $ts);
    }
    return $out;
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return "à l'instant";
    }
    if ($diff < 3600) {
        return 'il y a ' . floor($diff / 60) . ' min';
    }
    if ($diff < 86400) {
        return 'il y a ' . floor($diff / 3600) . ' h';
    }
    $days = (int) floor($diff / 86400);
    if ($days === 1) {
        return 'hier';
    }
    if ($days < 30) {
        return "il y a $days jours";
    }
    return 'le ' . format_date_fr($datetime);
}

/** Statistiques de vote calculées à partir d'une ligne de requête. */
function vote_stats(array $r): array
{
    $pro = (int) $r['pro'];
    $neutral = (int) $r['neutral'];
    $con = (int) $r['con'];
    $total = $pro + $neutral + $con;
    $pct = fn($n) => $total > 0 ? (int) round($n * 100 / $total) : 0;
    return [
        'pro' => $pro,
        'neutral' => $neutral,
        'con' => $con,
        'total' => $total,
        'pro_pct' => $pct($pro),
        'neutral_pct' => $pct($neutral),
        'con_pct' => $pct($con),
        'missing' => max(0, VOTE_THRESHOLD - $pro),
    ];
}

function status_badge(array $r): string
{
    $pro = (int) ($r['pro'] ?? 0);
    switch ($r['status']) {
        case 'validated':
            return '<span class="badge badge-validated">✅ Validée par le Campus</span>';
        case 'threshold':
            return '<span class="badge badge-threshold">🎯 Seuil atteint (' . $pro . '/' . VOTE_THRESHOLD . ')</span>';
        case 'voting':
            return '<span class="badge badge-voting">Vote en cours (' . $pro . '/' . VOTE_THRESHOLD . ')</span>';
        case 'pending':
            return '<span class="badge badge-pending">⏳ Modération initiale</span>';
        case 'rejected':
            return '<span class="badge badge-rejected">❌ Rejetée</span>';
    }
    return '';
}

/** Nom public de l'auteur (respecte l'anonymat - Story 8). */
function author_display(array $r): array
{
    if ((int) $r['is_anonymous'] === 1) {
        return ['name' => 'Étudiant Anonyme', 'initials' => '🎭', 'anon' => true, 'promo' => 'Identité protégée'];
    }
    return [
        'name' => $r['first_name'] . ' ' . $r['last_name'],
        'initials' => initials($r['first_name'], $r['last_name']),
        'anon' => false,
        'promo' => $r['promo'] ?? '',
    ];
}

/* --------------------------------------------------------------------------
   Accès aux données
   -------------------------------------------------------------------------- */

const REQUEST_SELECT = "
    SELECT r.*, c.name AS category_name, c.slug AS category_slug,
           u.first_name, u.last_name, u.promo,
           COALESCE(v.pro, 0) AS pro, COALESCE(v.neutral, 0) AS neutral, COALESCE(v.con, 0) AS con,
           COALESCE(v.total, 0) AS total_votes, COALESCE(cm.cnt, 0) AS comment_count
    FROM requests r
    JOIN categories c ON c.id = r.category_id
    JOIN users u ON u.id = r.user_id
    LEFT JOIN (
        SELECT request_id, SUM(value = 'pro') AS pro, SUM(value = 'neutral') AS neutral,
               SUM(value = 'con') AS con, COUNT(*) AS total
        FROM votes GROUP BY request_id
    ) v ON v.request_id = r.id
    LEFT JOIN (SELECT request_id, COUNT(*) AS cnt FROM comments GROUP BY request_id) cm ON cm.request_id = r.id
";

function find_request(int $id): ?array
{
    $stmt = db()->prepare(REQUEST_SELECT . ' WHERE r.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY id')->fetchAll();
}

function user_vote(int $requestId, int $userId): ?string
{
    $stmt = db()->prepare('SELECT value FROM votes WHERE request_id = ? AND user_id = ?');
    $stmt->execute([$requestId, $userId]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : $value;
}

function notify(int $userId, ?int $requestId, string $type, string $title, string $message): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id, request_id, type, title, message) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $requestId, $type, $title, $message]);
}

function audit(?int $adminId, int $requestId, string $action, string $message): void
{
    $stmt = db()->prepare('INSERT INTO audit_log (admin_id, request_id, action, message) VALUES (?, ?, ?, ?)');
    $stmt->execute([$adminId, $requestId, $action, $message]);
}

/**
 * Notifie l'auteur d'une requête et tous les votants ayant activé la préférence donnée.
 * $prefColumn est une colonne de la table users (notif_validation / notif_threshold).
 */
function notify_participants(array $request, string $prefColumn, string $type, string $title, string $message): void
{
    if (!in_array($prefColumn, ['notif_validation', 'notif_threshold'], true)) {
        return;
    }
    $stmt = db()->prepare("
        SELECT DISTINCT u.id FROM users u
        WHERE u.$prefColumn = 1
          AND (u.id = :author OR u.id IN (SELECT user_id FROM votes WHERE request_id = :rid))
          AND u.email NOT LIKE '%@demo.campus'
    ");
    $stmt->execute(['author' => $request['user_id'], 'rid' => $request['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        notify((int) $uid, (int) $request['id'], $type, $title, $message);
    }
}

/** Passe une requête en « Seuil atteint » si les conditions sont remplies (Story 9). */
function check_threshold(int $requestId): void
{
    $r = find_request($requestId);
    if (!$r || $r['status'] !== 'voting' || $r['is_reported']) {
        return;
    }
    $s = vote_stats($r);
    if ($s['pro'] >= VOTE_THRESHOLD && $s['pro_pct'] >= THRESHOLD_MIN_PCT) {
        db()->prepare("UPDATE requests SET status = 'threshold', threshold_at = NOW() WHERE id = ?")->execute([$requestId]);
        $short = mb_strimwidth($r['title'], 0, 45, '…');
        notify_participants($r, 'notif_threshold', 'threshold', 'Seuil de ' . VOTE_THRESHOLD . ' votes atteint', "« $short » est transmise à la commission.");
        audit(null, $requestId, 'threshold', 'Requête ' . request_ref($requestId) . " ($short) a franchi le palier des " . VOTE_THRESHOLD . " votes et a été inscrite à l'ordre du jour.");
    }
}

function unread_notifications_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function latest_notifications(int $userId, int $limit = 5): array
{
    $stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC, id DESC LIMIT ' . (int) $limit);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function notif_icon(string $type): string
{
    return ['validated' => '✅', 'threshold' => '🎯', 'comment' => '💬', 'moderation' => '🛡️', 'info' => 'ℹ️'][$type] ?? 'ℹ️';
}
