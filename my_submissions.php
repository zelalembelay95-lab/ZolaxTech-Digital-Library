<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_login();
$page_title = "My Submissions";

$user_id = current_user_id();
$stmt = $conn->prepare("SELECT id, title, item_type, status, created_at FROM items WHERE submitter_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$items = $stmt->get_result();

require_once 'includes/header.php';
?>

<section class="page-section wide">
  <h1>My Submissions</h1>

  <?php if (isset($_GET['submitted'])): ?>
    <div class="alert alert-success">Your item was submitted successfully!</div>
  <?php endif; ?>

  <table class="item-table">
    <thead><tr><th></th><th>Title</th><th>Status</th><th>Submitted</th></tr></thead>
    <tbody>
      <?php while ($item = $items->fetch_assoc()): ?>
        <tr>
          <td class="type-col"><?php echo type_icon($item['item_type']); ?></td>
          <td><a href="item.php?id=<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['title']); ?></a></td>
          <td><span class="status-badge status-<?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span></td>
          <td><?php echo format_date($item['created_at']); ?></td>
        </tr>
      <?php endwhile; ?>
      <?php if ($items->num_rows === 0): ?>
        <tr><td colspan="4" class="empty-state">You haven't submitted anything yet. <a href="submit.php">Submit your first item</a>.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<?php
$stmt->close();
require_once 'includes/footer.php';
?>
