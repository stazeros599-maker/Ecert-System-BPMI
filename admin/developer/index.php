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
$module_colors = ['#e76f51', '#2a9d8f', '#264653', '#e9c46a', '#f4a261', '#577590', '#8ab17d', '#b56576'];
$module_total = 0;
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
    $module_total = array_sum(array_column($module_counts, 'total'));

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

<!-- Include Chart.js for the donut chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* --- Page Wrapper --- */
    .developer-dashboard {
        background: var(--bg-body);
        min-height: calc(100vh - 60px);
        padding: 30px 20px;
        font-family: 'Inter', 'Segoe UI', sans-serif;
        transition: background 0.25s ease, color 0.25s ease;
    }

    .dash-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .dash-header {
        display: flex;
        justify-content: flex-end;
        align-items: flex-end;
        margin-bottom: 24px;
    }
    .dash-header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .dash-title {
        color: var(--text-primary);
        font-size: 15px;
        font-weight: 500;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .dev-title-icon {
        width: 26px;
        height: 26px;
        object-fit: contain;
        flex: 0 0 26px;
    }
    .dev-title-icon-dark {
        display: none;
    }
    [data-theme="dark"] .dev-title-icon-light {
        display: none;
    }
    [data-theme="dark"] .dev-title-icon-dark {
        display: block;
    }

    /* --- Modern Stat Cards --- */
    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px 24px;
        box-shadow: 0 4px 20px var(--shadow);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .stat-label {
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }
    .stat-value {
        color: var(--text-primary);
        font-size: 32px;
        font-weight: 700;
        line-height: 1;
    }

    /* --- Modern Panels --- */
    .modern-panel {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: 0 4px 20px var(--shadow);
        margin-bottom: 24px;
        overflow: hidden;
        height: calc(100% - 24px); /* align heights */
    }
    .panel-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .panel-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .panel-title .glyphicon { color: #28a745; }

    /* --- Table Styling --- */
    .modern-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .modern-table th {
        background: var(--bg-table-header);
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.5px;
        padding: 14px 24px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
        white-space: nowrap;
    }
    .modern-table td {
        padding: 14px 24px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    .modern-table tbody tr:last-child td { border-bottom: none; }
    .modern-table tbody tr:hover { background: rgba(0,0,0,0.02); }
    [data-theme="dark"] .modern-table tbody tr:hover { background: rgba(255,255,255,0.02); }

    /* --- Badges & Tags --- */
    .actor-tag { font-weight: 600; color: var(--text-primary); text-transform: capitalize; }
    .actor-sub { font-size: 11px; color: var(--text-muted); display: block; }

    /* --- Chart Container --- */
    .chart-container {
        position: relative;
        height: 270px;
        margin: 12px auto 8px;
        max-width: 500px;
    }
    .module-legend {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 2px 16px;
        list-style: none;
        margin: 0;
        padding: 0 20px 18px;
    }
    .module-legend-item { min-width: 0; }
    .module-legend-button {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 7px 4px;
        border: 0;
        background: transparent;
        color: var(--text-primary);
        text-align: left;
        font-size: 12px;
        cursor: pointer;
    }
    .module-legend-button[aria-pressed="false"] { opacity: 0.45; }
    .module-legend-swatch {
        width: 11px;
        height: 11px;
        flex: 0 0 11px;
        border-radius: 3px;
    }
    .module-legend-name {
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .module-legend-percent {
        margin-left: auto;
        font-weight: 700;
        white-space: nowrap;
    }

    @media (max-width: 767px) {
        .chart-container { height: 250px; }
        .module-legend {
            grid-template-columns: 1fr;
            padding-right: 16px;
            padding-left: 16px;
        }
    }

    .empty-state {
        padding: 40px 20px;
        text-align: center;
        color: var(--text-muted);
    }
</style>

<div class="developer-dashboard">
    <div class="dash-container">
        
        <!-- Header -->
        <div class="dash-header">
            <div class="dash-header-actions">
                <a href="logs.php" class="btn btn-primary" style="background: #1a3c5e; border: none; border-radius: 8px; padding: 10px 20px; font-weight: 600;">
                    <span class="glyphicon glyphicon-list-alt"></span> View Full Activity Logs
                </a>
                <span class="page-info" role="img" tabindex="0" aria-label="Monitor system activity, module trends, and recent events." data-tooltip="Monitor system activity, module trends, and recent events."><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span></span>
            </div>
        </div>

        <?php if (!$has_logs_table): ?>
            <div class="alert alert-warning" style="border-radius: 8px;">
                <span class="glyphicon glyphicon-warning-sign"></span>
                The activity log table is not available yet.
            </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="row" style="margin-bottom: 10px;">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-label">Total Events</div>
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-label">Events Today</div>
                    <div class="stat-value"><?php echo number_format($stats['today']); ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-label">Failed Events</div>
                    <div class="stat-value" style="color: #c62828;"><?php echo number_format($stats['failures']); ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-label">Unique Actors</div>
                    <div class="stat-value"><?php echo number_format($stats['actors']); ?></div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Activity Table -->
            <div class="col-md-8">
                <div class="modern-panel">
                    <div class="panel-header">
                        <h3 class="panel-title"><span class="glyphicon glyphicon-time"></span> Recent Activity</h3>
                    </div>
                    <div style="overflow-x: auto;">
                        <?php if (!empty($recent_logs)): ?>
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Actor</th>
                                        <th>Module / Action</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_logs as $log): ?>
                                        <?php $status = strtolower((string) ($log['status'] ?? 'warning')); ?>
                                        <tr>
                                            <td style="white-space: nowrap;">
                                                <?php echo htmlspecialchars(date('d/m/Y', strtotime($log['created_at']))); ?><br>
                                                <span style="color: var(--text-muted); font-size: 12px;"><?php echo htmlspecialchars(date('H:i', strtotime($log['created_at']))); ?></span>
                                            </td>
                                            <td>
                                                <span class="actor-tag"><?php echo htmlspecialchars($log['actor_type'] ?? '-'); ?></span>
                                                <span class="actor-sub"><?php echo htmlspecialchars($log['actor_id'] ?? '-'); ?></span>
                                            </td>
                                            <td>
                                                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($log['module'] ?? '-')))); ?></strong><br>
                                                <span style="color: var(--text-muted); font-size: 12px;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($log['action'] ?? '-')))); ?></span>
                                            </td>
                                            <td>
                                                <span class="status-pill <?php echo htmlspecialchars($status ?: 'warning'); ?>">
                                                    <?php echo htmlspecialchars($status ?: 'unknown'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">No activity has been recorded.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Events by Module & Chart -->
            <div class="col-md-4">
                <div class="modern-panel">
                    <div class="panel-header">
                        <h3 class="panel-title"><span class="glyphicon glyphicon-stats"></span> Events by Module</h3>
                    </div>
                    
                    <?php if (!empty($module_counts)): ?>
                        <!-- Chart.js Canvas -->
                        <div class="chart-container">
                            <canvas id="moduleChart"></canvas>
                        </div>
                        <ul class="module-legend" id="moduleLegend">
                            <?php foreach ($module_counts as $index => $module): ?>
                                <?php
                                $module_label = ucfirst(str_replace('_', ' ', (string) ($module['module'] ?? '-')));
                                $module_percentage = $module_total > 0 ? ((int) $module['total'] / $module_total) * 100 : 0;
                                ?>
                                <li class="module-legend-item">
                                    <button type="button" class="module-legend-button" data-module-index="<?php echo $index; ?>" aria-pressed="true">
                                        <span class="module-legend-swatch" style="background-color: <?php echo htmlspecialchars($module_colors[$index % count($module_colors)]); ?>;"></span>
                                        <span class="module-legend-name"><?php echo htmlspecialchars($module_label); ?></span>
                                        <span class="module-legend-percent"><?php echo number_format($module_percentage, 1); ?>%</span>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-state">No module data available.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Chart.js Initialization -->
<?php if (!empty($module_counts)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('moduleChart').getContext('2d');
        
        // Extract data from PHP to JS
        const labels = <?php echo json_encode(array_map(function($m) { 
            return ucfirst(str_replace('_', ' ', $m['module'])); 
        }, $module_counts)); ?>;
        
        const dataValues = <?php echo json_encode(array_map(function($m) { 
            return (int)$m['total']; 
        }, $module_counts)); ?>;

        const lightColors = <?php echo json_encode($module_colors); ?>;
        const darkColors = ['#ff8066', '#35c4b2', '#64b5d1', '#ffd166', '#ffad66', '#8da9e6', '#a3d977', '#ed8fab'];
        const themeStyles = getComputedStyle(document.documentElement);

        const moduleChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: dataValues,
                    backgroundColor: lightColors,
                    borderWidth: 2,
                    borderColor: themeStyles.getPropertyValue('--bg-card').trim(),
                    hoverBorderWidth: 2,
                    hoverOffset: 6,
                    spacing: 2,
                    borderRadius: 3,
                    radius: '82%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%', // Makes it a donut
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1000,
                    easing: 'easeOutCubic'
                },
                transitions: {
                    active: {
                        animation: {
                            duration: 220,
                            easing: 'easeOutCubic'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.85)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 6,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce(function(sum, value) { return sum + value; }, 0);
                                const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : '0.0';
                                return ' ' + context.label + ': ' + context.raw + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        function syncChartTheme() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const styles = getComputedStyle(document.documentElement);
            const dataset = moduleChart.data.datasets[0];
            const colors = isDark ? darkColors : lightColors;

            dataset.backgroundColor = colors;
            dataset.borderColor = styles.getPropertyValue('--bg-card').trim();
            moduleChart.options.plugins.tooltip.backgroundColor = isDark ? '#f1f3f5' : 'rgba(0,0,0,0.85)';
            moduleChart.options.plugins.tooltip.titleColor = isDark ? '#212529' : '#ffffff';
            moduleChart.options.plugins.tooltip.bodyColor = isDark ? '#212529' : '#ffffff';
            document.querySelectorAll('#moduleLegend .module-legend-swatch').forEach(function(swatch, index) {
                swatch.style.backgroundColor = colors[index % colors.length];
            });
            moduleChart.update();
        }

        document.getElementById('moduleLegend').addEventListener('click', function(event) {
            const button = event.target.closest('[data-module-index]');
            if (!button) return;

            const index = Number(button.dataset.moduleIndex);
            moduleChart.toggleDataVisibility(index);
            button.setAttribute('aria-pressed', String(moduleChart.getDataVisibility(index)));
            moduleChart.update();
        });

        syncChartTheme();
        new MutationObserver(syncChartTheme).observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });
    });
</script>
<?php endif; ?>

<?php include '../footer.php'; ?>
