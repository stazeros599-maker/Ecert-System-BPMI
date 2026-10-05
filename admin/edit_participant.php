<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include '../db.php';
require 'check_role.php';
require_role('admin');   // ← Only admins can access

$ic = isset($_GET['ic']) ? trim($_GET['ic']) : '';
if (empty($ic)) { header('Location: index.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM participant WHERE icNum = ?");
$stmt->bind_param("s", $ic);
$stmt->execute();
$participant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$participant) { header('Location: index.php'); exit; }

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullName = trim($_POST['fullName']);
    $insider = isset($_POST['insider']) ? intval($_POST['insider']) : 0;
    
    $errors = [];
    if (empty($fullName)) $errors[] = 'Name is required.';
    
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE participant SET fullName = ?, insider = ? WHERE icNum = ?");
        $stmt->bind_param("sis", $fullName, $insider, $ic);
        
        if ($stmt->execute()) {
            // Sync all certificates with new name
            $sync = $conn->prepare("UPDATE certificates SET name = ? WHERE nokp = ?");
            $sync->bind_param("ss", $fullName, $ic);
            $sync->execute();
            $sync->close();
            
            $_SESSION['admin_message'] = 'Participant updated successfully!';
            $_SESSION['admin_message_type'] = 'success';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Database error: ' . $stmt->error;
        }
        $stmt->close();
    }
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $message_type = 'danger';
    }
}

$page_title = 'Edit Participant - eCert BPMI';
include 'header.php';
include 'nav.php';
include 'nav_stack.php';
push_nav_stack();
?>

<div class="container" style="padding-top: 20px; max-width: 700px;">
    <div style="background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: hidden;">
        <div style="background: #1a3c5e; color: white; padding: 15px 20px; font-weight: 600;">
            <span class="glyphicon glyphicon-edit"></span> Edit Participant
        </div>
        <div style="padding: 25px 30px;">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-weight: 600; color: #555;">IC Number</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($participant['icNum']); ?>" disabled style="background: #f5f5f5; height: 45px;">
                    <small style="color: #888;">IC number cannot be changed.</small>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-weight: 600; color: #555;">Full Name</label>
                    <input type="text" name="fullName" class="form-control" value="<?php echo htmlspecialchars($participant['fullName']); ?>" required style="height: 45px;">
                </div>
                
                <div class="form-group" style="padding: 15px; background: #f8f9fa; border-radius: 6px; margin-bottom: 20px;">
                    <label style="font-weight: 600; color: #555; display: block; margin-bottom: 10px;">Participant Type</label>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="insider" value="1" <?php echo $participant['insider'] == 1 ? 'checked' : ''; ?>>
                            <span style="color: #28a745;">👤 Perikanan Staff (Insider)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="insider" value="0" <?php echo $participant['insider'] == 0 ? 'checked' : ''; ?>>
                            <span style="color: #6c757d;">👤 Non-Insider (Public)</span>
                        </label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-weight: 600; background: #1a3c5e; border: none;">
                        <span class="glyphicon glyphicon-save"></span> Save Changes
                    </button>
                    <a href="index.php" class="btn btn-default" style="padding: 12px 30px; font-weight: 600;">
                        <span class="glyphicon glyphicon-remove"></span> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<?php if (isset($conn) && $conn instanceof mysqli) $conn->close(); ?>