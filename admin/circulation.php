<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_role(['admin','librarian']);
$in_admin = true;
$page_title = "Circulation Desk";

$error_message = "";
$success_message = "";

// ---------- Handle Check Out ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $member_identifier = trim($_POST['member_identifier'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');

    if ($member_identifier === '' || $barcode === '') {
        $error_message = "Member and barcode are both required.";
    } else {
        $mstmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR membership_no = ? LIMIT 1");
        $mstmt->bind_param("ss", $member_identifier, $member_identifier);
        $mstmt->execute();
        $member = $mstmt->get_result()->fetch_assoc();
        $mstmt->close();

        if (!$member) {
            $error_message = "No member found with that username or membership number.";
        } else {
            $block_reason = borrowing_block_reason($conn, $member);
            if ($block_reason !== "") {
                $error_message = $block_reason;
            } else {
                $cstmt = $conn->prepare("SELECT c.*, i.title FROM copies c JOIN items i ON i.id = c.item_id WHERE c.barcode = ?");
                $cstmt->bind_param("s", $barcode);
                $cstmt->execute();
                $copy = $cstmt->get_result()->fetch_assoc();
                $cstmt->close();

                if (!$copy) {
                    $error_message = "No copy found with that barcode.";
                } elseif ($copy['status'] === 'available') {
                    $loan_date = date('Y-m-d');
                    $due_date = date('Y-m-d', strtotime("+" . LOAN_PERIOD_DAYS . " days"));

                    $ins = $conn->prepare("INSERT INTO loans (copy_id, member_id, loan_date, due_date) VALUES (?, ?, ?, ?)");
                    $ins->bind_param("iiss", $copy['id'], $member['id'], $loan_date, $due_date);
                    $ins->execute();
                    $ins->close();

                    $upd = $conn->prepare("UPDATE copies SET status = 'checked_out' WHERE id = ?");
                    $upd->bind_param("i", $copy['id']);
                    $upd->execute();
                    $upd->close();

                    $success_message = "Checked out \"" . htmlspecialchars($copy['title']) . "\" to " . htmlspecialchars($member['full_name']) . ". Due back " . format_date($due_date) . ".";
                } elseif ($copy['status'] === 'reserved') {
                    // A reserved copy can only be checked out by the member
                    // whose hold made it ready -- otherwise it stays reserved.
                    $rstmt = $conn->prepare("SELECT id FROM holds WHERE ready_copy_id = ? AND member_id = ? AND status = 'ready'");
                    $rstmt->bind_param("ii", $copy['id'], $member['id']);
                    $rstmt->execute();
                    $ready_hold = $rstmt->get_result()->fetch_assoc();
                    $rstmt->close();

                    if (!$ready_hold) {
                        $error_message = "This copy is reserved for another member's hold.";
                    } else {
                        $loan_date = date('Y-m-d');
                        $due_date = date('Y-m-d', strtotime("+" . LOAN_PERIOD_DAYS . " days"));

                        $ins = $conn->prepare("INSERT INTO loans (copy_id, member_id, loan_date, due_date) VALUES (?, ?, ?, ?)");
                        $ins->bind_param("iiss", $copy['id'], $member['id'], $loan_date, $due_date);
                        $ins->execute();
                        $ins->close();

                        $upd = $conn->prepare("UPDATE copies SET status = 'checked_out' WHERE id = ?");
                        $upd->bind_param("i", $copy['id']);
                        $upd->execute();
                        $upd->close();

                        $upd2 = $conn->prepare("UPDATE holds SET status = 'fulfilled' WHERE id = ?");
                        $upd2->bind_param("i", $ready_hold['id']);
                        $upd2->execute();
                        $upd2->close();

                        $success_message = "Checked out \"" . htmlspecialchars($copy['title']) . "\" (from hold) to " . htmlspecialchars($member['full_name']) . ". Due back " . format_date($due_date) . ".";
                    }
                } else {
                    $error_message = "This copy is not available (status: " . $copy['status'] . ").";
                }
            }
        }
    }
}

// ---------- Handle Return ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return') {
    $barcode = trim($_POST['return_barcode'] ?? '');

    if ($barcode === '') {
        $error_message = "Please enter a barcode.";
    } else {
        $cstmt = $conn->prepare("SELECT c.*, i.title, i.id AS item_id FROM copies c JOIN items i ON i.id = c.item_id WHERE c.barcode = ?");
        $cstmt->bind_param("s", $barcode);
        $cstmt->execute();
        $copy = $cstmt->get_result()->fetch_assoc();
        $cstmt->close();

        if (!$copy) {
            $error_message = "No copy found with that barcode.";
        } else {
            $lstmt = $conn->prepare("SELECT * FROM loans WHERE copy_id = ? AND return_date IS NULL ORDER BY id DESC LIMIT 1");
            $lstmt->bind_param("i", $copy['id']);
            $lstmt->execute();
            $loan = $lstmt->get_result()->fetch_assoc();
            $lstmt->close();

            if (!$loan) {
                $error_message = "This copy does not have an active loan.";
            } else {
                $today = date('Y-m-d');
                $upd = $conn->prepare("UPDATE loans SET return_date = ? WHERE id = ?");
                $upd->bind_param("si", $today, $loan['id']);
                $upd->execute();
                $upd->close();

                $fine_note = "";
                if (strtotime($loan['due_date']) < strtotime($today)) {
                    $days_late = days_overdue($loan['due_date']);
                    $amount = round($days_late * FINE_PER_DAY, 2);
                    if ($amount > 0) {
                        $reason = "Overdue return ($days_late day" . ($days_late == 1 ? '' : 's') . " late)";
                        $fstmt = $conn->prepare("INSERT INTO fines (loan_id, member_id, amount, reason) VALUES (?, ?, ?, ?)");
                        $fstmt->bind_param("iids", $loan['id'], $loan['member_id'], $amount, $reason);
                        $fstmt->execute();
                        $fstmt->close();
                        $fine_note = " A fine of $" . number_format($amount, 2) . " was applied ($days_late day(s) late).";
                    }
                }

                // Check for a pending hold on this title -- oldest first
                $hstmt = $conn->prepare("SELECT * FROM holds WHERE item_id = ? AND status = 'pending' ORDER BY hold_date ASC, id ASC LIMIT 1");
                $hstmt->bind_param("i", $copy['item_id']);
                $hstmt->execute();
                $hold = $hstmt->get_result()->fetch_assoc();
                $hstmt->close();

                if ($hold) {
                    $upd = $conn->prepare("UPDATE copies SET status = 'reserved' WHERE id = ?");
                    $upd->bind_param("i", $copy['id']);
                    $upd->execute();
                    $upd->close();

                    $upd2 = $conn->prepare("UPDATE holds SET status = 'ready', ready_copy_id = ? WHERE id = ?");
                    $upd2->bind_param("ii", $copy['id'], $hold['id']);
                    $upd2->execute();
                    $upd2->close();

                    $fine_note .= " This copy is now reserved for a member on the hold list.";
                } else {
                    $upd = $conn->prepare("UPDATE copies SET status = 'available' WHERE id = ?");
                    $upd->bind_param("i", $copy['id']);
                    $upd->execute();
                    $upd->close();
                }

                $success_message = "Returned \"" . htmlspecialchars($copy['title']) . "\"." . $fine_note;
            }
        }
    }
}

$pg = get_pagination_params(20, 100);
$total_rows = $conn->query("SELECT COUNT(*) c FROM loans WHERE return_date IS NULL")->fetch_assoc()['c'];
$total_pg = total_pages($total_rows, $pg['per_page']);

$stmt = $conn->prepare("SELECT l.id, l.due_date, l.loan_date, c.barcode, i.title, u.full_name, u.membership_no
                         FROM loans l
                         JOIN copies c ON c.id = l.copy_id
                         JOIN items i ON i.id = c.item_id
                         JOIN users u ON u.id = l.member_id
                         WHERE l.return_date IS NULL
                         ORDER BY l.due_date ASC
                         LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $pg['per_page'], $pg['offset']);
$stmt->execute();
$active_loans = $stmt->get_result();

require_once '../includes/header.php';
?>

<section class="page-section wide">
  <h1>Circulation Desk</h1>

  <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
  <?php if ($error_message): ?><div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

  <div class="two-col">
    <div class="admin-form-card">
      <h2>Check Out</h2>
      <form method="POST" class="form">
        <input type="hidden" name="action" value="checkout">
        <label for="member_identifier">Member (username or card no.) *</label>
        <input type="text" id="member_identifier" name="member_identifier" placeholder="e.g. contributor1 or LIB-000002" required>

        <label for="barcode">Copy Barcode *</label>
        <input type="text" id="barcode" name="barcode" placeholder="e.g. CS-000123" required>

        <button type="submit" class="btn btn-primary">Check Out</button>
      </form>
    </div>

    <div class="admin-form-card">
      <h2>Return</h2>
      <form method="POST" class="form">
        <input type="hidden" name="action" value="return">
        <label for="return_barcode">Copy Barcode *</label>
        <input type="text" id="return_barcode" name="return_barcode" placeholder="e.g. CS-000123" required>

        <button type="submit" class="btn btn-primary">Return</button>
      </form>
    </div>
  </div>

  <h2>Currently Checked Out (<?php echo number_format($total_rows); ?>)</h2>
  <table class="item-table">
    <thead><tr><th>Title</th><th>Barcode</th><th>Member</th><th>Due Date</th></tr></thead>
    <tbody>
      <?php while ($l = $active_loans->fetch_assoc()):
        $overdue = strtotime($l['due_date']) < strtotime(date('Y-m-d')); ?>
        <tr>
          <td><?php echo htmlspecialchars($l['title']); ?></td>
          <td><?php echo htmlspecialchars($l['barcode']); ?></td>
          <td><?php echo htmlspecialchars($l['full_name']); ?> (<?php echo htmlspecialchars($l['membership_no']); ?>)</td>
          <td class="<?php echo $overdue ? 'text-overdue' : ''; ?>">
            <?php echo format_date($l['due_date']); ?>
            <?php if ($overdue): ?><span class="status-badge status-rejected">Overdue</span><?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      <?php if ($active_loans->num_rows === 0): ?>
        <tr><td colspan="4" class="empty-state">No items currently checked out.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php render_pagination($pg['page'], $total_pg, "circulation.php?"); ?>
</section>

<?php
$stmt->close();
require_once '../includes/footer.php';
?>
