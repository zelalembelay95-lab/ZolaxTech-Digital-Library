<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
$page_title = "Search Results";

$q = trim($_GET['q'] ?? '');
$type_filter = $_GET['type'] ?? '';
$allowed_types = ['book','thesis','article','report','image','dataset','other'];

// Build a MySQL BOOLEAN MODE query: each word becomes a required,
// prefix-matched term, e.g. "web programming" -> "+web* +programming*"
function build_boolean_query($raw) {
    $words = preg_split('/\s+/', trim($raw));
    $terms = [];
    foreach ($words as $w) {
        $w = preg_replace('/[+\-<>~()"@*]+/', '', $w);
        if ($w === '') continue;
        $terms[] = '+' . $w . '*';
    }
    return implode(' ', $terms);
}

$boolean_q = build_boolean_query($q);
$pg = get_pagination_params(20, 100);
$total_rows = 0;
$items = null;

require_once 'includes/header.php';
?>

<section class="page-section wide">
  <h1>Search Results</h1>

  <?php if ($q === '' || $boolean_q === ''): ?>
    <p class="empty-state">Please enter a search term above.</p>

  <?php else:
      // ----- Count matching items (combining title/author/abstract
      // matches with matches inside extra metadata like subjects).
      // Uses a derived table (materialized once) + JOIN rather than
      // an IN(...) subquery, which measured ~3x faster at 100,000+ rows
      // (MySQL/MariaDB can otherwise treat IN(UNION ...) as a dependent
      // subquery re-run per row instead of computing it once). -----
      $type_sql = "";
      $params = [$boolean_q, $boolean_q];
      $types = "ss";
      if ($type_filter !== '' && in_array($type_filter, $allowed_types, true)) {
          $type_sql = " AND i.item_type = ?";
      }

      $matched_subquery = "
          SELECT id FROM items WHERE status='approved' AND MATCH(title, author, abstract) AGAINST (? IN BOOLEAN MODE)
          UNION
          SELECT im.item_id AS id FROM item_metadata im
            INNER JOIN items ii ON ii.id = im.item_id
            WHERE ii.status='approved' AND MATCH(im.field_value) AGAINST (? IN BOOLEAN MODE)
      ";

      $count_sql = "SELECT COUNT(*) c FROM items i
                    INNER JOIN ($matched_subquery) matched ON matched.id = i.id
                    WHERE i.status='approved' $type_sql";
      $bind_params = $params;
      $bind_types = $types;
      if ($type_sql !== "") { $bind_params[] = $type_filter; $bind_types .= "s"; }

      $count_stmt = $conn->prepare($count_sql);
      $count_stmt->bind_param($bind_types, ...$bind_params);
      $count_stmt->execute();
      $total_rows = $count_stmt->get_result()->fetch_assoc()['c'];
      $count_stmt->close();

      $total_pg = total_pages($total_rows, $pg['per_page']);

      $sql = "SELECT i.id, i.title, i.author, i.item_type, i.publication_date, i.view_count
              FROM items i
              INNER JOIN ($matched_subquery) matched ON matched.id = i.id
              WHERE i.status='approved' $type_sql
              ORDER BY i.title ASC
              LIMIT ? OFFSET ?";
      $run_params = $params;
      $run_types = $types;
      if ($type_sql !== "") { $run_params[] = $type_filter; $run_types .= "s"; }
      $run_params[] = $pg['per_page'];
      $run_params[] = $pg['offset'];
      $run_types .= "ii";

      $stmt = $conn->prepare($sql);
      $stmt->bind_param($run_types, ...$run_params);
      $stmt->execute();
      $items = $stmt->get_result();
  ?>
    <p class="result-count">
      <?php echo number_format($total_rows); ?> result<?php echo $total_rows == 1 ? '' : 's'; ?>
      for "<strong><?php echo htmlspecialchars($q); ?></strong>"
    </p>

    <form class="filter-bar" method="GET" action="search.php">
      <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
      <label>Type:
        <select name="type" onchange="this.form.submit()">
          <option value="">All Types</option>
          <?php foreach ($allowed_types as $t): ?>
            <option value="<?php echo $t; ?>" <?php echo $type_filter === $t ? 'selected' : ''; ?>><?php echo ucfirst($t); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>

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
        <p class="empty-state">No items matched your search. Try different keywords.</p>
      <?php endif; ?>
    </div>

    <?php
      $base_url = "search.php?q=" . urlencode($q) . "&type=" . urlencode($type_filter) . "&";
      render_pagination($pg['page'], $total_pg, $base_url);
      $stmt->close();
    ?>
  <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>
