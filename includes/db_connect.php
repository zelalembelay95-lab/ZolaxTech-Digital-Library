<?php
/**
 * db_connect.php
 * ---------------------------------------------------------
 * Central database connection. Every page includes this file
 * first so it can talk to the database and use sessions.
 *
 * LOCAL DEVELOPMENT (XAMPP/WAMP) DEFAULTS ARE SET BELOW.
 * When you deploy live, replace the 4 values with the ones
 * shown in your hosting control panel -- see HOW_TO_GO_LIVE.docx.
 * ---------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----- EDIT THESE 4 LINES WHEN YOU DEPLOY -----
$db_host = "zolaxdigitallibrary.site.je";
$db_name = "if0_42847514_digital_library";
$db_user = "if0_42847514";
$db_pass = "Sewmh101";
// -----------------------------------------------

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
