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

$page_title = 'Developer Dashboard - eCert BPMI';

$stats = [
    'total' => 0,
    'today' => 0,
    'failures' => 0,
    'actors' => 0
];
$recent_logs = [];
$module_counts = [];
$has_logs_table = false;

$table_check = @$conn->query("SHOW TABLES LIKE 'activity_logs'");
$has_logs_table = $table_check && $table_check->num_rows > 0;

if ($has_logs_table) {
    $stats['total'] = (int) ($conn->query("SELECT COUNT(*) AS c FROM activity_logs")->fetch_assoc()['c'] ?? 0);
    $stats['today'] = (int) ($conn->query("SELECT COUNT(*) AS c FROM activity_logs WHERE created_at >= CURDATE()")->fetch_assoc()['c'] ?? 0);
    $stats['failures'] = (int) ($conn->query("SELECT COUNT(*) AS c FROM activity_logs WHERE status = 'failure'")->fetch_assoc()['c'] ?? 0);
    $stats['actors'] = (int) ($conn->query("SELECT COUNT(DISTINCT actor_id) AS c FROM activity_logs WHERE actor_id IS NOT NULL AND actor_id <> ''")->fetch_assoc()['c'] ?? 0);

    $module_result = $conn->query("SELECT module, COUNT(*) AS total FROM activity_logs GROUP BY module ORDER BY total DESC LIMIT 8");
    if ($module_result) {
        while ($row = $module_result->fetch_assoc()) {
            $module_counts[] = $row;
        }
    }

    $recent_result = $conn->query("SELECT created_at, actor_type, actor_id, module, action, status, message FROM activity_logs ORDER BY created_at DESC LIMIT 10");
    if ($recent_result) {
        while ($row = $recent_result->fetch_assoc()) {
            $recent_logs[] = $row;
        }
    }
}

include '../header.php';
include '../nav.php';
?>

<style>
    .developer-dashboard { padding-top: 20px; padding-bottom: 30px; }
    .developer-dashboard h2 { color: var(--text-primary); font-size: 24px; font-weight: 700; margin: 0 0 10px; }
    .developer-dashboard hr { border-top: 2px solid var(--border-color); margin: 10px 0 25px; }
    .dev-stat-card, .dev-panel {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        box-shadow: 0 2px 10px var(--shadow);
    }
    .dev-stat-card { padding: 18px 20px; margin-bottom: 20px; min-height: 112px; }
    .dev-stat-label { color: var(--text-muted); font-size: 12px; text-transform: uppercase; font-weight: 600; }
    .dev-stat-value { color: var(--text-primary); font-size: 30px; font-weight: 700; line-height: 1.2; margin-top: 8px; }
    .dev-panel { overflow: hidden; margin-bottom: 20px; }
    .dev-panel-heading { background: var(--bg-panel-head); color: var(--text-invert); padding: 13px 18px; font-weight: 600; }
    .dev-panel-body { padding: 0; }
    .dev-panel table { width: 100%; margin: 0; border-collapse: collapse; font-size: 13px; }
    .dev-panel th { color: var(--text-muted); background: var(--bg-table-header); font-size: 11px; text-transform: uppercase; }
    .dev-panel th, .dev-panel td { padding: 11px 12px; border-bottom: 1px solid var(--border-color); text-align: left; vertical-align: top; }
    .dev-panel tr:last-child td { border-bottom: 0; }
    .dev-panel tr:hover td { background: var(--bg-hover); }
    .dev-status { border-radius: 10px; display: inline-block; font-size: 10px; font-weight: 700; padding: 3px 8px; text-transform: uppercase; }
    .dev-status.success { background: #d4edda; color: #155724; }
    .dev-status.warning { background: #fff3cd; color: #856404; }
    .dev-status.failure { background: #f8d7da; color: #721c24; }
    [data-theme="dark"] .dev-status.success { background: #1a3a24; color: #a3d9a5; }
    [data-theme="dark"] .dev-status.warning { background: #3a2e1a; color: #f5d99a; }
    [data-theme="dark"] .dev-status.failure { background: #3a1a1e; color: #f5a3ab; }
    .dev-actor { color: var(--text-muted); font-size: 12px; }
    .dev-empty { color: var(--text-muted); padding: 28px 18px; text-align: center; }
    .dev-actions { margin-bottom: 20px; }
</style>

<div class="container developer-dashboard">
    <h2><span class="glyphicon glyphicon-dashboard"></span> Developer Dashboard</h2>
    <hr>

    <?php if (!$has_logs_table): ?>
        <div class="alert alert-warning">
            <span class="glyphicon glyphicon-warning-sign"></span>
            The activity log table is not available yet. Recent activity will appear here once logging is enabled.
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="dev-stat-card">
                <div class="dev-stat-label">Total Events</div>
                <div class="dev-stat-value"><?php echo number_format($stats['total']); ?></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="dev-stat-card">
                <div class="dev-stat-label">Events Today</div>
                <div class="dev-stat-value"><?php echo number_format($stats['today']); ?></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="dev-stat-card">
                <div class="dev-stat-label">Failed Events</div>
                <div class="dev-stat-value"><?php echo number_format($stats['failures']); ?></div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="dev-stat-card">
                <div class="dev-stat-label">Unique Actors</div>
                <div class="dev-stat-value"><?php echo number_format($stats['actors']); ?></div>
            </div>
        </div>
    </div>

    <div class="dev-actions">
        <a href="logs.php" class="btn btn-primary" style="background: #1a3c5e; border: none;">
            <span class="glyphicon glyphicon-list-alt"></span> View Full Activity Logs
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="dev-panel">
                <div class="dev-panel-heading"><span class="glyphicon glyphicon-time"></span> Recent Activity</div>
                <div class="dev-panel-body" style="overflow-x: auto;">
                    <?php if (!empty($recent_logs)): ?>
                        <table>
                            <thead>
                                <tr><th>Time</th><th>Actor</th><th>Module / Action</th><th>Status</th><th>Message</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_logs as $log): ?>
                                    <?php $status = strtolower((string) ($log['status'] ?? 'warning')); ?>
                                    <tr>
                                        <td style="white-space: nowrap; color: var(--text-muted);">
                                            <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($log['created_at']))); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($log['actor_type'] ?? '-'); ?></strong><br>
                                            <span class="dev-actor"><?php echo htmlspecialchars($log['actor_id'] ?? '-'); ?></span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($log['module'] ?? '-')))); ?><br>
                                            <span class="dev-actor"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($log['action'] ?? '-')))); ?></span>
                                        </td>
                                        <td><span class="dev-status <?php echo htmlspecialchars($status ?: 'warning'); ?>"><?php echo htmlspecialchars($status ?: 'unknown'); ?></span></td>
                                        <td style="min-width: 180px;"><?php echo htmlspecialchars($log['message'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="dev-empty">No activity has been recorded.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="dev-panel">
                <div class="dev-panel-heading"><span class="glyphicon glyphicon-stats"></span> Events by Module</div>
                <div class="dev-panel-body">
                    <?php if (!empty($module_counts)): ?>
                        <table>
                            <thead><tr><th>Module</th><th>Events</th></tr></thead>
                            <tbody>
                                <?php foreach ($module_counts as $module): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($module['module'] ?? '-')))); ?></td>
                                        <td><strong><?php echo number_format((int) $module['total']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="dev-empty">No module data available.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>
