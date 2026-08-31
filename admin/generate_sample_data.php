<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_role('admin');

// ---------- AJAX batch endpoint ----------
// Called repeatedly by the JavaScript on this page, inserting a small
// batch each time so a single web request never runs long enough to
// hit a shared host's execution time limit.
if (isset($_GET['action']) && $_GET['action'] === 'batch') {
    header('Content-Type: application/json');

    $batch_size = min(1000, max(1, (int)($_POST['batch_size'] ?? 200)));
    $collection_id = (int)($_POST['collection_id'] ?? 0);
    $submitter_id = current_user_id();

    $check = $conn->prepare("SELECT id FROM collections WHERE id = ?");
    $check->bind_param("i", $collection_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(['error' => 'Invalid collection selected.']);
        exit();
    }
    $check->close();

    $adjectives = ['Introduction to','Advanced','Principles of','Fundamentals of','Modern','Applied','A Guide to','Understanding','Practical','Essentials of'];
    $subjects = ['Web Development','Databases','Artificial Intelligence','Networking','Operating Systems','Software Engineering','Data Structures','Cybersecurity','Cloud Computing','Mobile Development','Machine Learning','Human-Computer Interaction'];
    $first_names = ['James','Maria','Ahmed','Chen','Fatima','John','Sara','Daniel','Aisha','Michael','Grace','Samuel'];
    $last_names = ['Smith','Garcia','Khan','Wang','Ahmed','Brown','Kebede','Johnson','Diallo','Lee','Mensah','Taylor'];
    $types = ['book','thesis','article','report','other'];

    $conn->begin_transaction();
    $stmt = $conn->prepare("INSERT INTO items
        (collection_id, submitter_id, title, author, abstract, publisher, publication_date, language, item_type, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'en', ?, 'approved')");

    for ($i = 0; $i < $batch_size; $i++) {
        $title = $adjectives[array_rand($adjectives)] . ' ' . $subjects[array_rand($subjects)] . ' (Vol. ' . rand(1, 9) . ')';
        $author = $first_names[array_rand($first_names)] . ' ' . $last_names[array_rand($last_names)];
        $abstract = 'This is an auto-generated sample record used to demonstrate the repository at scale. Subject area: ' . $subjects[array_rand($subjects)] . '.';
        $publisher = 'Sample Press';
        $year = rand(1998, 2025);
        $pub_date = sprintf('%04d-%02d-%02d', $year, rand(1,12), rand(1,28));
        $item_type = $types[array_rand($types)];

        $stmt->bind_param("iissssss", $collection_id, $submitter_id, $title, $author, $abstract, $publisher, $pub_date, $item_type);
        $stmt->execute();
    }
    $stmt->close();
    $conn->commit();

    echo json_encode(['inserted' => $batch_size]);
    exit();
}

// ---------- Normal page load ----------
$in_admin = true;
$page_title = "Generate Sample Data";

$collections = $conn->query("SELECT c.id, c.name, co.name AS community_name FROM collections c JOIN communities co ON co.id = c.community_id ORDER BY co.name, c.name");
$collection_options = [];
while ($row = $collections->fetch_assoc()) { $collection_options[] = $row; }

$current_total = $conn->query("SELECT COUNT(*) c FROM items")->fetch_assoc()['c'];

require_once '../includes/header.php';
?>

<section class="page-section narrow">
  <h1>Generate Sample Data</h1>
  <p>Use this tool to populate the repository with realistic-looking
     demo items, so you can see how browsing, search, and pagination
     behave at scale. Currently <strong><?php echo number_format($current_total); ?></strong>
     items exist in the database.</p>

  <div class="admin-form-card">
    <label for="collection_id">Add sample items to:</label>
    <select id="collection_id">
      <?php foreach ($collection_options as $c): ?>
        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['community_name'] . ' / ' . $c['name']); ?></option>
      <?php endforeach; ?>
    </select>

    <label for="total_count">How many items to generate:</label>
    <input type="number" id="total_count" value="1000" min="1" max="200000">

    <label for="batch_size">Batch size (per request):</label>
    <input type="number" id="batch_size" value="250" min="10" max="1000">

    <button id="start-btn" class="btn btn-primary">Start Generating</button>
    <button id="stop-btn" class="btn btn-secondary" disabled>Stop</button>

    <div class="progress-wrap">
      <div class="progress-bar"><div id="progress-fill" class="progress-fill"></div></div>
      <p id="progress-text">Not started.</p>
    </div>
  </div>

  <p class="form-footer-link">For generating 50,000&ndash;100,000+ items in
     one go, it's faster and safer to run
     <code>scripts/seed_demo_data.php</code> from the command line
     locally (see the README), then upload the resulting database.</p>
</section>

<script>
(function () {
  const startBtn = document.getElementById('start-btn');
  const stopBtn = document.getElementById('stop-btn');
  const fill = document.getElementById('progress-fill');
  const text = document.getElementById('progress-text');
  let stopped = false;
  let done = 0;

  stopBtn.addEventListener('click', function () {
    stopped = true;
    stopBtn.disabled = true;
  });

  startBtn.addEventListener('click', async function () {
    const total = parseInt(document.getElementById('total_count').value, 10);
    const batchSize = parseInt(document.getElementById('batch_size').value, 10);
    const collectionId = document.getElementById('collection_id').value;

    stopped = false;
    done = 0;
    startBtn.disabled = true;
    stopBtn.disabled = false;

    while (done < total && !stopped) {
      const thisBatch = Math.min(batchSize, total - done);
      try {
        const res = await fetch('generate_sample_data.php?action=batch', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `batch_size=${thisBatch}&collection_id=${collectionId}`
        });
        const data = await res.json();
        if (data.error) {
          text.textContent = 'Error: ' + data.error;
          break;
        }
        done += data.inserted;
        const pct = Math.round((done / total) * 100);
        fill.style.width = pct + '%';
        text.textContent = done.toLocaleString() + ' / ' + total.toLocaleString() + ' items created (' + pct + '%)';
      } catch (e) {
        text.textContent = 'Request failed — stopping.';
        break;
      }
    }

    if (stopped) {
      text.textContent += ' — stopped by user.';
    } else if (done >= total) {
      text.textContent = 'Done! ' + done.toLocaleString() + ' items created.';
    }
    startBtn.disabled = false;
    stopBtn.disabled = true;
  });
})();
</script>

<?php require_once '../includes/footer.php'; ?>
