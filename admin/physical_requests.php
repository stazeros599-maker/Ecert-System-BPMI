<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include '../db.php';

$admin_id = $_SESSION['admin_id'];
include 'nav_stack.php';
push_nav_stack();

// Handle status update
if (isset($_POST['update_status'])) {
    $request_id = intval($_POST['request_id']);
    $status = $_POST['status'];
    $admin_notes = trim($_POST['admin_notes']);
    
    if (in_array($status, ['pending', 'approved', 'rejected', 'shipped'])) {
        $stmt = $conn->prepare("UPDATE physical_requests SET status = ?, admin_notes = ? WHERE request_id = ?");
        $stmt->bind_param("ssi", $status, $admin_notes, $request_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['admin_message'] = 'Request status updated.';
        $_SESSION['admin_message_type'] = 'success';
        header('Location: physical_requests.php');
        exit;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM physical_requests WHERE request_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['admin_message'] = 'Request deleted.';
    $_SESSION['admin_message_type'] = 'success';
    header('Location: physical_requests.php');
    exit;
}

// Filter
$filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sql = "SELECT * FROM physical_requests WHERE 1=1";
if (in_array($filter, ['pending', 'approved', 'rejected', 'shipped'])) {
    $sql .= " AND status = '" . $conn->real_escape_string($filter) . "'";
}
$sql .= " ORDER BY requested_at DESC";
$result = $conn->query($sql);

// Counts
$count_all = $conn->query("SELECT COUNT(*) as c FROM physical_requests")->fetch_assoc()['c'];
$count_pending = $conn->query("SELECT COUNT(*) as c FROM physical_requests WHERE status = 'pending'")->fetch_assoc()['c'];
$count_approved = $conn->query("SELECT COUNT(*) as c FROM physical_requests WHERE status = 'approved'")->fetch_assoc()['c'];
$count_shipped = $conn->query("SELECT COUNT(*) as c FROM physical_requests WHERE status = 'shipped'")->fetch_assoc()['c'];
$count_rejected = $conn->query("SELECT COUNT(*) as c FROM physical_requests WHERE status = 'rejected'")->fetch_assoc()['c'];

$message = isset($_SESSION['admin_message']) ? $_SESSION['admin_message'] : '';
$message_type = isset($_SESSION['admin_message_type']) ? $_SESSION['admin_message_type'] : '';
unset($_SESSION['admin_message']);
unset($_SESSION['admin_message_type']);

$page_title = 'Physical Requests - eCert BPMI';
include 'header.php';
include 'nav.php';
?>

<div class="container" style="padding-top: 20px;">
    
    <div class="row">
        <div class="col-md-12">
            <h2 style="font-size: 24px; font-weight: 700; color: #1a3c5e; margin: 0 0 10px 0;">
                <span class="glyphicon glyphicon-envelope"></span> Physical Certificate Requests
            </h2>
            <hr style="border-top: 2px solid #e1e5eb; margin: 10px 0 25px 0;">
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible" style="border-radius: 8px;">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="row" style="margin-bottom: 15px;">
        <div class="col-md-12">
            <div style="background: white; border-radius: 10px; padding: 10px 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <span style="font-weight: 600; color: #555; font-size: 13px; margin-right: 5px;">
                    <span class="glyphicon glyphicon-filter"></span> Filter:
                </span>
                <a href="?status=all" style="padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; <?php echo $filter === 'all' ? 'background: #1a3c5e; color: white;' : 'background: #f0f2f5; color: #555;'; ?>">
                    All <span style="padding: 1px 8px; border-radius: 10px; font-size: 11px; background: rgba(0,0,0,0.1);"><?php echo $count_all; ?></span>
                </a>
                <a href="?status=pending" style="padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; <?php echo $filter === 'pending' ? 'background: #ffc107; color: #856404;' : 'background: #f0f2f5; color: #555;'; ?>">
                    Pending <span style="padding: 1px 8px; border-radius: 10px; font-size: 11px; background: rgba(0,0,0,0.1);"><?php echo $count_pending; ?></span>
                </a>
                <a href="?status=approved" style="padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; <?php echo $filter === 'approved' ? 'background: #17a2b8; color: white;' : 'background: #f0f2f5; color: #555;'; ?>">
                    Approved <span style="padding: 1px 8px; border-radius: 10px; font-size: 11px; background: rgba(0,0,0,0.1);"><?php echo $count_approved; ?></span>
                </a>
                <a href="?status=shipped" style="padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; <?php echo $filter === 'shipped' ? 'background: #28a745; color: white;' : 'background: #f0f2f5; color: #555;'; ?>">
                    Shipped/Claimed <span style="padding: 1px 8px; border-radius: 10px; font-size: 11px; background: rgba(0,0,0,0.1);"><?php echo $count_shipped; ?></span>
                </a>
                <a href="?status=rejected" style="padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; text-decoration: none; <?php echo $filter === 'rejected' ? 'background: #dc3545; color: white;' : 'background: #f0f2f5; color: #555;'; ?>">
                    Rejected <span style="padding: 1px 8px; border-radius: 10px; font-size: 11px; background: rgba(0,0,0,0.1);"><?php echo $count_rejected; ?></span>
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div style="background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: hidden;">
                <div style="background: #1a3c5e; color: white; padding: 15px 20px;">
                    <span class="glyphicon glyphicon-list"></span> All Requests
                    <span style="background: rgba(255,255,255,0.2); padding: 3px 12px; border-radius: 20px; margin-left: 10px; font-size: 14px;">
                        <?php echo $result->num_rows; ?>
                    </span>
                </div>
                
                <div style="padding: 15px; overflow-x: auto;">
                    <table class="table table-bordered" style="margin-bottom: 0; min-width: 900px;">
                        <thead>
                            <tr>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">#</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Serial</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Name</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Contact</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Address</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Status</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Requested</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php 
                                $no = 1;
                                while ($row = $result->fetch_assoc()): 
                                    $status_styles = [
                                        'pending'  => ['bg' => '#ffc107', 'color' => '#856404', 'label' => 'Pending'],
                                        'approved' => ['bg' => '#17a2b8', 'color' => 'white',   'label' => 'Approved'],
                                        'shipped'  => ['bg' => '#28a745', 'color' => 'white',   'label' => 'Shipped'],
                                        'rejected' => ['bg' => '#dc3545', 'color' => 'white',   'label' => 'Rejected']
                                    ];
                                    $st = $status_styles[$row['status']] ?? ['bg' => '#6c757d', 'color' => 'white', 'label' => ucfirst($row['status'])];
                                ?>
                                    <tr>
                                        <td style="padding: 10px;"><?php echo $no; ?></td>
                                        <td style="padding: 10px;"><strong><?php echo htmlspecialchars($row['serialNum']); ?></strong></td>
                                        <td style="padding: 10px;">
                                            <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                            <small style="color: #888;"><?php echo htmlspecialchars($row['nokp']); ?></small>
                                        </td>
                                        <td style="padding: 10px; font-size: 13px;">
                                            <div><span class="glyphicon glyphicon-envelope"></span> <?php echo htmlspecialchars($row['email']); ?></div>
                                            <div><span class="glyphicon glyphicon-earphone"></span> <?php echo htmlspecialchars($row['phone']); ?></div>
                                        </td>
                                        <td style="padding: 10px; font-size: 13px; max-width: 200px;">
                                            <?php echo nl2br(htmlspecialchars($row['address'])); ?>
                                        </td>
                                        <td style="padding: 10px; text-align: center;">
                                            <span style="background: <?php echo $st['bg']; ?>; color: <?php echo $st['color']; ?>; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700;">
                                                <?php echo $st['label']; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 10px; font-size: 13px;">
                                            <?php echo date('d/m/Y', strtotime($row['requested_at'])); ?><br>
                                            <small style="color: #888;"><?php echo date('H:i', strtotime($row['requested_at'])); ?></small>
                                        </td>
                                        <td style="padding: 10px; white-space: nowrap;">
                                            <button type="button" class="btn btn-warning btn-xs" title="Manage" 
                                                    onclick="manageRequest(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                                    style="margin: 1px; padding: 4px 8px;">
                                                <span class="glyphicon glyphicon-edit"></span>
                                            </button>
                                            <a href="physical_requests.php?delete=<?php echo $row['request_id']; ?>" 
                                               onclick="return confirm('Delete this request?')" 
                                               class="btn btn-danger btn-xs" title="Delete" 
                                               style="margin: 1px; padding: 4px 8px;">
                                                <span class="glyphicon glyphicon-trash"></span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php 
                                $no++;
                                endwhile; 
                                ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="padding: 40px; text-align: center; color: #999;">
                                        <span class="glyphicon glyphicon-inbox" style="font-size: 40px; display: block; margin-bottom: 10px; color: #ddd;"></span>
                                        No requests found.
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

<!-- Manage Modal -->
<div class="modal fade" id="manageModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header" style="background: #1a3c5e; color: white;">
                    <button type="button" class="close" data-dismiss="modal" style="color: white; opacity: 1;">&times;</button>
                    <h4 class="modal-title"><span class="glyphicon glyphicon-edit"></span> Manage Request</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="m_request_id">
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="font-weight: 600; color: #333; font-size: 14px; margin-bottom: 8px;" id="m_name"></div>
                        <div style="color: #666; font-size: 13px;" id="m_serial"></div>
                        <div style="color: #666; font-size: 13px;" id="m_contact"></div>
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight: 600;">Update Status</label>
                        <select name="status" id="m_status" class="form-control" style="height: 42px;">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="shipped">Shipped</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight: 600;">Admin Notes (Optional)</label>
                        <textarea name="admin_notes" id="m_notes" class="form-control" rows="3" placeholder="Internal notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function manageRequest(data) {
    document.getElementById('m_request_id').value = data.request_id;
    document.getElementById('m_name').textContent = data.name;
    document.getElementById('m_serial').textContent = 'Serial: ' + data.serialNum;
    document.getElementById('m_contact').textContent = data.email + ' | ' + data.phone;
    document.getElementById('m_status').value = data.status;
    document.getElementById('m_notes').value = data.admin_notes || '';
    $('#manageModal').modal('show');
}
</script>

<?php include 'footer.php'; ?>
<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close(); 
}
?>