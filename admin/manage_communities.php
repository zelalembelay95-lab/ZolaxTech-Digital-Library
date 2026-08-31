<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_role(['admin','librarian']);
$in_admin = true;
$page_title = "Manage Communities";

$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if ($name === '') {
        $error_message = "Community name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO communities (name, description, parent_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $description, $parent_id);
        $stmt->execute();
        $stmt->close();
        $success_message = "Community created.";
    }
}

$all_communities = $conn->query("SELECT id, name FROM communities ORDER BY name");
$parent_options = [];
while ($row = $all_communities->fetch_assoc()) { $parent_options[] = $row; }

$communities = $conn->query("SELECT c.id, c.name, c.parent_id,
                                    (SELECT COUNT(*) FROM collections col WHERE col.community_id = c.id) AS collection_count
                              FROM communities c ORDER BY c.name");

require_once '../includes/header.php';
?>

<section class="page-section wide">
  <h1>Manage Communities</h1>

  <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
  <?php if ($error_message): ?><div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

  <div class="admin-form-card">
    <h2>Create a New Community</h2>
    <form method="POST" class="form">
      <label for="name">Name *</label>
      <input type="text" id="name" name="name" required>

      <label for="description">Description</label>
      <textarea id="description" name="description" rows="3"></textarea>

      <label for="parent_id">Parent Community (optional)</label>
      <select id="parent_id" name="parent_id">
        <option value="">-- None (top level) --</option>
        <?php foreach ($parent_options as $p): ?>
          <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn btn-primary">Create Community</button>
    </form>
  </div>

  <h2>Existing Communities</h2>
  <table class="item-table">
    <thead><tr><th>Name</th><th>Type</th><th>Collections</th></tr></thead>
    <tbody>
      <?php while ($c = $communities->fetch_assoc()): ?>
        <tr>
          <td><a href="../community.php?id=<?php echo $c['id']; ?>" target="_blank"><?php echo htmlspecialchars($c['name']); ?></a></td>
          <td><?php echo $c['parent_id'] ? 'Sub-community' : 'Top-level'; ?></td>
          <td><?php echo $c['collection_count']; ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</section>

<?php require_once '../includes/footer.php'; ?>
