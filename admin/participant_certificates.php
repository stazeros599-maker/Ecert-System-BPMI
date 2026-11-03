<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include '../db.php';
require 'check_role.php';
require_role('admin');   // ← Only admins can access
include 'nav_stack.php';
push_nav_stack();

// Get IC from URL
$ic = isset($_GET['ic']) ? trim($_GET['ic']) : '';

if (empty($ic)) {
    header('Location: index.php');
    exit;
}

// ============================================
// Get participant info
// ============================================
$stmt = $conn->prepare("SELECT * FROM participant WHERE icNum = ?");
$stmt->bind_param("s", $ic);
$stmt->execute();
$participant = $stmt->get_result()->fetch_assoc();
$stmt->close();

// If participant not in participant table (orphan), get name from certificates
if (!$participant) {
    $stmt = $conn->prepare("SELECT name FROM certificates WHERE nokp = ? LIMIT 1");
    $stmt->bind_param("s", $ic);
    $stmt->execute();
    $cert_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$cert_row) {
        // Nothing at all — bail out
        header('Location: index.php');
        exit;
    }
    
    $participant = [
        'icNum' => $ic,
        'fullName' => $cert_row['name'],
        'insider' => 0
    ];
}

$is_insider = isset($participant['insider']) && $participant['insider'] == 1;

// ============================================
// Get all certificates for this IC
// ============================================
$stmt = $conn->prepare("SELECT * FROM certificates WHERE nokp = ? ORDER BY course_date DESC");
$stmt->bind_param("s", $ic);
$stmt->execute();
$certificates = $stmt->get_result();
$cert_count = $certificates->num_rows;

$page_title = 'Participant Certificates - eCert BPMI';
include 'header.php';
include 'nav.php';
?>

<style>
    /* Profile header */
    .profile-header {
        background: var(--bg-card);
        border-radius: 10px;
        box-shadow: 0 2px 10px var(--shadow);
        padding: 30px 35px;
        margin-bottom: 25px;
        border-left: 6px solid <?php echo $is_insider ? '#4a90c2' : '#8b8f98'; ?>;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .profile-main {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    .profile-avatar {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: <?php echo $is_insider ? 'linear-gradient(135deg, #4a90c2, #2a5f7a)' : 'linear-gradient(135deg, #8b8f98, #555)'; ?>;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .profile-details h1 {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 5px 0;
    }

    .profile-details .ic-no {
        font-family: 'Courier New', monospace;
        font-size: 15px;
        color: var(--text-muted);
        letter-spacing: 1px;
    }

    .profile-badge {
        padding: 5px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        display: inline-block;
        margin-left: 10px;
    }

    .profile-badge.insider {
        background: #d4e9ff;
        color: #1a3c5e;
        border: 1px solid #1a3c5e;
    }

    [data-theme="dark"] .profile-badge.insider {
        background: #1e3a52;
        color: #a3c8e8;
        border-color: #4a90c2;
    }

    .profile-badge.public {
        background: #f0f0f0;
        color: #666;
        border: 1px solid #ddd;
    }

    [data-theme="dark"] .profile-badge.public {
        background: #2f3238;
        color: #b8bcc4;
        border-color: #4a4d54;
    }

    .profile-stats {
        display: flex;
        gap: 30px;
        flex-wrap: wrap;
    }

    .profile-stat {
        text-align: center;
    }

    .profile-stat .value {
        font-size: 32px;
        font-weight: 700;
        color: var(--accent);
        line-height: 1;
    }

    [data-theme="dark"] .profile-stat .value {
        color: #ffffff;
    }

    .profile-stat .label {
        font-size: 12px;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 6px;
        font-weight: 600;
    }

    /* Back button */
    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 18px;
        background: var(--accent);
        color: white;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s;
    }

    .back-btn:hover {
        background: var(--accent-hover);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(26, 60, 94, 0.3);
    }

    /* Table */
    .cert-list-card {
        background: var(--bg-card);
        border-radius: 10px;
        box-shadow: 0 2px 10px var(--shadow);
        overflow: hidden;
    }

    .cert-list-header {
        background: #1a3c5e;
        color: white;
        padding: 15px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .cert-list-header .title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 600;
    }

    .cert-list-header .count-badge {
        background: rgba(255,255,255,0.2);
        font-size: 14px;
        padding: 4px 12px;
        border-radius: 20px;
    }

    .cert-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 700px;
    }

    .cert-table th {
        background: var(--bg-table-header);
        border-bottom: 2px solid var(--accent);
        padding: 12px 10px;
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .cert-table td {
        padding: 12px 10px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        font-size: 14px;
    }

    .cert-table tbody tr:hover td {
        background: var(--bg-hover);
    }

    .cert-table .no-data {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-muted);
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: var(--bg-card);
        border-radius: 10px;
        box-shadow: 0 2px 10px var(--shadow);
    }

    .empty-state .glyphicon {
        font-size: 60px;
        color: var(--text-muted);
        opacity: 0.3;
        display: block;
        margin-bottom: 15px;
    }

    .empty-state h4 {
        color: var(--text-primary);
        margin-bottom: 8px;
    }

    .empty-state p {
        color: var(--text-muted);
    }

    @media (max-width: 768px) {
        .profile-header {
            padding: 20px;
        }
        .profile-avatar {
            width: 55px;
            height: 55px;
            font-size: 22px;
        }
        .profile-details h1 {
            font-size: 20px;
        }
        .profile-stat .value {
            font-size: 24px;
        }
        .profile-stats {
            gap: 20px;
        }
    }
</style>

<div class="container" style="padding-top: 20px;">

    <!-- Back Button -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-md-12">
            <a href="index.php" class="back-btn">
                <span class="glyphicon glyphicon-arrow-left"></span> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Participant Profile Header -->
    <div class="row">
        <div class="col-md-12">
            <div class="profile-header">
                <div class="profile-main">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($participant['fullName'], 0, 1)); ?>
                    </div>
                    <div class="profile-details">
                        <h1>
                            <?php echo htmlspecialchars($participant['fullName']); ?>
                            <span class="profile-badge <?php echo $is_insider ? 'insider' : 'public'; ?>">
                                <?php echo $is_insider ? 'Insider · Perikanan Staff' : 'Public'; ?>
                            </span>
                        </h1>
                        <div class="ic-no">
                            <span class="glyphicon glyphicon-credit-card"></span>
                            <?php echo htmlspecialchars($participant['icNum']); ?>
                        </div>
                    </div>
                </div>
                
                <div class="profile-stats">
                    <div class="profile-stat">
                        <div class="value"><?php echo $cert_count; ?></div>
                        <div class="label">Certificates</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Certificates List -->
    <div class="row">
        <div class="col-md-12">
            <div class="cert-list-card">
                <div class="cert-list-header">
                    <div class="title">
                        <span class="glyphicon glyphicon-certificate"></span>
                        Registered Certificates
                    </div>
                    <span class="count-badge"><?php echo $cert_count; ?> record<?php echo $cert_count != 1 ? 's' : ''; ?></span>
                </div>
                
                <?php if ($cert_count > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="cert-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">No.</th>
                                    <th>Serial No.</th>
                                    <th>Course</th>
                                    <th>Course Date</th>
                                    <th>Added On</th>
                                    <th style="text-align: center; width: 140px;">Certificate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                while ($cert = $certificates->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td><?php echo $no; ?></td>
                                        <td><strong><?php echo htmlspecialchars($cert['serialNum']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($cert['course_name']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($cert['course_date'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($cert['created_at'])); ?></td>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <a href="../view_certificate.php?serialNum=<?php echo urlencode($cert['serialNum']); ?>&back=<?php echo urlencode('participant_certificates.php?ic=' . $cert['nokp']); ?>" 
                                            target="_blank" 
                                            class="btn btn-info btn-xs" 
                                            title="View Certificate"
                                            style="margin: 1px; padding: 4px 8px;">
                                                <span class="glyphicon glyphicon-eye-open"></span>
                                            </a>
                                            <a href="edit_certificate.php?serialNum=<?php echo urlencode($cert['serialNum']); ?>" 
                                               class="btn btn-warning btn-xs" 
                                               title="Edit"
                                               style="margin: 1px; padding: 4px 8px;">
                                                <span class="glyphicon glyphicon-edit"></span>
                                            </a>
                                            <a href="index.php?delete=<?php echo urlencode($cert['serialNum']); ?>" 
                                               onclick="return confirm('Are you sure you want to delete this certificate?')"
                                               class="btn btn-danger btn-xs" 
                                               title="Delete"
                                               style="margin: 1px; padding: 4px 8px;">
                                                <span class="glyphicon glyphicon-trash"></span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php 
                                $no++;
                                endwhile; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="box-shadow: none;">
                        <span class="glyphicon glyphicon-inbox"></span>
                        <h4>No Certificates Found</h4>
                        <p>This participant has no registered certificates.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close(); 
}
?>