<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include '../db.php';
require 'check_role.php';
require_role('admin');   // ← Only admins can access

$admin_id = $_SESSION['admin_id'];
include 'nav_stack.php';
push_nav_stack();

// ============================================
// Handle Status Update
// ============================================
if (isset($_POST['update_status']) && isset($_POST['complaint_id'])) {
    $complaint_id = intval($_POST['complaint_id']);
    $new_status = $_POST['status'];
    
    if (in_array($new_status, ['new', 'in_progress', 'resolved'])) {
        $stmt = $conn->prepare("UPDATE complaints SET status = ?, admin_id = ? WHERE complaint_id = ?");
        $stmt->bind_param("sii", $new_status, $admin_id, $complaint_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['admin_message'] = 'Complaint status updated.';
        $_SESSION['admin_message_type'] = 'success';
    } else {
        $_SESSION['admin_message'] = 'Invalid status.';
        $_SESSION['admin_message_type'] = 'danger';
    }
    
    // Preserve search/filter on redirect
    $redirect = 'complaints.php';
    $query = [];
    if (!empty($_POST['return_search'])) $query[] = 'search=' . urlencode($_POST['return_search']);
    if (!empty($_POST['return_filter'])) $query[] = 'filter_status=' . urlencode($_POST['return_filter']);
    if (!empty($query)) $redirect .= '?' . implode('&', $query);
    
    header('Location: ' . $redirect);
    exit;
}

// ============================================
// Handle Delete
// ============================================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM complaints WHERE complaint_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['admin_message'] = 'Complaint deleted.';
    $_SESSION['admin_message_type'] = 'success';
    header('Location: complaints.php');
    exit;
}

// ============================================
// Search / Filter
// ============================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';

$sql = "SELECT c.*, a.username AS handled_by 
        FROM complaints c 
        LEFT JOIN admin_users a ON c.admin_id = a.id 
        WHERE 1=1";
$params = array();
$types = "";

if (!empty($search)) {
    $sql .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.subject LIKE ? OR c.message LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($filter_status)) {
    $sql .= " AND c.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$sql .= " ORDER BY c.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// ============================================
// Stats
// ============================================
$total_complaints = $conn->query("SELECT COUNT(*) as c FROM complaints")->fetch_assoc()['c'];
$new_count = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status = 'new'")->fetch_assoc()['c'];
$progress_count = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status = 'in_progress'")->fetch_assoc()['c'];
$resolved_count = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status = 'resolved'")->fetch_assoc()['c'];

$message = isset($_SESSION['admin_message']) ? $_SESSION['admin_message'] : '';
$message_type = isset($_SESSION['admin_message_type']) ? $_SESSION['admin_message_type'] : '';
unset($_SESSION['admin_message']);
unset($_SESSION['admin_message_type']);

$page_title = 'Complaints - eCert BPMI';
include 'header.php';
include 'nav.php';
?>

<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<div class="container">
    <!-- Title -->
    <div class="row">
        <div class="col-md-12">
            <h2 style="font-size: 24px; font-weight: 700; color: #1a3c5e; margin: 0 0 10px 0;">
                Public Complaints
            </h2>
            <hr style="border-top: 2px solid #e1e5eb; margin: 10px 0 25px 0;">
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-6">
            <div style="background: white; border-radius: 10px; padding: 20px 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); text-align: center; margin-bottom: 20px; min-height: 110px; border-top: 4px solid #1a3c5e;">
                <div style="font-size: 30px; font-weight: 700; color: #1a3c5e;"><?php echo $total_complaints; ?></div>
                <div style="color: #888; font-size: 13px; margin-top: 5px;">Total Complaints</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-6">
            <div style="background: white; border-radius: 10px; padding: 20px 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); text-align: center; margin-bottom: 20px; min-height: 110px; border-top: 4px solid #dc3545;">
                <div style="font-size: 30px; font-weight: 700; color: #dc3545;"><?php echo $new_count; ?></div>
                <div style="color: #888; font-size: 13px; margin-top: 5px;">New</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-6">
            <div style="background: white; border-radius: 10px; padding: 20px 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); text-align: center; margin-bottom: 20px; min-height: 110px; border-top: 4px solid #ffc107;">
                <div style="font-size: 30px; font-weight: 700; color: #ffc107;"><?php echo $progress_count; ?></div>
                <div style="color: #888; font-size: 13px; margin-top: 5px;">In Progress</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-6">
            <div style="background: white; border-radius: 10px; padding: 20px 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); text-align: center; margin-bottom: 20px; min-height: 110px; border-top: 4px solid #28a745;">
                <div style="font-size: 30px; font-weight: 700; color: #28a745;"><?php echo $resolved_count; ?></div>
                <div style="color: #888; font-size: 13px; margin-top: 5px;">Resolved</div>
            </div>
        </div>
    </div>

    <!-- Alert Message -->
    <?php if ($message): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade in" style="border-radius: 10px; padding: 12px 20px; margin-bottom: 20px;">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo $message; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Complaint List -->
    <div class="row">
        <div class="col-md-12">
            <div style="background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: hidden;">
                <!-- Header -->
                <div style="background: #1a3c5e; color: white; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <span class="glyphicon glyphicon-envelope"></span> All Complaints
                        <span style="background: rgba(255,255,255,0.2); font-size: 14px; padding: 4px 12px; border-radius: 20px;">
                            <?php echo $result->num_rows; ?>
                        </span>
                    </div>
                </div>

                <!-- Search + Filter -->
                <div style="padding: 12px 20px; background: #f0f2f5; border-bottom: 1px solid #e1e5eb;">
                    <form method="get" action="complaints.php" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; width: 100%;">
                        <div style="flex: 3; min-width: 200px;">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="🔍 Search by name, email, or subject..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   style="height: 36px; font-size: 14px;">
                        </div>
                        <div style="flex: 1; min-width: 130px;">
                            <select name="filter_status" class="form-control" style="height: 36px; font-size: 14px;">
                                <option value="">All Status</option>
                                <option value="new" <?php echo $filter_status == 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="in_progress" <?php echo $filter_status == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $filter_status == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-sm btn-info" style="height: 36px;">
                            <span class="glyphicon glyphicon-search"></span> Filter
                        </button>
                        <?php if (!empty($search) || !empty($filter_status)): ?>
                            <a href="complaints.php" class="btn btn-sm btn-default" style="height: 36px;">
                                <span class="glyphicon glyphicon-remove"></span> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table -->
                <div style="padding: 15px; overflow-x: auto;">
                    <table class="table table-bordered table-hover" style="margin-bottom: 0; min-width: 800px; width: 100%;">
                        <thead>
                            <tr>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">#</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">From</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Contact</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Subject</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Message</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px; text-align: center; min-width: 140px;">Status</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Handled By</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Date</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php 
                                $count = 1;
                                while ($row = $result->fetch_assoc()): 
                                    // Status styling
                                    $status_styles = [
                                        'new'         => ['bg' => '#dc3545', 'border' => '#a71d2a', 'color' => '#fff',    'label' => 'New'],
                                        'in_progress' => ['bg' => '#ffc107', 'border' => '#d39e00', 'color' => '#000',    'label' => 'In Progress'],
                                        'resolved'    => ['bg' => '#28a745', 'border' => '#1e7e34', 'color' => '#fff',    'label' => 'Resolved']
                                    ];
                                    $st = $status_styles[$row['status']] ?? ['bg' => '#6c757d', 'border' => '#545b62', 'color' => '#fff', 'label' => ucfirst($row['status'])];
                                ?>
                                    <tr>
                                        <td style="padding: 10px;"><?php echo $count; ?></td>
                                        <td style="padding: 10px;">
                                            <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                        </td>
                                        <td style="padding: 10px; font-size: 13px;">
                                            <div><span class="glyphicon glyphicon-envelope"></span> <?php echo htmlspecialchars($row['email']); ?></div>
                                            <?php if (!empty($row['phone'])): ?>
                                                <div><span class="glyphicon glyphicon-earphone"></span> <?php echo htmlspecialchars($row['phone']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 10px;"><?php echo htmlspecialchars($row['subject']); ?></td>
                                        <td style="padding: 10px; max-width: 250px; font-size: 13px; color: #555;">
                                            <?php echo htmlspecialchars(substr($row['message'], 0, 80)); ?><?php echo strlen($row['message']) > 80 ? '...' : ''; ?>
                                        </td>
                                        
                                        <!-- COLORFUL STATUS DROPDOWN -->
                                        <td style="padding: 10px; text-align: center; vertical-align: middle;">
                                            <form method="POST" action="complaints.php" style="margin: 0;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="complaint_id" value="<?php echo $row['complaint_id']; ?>">
                                                <input type="hidden" name="return_search" value="<?php echo htmlspecialchars($search); ?>">
                                                <input type="hidden" name="return_filter" value="<?php echo htmlspecialchars($filter_status); ?>">
                                                
                                                <select name="status" 
                                                        onchange="this.form.submit()" 
                                                        class="status-dropdown"
                                                        style="background: <?php echo $st['bg']; ?>; 
                                                               color: <?php echo $st['color']; ?>; 
                                                               border: 2px solid <?php echo $st['border']; ?>; 
                                                               font-weight: 700; 
                                                               font-size: 12px;
                                                               padding: 6px 28px 6px 12px;
                                                               border-radius: 20px;
                                                               cursor: pointer;
                                                               appearance: none;
                                                               -webkit-appearance: none;
                                                               -moz-appearance: none;
                                                               background-image: url(\"data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='<?php echo $row['status'] == 'in_progress' ? '%23000' : '%23fff'; ?>' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e\");
                                                               background-repeat: no-repeat;
                                                               background-position: right 8px center;
                                                               background-size: 12px;
                                                               outline: none;
                                                               transition: all 0.2s;">
                                                    <option value="new"         <?php echo $row['status'] == 'new' ? 'selected' : ''; ?>>● New</option>
                                                    <option value="in_progress" <?php echo $row['status'] == 'in_progress' ? 'selected' : ''; ?>>● In Progress</option>
                                                    <option value="resolved"    <?php echo $row['status'] == 'resolved' ? 'selected' : ''; ?>>● Resolved</option>
                                                </select>
                                            </form>
                                        </td>
                                        
                                        <td style="padding: 10px; font-size: 13px;">
                                            <?php if ($row['handled_by']): ?>
                                                <span style="color: #28a745;">✓ <?php echo htmlspecialchars($row['handled_by']); ?></span>
                                            <?php else: ?>
                                                <span style="color: #aaa;">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 10px; font-size: 13px;">
                                            <?php echo date('d/m/Y', strtotime($row['created_at'])); ?><br>
                                            <small style="color: #888;"><?php echo date('H:i', strtotime($row['created_at'])); ?></small>
                                        </td>
                                        <td style="padding: 10px; white-space: nowrap;">
                                            <!-- View -->
                                            <button type="button" class="btn btn-info btn-xs" title="View" 
                                                    onclick='viewComplaint(<?php echo json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                                    style="margin: 1px; padding: 4px 8px;">
                                                <span class="glyphicon glyphicon-eye-open"></span>
                                            </button>
                                            <!-- Delete -->
                                            <a href="complaints.php?delete=<?php echo $row['complaint_id']; ?>" 
                                               onclick="return confirm('Delete this complaint?')" 
                                               class="btn btn-danger btn-xs" title="Delete" 
                                               style="margin: 1px; padding: 4px 8px;">
                                                <span class="glyphicon glyphicon-trash"></span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php 
                                $count++;
                                endwhile; 
                                ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="padding: 40px; text-align: center; color: #999;">
                                        <span class="glyphicon glyphicon-inbox" style="font-size: 40px; display: block; margin-bottom: 10px; color: #ddd;"></span>
                                        No complaints found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     VIEW COMPLAINT MODAL (kept)
     ============================================ -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: #1a3c5e; color: white;">
                <button type="button" class="close" data-dismiss="modal" style="color: white; opacity: 1;">&times;</button>
                <h4 class="modal-title"><span class="glyphicon glyphicon-envelope"></span> Complaint Details</h4>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <tr><th style="width: 130px;">From</th><td id="v_name"></td></tr>
                    <tr><th>Email</th><td id="v_email"></td></tr>
                    <tr><th>Phone</th><td id="v_phone"></td></tr>
                    <tr><th>Subject</th><td id="v_subject"></td></tr>
                    <tr><th>Message</th><td id="v_message" style="white-space: pre-wrap;"></td></tr>
                    <tr><th>Status</th><td id="v_status"></td></tr>
                    <tr><th>Submitted</th><td id="v_created"></td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script>
function viewComplaint(data) {
    document.getElementById('v_name').textContent = data.name;
    document.getElementById('v_email').textContent = data.email;
    document.getElementById('v_phone').textContent = data.phone || '-';
    document.getElementById('v_subject').textContent = data.subject;
    document.getElementById('v_message').textContent = data.message;
    
    // Show status with colored badge
    var statusMap = {
        'new':         '<span style="background:#dc3545; color:white; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:600;">New</span>',
        'in_progress': '<span style="background:#ffc107; color:#000; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:600;">In Progress</span>',
        'resolved':    '<span style="background:#28a745; color:white; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:600;">Resolved</span>'
    };
    document.getElementById('v_status').innerHTML = statusMap[data.status] || data.status;
    
    document.getElementById('v_created').textContent = data.created_at;
    $('#viewModal').modal('show');
}
</script>

<style>
    /* Status dropdown hover effect */
    .status-dropdown:hover {
        transform: scale(1.05);
        box-shadow: 0 3px 8px rgba(0,0,0,0.2);
    }
    
    /* Fix dropdown option appearance in some browsers */
    .status-dropdown option {
        background: white;
        color: #333;
        padding: 8px;
    }
</style>

<?php include 'footer.php'; ?>
<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close(); 
}
?>