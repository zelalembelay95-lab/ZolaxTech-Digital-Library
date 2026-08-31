<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_role(['admin','librarian']);
$in_admin = true;
$page_title = "Review Submissions";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $action  = $_POST['action'] ?? '';
    if ($item_id > 0 && in_array($action, ['approve','reject'], true)) {
        $new_status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE items SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $item_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: review_submissions.php");
    exit();
}

$pg = get_pagination_params(20, 100);
$total_rows = $conn->query("SELECT COUNT(*) c FROM items WHERE status='pending'")->fetch_assoc()['c'];
$total_pg = total_pages($total_rows, $pg['per_page']);

$stmt = $conn->prepare("SELECT i.id, i.title, i.author, i.item_type, i.created_at, u.full_name AS submitter_name
                         FROM items i JOIN users u ON u.id = i.submitter_id
                         WHERE i.status = 'pending'
                         ORDER BY i.created_at ASC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $pg['per_page'], $pg['offset']);
$stmt->execute();
$items = $stmt->get_result();

require_once '../includes/header.php';
?>

<section class="page-section wide">
  <h1>Review Submissions</h1>
  <p class="result-count"><?php echo number_format($total_rows); ?> item<?php echo $total_rows == 1 ? '' : 's'; ?> awaiting review</p>

  <table class="item-table">
    <thead><tr><th></th><th>Title</th><th>Submitted By</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
      <?php while ($item = $items->fetch_assoc()): ?>
        <tr>
          <td class="type-col"><?php echo type_icon($item['item_type']); ?></td>
          <td><a href="../item.php?id=<?php echo $item['id']; ?>" target="_blank"><?php echo htmlspecialchars($item['title']); ?></a></td>
          <td><?php echo htmlspecialchars($item['submitter_name']); ?></td>
          <td><?php echo format_date($item['created_at']); ?></td>
          <td>
            <form method="POST" class="inline-form">
              <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
              <button type="submit" name="action" value="approve" class="btn btn-small btn-success">Approve</button>
              <button type="submit" name="action" value="reject" class="btn btn-small btn-danger">Reject</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      <?php if ($items->num_rows === 0): ?>
        <tr><td colspan="5" class="empty-state">No pending submissions. 🎉</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php render_pagination($pg['page'], $total_pg, "review_submissions.php?"); ?>
</section>

<?php
$stmt->close();
require_once '../includes/footer.php';
?>
