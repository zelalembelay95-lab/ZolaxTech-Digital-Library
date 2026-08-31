<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_role(['admin','librarian']);
$in_admin = true;
$page_title = "Manage Copies";

$error_message = "";
$success_message = "";

$items = $conn->query("SELECT id, title FROM items WHERE status='approved' ORDER BY title");
$item_options = [];
while ($row = $items->fetch_assoc()) { $item_options[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $barcode = trim($_POST['barcode'] ?? '');
    $shelf_location = trim($_POST['shelf_location'] ?? '');

    if ($item_id <= 0 || $barcode === '') {
        $error_message = "Item and barcode are required.";
    } else {
        $check = $conn->prepare("SELECT id FROM copies WHERE barcode = ?");
        $check->bind_param("s", $barcode);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error_message = "That barcode is already in use.";
        } else {
            $stmt = $conn->prepare("INSERT INTO copies (item_id, barcode, shelf_location) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $item_id, $barcode, $shelf_location);
            $stmt->execute();
            $stmt->close();
            $success_message = "Copy added.";
        }
        $check->close();
    }
}

$pg = get_pagination_params(30, 100);
$total_rows = $conn->query("SELECT COUNT(*) c FROM copies")->fetch_assoc()['c'];
$total_pg = total_pages($total_rows, $pg['per_page']);

$stmt = $conn->prepare("SELECT c.id, c.barcode, c.shelf_location, c.status, i.title, i.id AS item_id
                         FROM copies c JOIN items i ON i.id = c.item_id
                         ORDER BY c.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $pg['per_page'], $pg['offset']);
$stmt->execute();
$copies = $stmt->get_result();

require_once '../includes/header.php';
?>

<section class="page-section wide">
  <h1>Manage Copies</h1>

  <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
  <?php if ($error_message): ?><div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

  <div class="admin-form-card">
    <h2>Add a Physical Copy</h2>
    <form method="POST" class="form">
      <label for="item_id">Item (Title) *</label>
      <select id="item_id" name="item_id" required>
        <option value="">-- Select an item --</option>
        <?php foreach ($item_options as $it): ?>
          <option value="<?php echo $it['id']; ?>"><?php echo htmlspecialchars($it['title']); ?></option>
        <?php endforeach; ?>
      </select>

      <label for="barcode">Barcode *</label>
      <input type="text" id="barcode" name="barcode" placeholder="e.g. CS-000123" required>

      <label for="shelf_location">Shelf Location</label>
      <input type="text" id="shelf_location" name="shelf_location" placeholder="e.g. A12">

      <button type="submit" class="btn btn-primary">Add Copy</button>
    </form>
  </div>

  <h2>All Copies</h2>
  <table class="item-table">
    <thead><tr><th>Barcode</th><th>Title</th><th>Shelf</th><th>Status</th></tr></thead>
    <tbody>
      <?php while ($c = $copies->fetch_assoc()): ?>
        <tr>
          <td><?php echo htmlspecialchars($c['barcode']); ?></td>
          <td><a href="../item.php?id=<?php echo $c['item_id']; ?>" target="_blank"><?php echo htmlspecialchars($c['title']); ?></a></td>
          <td><?php echo htmlspecialchars($c['shelf_location'] ?: '—'); ?></td>
          <td><span class="status-badge status-<?php echo $c['status'] === 'available' ? 'approved' : 'pending'; ?>"><?php echo ucfirst(str_replace('_',' ',$c['status'])); ?></span></td>
        </tr>
      <?php endwhile; ?>
      <?php if ($copies->num_rows === 0): ?>
        <tr><td colspan="4" class="empty-state">No physical copies added yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php render_pagination($pg['page'], $total_pg, "manage_copies.php?"); ?>
</section>

<?php
$stmt->close();
require_once '../includes/footer.php';
?>
