<?php
// admin/header.php - Shared header for all admin pages
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Admin - eCert BPMI'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    
    <!-- Apply saved theme BEFORE render to avoid flash -->
    <script>
        (function() {
            var theme = localStorage.getItem('ecert-theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    
    <!-- Custom CSS -->
    <style>
        /* ============================================
           THEME VARIABLES — LIGHT MODE
           ============================================ */
        :root {
            --bg-body: #f0f2f5;
            --bg-card: #ffffff;
            --bg-card-alt: #f7f9fc;
            --bg-input: #ffffff;
            --bg-hover: #f8f9fa;
            --bg-navbar: #1a3c5e;
            --bg-panel-head: #1a3c5e;
            --bg-table-header: #f7f9fc;
            --bg-toolbar: #f0f2f5;
            
            --text-primary: #212529;
            --text-secondary: #495057;
            --text-muted: #6c757d;
            --text-invert: #ffffff;
            
            --border-color: #dee2e6;
            --border-table: #dee2e6;
            --border-strong: #ced4da;
            
            --shadow: rgba(0,0,0,0.08);
            --accent: #1a3c5e;
            --accent-hover: #0f2a42;
            --gold: #ffd700;
        }
        
        /* ============================================
           THEME VARIABLES — DARK MODE (IMPROVED)
           ============================================ */
        [data-theme="dark"] {
            /* Warmer, softer darks */
            --bg-body: #18191d;              /* Deep charcoal - easy on eyes */
            --bg-card: #212328;              /* Card sits 8% lighter than body */
            --bg-card-alt: #282a30;          /* Inner alt panels */
            --bg-input: #2a2c33;             /* Input fields */
            --bg-hover: #2f3238;             /* Hover state */
            --bg-navbar: #16181c;            /* Navbar darker than cards */
            --bg-panel-head: #262a31;        /* Panel headers */
            --bg-table-header: #262a31;      /* Table headers */
            --bg-toolbar: #262a31;           /* Toolbars */
            
            /* Text — WCAG AA compliant */
            --text-primary: #e8eaed;         /* 14.5:1 on card bg — excellent */
            --text-secondary: #b8bcc4;       /* 7.2:1 — good */
            --text-muted: #8b8f98;           /* 4.6:1 — meets AA */
            --text-invert: #ffffff;
            
            /* Borders — subtle but visible */
            --border-color: #34373e;
            --border-table: #34373e;
            --border-strong: #484c55;
            
            --shadow: rgba(0,0,0,0.5);
            --accent: #4a90c2;               /* Lighter blue for dark mode */
            --accent-hover: #5da3d4;
            --gold: #ffd24a;                 /* Slightly warmer gold */
        }
        
        /* ============================================
           BASE
           ============================================ */
        * {
            box-sizing: border-box;
        }
        
        body {
            background: var(--bg-body);
            padding-top: 70px;
            font-family: 'Segoe UI', Arial, sans-serif;
            overflow-x: hidden;
            color: var(--text-primary);
            transition: background 0.25s ease, color 0.25s ease;
        }
        
        /* ============================================
           NAVIGATION
           ============================================ */
        .navbar {
            background: var(--bg-navbar) !important;
            border: none;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 10px var(--shadow);
            min-height: 60px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            width: 100%;
            transition: background 0.25s ease;
        }
        
        .navbar-brand {
            color: white !important;
            font-weight: 600;
            font-size: 18px;
            padding: 18px 15px;
        }
        
        .navbar-nav > li > a {
            color: rgba(255,255,255,0.85) !important;
            padding: 18px 15px;
            transition: background 0.2s, color 0.2s;
        }
        
        .navbar-nav > li > a:hover {
            background: rgba(255,255,255,0.08) !important;
            color: white !important;
        }
        
        .navbar-nav > li.active > a {
            background: rgba(255,255,255,0.12) !important;
            color: white !important;
            border-bottom: 3px solid var(--gold);
        }
        
        .navbar .admin-user {
            color: var(--gold) !important;
            font-weight: 600;
        }
        
        .navbar-toggle {
            border-color: rgba(255,255,255,0.3);
            margin-top: 12px;
        }
        
        .navbar-toggle .icon-bar {
            background-color: white;
        }
        
        /* ============================================
           THEME TOGGLE BUTTON
           ============================================ */
        .theme-toggle-btn {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            color: white;
            padding: 8px 14px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            margin-top: 14px;
            margin-left: 10px;
        }
        
        .theme-toggle-btn:hover {
            background: rgba(255,255,255,0.18);
            transform: scale(1.05);
        }
        
        .theme-toggle-label {
            font-size: 13px;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .theme-toggle-btn {
                margin-top: 8px;
                margin-left: 15px;
                margin-bottom: 10px;
            }
            .theme-toggle-label {
                display: none;
            }
        }
        
        /* ============================================
           CARDS — override inline "background: white"
           ============================================ */
        [data-theme="dark"] div[style*="background: white"],
        [data-theme="dark"] div[style*="background:white"] {
            background: var(--bg-card) !important;
            color: var(--text-primary) !important;
        }
        
        /* ============================================
           INPUTS
           ============================================ */
        [data-theme="dark"] .form-control {
            background: var(--bg-input) !important;
            color: var(--text-primary) !important;
            border: 1px solid var(--border-color) !important;
        }
        
        [data-theme="dark"] .form-control:focus {
            background: var(--bg-input) !important;
            color: var(--text-primary) !important;
            border-color: var(--accent) !important;
            box-shadow: 0 0 0 3px rgba(74, 144, 194, 0.2) !important;
        }
        
        [data-theme="dark"] .form-control::placeholder {
            color: var(--text-muted) !important;
            opacity: 1;
        }
        
        /* Input disabled look */
        [data-theme="dark"] .form-control[disabled] {
            background: var(--bg-card-alt) !important;
            color: var(--text-muted) !important;
        }
        
        /* ============================================
           TEXT / HEADINGS
           ============================================ */
        [data-theme="dark"] h1,
        [data-theme="dark"] h2,
        [data-theme="dark"] h3,
        [data-theme="dark"] h4,
        [data-theme="dark"] h5,
        [data-theme="dark"] h6 {
            color: var(--text-primary) !important;
        }
        
        [data-theme="dark"] label {
            color: var(--text-secondary) !important;
        }
        
        [data-theme="dark"] p {
            color: var(--text-primary);
        }
        
        [data-theme="dark"] hr {
            border-color: var(--border-color) !important;
            opacity: 0.5;
        }
        
        /* Small/muted text */
        [data-theme="dark"] small,
        [data-theme="dark"] .text-muted {
            color: var(--text-muted) !important;
        }
        
        /* Inline color overrides */
        [data-theme="dark"] span[style*="color: #888"],
        [data-theme="dark"] span[style*="color:#888"],
        [data-theme="dark"] span[style*="color: #999"],
        [data-theme="dark"] span[style*="color:#999"],
        [data-theme="dark"] div[style*="color: #888"],
        [data-theme="dark"] div[style*="color:#888"] {
            color: var(--text-muted) !important;
        }
        
        [data-theme="dark"] span[style*="color: #666"],
        [data-theme="dark"] span[style*="color:#666"],
        [data-theme="dark"] div[style*="color: #666"],
        [data-theme="dark"] div[style*="color:#666"] {
            color: var(--text-secondary) !important;
        }
        
        [data-theme="dark"] span[style*="color: #555"],
        [data-theme="dark"] span[style*="color:#555"],
        [data-theme="dark"] label[style*="color: #555"],
        [data-theme="dark"] label[style*="color:#555"] {
            color: var(--text-secondary) !important;
        }
        
        [data-theme="dark"] div[style*="color: #333"],
        [data-theme="dark"] div[style*="color:#333"],
        [data-theme="dark"] span[style*="color: #333"],
        [data-theme="dark"] span[style*="color:#333"] {
            color: var(--text-primary) !important;
        }
        
        /* ============================================
           TABLES
           ============================================ */
        [data-theme="dark"] .table {
            color: var(--text-primary) !important;
            background: transparent;
        }
        
        [data-theme="dark"] .table > thead > tr > th {
            background: var(--bg-table-header) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-table) !important;
            font-weight: 600;
        }
        
        [data-theme="dark"] .table > tbody > tr > td {
            background: var(--bg-card) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-table) !important;
        }
        
        [data-theme="dark"] .table-bordered,
        [data-theme="dark"] .table-bordered > thead > tr > th,
        [data-theme="dark"] .table-bordered > tbody > tr > td {
            border-color: var(--border-table) !important;
        }
        
        [data-theme="dark"] .table-hover > tbody > tr:hover > td {
            background: var(--bg-hover) !important;
        }
        
        [data-theme="dark"] .table strong {
            color: var(--text-primary);
        }
        
        /* ============================================
           PANELS
           ============================================ */
        [data-theme="dark"] .panel {
            background: var(--bg-card);
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .panel-default {
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .panel-body {
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .panel-heading .badge {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        /* ============================================
           ALERTS — soft, readable
           ============================================ */
        [data-theme="dark"] .alert-success {
            background: #1a3a24;
            color: #a3d9a5;
            border-color: #2d5a3a;
        }
        
        [data-theme="dark"] .alert-danger {
            background: #3a1a1e;
            color: #f5a3ab;
            border-color: #5a2d33;
        }
        
        [data-theme="dark"] .alert-info {
            background: #1a2e3a;
            color: #a3d5ee;
            border-color: #2d4a5a;
        }
        
        [data-theme="dark"] .alert-warning {
            background: #3a2e1a;
            color: #f5d99a;
            border-color: #5a4a2d;
        }
        
        /* ============================================
           BUTTONS
           ============================================ */
        [data-theme="dark"] .btn-default {
            background: var(--bg-card-alt);
            color: var(--text-primary);
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .btn-default:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
            border-color: var(--border-strong);
        }
        
        /* ============================================
           MODALS
           ============================================ */
        [data-theme="dark"] .modal-content {
            background: var(--bg-card);
            color: var(--text-primary);
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .modal-header {
            background: var(--bg-panel-head) !important;
            color: white;
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .modal-body {
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .modal-footer {
            background: var(--bg-card);
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .close {
            color: white;
            opacity: 0.8;
        }
        
        [data-theme="dark"] .close:hover {
            color: white;
            opacity: 1;
        }
        
        /* ============================================
           SPECIFIC INLINE OVERRIDES
           ============================================ */
        /* Light backgrounds -> dark alt */
        [data-theme="dark"] div[style*="background: #f8f9fa"],
        [data-theme="dark"] div[style*="background:#f8f9fa"],
        [data-theme="dark"] div[style*="background: #f0f2f5"],
        [data-theme="dark"] div[style*="background:#f0f2f5"],
        [data-theme="dark"] div[style*="background: #f7f9fc"],
        [data-theme="dark"] div[style*="background:#f7f9fc"] {
            background: var(--bg-card-alt) !important;
        }
        
        /* Warning boxes */
        [data-theme="dark"] div[style*="background: #fff3cd"],
        [data-theme="dark"] div[style*="background:#fff3cd"] {
            background: #3a2e1a !important;
            color: #f5d99a !important;
        }
        
        [data-theme="dark"] div[style*="background: #f8d7da"],
        [data-theme="dark"] div[style*="background:#f8d7da"] {
            background: #3a1a1e !important;
            color: #f5a3ab !important;
        }
        
        [data-theme="dark"] div[style*="background: #e8f0fe"],
        [data-theme="dark"] div[style*="background:#e8f0fe"] {
            background: #1a2e3a !important;
            color: #a3d5ee !important;
        }
        
        /* Borders */
        [data-theme="dark"] *[style*="border-top: 2px solid #e1e5eb"],
        [data-theme="dark"] *[style*="border: 1px solid #e1e5eb"],
        [data-theme="dark"] *[style*="border: 2px solid #e1e5eb"],
        [data-theme="dark"] *[style*="border-bottom: 2px solid #1a3c5e"] {
            border-color: var(--border-color) !important;
        }
        
        /* Row color overrides — soften for dark mode */
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
        
        /* ============================================
           CONTAINER
           ============================================ */
        .container {
            max-width: 1200px;
            padding: 0 15px;
        }
        
        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 768px) {
            body {
                padding-top: 60px;
            }
            
            .navbar-brand {
                font-size: 16px;
                padding: 15px 10px;
            }
            
            .navbar-nav > li > a {
                padding: 10px 15px;
            }
            
            .navbar-nav > li.active > a {
                border-bottom: none;
                background: rgba(255,255,255,0.15) !important;
            }
        }
        
        @media (max-width: 480px) {
            .navbar-brand {
                font-size: 14px;
                padding: 12px 8px;
            }
            
            .navbar-nav > li > a {
                padding: 8px 12px;
                font-size: 13px;
            }
        }
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
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.25s ease;
            border: 1px solid transparent;
        }

        [data-theme="dark"] .stat-card {
            border-color: var(--border-color);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 18px var(--shadow);
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
            color: var(--accent);
            line-height: 1.2;
        }

        [data-theme="dark"] .stat-number {
            color: var(--text-primary);
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 13px;
            margin-top: 6px;
            font-weight: 500;
        }

        /* ============================================
        ICON SWITCHING — Light vs Dark Mode
        ============================================ */

        /* Default (light mode): show light icon, hide dark icon */
        .icon-light {
            display: block;
        }

        .icon-dark {
            display: none !important;
        }

        /* Dark mode: show dark icon, hide light icon */
        [data-theme="dark"] .icon-light {
            display: none !important;
        }

        [data-theme="dark"] .icon-dark {
            display: block !important;
        }

        /* ============================================
        RESPONSIVE
        ============================================ */
        @media (max-width: 768px) {
            .stat-card {
                min-height: 120px;
                padding: 15px 10px;
            }
            .stat-icon {
                width: 40px;
                height: 40px;
            }
            .stat-number {
                font-size: 26px;
            }
            .stat-label {
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .stat-card {
                min-height: 100px;
                padding: 12px 8px;
            }
            .stat-icon {
                width: 34px;
                height: 34px;
                margin-bottom: 6px;
            }
            .stat-number {
                font-size: 22px;
            }
            .stat-label {
                font-size: 11px;
            }
        }
        /* ============================================
        SMOOTH THEME TRANSITIONS
        ============================================ */
        html, body {
            transition: background-color 0.4s ease, color 0.4s ease;
        }

        .navbar,
        .stat-card,
        .panel,
        .panel-heading,
        .panel-body,
        .modal-content,
        .form-control,
        .table > tbody > tr > td,
        .table > thead > tr > th,
        .btn,
        .alert,
        hr,
        .cert-row td,
        [style*="background: white"],
        [style*="background: #f"] {
            transition: background-color 0.4s ease,
                        color 0.4s ease,
                        border-color 0.4s ease,
                        box-shadow 0.4s ease;
        }

        /* Icon cross-fade */
        .icon-light,
        .icon-dark {
            transition: opacity 0.3s ease;
        }

        /* ============================================
        ANIMATED TOGGLE BUTTON
        ============================================ */
        .theme-toggle-btn {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            color: white;
            padding: 8px 14px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 14px;
            margin-left: 10px;
        }

        .theme-toggle-btn:hover {
            background: rgba(255,255,255,0.18);
            transform: scale(1.05);
        }

        .theme-toggle-btn:active {
            transform: scale(0.95);
        }

        .theme-toggle-btn #themeIcon {
            display: inline-block;
            transition: transform 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }

        [data-theme="dark"] .theme-toggle-btn #themeIcon {
            transform: rotate(360deg);
        }

        /* ============================================
        PULSE ON TOGGLE
        ============================================ */
        @keyframes togglePulse {
            0%   { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.5); }
            50%  { transform: scale(1.15); box-shadow: 0 0 0 8px rgba(255, 215, 0, 0.15); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 215, 0, 0); }
        }

        .theme-toggle-btn.pulse {
            animation: togglePulse 0.5s ease;
        }
    </style>
</head>
<body>