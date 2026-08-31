<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
$page_title = "About";
require_once 'includes/header.php';
?>

<section class="page-section">
  <h1>About This Repository</h1>

  <div class="about-card">
    <h2>Student Information</h2>
    <p><strong>Name:</strong> [Your Full Name]</p>
    <p><strong>ID Number:</strong> [Your ID Number]</p>
    <p><strong>Department:</strong> [Your Department]</p>
    <p><strong>About me:</strong> [Replace this paragraph with 2&ndash;4
       sentences about yourself before submitting your assignment.]</p>
  </div>

  <div class="about-card">
    <h2>About the System</h2>
    <p>This Digital Library is an example institutional repository built
       for the CoSc3091 Web Programming course. Its architecture is
       inspired by DSpace, the widely used open-source repository
       platform: materials are organized into <strong>Communities</strong>
       (e.g. a faculty or department), which contain
       <strong>Collections</strong> (e.g. a course's reading list), which
       contain <strong>Items</strong> (a book, thesis, article, or other
       digital work), each of which can have one or more attached files
       (<strong>Bitstreams</strong>).</p>
    <p>The system supports user registration and login, a submission and
       review workflow, full-text search, browsing, download tracking,
       and an admin area for managing communities, collections, users,
       and pending submissions. The database and code are designed and
       indexed to remain fast with 100,000+ item records.</p>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
