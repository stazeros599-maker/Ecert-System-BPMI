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

// ============================================
// REPORT DATA — Insider vs Public
// ============================================
$sql_insider = "SELECT 
    SUM(CASE WHEN p.insider = 1 THEN 1 ELSE 0 END) AS insider_count,
    SUM(CASE WHEN p.insider = 0 OR p.insider IS NULL THEN 1 ELSE 0 END) AS public_count,
    COUNT(*) AS total
FROM certificates c
LEFT JOIN participant p ON c.nokp = p.icNum";

$stats = $conn->query($sql_insider)->fetch_assoc();
$insider_count = intval($stats['insider_count']);
$public_count = intval($stats['public_count']);
$total = intval($stats['total']);

$insider_pct = $total > 0 ? round(($insider_count / $total) * 100, 1) : 0;
$public_pct  = $total > 0 ? round(($public_count / $total) * 100, 1) : 0;

// ============================================
// REPORT DATA — Certificate format
// ============================================
$sql_certificate_types = "SELECT
    COUNT(CASE WHEN cert_type = 'physical' THEN 1 END) AS physical_count,
    COUNT(CASE WHEN cert_type IS NULL OR cert_type <> 'physical' THEN 1 END) AS e_cert_count
FROM certificates";

$certificate_type_stats = $conn->query($sql_certificate_types)->fetch_assoc();
$physical_cert_count = intval($certificate_type_stats['physical_count']);
$e_cert_count = intval($certificate_type_stats['e_cert_count']);
$certificate_type_total = $physical_cert_count + $e_cert_count;
$physical_cert_pct = $certificate_type_total > 0 ? round(($physical_cert_count / $certificate_type_total) * 100, 1) : 0;
$e_cert_pct = $certificate_type_total > 0 ? round(($e_cert_count / $certificate_type_total) * 100, 1) : 0;

// ============================================
// REPORT DATA — Unique participants
// ============================================
$sql_unique_alt = "SELECT 
    SUM(CASE WHEN insider = 1 THEN 1 ELSE 0 END) AS insider_count,
    SUM(CASE WHEN insider = 0 OR insider IS NULL THEN 1 ELSE 0 END) AS public_count,
    COUNT(*) AS total
FROM participant";

$unique_stats = $conn->query($sql_unique_alt)->fetch_assoc();
$unique_insider = intval($unique_stats['insider_count']);
$unique_public = intval($unique_stats['public_count']);
$unique_total = intval($unique_stats['total']);

$unique_insider_pct = $unique_total > 0 ? round(($unique_insider / $unique_total) * 100, 1) : 0;
$unique_public_pct  = $unique_total > 0 ? round(($unique_public / $unique_total) * 100, 1) : 0;

// ============================================
// REPORT DATA — Courses breakdown
// ============================================
$sql_courses = "SELECT 
    course_name,
    COUNT(*) AS count,
    SUM(CASE WHEN p.insider = 1 THEN 1 ELSE 0 END) AS insider_count,
    SUM(CASE WHEN p.insider = 0 OR p.insider IS NULL THEN 1 ELSE 0 END) AS public_count
FROM certificates c
LEFT JOIN participant p ON c.nokp = p.icNum
GROUP BY course_name
ORDER BY count DESC";

$courses_result = $conn->query($sql_courses);
$courses_data = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses_data[] = $row;
}

// ============================================
// REPORT DATA — Monthly trend
// ============================================
$sql_monthly = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') AS month,
    DATE_FORMAT(created_at, '%b %Y') AS month_label,
    COUNT(*) AS count
FROM certificates
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y')
ORDER BY month ASC";

$monthly_result = $conn->query($sql_monthly);
$monthly_data = [];
while ($row = $monthly_result->fetch_assoc()) {
    $monthly_data[] = $row;
}

// ============================================
// NARRATIVE DATA — Derived insights (print only)
// ============================================
$top_course = !empty($courses_data) ? $courses_data[0] : null;
$avg_certs_per_participant = $unique_total > 0 ? round($total / $unique_total, 2) : 0;

$peak_month = null;
if (!empty($monthly_data)) {
    $peak = $monthly_data[0];
    foreach ($monthly_data as $m) {
        if (intval($m['count']) > intval($peak['count'])) {
            $peak = $m;
        }
    }
    $peak_month = $peak;
}

$most_insider_course = null;
$highest_insider = 0;
foreach ($courses_data as $c) {
    if (intval($c['insider_count']) > $highest_insider) {
        $highest_insider = intval($c['insider_count']);
        $most_insider_course = $c;
    }
}

$page_title = 'Report - eCert BPMI';
include 'header.php';
include 'nav.php';
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    .report-page { padding: 20px 0; }

    .report-card {
        background: var(--bg-card);
        border-radius: 10px;
        box-shadow: 0 2px 10px var(--shadow);
        padding: 25px 30px;
        margin-bottom: 25px;
        border: 1px solid transparent;
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }

    [data-theme="dark"] .report-card {
        border-color: var(--border-color);
    }

    .report-card h3 {
        margin: 0 0 20px 0;
        font-size: 18px;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--border-color);
    }

    .report-card h3 .glyphicon { color: var(--accent); }

    .chart-container {
        position: relative;
        height: 380px;
        margin: 0 auto;
        max-width: 500px;
    }

    .stat-mini-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 15px;
        margin-top: 25px;
    }

    .stat-mini {
        padding: 15px 18px;
        border-radius: 8px;
        background: var(--bg-card-alt);
        border-left: 4px solid var(--accent);
    }

    .stat-mini.insider { border-left-color: #4a90c2; }
    .stat-mini.public  { border-left-color: #8b8f98; }

    .stat-mini .stat-mini-label {
        font-size: 12px;
        color: var(--text-muted);
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }

    .stat-mini .stat-mini-value {
        font-size: 26px;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1;
    }

    .stat-mini .stat-mini-sub {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 6px;
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    .report-table th {
        text-align: left;
        padding: 12px 14px;
        background: var(--bg-table-header);
        color: var(--text-primary);
        font-size: 13px;
        font-weight: 600;
        border-bottom: 2px solid var(--border-color);
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .report-table td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        font-size: 14px;
    }

    .report-table tr:hover td { background: var(--bg-hover); }

    .badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        display: inline-block;
    }

    .badge-insider { background: #d4e9ff; color: #1a3c5e; }
    [data-theme="dark"] .badge-insider { background: #1e3a52; color: #a3c8e8; }

    .badge-public { background: #f0f0f0; color: #666; }
    [data-theme="dark"] .badge-public { background: #2f3238; color: #b8bcc4; }

    .print-btn {
        background: var(--accent);
        color: white;
        border: none;
        padding: 10px 22px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .print-btn:hover {
        background: var(--accent-hover);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(26, 60, 94, 0.3);
    }

    /* ============================================
       PRINT-ONLY NARRATIVE
       ============================================ */
    .print-only {
        display: none;
    }

    @media print {
        .navbar, .print-btn, .no-print { display: none !important; }
        body { padding-top: 0 !important; background: white !important; }
        .report-card { box-shadow: none; border: 1px solid #ddd; page-break-inside: avoid; }
        .chart-container { height: 320px; }

        /* Show narrative blocks only when printing */
        .print-only {
            display: block;
            margin-top: 20px;
            padding: 18px 22px;
            background: #f8f9fa;
            border-left: 4px solid #1a3c5e;
            border-radius: 0 6px 6px 0;
            font-size: 13.5px;
            line-height: 1.7;
            color: #333;
            page-break-inside: avoid;
        }

        .print-only p {
            margin: 0 0 12px 0;
        }

        .print-only p:last-child {
            margin-bottom: 0;
        }

        .print-only strong {
            color: #1a3c5e;
        }

        .print-only ul {
            margin: 10px 0 12px 0;
            padding-left: 22px;
        }

        .print-only ul li {
            margin-bottom: 6px;
        }
    }
</style>

<div class="container report-page">
    <!-- Page Title -->
    <div class="row">
        <div class="col-md-12">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 10px;">
                <h2 style="font-size: 26px; font-weight: 700; color: var(--text-primary); margin: 0;">
                    <span class="glyphicon glyphicon-stats"></span> Participant Report
                </h2>
                <button class="print-btn" onclick="window.print()">
                    <span class="glyphicon glyphicon-print"></span> Print Report
                </button>
            </div>
            <hr style="border-top: 2px solid var(--border-color); margin: 10px 0 25px 0;">
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 25px;">
                Generated on <strong><?php echo date('d F Y, H:i'); ?></strong> — a snapshot of insider vs public participant distribution.
            </p>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row">
        <div class="col-md-12">
            <div class="report-card">
                <h3>
                    <span class="glyphicon glyphicon-list-alt"></span> Overview
                </h3>
                <div class="stat-mini-grid">
                    <div class="stat-mini insider">
                        <div class="stat-mini-label">Total Certificates</div>
                        <div class="stat-mini-value"><?php echo $total; ?></div>
                        <div class="stat-mini-sub">All issued certificates</div>
                    </div>
                    <div class="stat-mini insider">
                        <div class="stat-mini-label">Insider</div>
                        <div class="stat-mini-value"><?php echo $insider_count; ?></div>
                        <div class="stat-mini-sub"><?php echo $insider_pct; ?>% of certificates</div>
                    </div>
                    <div class="stat-mini public">
                        <div class="stat-mini-label">Public</div>
                        <div class="stat-mini-value"><?php echo $public_count; ?></div>
                        <div class="stat-mini-sub"><?php echo $public_pct; ?>% of certificates</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-label">Unique Participants</div>
                        <div class="stat-mini-value"><?php echo $unique_total; ?></div>
                        <div class="stat-mini-sub">Distinct IC numbers</div>
                    </div>
                </div>

                <!-- PRINT-ONLY NARRATIVE -->
                <div class="print-only">
                    <p>
                        <strong>1. Executive Summary</strong>
                    </p>
                    <p>
                        This report presents an analytical overview of participant distribution within the 
                        BPMI Perikanan eCertificate system as of <strong><?php echo date('d F Y'); ?></strong>.
                        The analysis covers <strong><?php echo $total; ?> issued certificates</strong> across 
                        <strong><?php echo count($courses_data); ?> course types</strong>, representing 
                        <strong><?php echo $unique_total; ?> unique participants</strong>.
                    </p>
                    <p>
                        Participants are categorised into two groups: <strong>Insiders</strong> — referring to staff of 
                        the Department of Fisheries Sabah (Perikanan Staff) — and <strong>Public</strong> — referring to 
                        external participants not affiliated with the department. The system currently records 
                        <strong><?php echo $insider_count; ?> insider certificates (<?php echo $insider_pct; ?>%)</strong> 
                        and <strong><?php echo $public_count; ?> public certificates (<?php echo $public_pct; ?>%)</strong>. 
                        On average, each participant has obtained <strong><?php echo $avg_certs_per_participant; ?> certificates</strong>.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pie Chart: Insider vs Public -->
    <div class="row">
        <div class="col-md-6">
            <div class="report-card">
                <h3>
                    <span class="glyphicon glyphicon-certificate"></span> Certificates by Type
                </h3>
                <div class="chart-container">
                    <canvas id="certPieChart"></canvas>
                </div>

                <!-- PRINT-ONLY NARRATIVE -->
                <div class="print-only">
                    <p><strong>2.1 Certificates by Format</strong></p>
                    <p>
                        Of the <strong><?php echo $certificate_type_total; ?> total certificates</strong> issued,
                        <strong><?php echo $physical_cert_count; ?> (<?php echo $physical_cert_pct; ?>%)</strong>
                        are physical certificates and
                        <strong><?php echo $e_cert_count; ?> (<?php echo $e_cert_pct; ?>%)</strong>
                        are e-certificates.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="report-card">
                <h3>
                    <span class="glyphicon glyphicon-user"></span> Unique Participants by Type
                </h3>
                <div class="chart-container">
                    <canvas id="participantPieChart"></canvas>
                </div>

                <!-- PRINT-ONLY NARRATIVE -->
                <div class="print-only">
                    <p><strong>2.2 Unique Participants by Type</strong></p>
                    <p>
                        When analysed by unique individuals rather than total certificate issuances, the distribution 
                        shifts. Out of <strong><?php echo $unique_total; ?> unique participants</strong>, 
                        <strong><?php echo $unique_insider; ?> (<?php echo $unique_insider_pct; ?>%)</strong> 
                        are insiders and <strong><?php echo $unique_public; ?> 
                        (<?php echo $unique_public_pct; ?>%)</strong> are public. 
                        Notably, insider staff tend to obtain 
                        <strong><?php echo $unique_insider > 0 ? round($insider_count / $unique_insider, 2) : 0; ?> certificates each</strong>, 
                        whereas public participants average 
                        <strong><?php echo $unique_public > 0 ? round($public_count / $unique_public, 2) : 0; ?> certificates each</strong>. 
                        This suggests that internal staff consistently pursue continuous professional development.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bar Chart: Monthly Trend -->
    <div class="row">
        <div class="col-md-12">
            <div class="report-card">
                <h3>
                    <span class="glyphicon glyphicon-calendar"></span> Monthly Certificates (Last 6 Months)
                </h3>
                <div style="position: relative; height: 300px;">
                    <canvas id="monthlyChart"></canvas>
                </div>

                <!-- PRINT-ONLY NARRATIVE -->
                <div class="print-only">
                    <p><strong>3. Monthly Issuance Trend</strong></p>
                    <p>
                        The bar chart above illustrates the monthly certificate issuance volume over the 
                        past six months. This trend provides insight into the department's training activity 
                        and participant engagement over time.
                        <?php if ($peak_month): ?>
                            The highest issuance was recorded in 
                            <strong><?php echo htmlspecialchars($peak_month['month_label']); ?></strong> 
                            with <strong><?php echo intval($peak_month['count']); ?> certificates</strong>, 
                            indicating a period of heightened training activity.
                        <?php endif; ?>
                        The general trend suggests consistent engagement from participants across multiple 
                        months, with periodic peaks corresponding to scheduled departmental programmes.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Courses Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="report-card">
                <h3>
                    <span class="glyphicon glyphicon-education"></span> Breakdown by Course
                </h3>
                <?php if (count($courses_data) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th style="text-align: right;">Total</th>
                                    <th style="text-align: right;">Insider</th>
                                    <th style="text-align: right;">Public</th>
                                    <th>Composition</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses_data as $course): 
                                    $c_total = intval($course['count']);
                                    $c_insider = intval($course['insider_count']);
                                    $c_public = intval($course['public_count']);
                                    $c_insider_pct = $c_total > 0 ? ($c_insider / $c_total) * 100 : 0;
                                    $c_public_pct = $c_total > 0 ? ($c_public / $c_total) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($course['course_name']); ?></strong></td>
                                        <td style="text-align: right; font-weight: 700;"><?php echo $c_total; ?></td>
                                        <td style="text-align: right;">
                                            <span class="badge badge-insider"><?php echo $c_insider; ?></span>
                                        </td>
                                        <td style="text-align: right;">
                                            <span class="badge badge-public"><?php echo $c_public; ?></span>
                                        </td>
                                        <td style="min-width: 180px;">
                                            <div style="display: flex; height: 18px; border-radius: 4px; overflow: hidden; background: var(--bg-card-alt);">
                                                <div style="width: <?php echo $c_insider_pct; ?>%; background: #4a90c2;" title="Insider: <?php echo round($c_insider_pct, 1); ?>%"></div>
                                                <div style="width: <?php echo $c_public_pct; ?>%; background: #8b8f98;" title="Public: <?php echo round($c_public_pct, 1); ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PRINT-ONLY NARRATIVE -->
                    <div class="print-only">
                        <p><strong>4. Course Analysis</strong></p>
                        <p>
                            The table above presents a detailed breakdown of certificate issuance per course, 
                            categorised by participant type.
                            <?php if ($top_course): ?>
                                The most popular course is 
                                <strong>"<?php echo htmlspecialchars($top_course['course_name']); ?>"</strong> 
                                with <strong><?php echo intval($top_course['count']); ?> certificates</strong>, 
                                representing <strong><?php echo round((intval($top_course['count']) / $total) * 100, 1); ?>%</strong> 
                                of all issuances.
                            <?php endif; ?>
                            <?php if ($most_insider_course): ?>
                                The course with the highest insider participation is 
                                <strong>"<?php echo htmlspecialchars($most_insider_course['course_name']); ?>"</strong> 
                                with <strong><?php echo $highest_insider; ?> insider staff</strong> having completed it, 
                                indicating the course's particular relevance to departmental operations.
                            <?php endif; ?>
                        </p>

                        <p><strong>5. Conclusion & Recommendations</strong></p>
                        <p>
                            Based on the analysis presented in this report, the following conclusions can be drawn:
                        </p>
                        <ul>
                            <li>
                                The BPMI Perikanan eCertificate system has issued 
                                <strong><?php echo $total; ?> certificates</strong> to 
                                <strong><?php echo $unique_total; ?> unique participants</strong> across 
                                <strong><?php echo count($courses_data); ?> course types</strong>.
                            </li>
                            <li>
                                Public participation forms the larger share of issuances 
                                (<strong><?php echo $public_pct; ?>%</strong>), indicating strong engagement 
                                from external stakeholders and the wider fishing community.
                            </li>
                            <li>
                                Insider staff demonstrate a higher per-person attendance rate 
                                (averaging <strong><?php echo $unique_insider > 0 ? round($insider_count / $unique_insider, 2) : 0; ?> 
                                certificates</strong> each), suggesting ongoing professional development within the department.
                            </li>
                            <li>
                                Training activity has been consistent across recent months, 
                                with the peak recorded in 
                                <strong><?php echo $peak_month ? htmlspecialchars($peak_month['month_label']) : 'N/A'; ?></strong>.
                            </li>
                        </ul>
                        <p>
                            <strong>Recommendations:</strong>
                        </p>
                        <ul>
                            <li>Continue promoting courses to the public to maintain community outreach.</li>
                            <li>Consider expanding high-demand courses identified in the analysis.</li>
                            <li>Encourage insider staff to enrol in a wider variety of courses for skill diversification.</li>
                            <li>Monitor monthly trends to plan training resources effectively during peak periods.</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 30px;">
                        No course data available.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Print footer -->
    <div class="print-only" style="text-align: center; border-top: 1px solid #ddd; margin-top: 20px; padding-top: 15px; background: none; border-left: none; border-radius: 0;">
        <p style="margin: 0; font-size: 12px; color: #888;">
            — End of Report —<br>
            This report was automatically generated by the eCert BPMI System on 
            <strong><?php echo date('d F Y, H:i A'); ?></strong>
        </p>
    </div>
</div>

<!-- ============================================
     CHART.JS CONFIGURATION
     ============================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // THEME DETECTION
    // ============================================
    var currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    var isDark = currentTheme === 'dark';
    
    // Text colors
    var legendTextColor = isDark ? '#ffffff' : '#212529';
    var tickTextColor = isDark ? '#e8eaed' : '#495057';
    var gridColor = isDark ? '#34373e' : '#e1e5eb';
    var tooltipBg = isDark ? 'rgba(30, 30, 30, 0.95)' : 'rgba(0,0,0,0.85)';
    
    console.log('Report charts loading — theme:', currentTheme, '| isDark:', isDark);

    // ============================================
    // CHART 1: Certificate format pie
    // ============================================
    var ctx1 = document.getElementById('certPieChart').getContext('2d');
    new Chart(ctx1, {
        type: 'pie',
        data: {
            labels: ['Physical Certificate', 'E-Certificate'],
            datasets: [{
                data: [<?php echo $physical_cert_count; ?>, <?php echo $e_cert_count; ?>],
                backgroundColor: ['rgba(255, 193, 7, 0.85)', 'rgba(23, 162, 184, 0.85)'],
                borderColor: ['#ffc107', '#17a2b8'],
                borderWidth: 2,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: legendTextColor,
                        font: { size: 13, weight: '400' },
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: tooltipBg,
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    padding: 12,
                    cornerRadius: 6,
                    callbacks: {
                        label: function(context) {
                            var total = context.dataset.data.reduce((a, b) => a + b, 0);
                            var value = context.parsed;
                            var pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return '  ' + context.label + ': ' + value + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });

    // ============================================
    // CHART 2: Participants Doughnut
    // ============================================
    var ctx2 = document.getElementById('participantPieChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['Insider (Perikanan Staff)', 'Public'],
            datasets: [{
                data: [<?php echo $unique_insider; ?>, <?php echo $unique_public; ?>],
                backgroundColor: ['rgba(74, 144, 194, 0.85)', 'rgba(139, 143, 152, 0.75)'],
                borderColor: ['#4a90c2', '#8b8f98'],
                borderWidth: 2,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '55%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: legendTextColor,
                        font: { size: 13, weight: '400' },
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: tooltipBg,
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    padding: 12,
                    cornerRadius: 6,
                    callbacks: {
                        label: function(context) {
                            var total = context.dataset.data.reduce((a, b) => a + b, 0);
                            var value = context.parsed;
                            var pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return '  ' + context.label + ': ' + value + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });

    // ============================================
    // CHART 3: Monthly Bar
    // ============================================
    var ctx3 = document.getElementById('monthlyChart').getContext('2d');
    new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($monthly_data, 'month_label')); ?>,
            datasets: [{
                label: 'Certificates Added',
                data: <?php echo json_encode(array_map('intval', array_column($monthly_data, 'count'))); ?>,
                backgroundColor: 'rgba(74, 144, 194, 0.7)',
                borderColor: '#4a90c2',
                borderWidth: 2,
                borderRadius: 6,
                hoverBackgroundColor: 'rgba(74, 144, 194, 0.95)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: tooltipBg,
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    padding: 12,
                    cornerRadius: 6
                }
            },
            scales: {
                x: {
                    ticks: { color: tickTextColor, font: { size: 12 } },
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: { 
                        color: tickTextColor, 
                        font: { size: 12 },
                        precision: 0
                    },
                    grid: { color: gridColor }
                }
            }
        }
    });
});
</script>

<?php include 'footer.php'; ?>
<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close(); 
}
?>