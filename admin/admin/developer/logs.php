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
// FILTERS
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
    .log-filter-bar {
        background: var(--bg-card);
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px var(--shadow);
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    .log-filter-bar .filter-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .log-filter-bar label {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        margin: 0;
    }
    .log-filter-bar input, .log-filter-bar select {
        height: 38px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        padding: 0 10px;
        font-size: 13px;
        background: var(--bg-input);
        color: var(--text-primary);
    }
    .log-clear-btn {
        height: 38px;
        padding: 0 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border: 1px solid #adb5bd;
        border-radius: 4px;
        background: transparent;
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.2s, border-color 0.2s, color 0.2s;
    }
    .log-clear-btn:hover,
    .log-clear-btn:focus {
        background: #f1f3f5;
        border-color: #6c757d;
        color: var(--text-primary);
        text-decoration: none;
    }
    [data-theme="dark"] .log-clear-btn {
        border-color: #59616b;
        color: var(--text-secondary);
    }
    [data-theme="dark"] .log-clear-btn:hover,
    [data-theme="dark"] .log-clear-btn:focus {
        background: var(--bg-hover);
        border-color: var(--accent);
        color: var(--text-primary);
    }
    .log-table {
        background: var(--bg-card);
        border-radius: 10px;
        box-shadow: 0 2px 10px var(--shadow);
        overflow: hidden;
    }
    .log-table table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
        font-size: 13px;
    }
    .log-table th {
        background: var(--bg-table-header);
        padding: 12px 10px;
        text-align: left;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--accent);
        font-size: 12px;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .log-table td {
        padding: 10px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: top;
    }
    .log-table tr:hover td { background: var(--bg-hover); }

    .badge-status {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .badge-status.success { background: #d4edda; color: #155724; }
    .badge-status.failure { background: #f8d7da; color: #721c24; }
    .badge-status.warning { background: #fff3cd; color: #856404; }

    [data-theme="dark"] .badge-status.success { background: #1a3a24; color: #a3d9a5; }
    [data-theme="dark"] .badge-status.failure { background: #3a1a1e; color: #f5a3ab; }
    [data-theme="dark"] .badge-status.warning { background: #3a2e1a; color: #f5d99a; }

    .actor-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .actor-badge.admin     { background: #1a3c5e; color: white; }
    .actor-badge.developer { background: #6f42c1; color: white; }
    .actor-badge.public    { background: #6c757d; color: white; }
    .actor-badge.system    { background: #444; color: white; }

    .pagination { text-align: center; margin-top: 20px; }
    .pagination a, .pagination span {
        display: inline-block;
        padding: 6px 12px;
        margin: 2px;
        background: var(--bg-card);
        border-radius: 4px;
        text-decoration: none;
        color: var(--text-primary);
        font-size: 13px;
    }
    .pagination .current { background: #1a3c5e; color: white; }
</style>

<div class="container" style="padding-top: 20px;">
    <h2 style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin: 0 0 10px 0;">
        <span class="glyphicon glyphicon-list-alt"></span> Activity Logs
    </h2>
    <hr style="border-top: 2px solid var(--border-color); margin: 10px 0 25px 0;">
    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
        Total records: <strong><?php echo number_format($total); ?></strong>
        &mdash; showing page <?php echo $page; ?> of <?php echo $total_pages; ?>
    </p>

    <!-- Filters -->
    <form method="get" class="log-filter-bar">
        <div class="filter-item" style="flex: 2; min-width: 180px;">
            <label>Search</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Message, actor, target...">
        </div>
        <div class="filter-item">
            <label>Actor</label>
            <select name="actor">
                <option value="">All</option>
                <option value="public" <?php echo $filter_actor == 'public' ? 'selected' : ''; ?>>Public</option>
                <option value="admin" <?php echo $filter_actor == 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="developer" <?php echo $filter_actor == 'developer' ? 'selected' : ''; ?>>Developer</option>
                <option value="system" <?php echo $filter_actor == 'system' ? 'selected' : ''; ?>>System</option>
            </select>
        </div>
        <div class="filter-item">
            <label>Module</label>
            <select name="module">
                <option value="">All</option>
                <?php
                $mods = ['auth', 'certificate', 'participant', 'complaint', 'physical_request', 'scanner', 'report'];
                foreach ($mods as $m) {
                    $sel = $filter_module == $m ? 'selected' : '';
                    echo "<option value=\"$m\" $sel>" . ucfirst(str_replace('_', ' ', $m)) . "</option>";
                }
                ?>
            </select>
        </div>
        <div class="filter-item">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="success" <?php echo $filter_status == 'success' ? 'selected' : ''; ?>>Success</option>
                <option value="warning" <?php echo $filter_status == 'warning' ? 'selected' : ''; ?>>Warning</option>
                <option value="failure" <?php echo $filter_status == 'failure' ? 'selected' : ''; ?>>Failure</option>
            </select>
        </div>
        <div class="filter-item">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 20px; background: #1a3c5e; border: none;">
                <span class="glyphicon glyphicon-filter"></span> Filter
            </button>
        </div>
        <?php if ($filter_actor || $filter_module || $filter_status || $filter_search): ?>
            <div class="filter-item">
                <label>&nbsp;</label>
                <a href="logs.php" class="log-clear-btn">
                    <span class="glyphicon glyphicon-remove"></span> Clear
                </a>
            </div>
        <?php endif; ?>
    </form>

    <!-- Log Table -->
    <div class="log-table">
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Actor</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Status</th>
                        <th>Message</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs->num_rows > 0): ?>
                        <?php while ($log = $logs->fetch_assoc()): ?>
                            <tr>
                                <td style="font-size: 12px; color: var(--text-muted); white-space: nowrap;">
                                    <?php echo date('d/m/Y', strtotime($log['created_at'])); ?><br>
                                    <strong><?php echo date('H:i:s', strtotime($log['created_at'])); ?></strong>
                                </td>
                                <td>
                                    <span class="actor-badge <?php echo htmlspecialchars($log['actor_type']); ?>">
                                        <?php echo htmlspecialchars($log['actor_type']); ?>
                                    </span><br>
                                    <small><?php echo htmlspecialchars($log['actor_id'] ?? '-'); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $module = $log['module'] ?? '-';
                                    echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $module)));
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $action = $log['action'] ?? '-';
                                    echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $action)));
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $target = $log['target_id'] ?? $log['target'] ?? '-';
                                    echo htmlspecialchars((string) $target);
                                    ?>
                                </td>
                                <td>
                                    <?php $status = strtolower((string)($log['status'] ?? '')); ?>
                                    <span class="badge-status <?php echo htmlspecialchars($status ?: 'warning'); ?>">
                                        <?php echo htmlspecialchars($status ?: 'unknown'); ?>
                                    </span>
                                </td>
                                <td style="max-width: 280px; word-break: break-word;">
                                    <?php echo htmlspecialchars($log['message'] ?? '-'); ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted); white-space: nowrap;">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                No activity logs found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Prev</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../footer.php'; ?>