<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_role('admin');
$in_admin = true;
$page_title = "Bulk Import";

$error_message = "";
$success_message = "";

$collections = $conn->query("SELECT c.id, c.name, co.name AS community_name FROM collections c JOIN communities co ON co.id = c.community_id ORDER BY co.name, c.name");
$collection_options = [];
while ($row = $collections->fetch_assoc()) { $collection_options[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $default_collection_id = (int)($_POST['collection_id'] ?? 0);

    if ($default_collection_id <= 0) {
        $error_message = "Please choose a default collection for the imported items.";
    } elseif ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "There was a problem uploading the CSV file.";
    } else {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            $error_message = "Could not read the uploaded file.";
        } else {
            $header = fgetcsv($handle);
            $header = array_map('trim', array_map('strtolower', $header));
            $required = ['title'];
            $missing = array_diff($required, $header);

            if (!empty($missing)) {
                $error_message = "CSV must at least have a 'title' column.";
            } else {
                $conn->begin_transaction();
                $inserted = 0;
                $submitter_id = current_user_id();

                $stmt = $conn->prepare("INSERT INTO items
                    (collection_id, submitter_id, title, author, abstract, publisher, publication_date, language, item_type, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')");

                while (($row = fgetcsv($handle)) !== false) {
                    $data = array_combine($header, array_pad($row, count($header), ''));
                    $title = trim($data['title'] ?? '');
                    if ($title === '') continue;

                    $author = trim($data['author'] ?? '');
                    $abstract = trim($data['abstract'] ?? '');
                    $publisher = trim($data['publisher'] ?? '');
                    $pub_date = trim($data['publication_date'] ?? '') ?: null;
                    $language = trim($data['language'] ?? '') ?: 'en';
                    $item_type = trim($data['item_type'] ?? '') ?: 'book';
                    $allowed_types = ['book','thesis','article','report','image','dataset','other'];
                    if (!in_array($item_type, $allowed_types, true)) $item_type = 'other';

                    $stmt->bind_param("iisssssss", $default_collection_id, $submitter_id, $title, $author,
                        $abstract, $publisher, $pub_date, $language, $item_type);
                    $stmt->execute();
                    $inserted++;
                }
                $stmt->close();
                $conn->commit();
                fclose($handle);
                $success_message = "$inserted item(s) imported successfully.";
            }
        }
    }
}

require_once '../includes/header.php';
?>

<section class="page-section narrow">
  <h1>Bulk Import (CSV)</h1>
  <p>Upload a CSV file to create many items at once &mdash; useful for
     loading an existing library catalog. Columns: <code>title</code>
     (required), <code>author</code>, <code>abstract</code>,
     <code>publisher</code>, <code>publication_date</code> (YYYY-MM-DD),
     <code>language</code>, <code>item_type</code>
     (book/thesis/article/report/image/dataset/other).</p>

  <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
  <?php if ($error_message): ?><div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="form">
    <label for="collection_id">Import into Collection *</label>
    <select id="collection_id" name="collection_id" required>
      <option value="">-- Select a collection --</option>
      <?php foreach ($collection_options as $c): ?>
        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['community_name'] . ' / ' . $c['name']); ?></option>
      <?php endforeach; ?>
    </select>

    <label for="csv_file">CSV File *</label>
    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>

    <button type="submit" class="btn btn-primary">Import</button>
  </form>

  <p class="form-footer-link">Need to load a very large catalog (tens of
     thousands of rows)? Use the command-line seeder in
     <code>scripts/seed_demo_data.php</code> instead &mdash; it is built
     for that scale and won't hit a web request's time limit.</p>
</section>

<?php require_once '../includes/footer.php'; ?>
