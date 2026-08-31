<?php
/**
 * functions.php
 * Shared helper functions used across the repository.
 * Include this AFTER db_connect.php.
 */

// ---------- Auth helpers ----------

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_user_role() {
    return $_SESSION['role'] ?? null;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}

// $roles can be a single role string or an array of allowed roles
function require_role($roles) {
    require_login();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array(current_user_role(), $roles, true)) {
        http_response_code(403);
        die("Access denied. You do not have permission to view this page.");
    }
}

function is_admin() {
    return current_user_role() === 'admin';
}

function is_librarian_or_admin() {
    return in_array(current_user_role(), ['admin', 'librarian'], true);
}

// ---------- Pagination ----------
// Returns ['page' => int, 'per_page' => int, 'offset' => int]
function get_pagination_params($default_per_page = 20, $max_per_page = 100) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : $default_per_page;
    $per_page = max(1, min($max_per_page, $per_page));
    $offset = ($page - 1) * $per_page;
    return ['page' => $page, 'per_page' => $per_page, 'offset' => $offset];
}

function total_pages($total_rows, $per_page) {
    return max(1, (int)ceil($total_rows / $per_page));
}

// Renders a simple Prev/Next + numbered pagination strip.
// $base_url should already contain any existing query string params
// you want preserved, ending in either "?" or "&".
function render_pagination($page, $total_pages, $base_url) {
    if ($total_pages <= 1) return;

    echo '<nav class="pagination" aria-label="Pagination">';
    if ($page > 1) {
        echo '<a href="' . $base_url . 'page=' . ($page - 1) . '">&laquo; Prev</a>';
    }

    $window = 2;
    $start = max(1, $page - $window);
    $end = min($total_pages, $page + $window);

    if ($start > 1) {
        echo '<a href="' . $base_url . 'page=1">1</a>';
        if ($start > 2) echo '<span class="dots">&hellip;</span>';
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $page) {
            echo '<span class="current">' . $i . '</span>';
        } else {
            echo '<a href="' . $base_url . 'page=' . $i . '">' . $i . '</a>';
        }
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) echo '<span class="dots">&hellip;</span>';
        echo '<a href="' . $base_url . 'page=' . $total_pages . '">' . $total_pages . '</a>';
    }

    if ($page < $total_pages) {
        echo '<a href="' . $base_url . 'page=' . ($page + 1) . '">Next &raquo;</a>';
    }
    echo '</nav>';
}

// ---------- Formatting ----------

function format_date($date) {
    if (empty($date) || $date === '0000-00-00') return 'Unknown';
    return date('d M Y', strtotime($date));
}

function excerpt($text, $length = 200) {
    $text = trim(strip_tags($text ?? ''));
    // Use mb_* if available (correct for accented/multi-byte text); fall
    // back to plain strlen/substr so this never fatals on a host where
    // the mbstring extension happens to be disabled.
    $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    if ($len <= $length) return $text;
    return (function_exists('mb_substr') ? mb_substr($text, 0, $length) : substr($text, 0, $length)) . '...';
}

function type_icon($type) {
    $icons = [
        'book' => '📘', 'thesis' => '🎓', 'article' => '📰',
        'report' => '📄', 'image' => '🖼️', 'dataset' => '📊', 'other' => '📁',
    ];
    return $icons[$type] ?? '📁';
}

function format_bytes($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// ---------- File storage ----------
// Spreads uploaded files across sub-folders (bucketed by item id ranges)
// so no single folder ever holds an unmanageable number of files, e.g.
// item 1 to 999 -> uploads/0/, item 1000 to 1999 -> uploads/1/, etc.
// This matters once a repository holds tens of thousands of files.
function bucket_path_for_item($item_id) {
    return (int) floor($item_id / 1000);
}

function ensure_upload_dir($bucket) {
    $dir = __DIR__ . '/../uploads/' . $bucket;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

// ---------- Circulation (physical library) ----------

const LOAN_PERIOD_DAYS = 14;
const MAX_RENEWALS = 2;
const FINE_PER_DAY = 0.50;
const DEFAULT_MAX_LOANS = 3;

// Generates the next membership card number, e.g. LIB-000042.
// Used at registration so every member gets a library card number.
function generate_membership_no($conn) {
    $result = $conn->query("SELECT membership_no FROM users WHERE membership_no IS NOT NULL ORDER BY id DESC LIMIT 1");
    $next = 1;
    if ($row = $result->fetch_assoc()) {
        $digits = (int) substr($row['membership_no'], 4);
        $next = $digits + 1;
    }
    return 'LIB-' . str_pad($next, 6, '0', STR_PAD_LEFT);
}

function is_overdue($due_date, $return_date) {
    return $return_date === null && strtotime($due_date) < strtotime(date('Y-m-d'));
}

function days_overdue($due_date) {
    $diff = (strtotime(date('Y-m-d')) - strtotime($due_date)) / 86400;
    return max(0, (int) $diff);
}

// How many active (not yet returned) loans does this member currently have?
function active_loan_count($conn, $member_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM loans WHERE member_id = ? AND return_date IS NULL");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return (int) $count;
}

// Total unpaid fines for this member, in currency units (e.g. dollars).
function unpaid_fines_total($conn, $member_id) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) total FROM fines WHERE member_id = ? AND status = 'unpaid'");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    return (float) $total;
}

// Central eligibility check used before any checkout or renewal is allowed.
// Returns an empty string if the member may borrow, or an error message.
function borrowing_block_reason($conn, $member) {
    if ($member['membership_status'] === 'suspended') {
        return "This membership is suspended.";
    }
    if (unpaid_fines_total($conn, $member['id']) >= 10.00) {
        return "Outstanding fines of $" . number_format(unpaid_fines_total($conn, $member['id']), 2) . " must be paid before borrowing more items.";
    }
    if (active_loan_count($conn, $member['id']) >= (int) $member['max_loans']) {
        return "This member has reached their loan limit (" . (int) $member['max_loans'] . ").";
    }
    return "";
}
