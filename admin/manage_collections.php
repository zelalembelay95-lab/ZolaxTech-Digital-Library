<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_role(['admin','librarian']);
$in_admin = true;
$page_title = "Manage Collections";

$error_message = "";
$success_message = "";

$communities = $conn->query("SELECT id, name FROM communities ORDER BY name");
$community_options = [];
while ($row = $communities->fetch_assoc()) { $community_options[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $community_id = (int)($_POST['community_id'] ?? 0);

    if ($name === '' || $community_id <= 0) {
        $error_message = "Name and Community are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO collections (name, description, community_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $description, $community_id);
        $stmt->execute();
        $stmt->close();
        $success_message = "Collection created.";
    }
}

$collections = $conn->query("SELECT col.id, col.name, co.name AS community_name,
                                     (SELECT COUNT(*) FROM items i WHERE i.collection_id = col.id) AS item_count
                              FROM collections col JOIN communities co ON co.id = col.community_id
                              ORDER BY co.name, col.name");

require_once '../includes/header.php';
?>

<section class="page-section wide">
  <h1>Manage Collections</h1>

  <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
  <?php if ($error_message): ?><div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

  <div class="admin-form-card">
    <h2>Create a New Collection</h2>
    <form method="POST" class="form">
      <label for="name">Name *</label>
      <input type="text" id="name" name="name" required>

      <label for="description">Description</label>
      <textarea id="description" name="description" rows="3"></textarea>

      <label for="community_id">Community *</label>
      <select id="community_id" name="community_id" required>
        <option value="">-- Select a community --</option>
        <?php foreach ($community_options as $c): ?>
          <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn btn-primary">Create Collection</button>
    </form>
  </div>

  <h2>Existing Collections</h2>
  <table class="item-table">
    <thead><tr><th>Name</th><th>Community</th><th>Items</th></tr></thead>
    <tbody>
      <?php while ($c = $collections->fetch_assoc()): ?>
        <tr>
          <td><a href="../collection.php?id=<?php echo $c['id']; ?>" target="_blank"><?php echo htmlspecialchars($c['name']); ?></a></td>
          <td><?php echo htmlspecialchars($c['community_name']); ?></td>
          <td><?php echo number_format($c['item_count']); ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</section>

<?php require_once '../includes/footer.php'; ?>
