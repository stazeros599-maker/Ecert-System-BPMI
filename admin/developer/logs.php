<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

include '../../db.php';
require '../check_role.php';
require_role('developer');

include '../nav_stack.php';
push_nav_stack();

// ============================================
// FILTERS (UNCHANGED)
// ============================================
$filter_actor  = $_GET['actor']  ?? '';
$filter_module = $_GET['module'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_search = $_GET['q']      ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$sql = "SELECT * FROM activity_logs WHERE 1=1";
$params = [];
$types = "";

if ($filter_actor) {
    $sql .= " AND actor_type = ?";
    $params[] = $filter_actor;
    $types .= "s";
}
if ($filter_module) {
    $sql .= " AND module = ?";
    $params[] = $filter_module;
    $types .= "s";
}
if ($filter_status) {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($filter_search) {
    $sql .= " AND (message LIKE ? OR actor_id LIKE ? OR target_id LIKE ?)";
    $like = '%' . $filter_search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}

// Get total count
$count_sql = str_replace("SELECT *", "SELECT COUNT(*) as c", $sql);
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
$total_pages = max(1, ceil($total / $per_page));

// Get page data
$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result();
$stmt->close();

$page_title = 'Activity Logs - eCert BPMI';
include '../header.php';
include '../nav.php';
?>

<style>
    /* --- Modern Wrapper --- */
    .modern-logs-container {
        background: #f8f9fa; /* Soft light background for the page */
        min-height: calc(100vh - 60px);
        padding: 30px 20px;
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    [data-theme="dark"] .modern-logs-container { background: #121212; }

    .logs-card {
        background: var(--bg-card);
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid var(--border-color);
        padding: 24px;
        max-width: 1400px;
        margin: 0 auto;
    }

    /* --- Header & Tabs --- */
    .logs-header {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        margin-bottom: 24px;
        border-bottom: 1px solid var(--border-color);
    }
    .logs-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 16px;
        color: var(--text-muted);
        font-size: 13px;
    }
    .logs-title {
        font-size: 15px;
        font-weight: 500;
        color: var(--text-muted);
        margin: 0 0 16px 0;
    }
    .logs-tabs {
        display: flex;
        gap: 24px;
    }
    .logs-tab {
        padding-bottom: 12px;
        font-size: 14px;
        font-weight: 600;
        color: var(--text-muted);
        border-bottom: 2px solid transparent;
        margin-bottom: -1px; /* Overlap the border */
        cursor: default;
    }
    .logs-tab.active {
        color: #28a745; /* Green from image */
        border-bottom-color: #28a745;
    }

    /* --- Filter Bar --- */
    .filter-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .filter-group {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .search-box {
        position: relative;
        min-width: 240px;
    }
    .search-box input {
        width: 100%;
        height: 40px;
        padding: 0 12px 0 36px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 13px;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
    }
    .search-box input:focus { border-color: #28a745; }
    .search-box .glyphicon {
        position: absolute;
        left: 12px;
        top: 12px;
        color: var(--text-muted);
        font-size: 14px;
    }
    
    .filter-select {
        height: 40px;
        padding: 0 30px 0 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 13px;
        background: var(--bg-card);
        color: var(--text-primary);
        appearance: none;
        background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
        background-repeat: no-repeat;
        background-position: right 12px top 50%;
        background-size: 10px auto;
        cursor: pointer;
    }

    .btn-filter {
        height: 40px;
        padding: 0 16px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-filter:hover { background: #218838; }

    /* --- Modern Table --- */
    .modern-table-wrapper {
        overflow-x: auto;
    }
    .modern-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .modern-table th {
        background: transparent;
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.5px;
        padding: 16px 12px;
        border-bottom: 1px solid var(--border-color);
        text-align: left;
        white-space: nowrap;
    }
    .modern-table td {
        padding: 16px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    .modern-table tbody tr:last-child td { border-bottom: none; }
    .modern-table tbody tr:hover { background: rgba(0,0,0,0.02); }
    [data-theme="dark"] .modern-table tbody tr:hover { background: rgba(255,255,255,0.02); }

    /* --- Badges & Tags --- */
    .actor-tag {
        font-size: 12px;
        color: var(--text-primary);
        font-weight: 600;
    }
    .actor-sub { font-size: 11px; color: var(--text-muted); display: block; }

    /* --- Pagination --- */
    .modern-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 24px;
        font-size: 13px;
        color: var(--text-muted);
    }
    .page-buttons {
        display: flex;
        gap: 4px;
    }
    .page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        color: var(--text-primary);
        text-decoration: none;
        background: var(--bg-card);
        font-weight: 500;
    }
    .page-btn:hover { background: var(--bg-hover); text-decoration: none; color: var(--text-primary); }
    .page-btn.active { background: #1a3c5e; color: white; border-color: #1a3c5e; }
</style>

<div class="modern-logs-container">
    <div class="logs-card">
        
        <!-- Header Area -->
        <div class="logs-header">
            <div class="logs-meta">
                <span>Total records: <strong><?php echo number_format($total); ?></strong></span>
                <span class="page-info" role="img" tabindex="0" aria-label="Review recorded system events, outcomes, and durations." data-tooltip="Review recorded system events, outcomes, and durations."><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span></span>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="get" class="filter-wrapper">
            <div class="filter-group">
                <div class="search-box">
                    <span class="glyphicon glyphicon-search"></span>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Search message, IC, or target...">
                </div>
                
                <select name="actor" class="filter-select">
                    <option value="">All Actors</option>
                    <option value="public" <?php echo $filter_actor == 'public' ? 'selected' : ''; ?>>Public</option>
                    <option value="admin" <?php echo $filter_actor == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="developer" <?php echo $filter_actor == 'developer' ? 'selected' : ''; ?>>Developer</option>
                    <option value="system" <?php echo $filter_actor == 'system' ? 'selected' : ''; ?>>System</option>
                </select>
                
                <select name="status" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="success" <?php echo $filter_status == 'success' ? 'selected' : ''; ?>>Success</option>
                    <option value="warning" <?php echo $filter_status == 'warning' ? 'selected' : ''; ?>>Warning</option>
                    <option value="failure" <?php echo $filter_status == 'failure' ? 'selected' : ''; ?>>Failure</option>
                </select>

                <select name="module" class="filter-select" style="display:none;"> <!-- Hidden to match image but keep functionality -->
                    <option value="">All Modules</option>
                    <?php
                    $mods = ['auth', 'certificate', 'participant', 'complaint', 'physical_request', 'scanner', 'report'];
                    foreach ($mods as $m) {
                        $sel = $filter_module == $m ? 'selected' : '';
                        echo "<option value=\"$m\" $sel>" . ucfirst(str_replace('_', ' ', $m)) . "</option>";
                    }
                    ?>
                </select>
                
                <button type="submit" class="btn-filter">Filter</button>
                
                <?php if ($filter_actor || $filter_module || $filter_status || $filter_search): ?>
                    <a href="logs.php" style="display:inline-flex; align-items:center; height:40px; color:var(--text-muted); text-decoration:none; font-weight:600; padding: 0 10px;">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- The Table -->
        <div class="modern-table-wrapper">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Actor</th>
                        <th>Module / Action</th>
                        <th>Target</th>
                        <th>Status</th>
                        <th>Duration</th>
                        <th>Message</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs->num_rows > 0): ?>
                        <?php while ($log = $logs->fetch_assoc()): ?>
                            <?php $status = strtolower((string)($log['status'] ?? 'warning')); ?>
                            <?php $details = json_decode((string) ($log['details'] ?? ''), true); ?>
                            <tr>
                                <td style="white-space: nowrap;">
                                    <?php echo date('d/m/Y', strtotime($log['created_at'])); ?><br>
                                    <span style="color: var(--text-muted); font-size: 12px;"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span>
                                </td>
                                <td>
                                    <span class="actor-tag"><?php echo htmlspecialchars(ucfirst($log['actor_type'])); ?></span>
                                    <span class="actor-sub"><?php echo htmlspecialchars($log['actor_id'] ?? '-'); ?></span>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: var(--text-primary);">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $log['module']))); ?>
                                    </span><br>
                                    <span style="color: var(--text-muted); font-size: 12px;">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $log['action']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars((string) ($log['target_id'] ?? '-')); ?>
                                </td>
                                <td>
                                    <span class="status-pill <?php echo htmlspecialchars($status ?: 'warning'); ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <?php if (is_array($details) && isset($details['duration_ms']) && is_numeric($details['duration_ms'])): ?>
                                        <?php echo htmlspecialchars(number_format((float) $details['duration_ms'], 2)); ?> ms
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 300px; word-break: break-word;">
                                    <?php echo htmlspecialchars($log['message'] ?? '-'); ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted); white-space: nowrap;">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No logs match your filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="modern-pagination">
                <div>
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </div>
                <div class="page-buttons">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">&lt; Prev</a>
                    <?php endif; ?>

                    <?php 
                    // Simple logic to show max 5 page numbers
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $start + 4);
                    if ($end - $start < 4) $start = max(1, $end - 4);
                    
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">Next &gt;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../footer.php'; ?>
