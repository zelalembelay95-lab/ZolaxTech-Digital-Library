<?php
if (!isset($page_title)) {
    $page_title = "Digital Library";
}
$is_logged_in = is_logged_in();
$q_value = isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($page_title); ?> | Digital Library</title>
<link rel="stylesheet" href="<?php echo isset($in_admin) ? '../css/style.css' : 'css/style.css'; ?>">
</head>
<body>

<header class="site-header">
  <div class="container header-inner">
    <a href="<?php echo isset($in_admin) ? '../index.php' : 'index.php'; ?>" class="logo">📚 Digital Library</a>

    <form class="search-bar" action="<?php echo isset($in_admin) ? '../search.php' : 'search.php'; ?>" method="GET">
      <input type="text" name="q" placeholder="Search titles, authors, subjects..." value="<?php echo $q_value; ?>">
      <button type="submit">Search</button>
    </form>

    <nav class="main-nav">
      <?php $p = isset($in_admin) ? '../' : ''; ?>
      <a href="<?php echo $p; ?>index.php">Home</a>
      <a href="<?php echo $p; ?>browse.php">Browse</a>
      <a href="<?php echo $p; ?>communities.php">Communities</a>
      <a href="<?php echo $p; ?>about.php">About</a>
      <?php if ($is_logged_in): ?>
        <a href="<?php echo $p; ?>submit.php">Submit Item</a>
        <a href="<?php echo $p; ?>my_submissions.php">My Submissions</a>
        <a href="<?php echo $p; ?>my_loans.php">My Loans</a>
        <a href="<?php echo $p; ?>my_holds.php">My Holds</a>
        <?php if (is_librarian_or_admin()): ?>
          <a href="<?php echo $p; ?>admin/dashboard.php" class="btn-nav-admin">Admin</a>
        <?php endif; ?>
        <a href="<?php echo $p; ?>logout.php" class="btn-nav-logout">Logout</a>
        <span class="welcome-text">Hi, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
      <?php else: ?>
        <a href="<?php echo $p; ?>login.php">Login</a>
        <a href="<?php echo $p; ?>register.php" class="btn-nav-register">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="container page-main">
