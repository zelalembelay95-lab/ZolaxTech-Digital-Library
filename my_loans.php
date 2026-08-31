<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_login();
$page_title = "My Loans";

$user_id = current_user_id();
$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew_loan_id'])) {
    $loan_id = (int)$_POST['renew_loan_id'];

    $stmt = $conn->prepare("SELECT l.*, c.item_id FROM loans l JOIN copies c ON c.id = l.copy_id
                             WHERE l.id = ? AND l.member_id = ? AND l.return_date IS NULL");
    $stmt->bind_param("ii", $loan_id, $user_id);
    $stmt->execute();
    $loan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$loan) {
        $error_message = "Loan not found.";
    } elseif (strtotime($loan['due_date']) < strtotime(date('Y-m-d'))) {
        $error_message = "This item is overdue and can no longer be renewed online — please return it.";
    } elseif ($loan['renewed_count'] >= MAX_RENEWALS) {
        $error_message = "This item has already been renewed the maximum number of times.";
    } else {
        $hold_check = $conn->prepare("SELECT id FROM holds WHERE item_id = ? AND status = 'pending'");
        $hold_check->bind_param("i", $loan['item_id']);
        $hold_check->execute();
        if ($hold_check->get_result()->num_rows > 0) {
            $error_message = "This item cannot be renewed because another member has placed a hold on it.";
        } else {
            $new_due = date('Y-m-d', strtotime($loan['due_date'] . " +" . LOAN_PERIOD_DAYS . " days"));
            $upd = $conn->prepare("UPDATE loans SET due_date = ?, renewed_count = renewed_count + 1 WHERE id = ?");
            $upd->bind_param("si", $new_due, $loan_id);
            $upd->execute();
            $upd->close();
            $success_message = "Renewed! New due date: " . format_date($new_due) . ".";
        }
        $hold_check->close();
    }
}

$active_stmt = $conn->prepare("SELECT l.id, l.due_date, l.renewed_count, i.title, i.id AS item_id, c.barcode
                                FROM loans l JOIN copies c ON c.id = l.copy_id JOIN items i ON i.id = c.item_id
                                WHERE l.member_id = ? AND l.return_date IS NULL
                                ORDER BY l.due_date ASC");
$active_stmt->bind_param("i", $user_id);
$active_stmt->execute();
$active_loans = $active_stmt->get_result();

$history_stmt = $conn->prepare("SELECT l.loan_date, l.return_date, i.title
                                 FROM loans l JOIN copies c ON c.id = l.copy_id JOIN items i ON i.id = c.item_id
                                 WHERE l.member_id = ? AND l.return_date IS NOT NULL
                                 ORDER BY l.return_date DESC LIMIT 10");
$history_stmt->bind_param("i", $user_id);
$history_stmt->execute();
$history = $history_stmt->get_result();

require_once 'includes/header.php';
?>

<section class="page-section wide">
  <h1>My Loans</h1>

  <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
  <?php if ($error_message): ?><div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

  <h2>Currently Borrowed</h2>
  <table class="item-table">
    <thead><tr><th>Title</th><th>Barcode</th><th>Due Date</th><th>Renewals</th><th>Action</th></tr></thead>
    <tbody>
      <?php while ($l = $active_loans->fetch_assoc()):
        $overdue = strtotime($l['due_date']) < strtotime(date('Y-m-d')); ?>
        <tr>
          <td><a href="item.php?id=<?php echo $l['item_id']; ?>"><?php echo htmlspecialchars($l['title']); ?></a></td>
          <td><?php echo htmlspecialchars($l['barcode']); ?></td>
          <td class="<?php echo $overdue ? 'text-overdue' : ''; ?>">
            <?php echo format_date($l['due_date']); ?>
            <?php if ($overdue): ?><span class="status-badge status-rejected">Overdue</span><?php endif; ?>
          </td>
          <td><?php echo $l['renewed_count']; ?> / <?php echo MAX_RENEWALS; ?></td>
          <td>
            <?php if (!$overdue && $l['renewed_count'] < MAX_RENEWALS): ?>
              <form method="POST" class="inline-form">
                <input type="hidden" name="renew_loan_id" value="<?php echo $l['id']; ?>">
                <button type="submit" class="btn btn-small btn-primary">Renew</button>
              </form>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      <?php if ($active_loans->num_rows === 0): ?>
        <tr><td colspan="5" class="empty-state">You have no items currently borrowed.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <h2>Loan History</h2>
  <table class="item-table">
    <thead><tr><th>Title</th><th>Borrowed</th><th>Returned</th></tr></thead>
    <tbody>
      <?php while ($h = $history->fetch_assoc()): ?>
        <tr>
          <td><?php echo htmlspecialchars($h['title']); ?></td>
          <td><?php echo format_date($h['loan_date']); ?></td>
          <td><?php echo format_date($h['return_date']); ?></td>
        </tr>
      <?php endwhile; ?>
      <?php if ($history->num_rows === 0): ?>
        <tr><td colspan="3" class="empty-state">No past loans yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<?php
$active_stmt->close();
$history_stmt->close();
require_once 'includes/footer.php';
?>
