<?php 
// Only query if connection is still open
$unread = 0;
if (isset($conn) && $conn instanceof mysqli) {
    $res = @$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status = 'new'");
    if ($res) {
        $unread = $res->fetch_assoc()['c'];
    }
}

// Check physical_requests table safely
$pending_reqs = 0;
if (isset($conn) && $conn instanceof mysqli) {
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
            <a class="navbar-brand" href="../index.php" style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                <img src="../images/logo-perikanan.png" alt="Logo" style="height: 45px; width: auto;">
                <span>eCert BPMI</span>
            </a>
        </div>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="nav navbar-nav">
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
                        <?php 
                        $pending_reqs = 0;
                        if (isset($conn) && $conn instanceof mysqli) {
                            $res = @$conn->query("SELECT COUNT(*) as c FROM physical_requests WHERE status = 'pending'");
                            if ($res) $pending_reqs = $res->fetch_assoc()['c'];
                        }
                        if ($pending_reqs > 0): ?>
                            <span class="badge" style="background: #ffc107; color: #856404;"><?php echo $pending_reqs; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li id="nav-report"><a href="report.php">Report</a></li>
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
                    <a href="logout.php">
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
    document.querySelectorAll('.navbar-nav li').forEach(function(item) {
        item.classList.remove('active');
    });
    if (currentPage === 'index.php' || currentPage === '') {
        document.getElementById('nav-dashboard').classList.add('active');
    } else if (currentPage === 'add_certificate.php') {
        document.getElementById('nav-add').classList.add('active');
    } else if (currentPage === 'bulk_add.php') {
        document.getElementById('nav-bulk').classList.add('active');
    } else if (currentPage === 'edit_certificate.php') {
        document.getElementById('nav-dashboard').classList.add('active');
    } else if (currentPage === 'complaints.php') {
        document.getElementById('nav-complaints').classList.add('active');
    } else if (currentPage === 'report.php') {
        document.getElementById('nav-report').classList.add('active');
    } else if (currentPage === 'physical_requests.php') {
        document.getElementById('nav-physical').classList.add('active');
    }
    
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