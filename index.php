<!-- To run server: "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" -S 0.0.0.0:8000 -->
<?php
include 'db.php';
require_once 'admin/logger.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="eCertificate BPMI Perikanan">
    <meta name="author" content="BPMI Perikanan">
    <title>eCert.BPMI PERIKANAN</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/business-frontpage.css">
    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            background-image: url("images/background.jpg");
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            min-height: 100vh;
        }

        .page-overlay {
            min-height: 100vh;
            padding-top: 70px;
            padding-bottom: 100px;
        }

        .business-header {
            background: rgba(0, 0, 0, 0.55);
            padding: 70px 0;
            color: white;
        }

        .business-header .tagline {
            text-align: center;
            font-weight: bold;
            text-shadow: 2px 2px 5px #000;
        }

        .search-box {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 8px;
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .certificate-panel {
            margin-top: 30px;
            background: rgba(255,255,255,0.97);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .certificate-panel .panel-heading {
            font-size: 18px;
            font-weight: bold;
        }

        .certificate-table th {
            background: #f5f5f5;
            text-align: center;
            vertical-align: middle;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 5px;
            align-items: center;
        }

        .action-buttons .btn {
            width: 100%;
            max-width: 110px;
            margin: 0;
            font-size: 13px;
            padding: 5px 10px;
            white-space: nowrap;
        }

        .no-record {
            padding: 30px;
            text-align: center;
            color: #990000;
            font-style: italic;
        }

        .footer {
            background: rgba(26, 60, 94, 0.95);
            color: #ffffff;
            padding: 15px 20px;
            text-align: center;
            font-size: small;
            line-height: 1.7;
        }

        .footer a {
            color: #ffd700;
            text-decoration: none;
        }

        .footer a:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        .footer .disclaimer {
            font-size: 11px;
            color: #c8d4e0;
            margin-top: 5px;
            display: block;
        }

        /* Admin Button - Floating */
        .admin-btn-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 999;
        }

        .admin-btn-container .btn-admin {
            background: #1a3c5e;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .admin-btn-container .btn-admin:hover {
            background: #0f2a42;
            transform: scale(1.05);
            color: white;
            text-decoration: none;
        }

        .admin-btn-container .btn-admin .glyphicon {
            margin-right: 8px;
        }

        /* Navbar - Match Admin Look */
        .navbar {
            background: #1a3c5e !important;
            border: none !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2) !important;
            min-height: 60px;
        }

        .navbar-brand {
            color: white !important;
        }

        .navbar-nav > li > a {
            color: white !important;
            padding: 20px 15px;
        }

        .navbar-nav > li > a:hover,
        .navbar-nav > li > a:focus {
            background: rgba(255,255,255,0.1) !important;
            color: white !important;
        }

        .navbar-toggle {
            border-color: rgba(255,255,255,0.3);
            margin-top: 12px;
        }

        .navbar-toggle .icon-bar {
            background-color: white;
        }

        /* Search filter bar */
        .search-filter-bar {
            padding: 12px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e1e5eb;
            display: none;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .search-filter-bar.show {
            display: flex;
        }

        .search-filter-bar form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            width: 100%;
        }

        .search-filter-bar .form-control {
            height: 36px;
            font-size: 14px;
        }

        .search-filter-bar .btn {
            height: 36px;
        }

        /* Result count badge */
        .result-count {
            background: rgba(26, 60, 94, 0.1);
            color: #1a3c5e;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .no-ic-message {
            padding: 30px;
            text-align: center;
            color: #666;
            font-size: 16px;
        }

        .no-ic-message .glyphicon {
            font-size: 48px;
            color: #ddd;
            display: block;
            margin-bottom: 15px;
        }

        /* ============================================
           COMPLAINT / ADUAN AWAM SECTION
           Two-column layout: Form (left) + Contact Info (right)
           ============================================ */
        .complaint-section {
            background: linear-gradient(135deg, #1a3c5e 0%, #2a5f7a 100%);
            padding: 50px 0 40px;
            color: white;
        }

        .complaint-box {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            overflow: hidden;
        }

        .complaint-header {
            color: white;
            padding: 25px 30px 20px;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .complaint-header .glyphicon {
            color: #ffd700;
            font-size: 24px;
        }

        .complaint-body {
            padding: 30px;
        }

        /* Form (Left Column) */
        .complaint-box .form-group {
            margin-bottom: 15px;
        }

        .complaint-box .form-control {
            height: 48px;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 4px;
            font-size: 15px;
            color: #333;
            padding: 0 15px;
            transition: all 0.2s;
            box-shadow: none;
        }

        .complaint-box .form-control::placeholder {
            color: #888;
            opacity: 1;
        }

        .complaint-box .form-control:focus {
            background: #ffffff;
            color: #333;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.3);
            outline: none;
        }

        .complaint-box textarea.form-control {
            height: auto;
            min-height: 150px;
            padding: 15px;
            resize: vertical;
            font-family: inherit;
        }

        .btn-submit-complaint {
            background: #ffd700;
            color: #1a3c5e;
            border: none;
            padding: 14px 40px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .btn-submit-complaint:hover {
            background: #ffed4e;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
        }

        /* Contact Info (Right Column) */
        .contact-info {
            padding-left: 30px;
            border-left: 1px solid rgba(255, 255, 255, 0.15);
            height: 100%;
        }

        .contact-info-title {
            color: #ffd700;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 25px;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 22px;
            color: #e0e0e0;
            font-size: 14px;
            line-height: 1.6;
        }

        .contact-item:last-child {
            margin-bottom: 0;
        }

        .contact-icon {
            font-size: 18px;
            color: #ffd700;
            flex-shrink: 0;
            margin-top: 2px;
            width: 20px;
            text-align: center;
        }

        .contact-text {
            color: #e8eef4;
            word-break: break-word;
        }

        .contact-text strong {
            color: #ffffff;
            display: block;
            margin-bottom: 2px;
            font-size: 13px;
            letter-spacing: 0.3px;
        }

        .contact-text a {
            color: #ffd700;
            text-decoration: none;
        }

        .contact-text a:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        /* Alerts */
        .complaint-alert {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .complaint-alert.success {
            background: rgba(40, 167, 69, 0.2);
            color: #b8e6c8;
            border: 1px solid rgba(40, 167, 69, 0.5);
        }

        .complaint-alert.error {
            background: rgba(220, 53, 69, 0.2);
            color: #f5c6cb;
            border: 1px solid rgba(220, 53, 69, 0.5);
        }

        @media (max-width: 768px) {
            .business-header {
                padding: 45px 15px;
            }
            .business-header .tagline {
                font-size: 26px;
            }
            .search-box {
                margin-left: 10px;
                margin-right: 10px;
            }
            .admin-btn-container {
                bottom: 15px;
                right: 15px;
            }
            .admin-btn-container .btn-admin {
                padding: 8px 15px;
                font-size: 12px;
            }
            .search-filter-bar form {
                flex-direction: column;
            }
            .search-filter-bar form > div {
                width: 100%;
            }
            .search-filter-bar .btn {
                width: 100%;
            }
            .action-buttons .btn {
                font-size: 12px;
                padding: 4px 8px;
                max-width: 100px;
            }

            /* Complaint responsive */
            .complaint-section {
                padding: 35px 0 25px;
            }

            .complaint-header {
                font-size: 18px;
                padding: 20px 20px 15px;
            }

            .complaint-body {
                padding: 20px;
            }

            .contact-info {
                padding-left: 0;
                border-left: none;
                border-top: 1px solid rgba(255, 255, 255, 0.15);
                padding-top: 25px;
                margin-top: 25px;
            }

            .complaint-box .form-control {
                height: 45px;
                font-size: 14px;
            }

            .btn-submit-complaint {
                width: 100%;
                padding: 14px 20px;
            }

            /* Public page mobile layout */
            body {
                overflow-x: hidden;
            }
            .page-overlay {
                padding-top: 60px;
                padding-bottom: 80px;
            }
            .navbar-brand {
                font-size: 14px;
                padding-left: 10px;
                padding-right: 8px;
            }
            .navbar-brand img {
                height: 36px !important;
            }
            .business-header {
                padding: 35px 15px;
            }
            .business-header .tagline {
                font-size: 22px;
                line-height: 1.3;
                margin-top: 8px;
            }
            .business-header p {
                font-size: 13px;
            }
            .search-box {
                padding: 16px;
                margin-top: 20px;
            }
            .search-box .input-group {
                display: block;
            }
            .search-box .input-group .form-control,
            .search-box .input-group .input-group-btn,
            .search-box .input-group .input-group-btn .btn {
                display: block;
                width: 100%;
            }
            .search-box .input-group .form-control {
                border-radius: 4px;
            }
            .search-box .input-group .input-group-btn .btn {
                margin-top: 8px;
                border-radius: 4px;
            }
            .certificate-panel {
                margin-top: 20px;
            }
            .certificate-panel .panel-heading {
                padding: 12px 15px;
                font-size: 16px;
            }
            .search-filter-bar {
                padding: 12px;
            }
            .search-filter-bar form > div,
            .search-filter-bar form > button,
            .search-filter-bar form > a {
                width: 100% !important;
            }
            .search-filter-bar .date-range-group {
                display: grid !important;
                grid-template-columns: 1fr;
                gap: 6px !important;
            }
            .search-filter-bar .date-range-group input,
            .search-filter-bar .date-range-group span {
                width: 100% !important;
            }
            .search-filter-bar .date-range-group span {
                display: none;
            }
            .search-filter-bar .btn {
                margin: 0;
            }
            .certificate-table {
                min-width: 680px;
                font-size: 12px;
            }
            .certificate-table th,
            .certificate-table td {
                padding: 8px 6px;
            }
            .action-buttons .btn {
                max-width: 105px;
                min-height: 32px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
            }
            .complaint-section {
                overflow: hidden;
            }
            .complaint-notice {
                align-items: flex-start;
            }
            .admin-btn-container .btn-admin {
                max-width: calc(100vw - 30px);
                white-space: nowrap;
            }
        }
        /* ============================================
        PUBLIC NOTICE
        ============================================ */
        .complaint-notice {
            display: flex;
            gap: 15px;
            background: rgba(255, 215, 0, 0.12);
            border-left: 4px solid #ffd700;
            border-radius: 6px;
            padding: 18px 20px;
            margin-bottom: 25px;
            align-items: flex-start;
        }

        .notice-icon {
            flex-shrink: 0;
            color: #ffd700;
            font-size: 24px;
            line-height: 1;
            margin-top: 2px;
        }

        .notice-content {
            color: #e8eef4;
            font-size: 14px;
            line-height: 1.7;
        }

        .notice-content strong {
            color: #ffffff;
            display: block;
            font-size: 15px;
            margin-bottom: 6px;
        }

        .notice-content strong:not(:first-child) {
            display: inline;
            font-size: 14px;
            color: #ffd700;
            margin-bottom: 0;
        }

        /* Mobile */
        @media (max-width: 768px) {
            .complaint-notice {
                padding: 15px 15px;
                gap: 12px;
            }
            
            .notice-icon {
                font-size: 20px;
            }
            
            .notice-content {
                font-size: 13px;
            }
            
            .notice-content strong {
                font-size: 14px;
            }
        }
    </style>
</head>

<body>

<div class="page-overlay">

    <!-- Navigation -->
    <nav class="navbar navbar-fixed-top">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="index.php" style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                    <img src="images/logo-perikanan.png" alt="Logo" style="height: 45px; width: auto;">
                    <span style="color: white; font-weight: 600;">eCert.BPMI PERIKANAN</span>
                </a>
            </div>
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav navbar-right">
                    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                        <li>
                            <a href="admin/index.php">
                                <span class="glyphicon glyphicon-dashboard"></span> Admin Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="admin/logout.php">
                                <span class="glyphicon glyphicon-log-out"></span> Logout
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <header class="business-header">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <h1 class="tagline">Kursus-Kursus Anjuran Bahagian Pembangunan Modal Insan</h1>
                    <h1 class="tagline" style="color: #ffd700;"> JABATAN PERIKANAN SABAH</h1>
                    <p style="text-align:center;">Sistem Semakan Sijil Kursus</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Search by IC (MANDATORY) -->
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="search-box">
                    <form method="get" action="index.php">
                        <div class="form-group">
                            <label for="nokp">No. Kad Pengenalan</label>
                            <div class="input-group">
                                <input id="nokp" name="nokp" type="text" class="form-control" 
                                       maxlength="12" placeholder="Enter Your Identification Card Number" 
                                       value="<?php echo isset($_GET['nokp']) ? htmlspecialchars($_GET['nokp']) : ''; ?>"
                                       required>
                                <span class="input-group-btn">
                                    <button class="btn btn-info" type="submit" name="btnHantar">
                                        <span class="glyphicon glyphicon-search"></span> Search
                                    </button>
                                </span>
                            </div>
                            <small class="text-muted">Enter your identification card number to view your certificates.</small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Certificate Results -->
    <div class="container">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <div class="certificate-panel panel panel-primary">
                    <div class="panel-heading">
                        <span class="glyphicon glyphicon-certificate"></span>
                        &nbsp; Certificate Records
                        <?php
                        // Only show count if IC was entered
                        if (isset($_GET['btnHantar']) && isset($_GET['nokp']) && !empty($_GET['nokp'])) {
                            $nokp = trim($_GET['nokp']);
                            $count_sql = "SELECT COUNT(*) as total FROM certificates WHERE nokp = ?";
                            $stmt_count = $conn->prepare($count_sql);
                            $stmt_count->bind_param("s", $nokp);
                            $stmt_count->execute();
                            $count_result = $stmt_count->get_result();
                            $total_count = $count_result->fetch_assoc()['total'];
                            $stmt_count->close();
                            ?>
                            <span class="result-count"><?php echo $total_count; ?> records</span>
                            <?php
                        }
                        ?>
                    </div>

                    <?php
                    // Check if IC was entered
                    if (isset($_GET['btnHantar']) && isset($_GET['nokp']) && !empty($_GET['nokp'])) {
                        
                        $nokp = trim($_GET['nokp']);
                        
                        // Build query - ONLY for this specific IC (no JOIN needed)
                        $sql = "SELECT * FROM certificates WHERE nokp = ?";
                        $params = array($nokp);
                        $types = "s";
                        
                        // Optional: Course name filter
                        if (isset($_GET['search_course']) && !empty($_GET['search_course'])) {
                            $search_course = trim($_GET['search_course']);
                            $sql .= " AND course_name LIKE ?";
                            $params[] = '%' . $search_course . '%';
                            $types .= "s";
                        }
                        
                        // Optional: Date range filter
                        if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
                            $date_from = trim($_GET['date_from']);
                            $sql .= " AND course_date >= ?";
                            $params[] = $date_from;
                            $types .= "s";
                        }
                        
                        if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
                            $date_to = trim($_GET['date_to']);
                            $sql .= " AND course_date <= ?";
                            $params[] = $date_to;
                            $types .= "s";
                        }
                        
                        $sql .= " ORDER BY course_date DESC";
                        
                        // Execute query
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            ?>
                            <!-- Search Filter Bar -->
                            <div class="search-filter-bar show">
                                <form method="get" action="index.php">
                                    <input type="hidden" name="nokp" value="<?php echo htmlspecialchars($nokp); ?>">
                                    <input type="hidden" name="btnHantar" value="1">
                                    
                                    <div style="flex: 2; min-width: 160px;">
                                        <input type="text" name="search_course" class="form-control" 
                                               placeholder="🔍 Filter by course..." 
                                               value="<?php echo isset($_GET['search_course']) ? htmlspecialchars($_GET['search_course']) : ''; ?>"
                                               style="height: 36px; font-size: 14px;">
                                    </div>
                                    
                                    <div class="date-range-group" style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
                                        <input type="date" name="date_from" 
                                               value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>"
                                               title="From Date" style="width: 150px; height: 36px; padding: 0 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                        <span>to</span>
                                        <input type="date" name="date_to" 
                                               value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>"
                                               title="To Date" style="width: 150px; height: 36px; padding: 0 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-sm btn-info" style="height: 36px;">
                                        <span class="glyphicon glyphicon-filter"></span> Filter
                                    </button>
                                    
                                    <?php 
                                    $has_filters = (isset($_GET['search_course']) && !empty($_GET['search_course'])) ||
                                                   (isset($_GET['date_from']) && !empty($_GET['date_from'])) ||
                                                   (isset($_GET['date_to']) && !empty($_GET['date_to']));
                                    if ($has_filters): 
                                    ?>
                                        <a href="index.php?nokp=<?php echo urlencode($nokp); ?>&btnHantar=1" class="btn btn-sm btn-default" style="height: 36px;">
                                            <span class="glyphicon glyphicon-remove"></span> Clear
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover certificate-table">
                                    <thead>
                                        <tr>
                                            <th style="text-align: center; width: 60px;">No.</th>
                                            <th>IC No.</th>
                                            <th>Name</th>
                                            <th>Course</th>
                                            <th>Date</th>
                                            <th style="text-align: center; width: 100px;">Type</th>
                                            <th style="text-align: center; width: 160px;">Certificate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $no = 1;
                                    while ($row = $result->fetch_assoc()) {
                                        ?>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;"><?php echo $no; ?></td>
                                            <td style="vertical-align: middle;"><?php echo htmlspecialchars($row['nokp']); ?></td>
                                            <td style="vertical-align: middle;"><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                            <td style="vertical-align: middle;"><?php echo htmlspecialchars($row['course_name']); ?></td>
                                            <td style="vertical-align: middle;"><?php echo date('d/m/Y', strtotime($row['course_date'])); ?></td>
                                            <td style="vertical-align: middle; text-align: center;">
                                                <?php if (isset($row['cert_type']) && $row['cert_type'] == 'physical'): ?>
                                                    <span style="background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; border: 1px solid #ffeeba;">
                                                        <span class="glyphicon glyphicon-file"></span> Physical
                                                    </span>
                                                <?php else: ?>
                                                    <span style="background: #d1ecf1; color: #0c5460; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; border: 1px solid #bee5eb;">
                                                        <span class="glyphicon glyphicon-cloud"></span> E-Cert
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center; vertical-align: middle;">
                                                <div class="action-buttons">
                                                    
                                                    <!-- VIEW — Always available -->
                                                    <a href="view_certificate.php?serialNum=<?php echo urlencode($row['serialNum']); ?>" 
                                                    target="_blank" class="btn btn-info btn-sm">
                                                        <span class="glyphicon glyphicon-eye-open"></span> View
                                                    </a>
                                                    
                                                    <!-- DOWNLOAD — Always available -->
                                                    <a href="download_certificate.php?serialNum=<?php echo urlencode($row['serialNum']); ?>" 
                                                    class="btn btn-success btn-sm">
                                                        <span class="glyphicon glyphicon-download-alt"></span> Download
                                                    </a>
                                                    
                                                    <!-- REQUEST — Only for physical certificates -->
                                                    <?php if (isset($row['cert_type']) && $row['cert_type'] == 'physical'): ?>
                                                        <a href="request_physical.php?serialNum=<?php echo urlencode($row['serialNum']); ?>" 
                                                        class="btn btn-warning btn-sm">
                                                            <span class="glyphicon glyphicon-send"></span> Request
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                        $no++;
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php
                        } else {
                            ?>
                            <div class="no-record">
                                <span class="glyphicon glyphicon-info-sign"></span>
                                <br><br>
                                No certificate records found for this identification card number.
                            </div>
                            <?php
                        }
                        
                        if ($result->num_rows > 0) {
                            log_activity($conn, 'certificate', 'search', 'success', 
                                "Found {$result->num_rows} certificates", $nokp, 
                                ['search_term' => $nokp, 'results' => $result->num_rows]);
                        } else {
                            log_activity($conn, 'certificate', 'search', 'warning', 
                                "No certificates found", $nokp, 
                                ['search_term' => $nokp]);
    }
                        if (isset($stmt)) $stmt->close();
                        
                    } else {
                        // No IC entered - show message
                        ?>
                        <div class="no-ic-message">
                            <span class="glyphicon glyphicon-search"></span>
                            <p>Please enter your <strong>Identification Card Number</strong> above to view your certificates.</p>
                            <p style="font-size: 14px; color: #999; margin-top: 10px;">
                                <span class="glyphicon glyphicon-lock" style="font-size: 14px; display: inline;"></span> 
                                Your data is private and only visible to you.
                            </p>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     COMPLAINT / ADUAN AWAM SECTION
     Two-column layout: Form (left) + Contact Info (right)
     ============================================ -->
<div class="complaint-section">
    <div class="container">
        <div class="complaint-box">
            
            <!-- Section Header -->
            <div class="complaint-header">
                <span class="glyphicon glyphicon-bullhorn"></span>
                Aduan Awam / Public Complaint
            </div>
            
            <div class="complaint-body">
                
                <!-- ============================================
                     PUBLIC NOTICE
                     ============================================ -->
                <div class="complaint-notice">
                    <div class="notice-icon">
                        <span class="glyphicon glyphicon-info-sign"></span>
                    </div>
                    <div class="notice-content">
                        <strong>Having trouble with your certificate?</strong>
                        If you encounter any issues such as a <strong>missing or unavailable certificate</strong>, 
                        a <strong>wrong certificate</strong> linked to your name, or a <strong>certificate that doesn't 
                        belong to you</strong>, please fill in the form below with the details of your problem. 
                        Our admin team will review your complaint and get back to you as soon as possible.
                    </div>
                </div>
                
                <!-- Success / Error Alerts -->
                <?php if (isset($_GET['complaint']) && $_GET['complaint'] == 'success'): ?>
                    <div class="complaint-alert success">
                        <span class="glyphicon glyphicon-ok-circle" style="font-size: 20px;"></span>
                        <span>Thank you! Your complaint has been submitted successfully. We'll respond to you soon.</span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['complaint']) && $_GET['complaint'] == 'error'): ?>
                    <div class="complaint-alert error">
                        <span class="glyphicon glyphicon-exclamation-sign" style="font-size: 20px;"></span>
                        <span>
                            <?php 
                            if (isset($_SESSION['complaint_errors'])) {
                                echo implode('<br>', $_SESSION['complaint_errors']);
                                unset($_SESSION['complaint_errors']);
                            } else {
                                echo 'There was an error submitting your complaint. Please try again.';
                            }
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    
                    <!-- LEFT COLUMN: Complaint Form -->
                    <div class="col-md-7">
                        <form method="POST" action="submit_complaint.php" id="complaintForm">
                            
                            <div class="form-group">
                                <input type="text" name="name" class="form-control" required 
                                       placeholder="Your Full Name">
                            </div>
                            
                            <div class="form-group">
                                <input type="email" name="email" class="form-control" required 
                                       placeholder="Your Email Address">
                            </div>
                            
                            <div class="form-group">
                                <input type="text" name="phone" class="form-control" 
                                       placeholder="Your Phone Number (optional)">
                            </div>
                            
                            <div class="form-group">
                                <input type="text" name="subject" class="form-control" required 
                                       placeholder="Subject (e.g. Missing Certificate)">
                            </div>
                            
                            <div class="form-group">
                                <textarea name="message" class="form-control" rows="5" required 
                                          placeholder="Describe your problem in detail — e.g. IC number, course name, what went wrong..."></textarea>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <button type="submit" class="btn-submit-complaint">
                                    <span class="glyphicon glyphicon-send"></span> Submit Complaint
                                </button>
                            </div>
                            
                        </form>
                    </div>
                    
                    <!-- RIGHT COLUMN: Contact Info -->
                    <div class="col-md-5">
                        <div class="contact-info">
                            
                            <div class="contact-info-title">Get In Touch</div>
                            
                            <div class="contact-item">
                                <span class="glyphicon glyphicon-map-marker contact-icon"></span>
                                <span class="contact-text">
                                    <strong>Address</strong>
                                    4th Floor, Wisma Pertanian Sabah,<br>
                                    Jalan Tasik, 88624 Kota Kinabalu
                                </span>
                            </div>
                            
                            <div class="contact-item">
                                <span class="glyphicon glyphicon-earphone contact-icon"></span>
                                <span class="contact-text">
                                    <strong>Telephone</strong>
                                    +6088-245490<br>
                                    +6088-245569
                                </span>
                            </div>
                            
                            <div class="contact-item">
                                <span class="glyphicon glyphicon-print contact-icon"></span>
                                <span class="contact-text">
                                    <strong>Fax</strong>
                                    +6088-240511
                                </span>
                            </div>
                            
                            <div class="contact-item">
                                <span class="glyphicon glyphicon-envelope contact-icon"></span>
                                <span class="contact-text">
                                    <strong>Email</strong>
                                    <a href="mailto:fish.dept@sabah.gov.my">fish.dept@sabah.gov.my</a>
                                </span>
                            </div>
                            
                        </div>
                    </div>
                    
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     FOOTER
     ============================================ -->
<div class="footer">
    Copyright © 2014 <a href="https://fishdept.sabah.gov.my/">Department of Fisheries Sabah</a>
    <span class="disclaimer">
        DISCLAIMER: The administrator and operator of this website shall not be liable for any loss or damage caused by the usage of any information obtained from this website.
    </span>
</div>

<!-- ============================================
     ADMIN LOGIN BUTTON - FLOATING
     ============================================ -->
<div class="admin-btn-container">
    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
        <a href="admin/index.php" class="btn-admin">
            <span class="glyphicon glyphicon-dashboard"></span> Admin Dashboard
        </a>
    <?php else: ?>
        <a href="admin/login.php" class="btn-admin" id="adminLoginBtn">
            <span class="glyphicon glyphicon-lock"></span> Admin Login
        </a>
    <?php endif; ?>
</div>

<!-- JavaScript -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

<script>
$(document).ready(function() {
    $('#adminLoginBtn').on('click', function(e) {
        $(this).html('<span class="glyphicon glyphicon-refresh glyphicon-spin"></span> Loading...');
    });
});
</script>

</body>
</html>
<?php $conn->close(); ?>
