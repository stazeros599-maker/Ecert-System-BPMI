<?php
$current_role = $_SESSION['admin_role'] ?? 'admin';
$is_developer = ($current_role === 'developer');
$nav_home = 'index.php';
$nav_logo = $is_developer ? '../../images/logo-perikanan.png' : '../images/logo-perikanan.png';
$nav_logout = $is_developer ? '../logout.php' : 'logout.php';

// Only query if connection is still open
$unread = 0;
if (!$is_developer && isset($conn) && $conn instanceof mysqli) {
    $res = @$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status = 'new'");
    if ($res) {
        $unread = $res->fetch_assoc()['c'];
    }
}

// Check physical_requests table safely
$pending_reqs = 0;
if (!$is_developer && isset($conn) && $conn instanceof mysqli) {
    $check = @$conn->query("SHOW TABLES LIKE 'physical_requests'");
    if ($check && $check->num_rows > 0) {
        $res = @$conn->query("SELECT COUNT(*) as c FROM physical_requests WHERE status = 'pending'");
        if ($res) {
            $pending_reqs = $res->fetch_assoc()['c'];
        }
    }
}
?>

<nav class="navbar navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#nav">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="<?php echo $nav_home; ?>" style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                <img src="<?php echo $nav_logo; ?>" alt="Logo" style="height: 45px; width: auto;">
                <span>eCert BPMI<?php echo $is_developer ? ' — DEV' : ''; ?></span>
            </a>
        </div>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="nav navbar-nav">
                <?php if ($is_developer): ?>
                    <li id="nav-dev-dashboard"><a href="<?php echo $nav_home; ?>">Dashboard</a></li>
                    <li id="nav-dev-logs"><a href="logs.php">Activity Logs</a></li>
                <?php else: ?>
                    <li id="nav-dashboard"><a href="index.php">Admin Dashboard</a></li>
                    <li id="nav-add"><a href="add_certificate.php">Add Certificate</a></li>
                    <li id="nav-bulk"><a href="bulk_add.php">Bulk Add</a></li>
                    <li id="nav-complaints">
                        <a href="complaints.php">
                            Complaints
                            <?php if ($unread > 0): ?>
                                <span class="badge" style="background: #dc3545;"><?php echo $unread; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li id="nav-physical">
                        <a href="physical_requests.php">
                            Physical Requests
                            <?php if ($pending_reqs > 0): ?>
                                <span class="badge" style="background: #ffc107; color: #856404;"><?php echo $pending_reqs; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li id="nav-report"><a href="report.php">Report</a></li>
                <?php endif; ?>
            </ul>
            <ul class="nav navbar-nav navbar-right">
                <li>
                    <a class="admin-user" style="color: #ffd700 !important; font-weight: 600;">
                        <span class="glyphicon glyphicon-user"></span> 
                        <?php echo htmlspecialchars(isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin'); ?>
                    </a>
                </li>
                <li>
                    <button type="button" class="theme-toggle-btn" id="themeToggle" title="Toggle Dark/Light Mode">
                        <span class="glyphicon glyphicon-moon" id="themeIcon">🌙</span>
                        <span class="theme-toggle-label" id="themeLabel">Dark</span>
                    </button>
                </li>
                <li>
                    <a href="<?php echo $nav_logout; ?>">
                        <span class="glyphicon glyphicon-log-out"></span> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Highlight active page -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var currentPage = window.location.pathname.split('/').pop();
    var isDeveloperPage = window.location.pathname.indexOf('/developer/') !== -1;
    var activeNavId;

    document.querySelectorAll('.navbar-nav li').forEach(function(item) {
        item.classList.remove('active');
    });

    if (isDeveloperPage) {
        activeNavId = currentPage === 'logs.php' ? 'nav-dev-logs' : 'nav-dev-dashboard';
    } else {
        var activeMap = {
            'index.php': 'nav-dashboard',
            '': 'nav-dashboard',
            'add_certificate.php': 'nav-add',
            'bulk_add.php': 'nav-bulk',
            'edit_certificate.php': 'nav-dashboard',
            'complaints.php': 'nav-complaints',
            'report.php': 'nav-report',
            'physical_requests.php': 'nav-physical'
        };
        activeNavId = activeMap[currentPage];
    }

    var activeNavItem = activeNavId ? document.getElementById(activeNavId) : null;
    if (activeNavItem) activeNavItem.classList.add('active');
    
    // ============================================
    // THEME TOGGLE LOGIC
    // ============================================
    var themeToggle = document.getElementById('themeToggle');
    var themeIcon = document.getElementById('themeIcon');
    var themeLabel = document.getElementById('themeLabel');
    
    function updateToggleUI() {
        var currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        if (currentTheme === 'dark') {
            themeIcon.textContent = '☀️';
            themeLabel.textContent = 'Light';
        } else {
            themeIcon.textContent = '🌙';
            themeLabel.textContent = 'Dark';
        }
    }
    
    // Apply initial UI state
    updateToggleUI();
    
    // Toggle handler
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            var currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            var newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            // Add pulse animation
            themeToggle.classList.add('pulse');
            setTimeout(function() {
                themeToggle.classList.remove('pulse');
            }, 500);
            
            // Change theme
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('ecert-theme', newTheme);
            
            updateToggleUI();
            
            // ============================================
            // AUTO-RELOAD ON REPORT PAGE
            // Charts need to be rebuilt with new theme colors
            // ============================================
            if (window.location.pathname.includes('report.php')) {
                setTimeout(function() { 
                    window.location.reload(); 
                }, 250);
            }
        });
    }
});
</script>