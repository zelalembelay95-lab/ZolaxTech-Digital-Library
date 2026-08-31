<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$collection_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT c.*, co.name AS community_name, co.id AS community_id
                         FROM collections c JOIN communities co ON co.id = c.community_id
                         WHERE c.id = ?");
$stmt->bind_param("i", $collection_id);
$stmt->execute();
$collection = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$collection) {
    http_response_code(404);
    $page_title = "Not Found";
    require_once 'includes/header.php';
    echo '<section class="page-section"><h1>Collection Not Found</h1></section>';
    require_once 'includes/footer.php';
    exit();
}

$pg = get_pagination_params(20, 100);

$count_stmt = $conn->prepare("SELECT COUNT(*) c FROM items WHERE collection_id = ? AND status = 'approved'");
$count_stmt->bind_param("i", $collection_id);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['c'];
$count_stmt->close();
$total_pg = total_pages($total_rows, $pg['per_page']);

$stmt = $conn->prepare("SELECT id, title, author, item_type, publication_date
                         FROM items WHERE collection_id = ? AND status = 'approved'
                         ORDER BY title ASC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $collection_id, $pg['per_page'], $pg['offset']);
$stmt->execute();
$items = $stmt->get_result();

$page_title = $collection['name'];
require_once 'includes/header.php';
?>

<section class="page-section wide">
  <nav class="breadcrumb">
    <a href="communities.php">Communities</a> &raquo;
    <a href="community.php?id=<?php echo $collection['community_id']; ?>"><?php echo htmlspecialchars($collection['community_name']); ?></a> &raquo;
    <?php echo htmlspecialchars($collection['name']); ?>
  </nav>
  <h1><?php echo htmlspecialchars($collection['name']); ?></h1>
  <p><?php echo htmlspecialchars($collection['description']); ?></p>
  <p class="result-count"><?php echo number_format($total_rows); ?> item<?php echo $total_rows == 1 ? '' : 's'; ?></p>

  <div class="item-grid">
    <?php while ($item = $items->fetch_assoc()): ?>
      <a href="item.php?id=<?php echo $item['id']; ?>" class="item-card">
        <span class="item-icon"><?php echo type_icon($item['item_type']); ?></span>
        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
        <p class="item-author"><?php echo htmlspecialchars($item['author'] ?: 'Unknown author'); ?></p>
        <p class="item-date"><?php echo format_date($item['publication_date']); ?></p>
      </a>
    <?php endwhile; ?>
    <?php if ($items->num_rows === 0): ?>
      <p class="empty-state">No items in this collection yet.</p>
    <?php endif; ?>
  </div>

  <?php
    $base_url = "collection.php?id=" . $collection_id . "&";
    render_pagination($pg['page'], $total_pg, $base_url);
  ?>
</section>

<?php
$stmt->close();
require_once 'includes/footer.php';
?>
