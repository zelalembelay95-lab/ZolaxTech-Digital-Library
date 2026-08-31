<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_role(['admin','librarian']);
$in_admin = true;
$page_title = "Manage Fines";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fine_id = (int)($_POST['fine_id'] ?? 0);
    if ($fine_id > 0) {
        $stmt = $conn->prepare("UPDATE fines SET status = 'paid', paid_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $fine_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: manage_fines.php");
    exit();
}

$filter = $_GET['filter'] ?? 'unpaid';
$where = $filter === 'paid' ? "WHERE f.status = 'paid'" : "WHERE f.status = 'unpaid'";

$total_unpaid = $conn->query("SELECT COALESCE(SUM(amount),0) t FROM fines WHERE status='unpaid'")->fetch_assoc()['t'];

$pg = get_pagination_params(30, 100);
$total_rows = $conn->query("SELECT COUNT(*) c FROM fines f $where")->fetch_assoc()['c'];
$total_pg = total_pages($total_rows, $pg['per_page']);

$stmt = $conn->prepare("SELECT f.id, f.amount, f.reason, f.status, f.created_at, u.full_name, u.membership_no
                         FROM fines f JOIN users u ON u.id = f.member_id
                         $where
                         ORDER BY f.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $pg['per_page'], $pg['offset']);
$stmt->execute();
$fines = $stmt->get_result();

require_once '../includes/header.php';
?>

<section class="page-section wide">
  <h1>Manage Fines</h1>
  <p class="result-count">Total unpaid fines: <strong>$<?php echo number_format($total_unpaid, 2); ?></strong></p>

  <div class="filter-bar">
    <a href="manage_fines.php?filter=unpaid" class="btn btn-small <?php echo $filter === 'unpaid' ? 'btn-primary' : 'btn-secondary'; ?>">Unpaid</a>
    <a href="manage_fines.php?filter=paid" class="btn btn-small <?php echo $filter === 'paid' ? 'btn-primary' : 'btn-secondary'; ?>">Paid</a>
  </div>

  <table class="item-table">
    <thead><tr><th>Member</th><th>Reason</th><th>Amount</th><th>Date</th><th>Action</th></tr></thead>
    <tbody>
      <?php while ($f = $fines->fetch_assoc()): ?>
        <tr>
          <td><?php echo htmlspecialchars($f['full_name']); ?> (<?php echo htmlspecialchars($f['membership_no']); ?>)</td>
          <td><?php echo htmlspecialchars($f['reason']); ?></td>
          <td>$<?php echo number_format($f['amount'], 2); ?></td>
          <td><?php echo format_date($f['created_at']); ?></td>
          <td>
            <?php if ($f['status'] === 'unpaid'): ?>
              <form method="POST" class="inline-form">
                <input type="hidden" name="fine_id" value="<?php echo $f['id']; ?>">
                <button type="submit" class="btn btn-small btn-success">Mark Paid</button>
              </form>
            <?php else: ?>
              <span class="status-badge status-approved">Paid</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      <?php if ($fines->num_rows === 0): ?>
        <tr><td colspan="5" class="empty-state">No fines to show.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php render_pagination($pg['page'], $total_pg, "manage_fines.php?filter=" . urlencode($filter) . "&"); ?>
</section>

<?php
$stmt->close();
require_once '../includes/footer.php';
?>
