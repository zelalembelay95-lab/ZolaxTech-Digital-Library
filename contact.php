<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
$page_title = "Contact";

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        $error_message = "Please fill in every field.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("INSERT INTO messages (name, email, message) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $message);
        if ($stmt->execute()) {
            $success_message = "Thanks, " . htmlspecialchars($name) . "! Your message has been saved.";
        } else {
            $error_message = "Something went wrong. Please try again.";
        }
        $stmt->close();
    }
}

require_once 'includes/header.php';
?>

<section class="page-section narrow">
  <h1>Contact Us</h1>

  <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
  <?php if ($error_message): ?><div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

  <form action="contact.php" method="POST" class="form" id="contact-form" novalidate>
    <label for="name">Full Name</label>
    <input type="text" id="name" name="name" required>

    <label for="email">Email Address</label>
    <input type="email" id="email" name="email" required>

    <label for="message">Message</label>
    <textarea id="message" name="message" rows="5" required></textarea>

    <button type="submit" class="btn btn-primary">Send Message</button>
  </form>
</section>

<?php require_once 'includes/footer.php'; ?>
