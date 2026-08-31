<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_role('admin');
$in_admin = true;
$page_title = "Manage Users";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $new_role = $_POST['role'] ?? '';
    if ($user_id > 0 && in_array($new_role, ['admin','librarian','user'], true)) {
        // Prevent an admin from demoting themselves and locking everyone out
        if ($user_id !== current_user_id()) {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $new_role, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: manage_users.php");
    exit();
}

$pg = get_pagination_params(25, 100);
$total_rows = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$total_pg = total_pages($total_rows, $pg['per_page']);

$stmt = $conn->prepare("SELECT id, full_name, email, username, role, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $pg['per_page'], $pg['offset']);
$stmt->execute();
$users = $stmt->get_result();

require_once '../includes/header.php';
?>

<section class="page-section wide">
  <h1>Manage Users</h1>
  <p class="result-count"><?php echo number_format($total_rows); ?> registered user<?php echo $total_rows == 1 ? '' : 's'; ?></p>

  <table class="item-table">
    <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
    <tbody>
      <?php while ($u = $users->fetch_assoc()): ?>
        <tr>
          <td><?php echo htmlspecialchars($u['full_name']); ?></td>
          <td><?php echo htmlspecialchars($u['username']); ?></td>
          <td><?php echo htmlspecialchars($u['email']); ?></td>
          <td>
            <?php if ($u['id'] == current_user_id()): ?>
              <span class="status-badge status-approved"><?php echo ucfirst($u['role']); ?> (you)</span>
            <?php else: ?>
              <form method="POST" class="inline-form">
                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                <select name="role" onchange="this.form.submit()">
                  <option value="user" <?php echo $u['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                  <option value="librarian" <?php echo $u['role'] === 'librarian' ? 'selected' : ''; ?>>Librarian</option>
                  <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
              </form>
            <?php endif; ?>
          </td>
          <td><?php echo format_date($u['created_at']); ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <?php render_pagination($pg['page'], $total_pg, "manage_users.php?"); ?>
</section>

<?php
$stmt->close();
require_once '../includes/footer.php';
?>
