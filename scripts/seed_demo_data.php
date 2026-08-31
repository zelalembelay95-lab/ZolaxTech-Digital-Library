<?php
/**
 * seed_demo_data.php
 * ---------------------------------------------------------
 * Command-line script to bulk-generate demo items so you can see
 * how the repository performs at real scale (tens or hundreds of
 * thousands of records).
 *
 * WHY A SEPARATE CLI SCRIPT?
 * Inserting 100,000 rows one at a time through a normal web page
 * would run into your host's execution time limit. Running this
 * from the command line has no such limit, and by batching many
 * rows into each INSERT statement (instead of one INSERT per row)
 * it can comfortably generate 100,000+ records in well under a
 * minute on ordinary hardware.
 *
 * USAGE (run locally, e.g. via XAMPP's php.exe, or on any server
 * where you have command-line/SSH access):
 *
 *   php seed_demo_data.php 100000
 *
 * If you omit the number, it defaults to 10,000.
 * This script only works against a LOCAL or SSH-accessible
 * database -- most free hosts (like InfinityFree) do not give you
 * shell access, so this is meant to be run locally, after which
 * you can export the resulting database and import it live, or
 * simply use this to sanity-check performance before deploying.
 * ---------------------------------------------------------
 */

// ----- EDIT THESE 4 LINES TO MATCH YOUR DATABASE -----
$db_host = "localhost";
$db_name = "digital_library";
$db_user = "root";
$db_pass = "";
// ------------------------------------------------------

$target_count = isset($argv[1]) ? (int)$argv[1] : 10000;
$batch_rows   = 500;   // rows per multi-value INSERT statement
$chunk_rows   = 5000;  // rows per transaction commit

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}
$conn->set_charset("utf8mb4");

// Make sure at least one collection exists to attach items to
$result = $conn->query("SELECT id FROM collections LIMIT 1");
if ($result->num_rows === 0) {
    $conn->query("INSERT INTO communities (name, description) VALUES ('Demo Community', 'Auto-created for seeding')");
    $community_id = $conn->insert_id;
    $conn->query("INSERT INTO collections (community_id, name, description) VALUES ($community_id, 'Demo Collection', 'Auto-created for seeding')");
}
$collections = [];
$res = $conn->query("SELECT id FROM collections");
while ($row = $res->fetch_assoc()) { $collections[] = (int)$row['id']; }

// Make sure at least one user exists to be the "submitter"
$res = $conn->query("SELECT id FROM users LIMIT 1");
if ($res->num_rows === 0) {
    die("No users found. Import database/schema.sql first (it creates a default admin account), then re-run this script.\n");
}
$submitter_id = (int)$res->fetch_assoc()['id'];

$adjectives = ['Introduction to','Advanced','Principles of','Fundamentals of','Modern','Applied','A Guide to','Understanding','Practical','Essentials of','Topics in','Foundations of'];
$subjects = ['Web Development','Databases','Artificial Intelligence','Networking','Operating Systems','Software Engineering','Data Structures','Cybersecurity','Cloud Computing','Mobile Development','Machine Learning','Human-Computer Interaction','Computer Graphics','Distributed Systems','Information Retrieval'];
$first_names = ['James','Maria','Ahmed','Chen','Fatima','John','Sara','Daniel','Aisha','Michael','Grace','Samuel','Lucia','Kwame','Emeka','Sofia'];
$last_names = ['Smith','Garcia','Khan','Wang','Ahmed','Brown','Kebede','Johnson','Diallo','Lee','Mensah','Taylor','Rossi','Nguyen','Okafor'];
$types = ['book','thesis','article','report','other'];

echo "Seeding $target_count demo items into " . count($collections) . " collection(s)...\n";
$start_time = microtime(true);
$inserted_total = 0;

$conn->autocommit(false);

while ($inserted_total < $target_count) {
    $conn->begin_transaction();
    $rows_this_chunk = min($chunk_rows, $target_count - $inserted_total);

    for ($offset = 0; $offset < $rows_this_chunk; $offset += $batch_rows) {
        $rows_this_batch = min($batch_rows, $rows_this_chunk - $offset);
        $values = [];
        $types_str = "";
        $params = [];

        for ($i = 0; $i < $rows_this_batch; $i++) {
            $title = $adjectives[array_rand($adjectives)] . ' ' . $subjects[array_rand($subjects)] . ' (Vol. ' . rand(1, 20) . ')';
            $author = $first_names[array_rand($first_names)] . ' ' . $last_names[array_rand($last_names)];
            $abstract = 'Auto-generated demo record for scale testing. Subject: ' . $subjects[array_rand($subjects)] . '.';
            $publisher = 'Sample Press';
            $year = rand(1990, 2025);
            $pub_date = sprintf('%04d-%02d-%02d', $year, rand(1, 12), rand(1, 28));
            $item_type = $types[array_rand($types)];
            $collection_id = $collections[array_rand($collections)];

            $values[] = "(?, ?, ?, ?, ?, ?, ?, 'en', ?, 'approved')";
            array_push($params, $collection_id, $submitter_id, $title, $author, $abstract, $publisher, $pub_date, $item_type);
            $types_str .= "iissssss";
        }

        $sql = "INSERT INTO items
                (collection_id, submitter_id, title, author, abstract, publisher, publication_date, language, item_type, status)
                VALUES " . implode(", ", $values);

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types_str, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    $inserted_total += $rows_this_chunk;

    $elapsed = round(microtime(true) - $start_time, 1);
    echo "  ...$inserted_total / $target_count inserted ({$elapsed}s elapsed)\n";
}

$conn->autocommit(true);
$total_time = round(microtime(true) - $start_time, 2);
echo "Done. Inserted $inserted_total items in {$total_time} seconds.\n";

// Quick benchmark: how fast is a full-text search over the whole table now?
$bench_start = microtime(true);
$conn->query("SELECT id, title FROM items WHERE status='approved' AND MATCH(title, author, abstract) AGAINST ('+web* +development*' IN BOOLEAN MODE) LIMIT 20");
$bench_time = round((microtime(true) - $bench_start) * 1000, 1);
echo "Sample full-text search across the table took {$bench_time} ms.\n";

$count_result = $conn->query("SELECT COUNT(*) c FROM items")->fetch_assoc();
echo "Total items now in the database: " . number_format($count_result['c']) . "\n";
