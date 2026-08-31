<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
$page_title = "Home";

// Repository-wide stats
$stats = [];
$stats['items'] = $conn->query("SELECT COUNT(*) c FROM items WHERE status='approved'")->fetch_assoc()['c'];
$stats['communities'] = $conn->query("SELECT COUNT(*) c FROM communities")->fetch_assoc()['c'];
$stats['collections'] = $conn->query("SELECT COUNT(*) c FROM collections")->fetch_assoc()['c'];
$stats['downloads'] = $conn->query("SELECT COUNT(*) c FROM downloads")->fetch_assoc()['c'];

// Recent additions
$recent = $conn->query("SELECT id, title, author, item_type, publication_date
                         FROM items WHERE status='approved'
                         ORDER BY created_at DESC LIMIT 6");

require_once 'includes/header.php';
?>

<section class="hero">
  <h1>Search the Repository</h1>
  <p>Browse and download books, theses, articles, and other digital
     materials held in this repository.</p>
  <form class="hero-search" action="search.php" method="GET">
    <input type="text" name="q" placeholder="Search by title, author, or subject...">
    <button type="submit" class="btn btn-primary">Search</button>
  </form>
  <div class="hero-actions">
    <a href="browse.php" class="btn btn-secondary">Browse All Items</a>
    <a href="communities.php" class="btn btn-secondary">Browse by Community</a>
  </div>
</section>

<section class="stats-strip">
  <div class="stat-box"><span class="stat-number"><?php echo number_format($stats['items']); ?></span><span class="stat-label">Items</span></div>
  <div class="stat-box"><span class="stat-number"><?php echo number_format($stats['communities']); ?></span><span class="stat-label">Communities</span></div>
  <div class="stat-box"><span class="stat-number"><?php echo number_format($stats['collections']); ?></span><span class="stat-label">Collections</span></div>
  <div class="stat-box"><span class="stat-number"><?php echo number_format($stats['downloads']); ?></span><span class="stat-label">Downloads</span></div>
</section>

<section class="recent-items">
  <h2>Recently Added</h2>
  <div class="item-grid">
    <?php while ($item = $recent->fetch_assoc()): ?>
      <a href="item.php?id=<?php echo $item['id']; ?>" class="item-card">
        <span class="item-icon"><?php echo type_icon($item['item_type']); ?></span>
        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
        <p class="item-author"><?php echo htmlspecialchars($item['author'] ?: 'Unknown author'); ?></p>
        <p class="item-date"><?php echo format_date($item['publication_date']); ?></p>
      </a>
    <?php endwhile; ?>
    <?php if ($recent->num_rows === 0): ?>
      <p class="empty-state">No items have been added yet.</p>
    <?php endif; ?>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
