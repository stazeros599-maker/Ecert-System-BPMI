<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include '../db.php';
include 'nav_stack.php';
push_nav_stack();

// ============================================
// Handle delete — deletes participant + all their certificates
// ============================================
if (isset($_GET['delete_participant']) && !empty($_GET['delete_participant'])) {
    $ic = $_GET['delete_participant'];
    
    $stmt = $conn->prepare("SELECT certificate_file FROM certificates WHERE nokp = ?");
    $stmt->bind_param("s", $ic);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $file = '../certificates/' . $row['certificate_file'];
        if (file_exists($file)) {
            unlink($file);
        }
    }
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM certificates WHERE nokp = ?");
    $stmt->bind_param("s", $ic);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM participant WHERE icNum = ?");
    $stmt->bind_param("s", $ic);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['admin_message'] = 'Participant and all their certificates deleted successfully.';
    $_SESSION['admin_message_type'] = 'success';
    
    // Preserve filter
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    header('Location: index.php?filter=' . urlencode($filter));
    exit;
}

// ============================================
// READ FILTER + SEARCH
// ============================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';

// Validate filter value
if (!in_array($filter, ['all', 'public', 'insider'])) {
    $filter = 'all';
}

// ============================================
// MAIN QUERY — participants with cert count
// ============================================
$sql = "SELECT 
            p.icNum,
            p.fullName,
            p.insider,
            COUNT(c.serialNum) AS cert_count
        FROM participant p
        LEFT JOIN certificates c ON c.nokp = p.icNum
        WHERE 1=1";
$params = array();
$types = "";

// Apply filter
if ($filter === 'insider') {
    $sql .= " AND p.insider = 1";
} elseif ($filter === 'public') {
    $sql .= " AND (p.insider = 0 OR p.insider IS NULL)";
}
// 'all' → no filter

// Apply search
if (!empty($search)) {
    $sql .= " AND (p.fullName LIKE ? OR p.icNum LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$sql .= " GROUP BY p.icNum, p.fullName, p.insider
          ORDER BY p.fullName ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $filtered_count = $result->num_rows;
} else {
    $result = $conn->query($sql);
    $filtered_count = $result->num_rows;
}

// ============================================
// FILTER COUNTS — for badges
// ============================================
$count_all = $conn->query("SELECT COUNT(*) as c FROM participant")->fetch_assoc()['c'];
$count_insider = $conn->query("SELECT COUNT(*) as c FROM participant WHERE insider = 1")->fetch_assoc()['c'];
$count_public = $conn->query("SELECT COUNT(*) as c FROM participant WHERE insider = 0 OR insider IS NULL")->fetch_assoc()['c'];

// ============================================
// HISTORY — last 5 added certificates
// ============================================
$history_sql = "SELECT c.*, p.insider 
                FROM certificates c 
                LEFT JOIN participant p ON c.nokp = p.icNum 
                ORDER BY c.created_at DESC 
                LIMIT 5";
$history_result = $conn->query($history_sql);

// ============================================
// STATS
// ============================================
$total = $conn->query("SELECT COUNT(*) as count FROM certificates")->fetch_assoc()['count'];
$recent = $conn->query("SELECT COUNT(*) as count FROM certificates WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'];
$unique_participants = $conn->query("SELECT COUNT(*) as count FROM participant")->fetch_assoc()['count'];
$course_types = $conn->query("SELECT COUNT(DISTINCT course_name) as count FROM certificates")->fetch_assoc()['count'];

$message = isset($_SESSION['admin_message']) ? $_SESSION['admin_message'] : '';
$message_type = isset($_SESSION['admin_message_type']) ? $_SESSION['admin_message_type'] : '';
unset($_SESSION['admin_message']);
unset($_SESSION['admin_message_type']);

$page_title = 'Admin Dashboard - eCert BPMI';
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
                Dashboard
            </h2>
            <hr style="border-top: 2px solid #e1e5eb; margin: 10px 0 25px 0;">
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-6">
            <div class="stat-card">
                <img src="../icons/certificate-light-mode.png" alt="Total Certificates" class="stat-icon icon-light">
                <img src="../icons/certificate-dark-mode.png" alt="Total Certificates" class="stat-icon icon-dark">
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="stat-label">Total Certificates</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-6">
            <div class="stat-card">
                <img src="../icons/calendar-light-mode.png" alt="Recent" class="stat-icon icon-light">
                <img src="../icons/calendar-dark-mode.png" alt="Recent" class="stat-icon icon-dark">
                <div class="stat-number"><?php echo $recent; ?></div>
                <div class="stat-label">Added (Last 30 Days)</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-6">
            <div class="stat-card">
                <img src="../icons/user-light-mode.png" alt="Participants" class="stat-icon icon-light">
                <img src="../icons/user-dark-mode.png" alt="Participants" class="stat-icon icon-dark">
                <div class="stat-number"><?php echo $unique_participants; ?></div>
                <div class="stat-label">Unique Participants</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-6">
            <div class="stat-card">
                <img src="../icons/training-light-mode.png" alt="Courses" class="stat-icon icon-light">
                <img src="../icons/training-dark-mode.png" alt="Courses" class="stat-icon icon-dark">
                <div class="stat-number"><?php echo $course_types; ?></div>
                <div class="stat-label">Course Types</div>
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

    <!-- ============================================
         FILTER TABS
         ============================================ -->
    <div class="row" style="margin-bottom: 15px;">
        <div class="col-md-12">
            <div class="filter-tabs">
                <span class="filter-tabs-label">
                    <span class="glyphicon glyphicon-filter"></span> Filter:
                </span>
                
                <a href="index.php?filter=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <span class="glyphicon glyphicon-th-list"></span>
                    All
                    <span class="filter-tab-count"><?php echo $count_all; ?></span>
                </a>
                
                <a href="index.php?filter=insider<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="filter-tab filter-tab-insider <?php echo $filter === 'insider' ? 'active' : ''; ?>">
                    <span class="glyphicon glyphicon-user"></span>
                    Insider
                    <span class="filter-tab-count"><?php echo $count_insider; ?></span>
                </a>
                
                <a href="index.php?filter=public<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="filter-tab filter-tab-public <?php echo $filter === 'public' ? 'active' : ''; ?>">
                    <span class="glyphicon glyphicon-user"></span>
                    Public
                    <span class="filter-tab-count"><?php echo $count_public; ?></span>
                </a>
                
                <?php if ($filter !== 'all' || !empty($search)): ?>
                    <a href="index.php" class="filter-clear" title="Clear all filters">
                        <span class="glyphicon glyphicon-remove"></span> Reset
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================
         PARTICIPANT LIST + HISTORY SIDEBAR
         ============================================ -->
    <div class="row">
        
        <!-- LEFT: Participant List (9 cols) -->
        <div class="col-md-9">
            <div style="background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: none; overflow: hidden;">
                <div style="background: #1a3c5e; color: white; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <span class="glyphicon glyphicon-user"></span> 
                        <?php 
                        if ($filter === 'insider') echo 'Insider Participants';
                        elseif ($filter === 'public') echo 'Public Participants';
                        else echo 'All Participants';
                        ?>
                        <span style="background: rgba(255,255,255,0.2); font-size: 14px; padding: 4px 12px; border-radius: 20px;"><?php echo $filtered_count; ?></span>
                        <?php if (!empty($search)): ?>
                            <span style="background: #ffc107; color: #333; font-size: 12px; padding: 2px 10px; border-radius: 20px;">
                                🔍 "<strong><?php echo htmlspecialchars($search); ?></strong>"
                            </span>
                        <?php endif; ?>
                    </div>
                    <a href="add_certificate.php" style="background: #28a745; color: white; border: none; padding: 6px 16px; border-radius: 50px; font-size: 13px; font-weight: 600; text-decoration: none;">
                        <span class="glyphicon glyphicon-plus"></span> Add Certificate
                    </a>
                </div>

                <!-- Search Bar -->
                <div style="padding: 12px 20px; background: #f0f2f5; border-bottom: 1px solid #e1e5eb;">
                    <form method="get" action="index.php" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; width: 100%;">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        
                        <div style="flex: 1; position: relative;">
                            <span class="glyphicon glyphicon-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #888; font-size: 14px; z-index: 5;"></span>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search by name or IC number..."
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   style="height: 42px; font-size: 14px; padding-left: 40px; border: 2px solid #e1e5eb; border-radius: 6px;">
                        </div>
                        <button type="submit" class="btn btn-info" style="height: 42px; padding: 0 24px; font-weight: 600; background: #1a3c5e; border-color: #1a3c5e;">
                            <span class="glyphicon glyphicon-search"></span> Search
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="index.php?filter=<?php echo htmlspecialchars($filter); ?>" class="btn" style="height: 42px; padding: 0 24px; font-weight: 600; background: #6c757d; color: white; border: 2px solid #6c757d; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px;">
                                <span class="glyphicon glyphicon-remove"></span> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Participant Table -->
                <div style="padding: 15px; overflow-x: auto;">
                    <table class="table table-bordered" style="margin-bottom: 0; min-width: 700px; width: 100%; border-collapse: separate; border-spacing: 0;">
                        <thead>
                            <tr>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px; width: 60px;">Count</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Name</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">IC No.</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px; text-align: center;">Total Certificates</th>
                                <th style="background: #f7f9fc; border-bottom: 2px solid #1a3c5e; padding: 12px 10px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php 
                                $count = 1;
                                while ($row = $result->fetch_assoc()): 
                                    $is_insider = isset($row['insider']) && $row['insider'] == 1;
                                ?>
                                    <tr class="cert-row <?php echo $is_insider ? 'insider-row' : 'public-row'; ?>">
                                        <td style="padding: 10px;"><?php echo $count; ?></td>
                                        
                                        <td style="padding: 10px;">
                                            <a href="participant_certificates.php?ic=<?php echo urlencode($row['icNum']); ?>" 
                                               style="color: inherit; text-decoration: none; font-weight: 600;"
                                               onmouseover="this.style.textDecoration='underline'; this.style.color='#1a3c5e';"
                                               onmouseout="this.style.textDecoration='none'; this.style.color='inherit';"
                                               title="View all certificates for this participant">
                                                <?php echo htmlspecialchars($row['fullName']); ?>
                                            </a>
                                            <?php if ($is_insider): ?>
                                                <span class="badge-insider-tag">Insider</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td style="padding: 10px;">
                                            <span style="font-family: 'Courier New', monospace;">
                                                <?php echo htmlspecialchars($row['icNum']); ?>
                                            </span>
                                        </td>
                                        
                                        <td style="padding: 10px; text-align: center;">
                                            <?php if (intval($row['cert_count']) > 0): ?>
                                                <span class="cert-count-badge"><?php echo intval($row['cert_count']); ?></span>
                                            <?php else: ?>
                                                <span class="cert-count-zero">0</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td style="padding: 10px; white-space: nowrap; min-width: 100px;">
                                            <a href="participant_certificates.php?ic=<?php echo urlencode($row['icNum']); ?>" 
                                               class="btn btn-info btn-xs" 
                                               title="View Certificates" 
                                               style="margin: 1px; padding: 4px 8px;">
                                                <span class="glyphicon glyphicon-eye-open"></span>
                                            </a>
                                            <a href="edit_participant.php?ic=<?php echo urlencode($row['icNum']); ?>" 
                                               class="btn btn-warning btn-xs" 
                                               title="Edit Participant" 
                                               style="margin: 1px; padding: 4px 8px;">
                                                <span class="glyphicon glyphicon-edit"></span>
                                            </a>
                                            <a href="index.php?delete_participant=<?php echo urlencode($row['icNum']); ?>&filter=<?php echo htmlspecialchars($filter); ?>" 
                                               onclick="return confirm('Delete this participant AND all their certificates? This cannot be undone.')" 
                                               class="btn btn-danger btn-xs" 
                                               title="Delete Participant" 
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
                                    <td colspan="5" style="padding: 40px; text-align: center; color: #999;">
                                        <span class="glyphicon glyphicon-inbox" style="font-size: 40px; display: block; margin-bottom: 10px; color: #ddd;"></span>
                                        <?php if (!empty($search)): ?>
                                            No participants found for "<strong><?php echo htmlspecialchars($search); ?></strong>".
                                        <?php else: ?>
                                            No <?php echo $filter !== 'all' ? $filter : ''; ?> participants found.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- RIGHT: History Sidebar -->
        <div class="col-md-3">
            <div class="history-card">
                <div class="history-header">
                    <span class="glyphicon glyphicon-time"></span>
                    History (Last Added)
                </div>
                <div class="history-body">
                    <?php if ($history_result && $history_result->num_rows > 0): ?>
                        <?php while ($h = $history_result->fetch_assoc()): 
                            $h_insider = isset($h['insider']) && $h['insider'] == 1;
                        ?>
                            <a href="participant_certificates.php?ic=<?php echo urlencode($h['nokp']); ?>" 
                               class="history-item <?php echo $h_insider ? 'insider' : 'public'; ?>"
                               title="View <?php echo htmlspecialchars($h['name']); ?>'s certificates">
                                <div class="history-serial"><?php echo htmlspecialchars($h['serialNum']); ?></div>
                                <div class="history-name"><?php echo htmlspecialchars($h['name']); ?></div>
                                <div class="history-course"><?php echo htmlspecialchars($h['course_name']); ?></div>
                                <div class="history-date">
                                    <span class="glyphicon glyphicon-calendar"></span>
                                    <?php echo date('d/m/Y', strtotime($h['created_at'])); ?>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="history-empty">
                            <span class="glyphicon glyphicon-inbox"></span>
                            <p>No recent activity</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- ============================================
     STYLES
     ============================================ -->
<style>
    /* ============================================
       FILTER TABS
       ============================================ */
    .filter-tabs {
        background: var(--bg-card);
        border-radius: 10px;
        box-shadow: 0 2px 10px var(--shadow);
        padding: 10px 15px;
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
        border: 1px solid transparent;
        transition: background-color 0.25s ease, border-color 0.25s ease;
    }

    [data-theme="dark"] .filter-tabs {
        border-color: var(--border-color);
    }

    .filter-tabs-label {
        font-weight: 600;
        color: var(--text-muted);
        font-size: 13px;
        margin-right: 5px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .filter-tab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: var(--bg-card-alt);
        color: var(--text-secondary);
        text-decoration: none;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s ease;
        border: 2px solid transparent;
    }

    .filter-tab:hover {
        background: var(--bg-hover);
        color: var(--text-primary);
        text-decoration: none;
        transform: translateY(-1px);
    }

    .filter-tab.active {
        background: #1a3c5e;
        color: white;
        border-color: #1a3c5e;
    }

    [data-theme="dark"] .filter-tab.active {
        background: #4a90c2;
        border-color: #4a90c2;
    }

    .filter-tab-insider.active {
        background: #4a90c2;
        border-color: #4a90c2;
    }

    .filter-tab-public.active {
        background: #6c757d;
        border-color: #6c757d;
    }

    .filter-tab-count {
        display: inline-block;
        background: rgba(0,0,0,0.1);
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 700;
        min-width: 22px;
        text-align: center;
    }

    .filter-tab.active .filter-tab-count {
        background: rgba(255,255,255,0.25);
    }

    .filter-clear {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 8px 14px;
        background: transparent;
        color: #dc3545;
        text-decoration: none;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.2s ease;
        margin-left: auto;
    }

    .filter-clear:hover {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        text-decoration: none;
    }

    /* ============================================
       ROW COLORING
       ============================================ */
    .cert-row.insider-row td {
        background-color: #d4e9ff !important;
        color: #1a3c5e;
        border-color: #b8d4f0 !important;
    }
    .cert-row.insider-row:hover td {
        background-color: #c0ddfb !important;
    }
    
    .cert-row.public-row td {
        background-color: #ffffff !important;
        color: #333;
    }
    .cert-row.public-row:hover td {
        background-color: #f8f9fa !important;
    }

    [data-theme="dark"] .cert-row.insider-row td {
        background-color: #1e3a52 !important;
        color: #c8dcf0 !important;
        border-color: #2a4a66 !important;
    }
    [data-theme="dark"] .cert-row.insider-row:hover td {
        background-color: #264a68 !important;
    }

    [data-theme="dark"] .cert-row.public-row td {
        background-color: var(--bg-card) !important;
        color: var(--text-primary) !important;
    }
    [data-theme="dark"] .cert-row.public-row:hover td {
        background-color: var(--bg-hover) !important;
    }
    
    .cert-row td strong {
        font-weight: 600;
    }

    /* Insider badge */
    .badge-insider-tag {
        display: inline-block;
        background: #4a90c2;
        color: white;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        vertical-align: middle;
    }
    
    [data-theme="dark"] .badge-insider-tag {
        background: #2a5f7a;
        color: #a3c8e8;
        border: 1px solid #4a90c2;
    }

    /* Certificate count badge */
    .cert-count-badge {
        display: inline-block;
        min-width: 32px;
        padding: 4px 12px;
        background: #1a3c5e;
        color: white;
        font-size: 13px;
        font-weight: 700;
        border-radius: 20px;
        text-align: center;
    }
    
    [data-theme="dark"] .cert-count-badge {
        background: #4a90c2;
        color: #ffffff;
    }

    .cert-count-zero {
        display: inline-block;
        min-width: 32px;
        padding: 4px 12px;
        background: #f0f0f0;
        color: #999;
        font-size: 13px;
        font-weight: 600;
        border-radius: 20px;
        text-align: center;
    }
    
    [data-theme="dark"] .cert-count-zero {
        background: #2a2d33;
        color: #6c757d;
    }

    /* ============================================
       HISTORY SIDEBAR
       ============================================ */
    .history-card {
        background: var(--bg-card);
        border-radius: 10px;
        box-shadow: 0 2px 10px var(--shadow);
        border: 1px solid transparent;
        overflow: hidden;
        transition: background-color 0.25s ease, border-color 0.25s ease;
        position: sticky;
        top: 80px;
    }

    [data-theme="dark"] .history-card {
        border-color: var(--border-color);
    }

    .history-header {
        background: #1a3c5e;
        color: white;
        padding: 12px 16px;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .history-header .glyphicon {
        color: #ffd700;
    }

    .history-body {
        padding: 8px;
        max-height: 500px;
        overflow-y: auto;
    }

    .history-body::-webkit-scrollbar { width: 6px; }
    .history-body::-webkit-scrollbar-track { background: transparent; }
    .history-body::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 3px;
    }

    .history-item {
        display: block;
        padding: 10px 12px;
        margin-bottom: 6px;
        border-radius: 6px;
        text-decoration: none;
        border-left: 3px solid transparent;
        transition: all 0.2s ease;
        color: inherit;
    }

    .history-item:hover {
        text-decoration: none;
        color: inherit;
        transform: translateX(3px);
    }

    .history-item.insider {
        background: #eaf4ff;
        border-left-color: #4a90c2;
    }
    .history-item.insider:hover { background: #d4e9ff; }

    [data-theme="dark"] .history-item.insider {
        background: #1e2c45;
        border-left-color: #4a90c2;
    }
    [data-theme="dark"] .history-item.insider:hover { background: #263a58; }

    .history-item.public {
        background: var(--bg-card-alt);
        border-left-color: #8b8f98;
    }
    .history-item.public:hover { background: var(--bg-hover); }

    .history-serial {
        font-size: 11px;
        font-weight: 700;
        color: #1a3c5e;
        letter-spacing: 0.5px;
        margin-bottom: 3px;
    }
    [data-theme="dark"] .history-serial { color: #7ab8e0; }

    .history-name {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 2px;
    }

    .history-course {
        font-size: 11px;
        color: var(--text-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 4px;
    }

    .history-date {
        font-size: 10px;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .history-empty {
        text-align: center;
        padding: 30px 15px;
        color: var(--text-muted);
    }
    .history-empty .glyphicon {
        font-size: 32px;
        opacity: 0.3;
        display: block;
        margin-bottom: 10px;
    }
    .history-empty p { margin: 0; font-size: 13px; }

    /* ============================================
       STAT CARDS
       ============================================ */
    .stat-card {
        background: var(--bg-card);
        border-radius: 10px;
        padding: 20px 15px;
        box-shadow: 0 2px 10px var(--shadow);
        text-align: center;
        margin-bottom: 20px;
        min-height: 140px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.25s ease, border-color 0.25s ease;
        border: 1px solid transparent;
    }

    [data-theme="dark"] .stat-card {
        background: #1a2438;
        border-color: #2a3d5c;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 18px var(--shadow);
    }

    [data-theme="dark"] .stat-card:hover {
        background: #1e2c45;
        border-color: #35496c;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        object-fit: contain;
        margin-bottom: 10px;
        transition: transform 0.2s ease;
    }

    .stat-card:hover .stat-icon {
        transform: scale(1.08);
    }

    .stat-number {
        font-size: 32px;
        font-weight: 700;
        color: #1a3c5e;
        line-height: 1.2;
        transition: color 0.25s ease;
    }

    [data-theme="dark"] .stat-number {
        color: #ffffff;
        text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    }

    .stat-label {
        color: #888;
        font-size: 13px;
        margin-top: 6px;
        font-weight: 500;
        transition: color 0.25s ease;
    }

    [data-theme="dark"] .stat-label {
        color: #94a3b8;
    }

    /* ICON SWITCHING */
    .icon-light { display: block; }
    .icon-dark  { display: none !important; }
    [data-theme="dark"] .icon-light { display: none !important; }
    [data-theme="dark"] .icon-dark  { display: block !important; }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 991px) {
        .history-card {
            margin-top: 20px;
            position: static;
        }
    }

    @media (max-width: 768px) {
        .stat-card { min-height: 120px; padding: 15px 10px; }
        .stat-icon { width: 40px; height: 40px; }
        .stat-number { font-size: 26px; }
        .stat-label { font-size: 12px; }
        .history-body { max-height: none; }
        .filter-tabs {
            padding: 8px 10px;
        }
        .filter-tab {
            font-size: 12px;
            padding: 6px 12px;
       