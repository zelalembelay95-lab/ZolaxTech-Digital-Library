<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
$page_title = "Register";

if (is_logged_in()) {
    header("Location: index.php");
    exit();
}

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || $email === '' || $username === '' || $password === '') {
        $error_message = "Please fill in every field.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm) {
        $error_message = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $check->bind_param("ss", $email, $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error_message = "That email or username is already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $membership_no = generate_membership_no($conn);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, username, password, role, membership_no) VALUES (?, ?, ?, ?, 'user', ?)");
            $stmt->bind_param("sssss", $full_name, $email, $username, $hashed_password, $membership_no);

            if ($stmt->execute()) {
                header("Location: login.php?registered=1");
                exit();
            } else {
                $error_message = "Something went wrong. Please try again.";
            }
            $stmt->close();
        }
        $check->close();
    }
}

require_once 'includes/header.php';
?>

<section class="page-section narrow">
  <h1>Create an Account</h1>

  <?php if ($error_message): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
  <?php endif; ?>

  <form action="register.php" method="POST" class="form" id="register-form" novalidate>
    <label for="full_name">Full Name</label>
    <input type="text" id="full_name" name="full_name" required
           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">

    <label for="email">Email Address</label>
    <input type="email" id="email" name="email" required
           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

    <label for="username">Username</label>
    <input type="text" id="username" name="username" required
           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">

    <label for="password">Password</label>
    <input type="password" id="password" name="password" placeholder="At least 6 characters" required minlength="6">

    <label for="confirm_password">Confirm Password</label>
    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">

    <button type="submit" class="btn btn-primary">Register</button>
  </form>

  <p class="form-footer-link">Already have an account? <a href="login.php">Login here</a>.</p>
</section>

<?php require_once 'includes/footer.php'; ?>
