<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
$page_title = "Browse";

$sort = $_GET['sort'] ?? 'title';
$allowed_sorts = [
    'title' => 'title ASC',
    'author' => 'author ASC',
    'date_new' => 'publication_date DESC',
    'date_old' => 'publication_date ASC',
    'popular' => 'view_count DESC',
];
$order_by = $allowed_sorts[$sort] ?? $allowed_sorts['title'];

$type_filter = $_GET['type'] ?? '';
$allowed_types = ['book','thesis','article','report','image','dataset','other'];

$where = "WHERE status = 'approved'";
$params = [];
$param_types = "";

if ($type_filter !== '' && in_array($type_filter, $allowed_types, true)) {
    $where .= " AND item_type = ?";
    $params[] = $type_filter;
    $param_types .= "s";
}

$pg = get_pagination_params(20, 100);

// Total count (uses the same indexed WHERE clause, so this stays fast
// even with 100,000+ rows thanks to idx_status / idx_type).
$count_sql = "SELECT COUNT(*) c FROM items $where";
$count_stmt = $conn->prepare($count_sql);
if ($params) $count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['c'];
$count_stmt->close();

$total_pg = total_pages($total_rows, $pg['per_page']);

$sql = "SELECT id, title, author, item_type, publication_date, view_count
        FROM items $where
        ORDER BY $order_by
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$bind_types = $param_types . "ii";
$bind_params = array_merge($params, [$pg['per_page'], $pg['offset']]);
$stmt->bind_param($bind_types, ...$bind_params);
$stmt->execute();
$items = $stmt->get_result();

require_once 'includes/header.php';

$base_url = "browse.php?sort=" . urlencode($sort) . "&type=" . urlencode($type_filter) . "&";
?>

<section class="page-section wide">
  <h1>Browse All Items</h1>
  <p class="result-count"><?php echo number_format($total_rows); ?> item<?php echo $total_rows == 1 ? '' : 's'; ?> found</p>

  <form class="filter-bar" method="GET" action="browse.php">
    <label>Sort by:
      <select name="sort" onchange="this.form.submit()">
        <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Title (A-Z)</option>
        <option value="author" <?php echo $sort === 'author' ? 'selected' : ''; ?>>Author (A-Z)</option>
        <option value="date_new" <?php echo $sort === 'date_new' ? 'selected' : ''; ?>>Newest First</option>
        <option value="date_old" <?php echo $sort === 'date_old' ? 'selected' : ''; ?>>Oldest First</option>
        <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Viewed</option>
      </select>
    </label>
    <label>Type:
      <select name="type" onchange="this.form.submit()">
        <option value="">All Types</option>
        <?php foreach ($allowed_types as $t): ?>
          <option value="<?php echo $t; ?>" <?php echo $type_filter === $t ? 'selected' : ''; ?>><?php echo ucfirst($t); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>

  <table class="item-table">
    <thead>
      <tr><th></th><th>Title</th><th>Author</th><th>Date</th><th>Views</th></tr>
    </thead>
    <tbody>
      <?php while ($item = $items->fetch_assoc()): ?>
        <tr>
          <td class="type-col"><?php echo type_icon($item['item_type']); ?></td>
          <td><a href="item.php?id=<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['title']); ?></a></td>
          <td><?php echo htmlspecialchars($item['author'] ?: '—'); ?></td>
          <td><?php echo format_date($item['publication_date']); ?></td>
          <td><?php echo number_format($item['view_count']); ?></td>
        </tr>
      <?php endwhile; ?>
      <?php if ($items->num_rows === 0): ?>
        <tr><td colspan="5" class="empty-state">No items match this filter.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php render_pagination($pg['page'], $total_pg, $base_url); ?>
</section>

<?php
$stmt->close();
require_once 'includes/footer.php';
?>
