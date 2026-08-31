<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
$page_title = "Login";

if (is_logged_in()) {
    header("Location: index.php");
    exit();
}

$error_message = "";
$success_message = isset($_GET['registered']) ? "Registration successful! You can now log in." : "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error_message = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id']   = $row['id'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['role']      = $row['role'];
                header("Location: index.php");
                exit();
            } else {
                $error_message = "Incorrect username or password.";
            }
        } else {
            $error_message = "Incorrect username or password.";
        }
        $stmt->close();
    }
}

require_once 'includes/header.php';
?>

<section class="page-section narrow">
  <h1>Login</h1>

  <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
  <?php if ($error_message): ?><div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

  <form action="login.php" method="POST" class="form" id="login-form" novalidate>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <button type="submit" class="btn btn-primary">Login</button>
  </form>

  <p class="form-footer-link">Don't have an account? <a href="register.php">Register here</a>.</p>
  <p class="form-footer-link demo-creds">Demo admin login: <strong>admin</strong> / <strong>Admin1234</strong></p>
</section>

<?php require_once 'includes/footer.php'; ?>
