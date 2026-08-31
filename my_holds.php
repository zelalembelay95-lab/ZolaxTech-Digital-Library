<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_login();
$page_title = "My Holds";

$user_id = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hold_id'])) {
    $hold_id = (int)$_POST['hold_id'];
    $stmt = $conn->prepare("UPDATE holds SET status = 'cancelled' WHERE id = ? AND member_id = ? AND status IN ('pending','ready')");
    $stmt->bind_param("ii", $hold_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: my_holds.php");
    exit();
}

$stmt = $conn->prepare("SELECT h.id, h.hold_date, h.status, i.id AS item_id, i.title, c.barcode
                         FROM holds h
                         JOIN items i ON i.id = h.item_id
                         LEFT JOIN copies c ON c.id = h.ready_copy_id
                         WHERE h.member_id = ? AND h.status IN ('pending','ready')
                         ORDER BY h.hold_date ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$holds = $stmt->get_result();

require_once 'includes/header.php';
?>

<section class="page-section wide">
  <h1>My Holds</h1>

  <table class="item-table">
    <thead><tr><th>Title</th><th>Requested</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
      <?php while ($h = $holds->fetch_assoc()): ?>
        <tr>
          <td><a href="item.php?id=<?php echo $h['item_id']; ?>"><?php echo htmlspecialchars($h['title']); ?></a></td>
          <td><?php echo format_date($h['hold_date']); ?></td>
          <td>
            <?php if ($h['status'] === 'ready'): ?>
              <span class="status-badge status-approved">Ready — barcode <?php echo htmlspecialchars($h['barcode']); ?></span>
            <?php else: ?>
              <span class="status-badge status-pending">Waiting in queue</span>
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
      <?php if ($holds->num_rows === 0): ?>
        <tr><td colspan="4" class="empty-state">You have no active holds.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<?php
$stmt->close();
require_once 'includes/footer.php';
?>
