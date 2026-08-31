<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$community_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM communities WHERE id = ?");
$stmt->bind_param("i", $community_id);
$stmt->execute();
$community = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$community) {
    http_response_code(404);
    $page_title = "Not Found";
    require_once 'includes/header.php';
    echo '<section class="page-section"><h1>Community Not Found</h1></section>';
    require_once 'includes/footer.php';
    exit();
}

$collections = $conn->prepare("SELECT c.id, c.name, c.description,
                                       (SELECT COUNT(*) FROM items i WHERE i.collection_id = c.id AND i.status='approved') AS item_count
                                FROM collections c WHERE c.community_id = ? ORDER BY c.name");
$collections->bind_param("i", $community_id);
$collections->execute();
$collections_result = $collections->get_result();

$sub_communities = $conn->prepare("SELECT id, name FROM communities WHERE parent_id = ? ORDER BY name");
$sub_communities->bind_param("i", $community_id);
$sub_communities->execute();
$subs_result = $sub_communities->get_result();

$page_title = $community['name'];
require_once 'includes/header.php';
?>

<section class="page-section wide">
  <nav class="breadcrumb"><a href="communities.php">Communities</a> &raquo; <?php echo htmlspecialchars($community['name']); ?></nav>
  <h1><?php echo htmlspecialchars($community['name']); ?></h1>
  <p><?php echo htmlspecialchars($community['description']); ?></p>

  <?php if ($subs_result->num_rows > 0): ?>
    <h2>Sub-Communities</h2>
    <div class="community-list">
      <?php while ($s = $subs_result->fetch_assoc()): ?>
        <a href="community.php?id=<?php echo $s['id']; ?>" class="community-card">
          <h3><?php echo htmlspecialchars($s['name']); ?></h3>
        </a>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>

  <h2>Collections</h2>
  <div class="community-list">
    <?php while ($col = $collections_result->fetch_assoc()): ?>
      <a href="collection.php?id=<?php echo $col['id']; ?>" class="community-card">
        <h3><?php echo htmlspecialchars($col['name']); ?></h3>
        <p><?php echo htmlspecialchars(excerpt($col['description'], 140)); ?></p>
        <span class="community-count"><?php echo number_format($col['item_count']); ?> item<?php echo $col['item_count'] == 1 ? '' : 's'; ?></span>
      </a>
    <?php endwhile; ?>
    <?php if ($collections_result->num_rows === 0): ?>
      <p class="empty-state">No collections in this community yet.</p>
    <?php endif; ?>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
