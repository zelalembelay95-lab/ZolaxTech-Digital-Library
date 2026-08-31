<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT i.*, c.name AS collection_name, c.id AS collection_id,
                                co.name AS community_name, co.id AS community_id
                         FROM items i
                         JOIN collections c ON c.id = i.collection_id
                         JOIN communities co ON co.id = c.community_id
                         WHERE i.id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    http_response_code(404);
    $page_title = "Not Found";
    require_once 'includes/header.php';
    echo '<section class="page-section"><h1>Item Not Found</h1><p>This item does not exist.</p></section>';
    require_once 'includes/footer.php';
    exit();
}

// Visibility rule: only approved items are public. Pending/rejected items
// are only visible to the submitter or a librarian/admin.
$can_view = ($item['status'] === 'approved')
    || (is_logged_in() && (current_user_id() == $item['submitter_id'] || is_librarian_or_admin()));

if (!$can_view) {
    http_response_code(403);
    $page_title = "Not Available";
    require_once 'includes/header.php';
    echo '<section class="page-section"><h1>Not Available</h1><p>This item is not yet published.</p></section>';
    require_once 'includes/footer.php';
    exit();
}

// Count a view (only once per session, so refreshing doesn't inflate stats)
$viewed_key = 'viewed_item_' . $item_id;
if ($item['status'] === 'approved' && empty($_SESSION[$viewed_key])) {
    $upd = $conn->prepare("UPDATE items SET view_count = view_count + 1 WHERE id = ?");
    $upd->bind_param("i", $item_id);
    $upd->execute();
    $upd->close();
    $_SESSION[$viewed_key] = true;
}

// ---------- Physical copies / holds ----------
$hold_error = "";
$hold_success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_hold') {
    require_login();
    $member_id = current_user_id();

    $existing = $conn->prepare("SELECT id FROM holds WHERE item_id = ? AND member_id = ? AND status IN ('pending','ready')");
    $existing->bind_param("ii", $item_id, $member_id);
    $existing->execute();
    if ($existing->get_result()->num_rows > 0) {
        $hold_error = "You already have an active hold on this item.";
    } else {
        $today = date('Y-m-d');
        $ins = $conn->prepare("INSERT INTO holds (item_id, member_id, hold_date) VALUES (?, ?, ?)");
        $ins->bind_param("iis", $item_id, $member_id, $today);
        $ins->execute();
        $ins->close();
        $hold_success = "Hold placed! You'll be next in line when a copy is returned.";
    }
    $existing->close();
}

$copy_stats = $conn->prepare("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available
    FROM copies WHERE item_id = ?");
$copy_stats->bind_param("i", $item_id);
$copy_stats->execute();
$copy_info = $copy_stats->get_result()->fetch_assoc();
$copy_stats->close();

$my_hold = null;
if (is_logged_in()) {
    $hstmt = $conn->prepare("SELECT h.*, c.barcode FROM holds h LEFT JOIN copies c ON c.id = h.ready_copy_id
                              WHERE h.item_id = ? AND h.member_id = ? AND h.status IN ('pending','ready')");
    $hstmt->bind_param("ii", $item_id, current_user_id());
    $hstmt->execute();
    $my_hold = $hstmt->get_result()->fetch_assoc();
    $hstmt->close();
}

// Extra repeatable metadata (subjects, contributors, identifiers, etc.)
$meta_stmt = $conn->prepare("SELECT field_name, field_value FROM item_metadata WHERE item_id = ? ORDER BY field_name");
$meta_stmt->bind_param("i", $item_id);
$meta_stmt->execute();
$meta_result = $meta_stmt->get_result();
$metadata = [];
while ($row = $meta_result->fetch_assoc()) {
    $metadata[$row['field_name']][] = $row['field_value'];
}
$meta_stmt->close();

// Attached files
$bit_stmt = $conn->prepare("SELECT id, file_name, file_size, mime_type FROM bitstreams WHERE item_id = ?");
$bit_stmt->bind_param("i", $item_id);
$bit_stmt->execute();
$bitstreams = $bit_stmt->get_result();

$page_title = $item['title'];
require_once 'includes/header.php';
?>

<section class="page-section wide">
  <nav class="breadcrumb">
    <a href="communities.php">Communities</a> &raquo;
    <a href="community.php?id=<?php echo $item['community_id']; ?>"><?php echo htmlspecialchars($item['community_name']); ?></a> &raquo;
    <a href="collection.php?id=<?php echo $item['collection_id']; ?>"><?php echo htmlspecialchars($item['collection_name']); ?></a>
  </nav>

  <?php if ($item['status'] !== 'approved'): ?>
    <div class="alert alert-error">
      Status: <strong><?php echo ucfirst($item['status']); ?></strong> &mdash; only visible to you and staff until approved.
    </div>
  <?php endif; ?>

  <div class="item-detail">
    <span class="item-detail-icon"><?php echo type_icon($item['item_type']); ?></span>
    <h1><?php echo htmlspecialchars($item['title']); ?></h1>
    <p class="item-detail-author"><?php echo htmlspecialchars($item['author'] ?: 'Unknown author'); ?></p>

    <table class="meta-table">
      <tr><th>Type</th><td><?php echo ucfirst($item['item_type']); ?></td></tr>
      <tr><th>Publication Date</th><td><?php echo format_date($item['publication_date']); ?></td></tr>
      <?php if ($item['publisher']): ?><tr><th>Publisher</th><td><?php echo htmlspecialchars($item['publisher']); ?></td></tr><?php endif; ?>
      <tr><th>Language</th><td><?php echo htmlspecialchars($item['language']); ?></td></tr>
      <?php if ($item['rights']): ?><tr><th>Rights</th><td><?php echo htmlspecialchars($item['rights']); ?></td></tr><?php endif; ?>
      <?php foreach ($metadata as $field => $values): ?>
        <tr><th><?php echo htmlspecialchars(ucfirst(str_replace('dc.', '', $field))); ?></th>
            <td><?php echo htmlspecialchars(implode('; ', $values)); ?></td></tr>
      <?php endforeach; ?>
      <tr><th>Views</th><td><?php echo number_format($item['view_count']); ?></td></tr>
      <tr><th>Downloads</th><td><?php echo number_format($item['download_count']); ?></td></tr>
    </table>

    <?php if (!empty($item['abstract'])): ?>
      <h2>Abstract</h2>
      <p class="item-abstract"><?php echo nl2br(htmlspecialchars($item['abstract'])); ?></p>
    <?php endif; ?>

    <?php if ((int)$copy_info['total'] > 0): ?>
      <h2>Physical Availability</h2>
      <?php if ($hold_success): ?><div class="alert alert-success"><?php echo htmlspecialchars($hold_success); ?></div><?php endif; ?>
      <?php if ($hold_error): ?><div class="alert alert-error"><?php echo htmlspecialchars($hold_error); ?></div><?php endif; ?>

      <p>
        <strong><?php echo (int)$copy_info['available']; ?></strong> of
        <strong><?php echo (int)$copy_info['total']; ?></strong> physical
        cop<?php echo $copy_info['total'] == 1 ? 'y' : 'ies'; ?> available.
      </p>

      <?php if ($my_hold): ?>
        <p>
          Your hold status:
          <?php if ($my_hold['status'] === 'ready'): ?>
            <span class="status-badge status-approved">Ready for pickup — barcode <?php echo htmlspecialchars($my_hold['barcode']); ?></span>
          <?php else: ?>
            <span class="status-badge status-pending">Waiting in queue</span>
          <?php endif; ?>
          (see <a href="my_holds.php">My Holds</a>)
        </p>
      <?php elseif ((int)$copy_info['available'] > 0): ?>
        <p>Visit the circulation desk with your library card to borrow a copy.</p>
      <?php elseif (is_logged_in()): ?>
        <form method="POST" class="inline-form">
          <input type="hidden" name="action" value="place_hold">
          <button type="submit" class="btn btn-primary">Place a Hold</button>
        </form>
      <?php else: ?>
        <p><a href="login.php">Log in</a> to place a hold on this item.</p>
      <?php endif; ?>
    <?php endif; ?>

    <h2>Files</h2>
    <?php if ($bitstreams->num_rows === 0): ?>
      <p class="empty-state">No files have been attached to this item.</p>
    <?php else: ?>
      <ul class="file-list">
        <?php while ($b = $bitstreams->fetch_assoc()): ?>
          <li>
            <a href="download.php?id=<?php echo $b['id']; ?>">⬇ <?php echo htmlspecialchars($b['file_name']); ?></a>
            <span class="file-size">(<?php echo format_bytes($b['file_size']); ?>)</span>
          </li>
        <?php endwhile; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<?php
$bit_stmt->close();
require_once 'includes/footer.php';
?>
