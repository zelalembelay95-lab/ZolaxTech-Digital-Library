<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_login();
$page_title = "Submit Item";

$error_message = "";
$allowed_extensions = ['pdf','epub','doc','docx','txt','ppt','pptx','odt'];
$max_file_size = 25 * 1024 * 1024; // 25 MB

$collections = $conn->query("SELECT c.id, c.name, co.name AS community_name
                              FROM collections c JOIN communities co ON co.id = c.community_id
                              ORDER BY co.name, c.name");
$collections_list = [];
while ($row = $collections->fetch_assoc()) { $collections_list[] = $row; }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title            = trim($_POST['title'] ?? '');
    $author           = trim($_POST['author'] ?? '');
    $abstract         = trim($_POST['abstract'] ?? '');
    $publisher        = trim($_POST['publisher'] ?? '');
    $publication_date = trim($_POST['publication_date'] ?? '');
    $language         = trim($_POST['language'] ?? 'en');
    $item_type        = $_POST['item_type'] ?? 'book';
    $rights           = trim($_POST['rights'] ?? '');
    $collection_id    = (int)($_POST['collection_id'] ?? 0);
    $subjects         = trim($_POST['subjects'] ?? '');
    $identifier       = trim($_POST['identifier'] ?? '');

    $allowed_types = ['book','thesis','article','report','image','dataset','other'];
    if (!in_array($item_type, $allowed_types, true)) $item_type = 'other';

    if ($title === '' || $collection_id <= 0) {
        $error_message = "Title and Collection are required.";
    } else {
        // Confirm the collection actually exists
        $check = $conn->prepare("SELECT id FROM collections WHERE id = ?");
        $check->bind_param("i", $collection_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            $error_message = "Please choose a valid collection.";
        }
        $check->close();
    }

    // Validate the uploaded file, if any
    $has_file = isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($error_message === '' && $has_file) {
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error_message = "There was a problem uploading your file.";
        } elseif ($_FILES['file']['size'] > $max_file_size) {
            $error_message = "File is too large (max 25 MB).";
        } else {
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_extensions, true)) {
                $error_message = "File type not allowed. Allowed: " . implode(', ', $allowed_extensions);
            }
        }
    }

    if ($error_message === '') {
        $status = is_librarian_or_admin() ? 'approved' : 'pending';
        $pub_date_sql = $publication_date !== '' ? $publication_date : null;
        $submitter_id = current_user_id();

        $stmt = $conn->prepare("INSERT INTO items
            (collection_id, submitter_id, title, author, abstract, publisher, publication_date, language, item_type, rights, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssssssss",
            $collection_id, $submitter_id, $title, $author, $abstract,
            $publisher, $pub_date_sql, $language, $item_type, $rights, $status);
        $stmt->execute();
        $item_id = $stmt->insert_id;
        $stmt->close();

        // Repeatable metadata: subjects (comma separated) + identifier
        if ($subjects !== '') {
            $meta_stmt = $conn->prepare("INSERT INTO item_metadata (item_id, field_name, field_value) VALUES (?, 'dc.subject', ?)");
            foreach (explode(',', $subjects) as $subj) {
                $subj = trim($subj);
                if ($subj === '') continue;
                $meta_stmt->bind_param("is", $item_id, $subj);
                $meta_stmt->execute();
            }
            $meta_stmt->close();
        }
        if ($identifier !== '') {
            $meta_stmt = $conn->prepare("INSERT INTO item_metadata (item_id, field_name, field_value) VALUES (?, 'dc.identifier', ?)");
            $meta_stmt->bind_param("is", $item_id, $identifier);
            $meta_stmt->execute();
            $meta_stmt->close();
        }

        // Store the uploaded file, bucketed into sub-folders for scale
        if ($has_file) {
            $bucket = bucket_path_for_item($item_id);
            $dir = ensure_upload_dir($bucket);
            $safe_name = preg_replace('/[^A-Za-z0-9._-]/', '_', $_FILES['file']['name']);
            $stored_name = $item_id . '_' . $safe_name;
            $dest = $dir . '/' . $stored_name;
            $relative_path = 'uploads/' . $bucket . '/' . $stored_name;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                $bit_stmt = $conn->prepare("INSERT INTO bitstreams (item_id, file_name, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?)");
                $orig_name = $_FILES['file']['name'];
                $size = filesize($dest);
                $mime = mime_content_type($dest) ?: 'application/octet-stream';
                $bit_stmt->bind_param("issis", $item_id, $orig_name, $relative_path, $size, $mime);
                $bit_stmt->execute();
                $bit_stmt->close();
            }
        }

        header("Location: my_submissions.php?submitted=1");
        exit();
    }
}

require_once 'includes/header.php';
?>

<section class="page-section narrow">
  <h1>Submit an Item</h1>
  <p>Fill in the details below. Items you submit will be
     <?php echo is_librarian_or_admin() ? 'published immediately.' : 'reviewed by a librarian before appearing publicly.'; ?></p>

  <?php if ($error_message): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
  <?php endif; ?>

  <form action="submit.php" method="POST" class="form" id="submit-form" enctype="multipart/form-data" novalidate>
    <label for="title">Title *</label>
    <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">

    <label for="author">Author(s)</label>
    <input type="text" id="author" name="author" placeholder="e.g. Jane Smith, John Doe" value="<?php echo htmlspecialchars($_POST['author'] ?? ''); ?>">

    <label for="abstract">Abstract / Description</label>
    <textarea id="abstract" name="abstract" rows="4"><?php echo htmlspecialchars($_POST['abstract'] ?? ''); ?></textarea>

    <label for="collection_id">Collection *</label>
    <select id="collection_id" name="collection_id" required>
      <option value="">-- Select a collection --</option>
      <?php foreach ($collections_list as $c): ?>
        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['community_name'] . ' / ' . $c['name']); ?></option>
      <?php endforeach; ?>
    </select>

    <label for="item_type">Item Type *</label>
    <select id="item_type" name="item_type">
      <option value="book">Book</option>
      <option value="thesis">Thesis</option>
      <option value="article">Article</option>
      <option value="report">Report</option>
      <option value="image">Image</option>
      <option value="dataset">Dataset</option>
      <option value="other">Other</option>
    </select>

    <label for="publisher">Publisher</label>
    <input type="text" id="publisher" name="publisher" value="<?php echo htmlspecialchars($_POST['publisher'] ?? ''); ?>">

    <label for="publication_date">Publication Date</label>
    <input type="date" id="publication_date" name="publication_date" value="<?php echo htmlspecialchars($_POST['publication_date'] ?? ''); ?>">

    <label for="language">Language</label>
    <input type="text" id="language" name="language" value="en">

    <label for="rights">Rights / License</label>
    <input type="text" id="rights" name="rights" placeholder="e.g. All rights reserved, CC BY 4.0">

    <label for="subjects">Subjects / Keywords</label>
    <input type="text" id="subjects" name="subjects" placeholder="Comma-separated, e.g. Databases, Web Development">

    <label for="identifier">Identifier (ISBN / DOI / URL)</label>
    <input type="text" id="identifier" name="identifier">

    <label for="file">Upload File (PDF, EPUB, DOC, etc. — max 25MB)</label>
    <input type="file" id="file" name="file">

    <button type="submit" class="btn btn-primary">Submit Item</button>
  </form>
</section>

<?php require_once 'includes/footer.php'; ?>
