<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$bitstream_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT b.*, i.status, i.submitter_id, i.id AS item_id
                         FROM bitstreams b
                         JOIN items i ON i.id = b.item_id
                         WHERE b.id = ?");
$stmt->bind_param("i", $bitstream_id);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$file) {
    http_response_code(404);
    die("File not found.");
}

$can_view = ($file['status'] === 'approved')
    || (is_logged_in() && (current_user_id() == $file['submitter_id'] || is_librarian_or_admin()));

if (!$can_view) {
    http_response_code(403);
    die("This file is not available.");
}

$full_path = __DIR__ . '/' . $file['file_path'];
if (!file_exists($full_path)) {
    http_response_code(404);
    die("File is missing from storage.");
}

// Log the download and update counters (only for approved/public items,
// so staff previewing a pending item doesn't inflate public stats).
if ($file['status'] === 'approved') {
    $log = $conn->prepare("INSERT INTO downloads (item_id, bitstream_id, user_id) VALUES (?, ?, ?)");
    $uid = current_user_id();
    $log->bind_param("iii", $file['item_id'], $bitstream_id, $uid);
    $log->execute();
    $log->close();

    $upd = $conn->prepare("UPDATE items SET download_count = download_count + 1 WHERE id = ?");
    $upd->bind_param("i", $file['item_id']);
    $upd->execute();
    $upd->close();
}

// Stream the file to the browser
header('Content-Description: File Transfer');
header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
header('Content-Length: ' . filesize($full_path));
header('Cache-Control: must-revalidate');
readfile($full_path);
exit();
