<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_role(['admin','librarian']);
$in_admin = true;
$page_title = "Admin Dashboard";

$counts = [];
$counts['approved'] = $conn->query("SELECT COUNT(*) c FROM items WHERE status='approved'")->fetch_assoc()['c'];
$counts['pending']  = $conn->query("SELECT COUNT(*) c FROM items WHERE status='pending'")->fetch_assoc()['c'];
$counts['rejected'] = $conn->query("SELECT COUNT(*) c FROM items WHERE status='rejected'")->fetch_assoc()['c'];
$counts['users']    = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$counts['communities'] = $conn->query("SELECT COUNT(*) c FROM communities")->fetch_assoc()['c'];
$counts['collections'] = $conn->query("SELECT COUNT(*) c FROM collections")->fetch_assoc()['c'];
$counts['downloads'] = $conn->query("SELECT COUNT(*) c FROM downloads")->fetch_assoc()['c'];
$counts['messages'] = $conn->query("SELECT COUNT(*) c FROM messages")->fetch_assoc()['c'];
$counts['copies'] = $conn->query("SELECT COUNT(*) c FROM copies")->fetch_assoc()['c'];
$counts['active_loans'] = $conn->query("SELECT COUNT(*) c FROM loans WHERE return_date IS NULL")->fetch_assoc()['c'];
$counts['overdue_loans'] = $conn->query("SELECT COUNT(*) c FROM loans WHERE return_date IS NULL AND due_date < CURDATE()")->fetch_assoc()['c'];
$counts['pending_holds'] = $conn->query("SELECT COUNT(*) c FROM holds WHERE status IN ('pending','ready')")->fetch_assoc()['c'];
$counts['unpaid_fines'] = $conn->query("SELECT COALESCE(SUM(amount),0) c FROM fines WHERE status='unpaid'")->fetch_assoc()['c'];

require_once '../includes/header.php';
?>

<section class="page-section wide">
  <h1>Admin Dashboard</h1>

  <div class="stats-strip admin-stats">
    <div class="stat-box"><span class="stat-number"><?php echo number_format($counts['approved']); ?></span><span class="stat-label">Published Items</span></div>
    <div class="stat-box highlight"><span class="stat-number"><?php echo number_format($counts['pending']); ?></span><span class="stat-label">Pending Review</span></div>
    <div class="stat-box"><span class="stat-number"><?php echo number_format($counts['rejected']); ?></span><span class="stat-label">Rejected</span></div>
    <div class="stat-box"><span class="stat-number"><?php echo number_format($counts['users']); ?></span><span class="stat-label">Users</span></div>
    <div class="stat-box"><span class="stat-number"><?php echo number_format($counts['communities']); ?></span><span class="stat-label">Communities</span></div>
    <div class="stat-box"><span class="stat-number"><?php echo number_format($counts['collections']); ?></span><span class="stat-label">Collections</span></div>
    <div class="stat-box"><span class="stat-number"><?php echo number_format($counts['downloads']); ?></span><span class="stat-label">Downloads</span></div>
    <div class="stat-box"><span class="stat-number"><?php echo number_format($counts['messages']); ?></span><span class="stat-label">Contact Messages</span></div>
    <div class="stat-box"><span class="stat-number"><?php echo number_format($counts['copies']); ?></span><span class="stat-label">Physical Copies</span></div>
    <div class="stat-box"><span class="stat-number"><?php echo number_format($counts['active_loans']); ?></span><span class="stat-label">Active Loans</span></div>
    <div class="stat-box highlight"><span class="stat-number"><?php echo number_format($counts['overdue_loans']); ?></span><span class="stat-label">Overdue Loans</span></div>
    <div class="stat-box"><span class="stat-number"><?php echo number_format($counts['pending_holds']); ?></span><span class="stat-label">Active Holds</span></div>
    <div class="stat-box highlight"><span class="stat-number">$<?php echo number_format($counts['unpaid_fines'], 2); ?></span><span class="stat-label">Unpaid Fines</span></div>
  </div>

  <div class="admin-links">
    <a href="review_submissions.php" class="admin-link-card">📥 Review Submissions (<?php echo $counts['pending']; ?> pending)</a>
    <a href="manage_communities.php" class="admin-link-card">🏛 Manage Communities</a>
    <a href="manage_collections.php" class="admin-link-card">🗂 Manage Collections</a>
    <a href="circulation.php" class="admin-link-card">🔄 Circulation Desk</a>
    <a href="manage_copies.php" class="admin-link-card">📚 Manage Copies</a>
    <a href="manage_holds.php" class="admin-link-card">✋ Manage Holds (<?php echo $counts['pending_holds']; ?>)</a>
    <a href="manage_fines.php" class="admin-link-card">💰 Manage Fines</a>
    <?php if (is_admin()): ?>
      <a href="manage_users.php" class="admin-link-card">👤 Manage Users</a>
      <a href="bulk_import.php" class="admin-link-card">📤 Bulk Import (CSV)</a>
      <a href="generate_sample_data.php" class="admin-link-card">🧪 Generate Sample Data</a>
    <?php endif; ?>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>
