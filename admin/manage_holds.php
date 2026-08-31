<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_role(['admin','librarian']);
$in_admin = true;
$page_title = "Manage Holds";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hold_id = (int)($_POST['hold_id'] ?? 0);
    if ($hold_id > 0) {
        $stmt = $conn->prepare("UPDATE holds SET status = 'cancelled' WHERE id = ? AND status IN ('pending','ready')");
        $stmt->bind_param("i", $hold_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: manage_holds.php");
    exit();
}

$stmt = $conn->query("SELECT h.id, h.hold_date, h.status, i.title, i.id AS item_id, u.full_name, u.membership_no,
                              c.barcode AS ready_barcode
                       FROM holds h
                       JOIN items i ON i.id = h.item_id
                       JOIN users u ON u.id = h.member_id
                       LEFT JOIN copies c ON c.id = h.ready_copy_id
                       WHERE h.status IN ('pending','ready')
                       ORDER BY h.status = 'ready' DESC, h.hold_date ASC");

require_once '../includes/header.php';
?>

<section class="page-section wide">
  <h1>Manage Holds</h1>
  <p>Holds are fulfilled automatically: when a copy of a title with a
     pending hold is returned at the Circulation Desk, that copy is
     marked "reserved" for the member who has been waiting longest.</p>

  <table class="item-table">
    <thead><tr><th>Title</th><th>Member</th><th>Requested</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
      <?php while ($h = $stmt->fetch_assoc()): ?>
        <tr>
          <td><a href="../item.php?id=<?php echo $h['item_id']; ?>" target="_blank"><?php echo htmlspecialchars($h['title']); ?></a></td>
          <td><?php echo htmlspecialchars($h['full_name']); ?> (<?php echo htmlspecialchars($h['membership_no']); ?>)</td>
          <td><?php echo format_date($h['hold_date']); ?></td>
          <td>
            <?php if ($h['status'] === 'ready'): ?>
              <span class="status-badge status-approved">Ready — barcode <?php echo htmlspecialchars($h['ready_barcode']); ?></span>
            <?php else: ?>
              <span class="status-badge status-pending">Waiting</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" class="inline-form">
              <input type="hidden" name="hold_id" value="<?php echo $h['id']; ?>">
              <button type="submit" class="btn btn-small btn-danger">Cancel</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      <?php if ($stmt->num_rows === 0): ?>
        <tr><td colspan="5" class="empty-state">No active holds.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<?php require_once '../includes/footer.php'; ?>
