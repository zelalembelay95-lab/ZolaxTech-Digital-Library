<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
$page_title = "Communities";

$communities = $conn->query("SELECT c.id, c.name, c.description, c.parent_id,
                                     (SELECT COUNT(*) FROM collections col WHERE col.community_id = c.id) AS collection_count
                              FROM communities c
                              ORDER BY c.parent_id IS NOT NULL, c.name");

require_once 'includes/header.php';
?>

<section class="page-section wide">
  <h1>Communities</h1>
  <p>Materials in this repository are organized into communities, which contain collections, which contain items &mdash; the same structure used by DSpace.</p>

  <div class="community-list">
    <?php while ($c = $communities->fetch_assoc()): ?>
      <a href="community.php?id=<?php echo $c['id']; ?>" class="community-card">
        <h3><?php echo $c['parent_id'] ? '&#8627; ' : ''; ?><?php echo htmlspecialchars($c['name']); ?></h3>
        <p><?php echo htmlspecialchars(excerpt($c['description'], 140)); ?></p>
        <span class="community-count"><?php echo $c['collection_count']; ?> collection<?php echo $c['collection_count'] == 1 ? '' : 's'; ?></span>
      </a>
    <?php endwhile; ?>
    <?php if ($communities->num_rows === 0): ?>
      <p class="empty-state">No communities have been created yet.</p>
    <?php endif; ?>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
