<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include '../db.php';

$admin_id = $_SESSION['admin_id'];
include 'nav_stack.php';
push_nav_stack();

$message = '';
$message_type = '';

// ============================================
// HANDLE BULK SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_submit'])) {
    $course_name = trim($_POST['course_name']);
    $course_date = $_POST['course_date'];
    $cert_type = in_array($_POST['cert_type'] ?? 'e-cert', ['e-cert', 'physical']) 
        ? $_POST['cert_type'] : 'e-cert';
    
    $participants = isset($_POST['participants']) ? $_POST['participants'] : [];
    $uploaded_files = isset($_FILES['cert_files']) ? $_FILES['cert_files'] : null;
    
    $errors = [];
    $success_count = 0;
    $error_rows = [];
    
    // Validate course details
    if (empty($course_name)) $errors[] = 'Course name is required.';
    if (empty($course_date)) $errors[] = 'Course date is required.';
    if (empty($participants)) $errors[] = 'At least one participant is required.';
    
    if (empty($errors)) {
        // Ensure certificates folder exists
        if (!is_dir('../certificates')) {
            mkdir('../certificates', 0777, true);
        }
        
        // Process each participant
        foreach ($participants as $i => $p) {
            $serialNum = trim($p['serialNum'] ?? '');
            $nokp = trim($p['nokp'] ?? '');
            $name = trim($p['name'] ?? '');
            $insider = isset($p['insider']) ? intval($p['insider']) : 0;
            
            // Row-level validation
            $row_errors = [];
            if (empty($serialNum)) $row_errors[] = 'Serial required';
            if (strlen($serialNum) > 10) $row_errors[] = 'Serial too long';
            if (empty($nokp) || strlen($nokp) != 12 || !ctype_digit($nokp)) $row_errors[] = 'IC invalid';
            if (empty($name)) $row_errors[] = 'Name required';
            
            // Check serial uniqueness (both in DB and in this batch)
            if (!empty($serialNum)) {
                $check_sn = $conn->prepare("SELECT serialNum FROM certificates WHERE serialNum = ?");
                $check_sn->bind_param("s", $serialNum);
                $check_sn->execute();
                if ($check_sn->get_result()->num_rows > 0) {
                    $row_errors[] = 'Serial already exists';
                }
                $check_sn->close();
            }
            
            // Handle file upload for this row
            $cert_file = '';
            if ($uploaded_files && isset($uploaded_files['name'][$i]) 
                && $uploaded_files['error'][$i] == 0 && !empty($uploaded_files['name'][$i])) {
                
                $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
                $ext = strtolower(pathinfo($uploaded_files['name'][$i], PATHINFO_EXTENSION));
                $max_size = 5 * 1024 * 1024;
                
                if ($uploaded_files['size'][$i] > $max_size) {
                    $row_errors[] = 'File too large';
                } elseif (in_array($ext, $allowed)) {
                    $cert_file = 'cert_' . time() . '_' . $i . '_' . rand(1000, 9999) . '.' . $ext;
                    $upload_path = '../certificates/' . $cert_file;
                    if (!move_uploaded_file($uploaded_files['tmp_name'][$i], $upload_path)) {
                        $row_errors[] = 'Upload failed';
                        $cert_file = '';
                    }
                } else {
                    $row_errors[] = 'File type invalid';
                }
            } else {
                $row_errors[] = 'Certificate file required';
            }
            
            // If row has errors, record and skip
            if (!empty($row_errors)) {
                $error_rows[] = 'Row ' . ($i + 1) . ' (' . $serialNum . '): ' . implode(', ', $row_errors);
                continue;
            }
            
            // Insert/update participant
            $check = $conn->prepare("SELECT icNum FROM participant WHERE icNum = ?");
            $check->bind_param("s", $nokp);
            $check->execute();
            $check_result = $check->get_result();
            
            if ($check_result->num_rows == 0) {
                $stmt_p = $conn->prepare("INSERT INTO participant (icNum, fullName, insider) VALUES (?, ?, ?)");
                $stmt_p->bind_param("ssi", $nokp, $name, $insider);
                $stmt_p->execute();
                $stmt_p->close();
            } else {
                $stmt_p = $conn->prepare("UPDATE participant SET fullName = ?, insider = ? WHERE icNum = ?");
                $stmt_p->bind_param("sis", $name, $insider, $nokp);
                $stmt_p->execute();
                $stmt_p->close();
            }
            $check->close();
            
            // Insert certificate
            $stmt = $conn->prepare("INSERT INTO certificates (serialNum, admin_id, nokp, name, course_name, course_date, certificate_file, cert_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissssss", $serialNum, $admin_id, $nokp, $name, $course_name, $course_date, $cert_file, $cert_type);
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_rows[] = 'Row ' . ($i + 1) . ': ' . $stmt->error;
                // Delete the uploaded file since insert failed
                if ($cert_file && file_exists('../certificates/' . $cert_file)) {
                    @unlink('../certificates/' . $cert_file);
                }
            }
            $stmt->close();
        }
    }
    
    // Build final message
    if (!empty($errors)) {
        $message = 'Errors: ' . implode('<br>', $errors);
        $message_type = 'danger';
    } elseif ($success_count > 0 && empty($error_rows)) {
        $_SESSION['admin_message'] = "Successfully added {$success_count} certificates!";
        $_SESSION['admin_message_type'] = 'success';
        header('Location: index.php');
        exit;
    } elseif ($success_count > 0 && !empty($error_rows)) {
        $message = "Added {$success_count} certificates. Some rows had errors:<br>" . implode('<br>', $error_rows);
        $message_type = 'warning';
    } else {
        $message = 'No certificates were added.<br>' . implode('<br>', $error_rows);
        $message_type = 'danger';
    }
}

$page_title = 'Bulk Add Certificates - eCert BPMI';
include 'header.php';
include 'nav.php';
?>

<style>
    .bulk-card {
        background: var(--bg-card, white);
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 20px;
    }
    .bulk-card .card-header {
        background: #1a3c5e;
        color: white;
        padding: 15px 20px;
        font-size: 16px;
        font-weight: 600;
    }
    .bulk-card .card-body {
        padding: 25px 30px;
    }
    .participant-row {
        background: #f8f9fa;
        border: 1px solid #e1e5eb;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 12px;
        transition: all 0.2s;
    }
    .participant-row:hover {
        border-color: #1a3c5e;
        box-shadow: 0 2px 8px rgba(26,60,94,0.1);
    }
    .participant-row .row-number {
        display: inline-block;
        width: 28px;
        height: 28px;
        background: #1a3c5e;
        color: white;
        border-radius: 50%;
        text-align: center;
        line-height: 28px;
        font-weight: 700;
        font-size: 13px;
        margin-right: 8px;
    }
    .remove-row-btn {
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 6px 12px;
        cursor: pointer;
        font-size: 13px;
    }
    .remove-row-btn:hover {
        background: #c82333;
    }
    .bulk-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid #e1e5eb;
    }
    .btn-add-row {
        background: #28a745;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
    }
    .btn-add-row:hover { background: #218838; }
    .btn-scan-bulk {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
    }
    .btn-submit-bulk {
        background: #1a3c5e;
        color: white;
        border: none;
        padding: 14px 40px;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 16px;
    }
    .btn-submit-bulk:hover { background: #0f2a42; }
    .inline-field {
        display: flex;
        flex-direction: column;
    }
    .inline-field label {
        font-size: 11px;
        font-weight: 600;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 3px;
        letter-spacing: 0.3px;
    }
    .inline-field input, .inline-field select {
        height: 38px;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 0 10px;
        font-size: 13px;
    }
    .inline-field input:focus {
        border-color: #1a3c5e;
        outline: none;
        box-shadow: 0 0 0 2px rgba(26,60,94,0.1);
    }
    .insider-radio-group {
        display: flex;
        gap: 10px;
        align-items: center;
        height: 38px;
        padding: 0 10px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 12px;
    }
    .insider-radio-group label {
        display: flex;
        align-items: center;
        gap: 4px;
        cursor: pointer;
        text-transform: none;
        color: #333;
        font-weight: 500;
        margin: 0;
        font-size: 12px;
    }
    .spin {
        animation: spin 1s linear infinite;
        display: inline-block;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>

<div class="container" style="padding-top: 20px; max-width: 1200px;">

    <!-- Title -->
    <div class="row">
        <div class="col-md-12">
            <h2 style="font-size: 24px; font-weight: 700; color: #1a3c5e; margin: 0 0 10px 0;">
                <span class="glyphicon glyphicon-duplicate"></span> Bulk Add Certificates
            </h2>
            <hr style="border-top: 2px solid #e1e5eb; margin: 10px 0 25px 0;">
            <p style="color: #666; margin-bottom: 20px;">
                Add certificates for <strong>multiple participants</strong> attending the <strong>same course</strong>.
                Fill in the course details once, then add each participant below.
            </p>
        </div>
    </div>

    <!-- Alert -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>" style="border-radius: 8px;">
            <strong>Result:</strong><br><?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" id="bulkForm">
        <input type="hidden" name="bulk_submit" value="1">
        
        <!-- COURSE DETAILS -->
        <div class="bulk-card">
            <div class="card-header">
                <span class="glyphicon glyphicon-book"></span> Course Details (applies to all participants)
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label style="font-weight: 600; font-size: 14px;">Course Name <span style="color: #d9534f;">*</span></label>
                            <input type="text" name="course_name" class="form-control" required 
                                   value="<?php echo htmlspecialchars($_POST['course_name'] ?? ''); ?>"
                                   placeholder="e.g., Kursus Asas Penyelenggaraan Enjin Sangkut Khas"
                                   style="height: 45px;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label style="font-weight: 600; font-size: 14px;">Course Date <span style="color: #d9534f;">*</span></label>
                            <input type="date" name="course_date" class="form-control" required 
                                   value="<?php echo htmlspecialchars($_POST['course_date'] ?? date('Y-m-d')); ?>"
                                   style="height: 45px;">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label style="font-weight: 600; font-size: 14px;">Certificate Type <span style="color: #d9534f;">*</span></label>
                            <select name="cert_type" class="form-control" required style="height: 45px;">
                                <option value="e-cert" <?php echo (($_POST['cert_type'] ?? '') == 'e-cert') ? 'selected' : ''; ?>>E-Certificate</option>
                                <option value="physical" <?php echo (($_POST['cert_type'] ?? '') == 'physical') ? 'selected' : ''; ?>>Physical Certificate</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PARTICIPANTS -->
        <div class="bulk-card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <span class="glyphicon glyphicon-user"></span> Participants
                    <span id="participantCount" style="background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 20px; font-size: 13px; margin-left: 8px;">1</span>
                </div>
                <button type="button" class="btn-add-row" onclick="addRow()" style="background: rgba(40,167,69,1);">
                    <span class="glyphicon glyphicon-plus"></span> Add Row
                </button>
            </div>
            <div class="card-body">
                
                <div id="participantsContainer">
                    <!-- Rows inserted here by JS -->
                </div>
                
                <div class="bulk-actions">
                    <button type="button" class="btn-add-row" onclick="addRow()">
                        <span class="glyphicon glyphicon-plus"></span> Add Another Participant
                    </button>
                    <button type="button" class="btn-scan-bulk" onclick="addRowAndScan()">
                        <span class="glyphicon glyphicon-search"></span> Add Row + Auto-fill from PDF
                    </button>
                </div>
                
            </div>
        </div>

        <!-- SUBMIT -->
        <div style="text-align: center; margin-top: 30px; margin-bottom: 40px;">
            <button type="submit" class="btn-submit-bulk">
                <span class="glyphicon glyphicon-save"></span> Save All Certificates
            </button>
            <a href="index.php" style="background: #6c757d; color: white; padding: 14px 30px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-left: 10px;">
                <span class="glyphicon glyphicon-remove"></span> Cancel
            </a>
        </div>
        
    </form>
</div>

<!-- ============================================
     BULK ADD JAVASCRIPT
     ============================================ -->
<script>
let rowCounter = 0;

document.addEventListener('DOMContentLoaded', function() {
    // Start with 1 empty row
    if (document.getElementById('participantsContainer').children.length === 0) {
        addRow();
    }
});

function addRow(focusScan = false) {
    rowCounter++;
    const container = document.getElementById('participantsContainer');
    
    const row = document.createElement('div');
    row.className = 'participant-row';
    row.dataset.rowId = rowCounter;
    row.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <div>
                <span class="row-number">${rowCounter}</span>
                <strong style="color: #1a3c5e;">Participant #${rowCounter}</strong>
            </div>
            <button type="button" class="remove-row-btn" onclick="removeRow(this)">
                <span class="glyphicon glyphicon-trash"></span> Remove
            </button>
        </div>
        <div class="row" style="margin: 0;">
            <div class="col-md-2" style="padding: 0 6px;">
                <div class="inline-field">
                    <label>Serial No. *</label>
                    <input type="text" name="participants[${rowCounter}][serialNum]" 
                           placeholder="PN-00XX" maxlength="10" required>
                </div>
            </div>
            <div class="col-md-2" style="padding: 0 6px;">
                <div class="inline-field">
                    <label>IC Number *</label>
                    <input type="text" name="participants[${rowCounter}][nokp]" 
                           class="bulk-ic-input" placeholder="123456789012" maxlength="12" 
                           pattern="[0-9]{12}" required>
                </div>
            </div>
            <div class="col-md-3" style="padding: 0 6px;">
                <div class="inline-field">
                    <label>Full Name *</label>
                    <input type="text" name="participants[${rowCounter}][name]" 
                           class="bulk-name-input" placeholder="Full name" required>
                </div>
            </div>
            <div class="col-md-2" style="padding: 0 6px;">
                <div class="inline-field">
                    <label>Type *</label>
                    <div class="insider-radio-group">
                        <label><input type="radio" name="participants[${rowCounter}][insider]" value="0" checked> Public</label>
                        <label><input type="radio" name="participants[${rowCounter}][insider]" value="1"> Insider</label>
                    </div>
                </div>
            </div>
            <div class="col-md-3" style="padding: 0 6px;">
                <div class="inline-field">
                    <label>Certificate File *</label>
                    <input type="file" name="cert_files[]" class="bulk-file-input" 
                           accept=".pdf,.jpg,.jpeg,.png,.gif" required
                           style="height: 38px; padding: 6px 10px;">
                </div>
                <div class="bulk-scan-area" style="margin-top: 6px; display: none;">
                    <button type="button" class="scan-row-btn" 
                            style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer;">
                        <span class="glyphicon glyphicon-search"></span> Auto-fill
                    </button>
                    <span class="scan-row-status" style="margin-left: 8px; font-size: 11px; color: #666;"></span>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(row);
    
    // Attach event listeners
    attachRowListeners(row);
    updateCount();
}

function removeRow(btn) {
    const row = btn.closest('.participant-row');
    row.remove();
    updateCount();
    renumberRows();
}

function updateCount() {
    const count = document.querySelectorAll('.participant-row').length;
    document.getElementById('participantCount').textContent = count;
}

function renumberRows() {
    const rows = document.querySelectorAll('.participant-row');
    rows.forEach((row, i) => {
        row.querySelector('.row-number').textContent = i + 1;
        row.querySelector('strong').textContent = 'Participant #' + (i + 1);
    });
}

function attachRowListeners(row) {
    const icInput = row.querySelector('.bulk-ic-input');
    const nameInput = row.querySelector('.bulk-name-input');
    const insiderRadios = row.querySelectorAll('input[name*="[insider]"]');
    const fileInput = row.querySelector('.bulk-file-input');
    const scanArea = row.querySelector('.bulk-scan-area');
    const scanBtn = row.querySelector('.scan-row-btn');
    const scanStatus = row.querySelector('.scan-row-status');
    
    // Auto-fill by IC
    if (icInput) {
        icInput.addEventListener('blur', function() {
            const ic = this.value.trim();
            if (ic.length !== 12) return;
            
            fetch('get_participant.php?type=ic&q=' + encodeURIComponent(ic))
                .then(r => r.json())
                .then(data => {
                    if (data.found) {
                        if (nameInput && !nameInput.value.trim()) {
                            nameInput.value = data.fullName;
                            highlightField(nameInput);
                        }
                        insiderRadios.forEach(radio => {
                            if (parseInt(radio.value) === data.insider) {
                                radio.checked = true;
                            }
                        });
                    }
                })
                .catch(err => console.error('Autofill error:', err));
        });
        
        icInput.addEventListener('paste', function() {
            setTimeout(() => this.dispatchEvent(new Event('blur')), 50);
        });
    }
    
    // Name suggestion
    if (nameInput) {
        let debounceTimer;
        nameInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const name = this.value.trim();
            if (name.length < 3) return;
            
            debounceTimer = setTimeout(() => {
                fetch('get_participant.php?type=name&q=' + encodeURIComponent(name))
                    .then(r => r.json())
                    .then(data => {
                        if (data.found && data.suggestions.length > 0) {
                            showSuggestions(nameInput, data.suggestions, icInput, insiderRadios);
                        }
                    })
                    .catch(err => console.error('Suggest error:', err));
            }, 300);
        });
    }
    
    // Show scan button when PDF selected
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) {
                scanArea.style.display = 'none';
                return;
            }
            
            const ext = file.name.split('.').pop().toLowerCase();
            if (ext === 'pdf') {
                scanArea.style.display = 'block';
            } else {
                scanArea.style.display = 'none';
            }
        });
    }
    
    // Scan PDF for this row
    if (scanBtn) {
        scanBtn.addEventListener('click', function() {
            const file = fileInput.files[0];
            if (!file) {
                scanStatus.innerHTML = '<span style="color: #dc3545;">No file selected</span>';
                return;
            }
            
            scanStatus.innerHTML = '<span class="glyphicon glyphicon-refresh spin"></span> Scanning...';
            scanBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('certificate_file', file);
            
            fetch('scan_certificate.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                scanBtn.disabled = false;
                
                if (!data.success) {
                    scanStatus.innerHTML = '<span style="color: #dc3545;">✗ ' + data.error + '</span>';
                    return;
                }
                
                const ext = data.extracted;
                let filled = [];
                
                // Fill Serial
                const serialInput = row.querySelector('input[name*="[serialNum]"]');
                if (ext.serialNum && serialInput && !serialInput.value.trim()) {
                    serialInput.value = ext.serialNum;
                    highlightField(serialInput);
                    filled.push('Serial');
                }
                
                // Fill IC
                if (ext.nokp && icInput && !icInput.value.trim()) {
                    icInput.value = ext.nokp;
                    highlightField(icInput);
                    filled.push('IC');
                }
                
                // Fill Name
                if (ext.name && nameInput && !nameInput.value.trim()) {
                    nameInput.value = ext.name;
                    highlightField(nameInput);
                    filled.push('Name');
                }
                
                // Auto-set insider
                if (ext.nokp) {
                    fetch('get_participant.php?type=ic&q=' + encodeURIComponent(ext.nokp))
                        .then(r => r.json())
                        .then(pdata => {
                            if (pdata.found) {
                                insiderRadios.forEach(radio => {
                                    if (parseInt(radio.value) === pdata.insider) {
                                        radio.checked = true;
                                    }
                                });
                            }
                        });
                }
                
                if (filled.length > 0) {
                    scanStatus.innerHTML = '<span style="color: #155724; font-weight: 600;">✓ ' + filled.join(', ') + '</span>';
                } else {
                    scanStatus.innerHTML = '<span style="color: #856404;">⚠ No fields detected</span>';
                }
            })
            .catch(err => {
                scanBtn.disabled = false;
                scanStatus.innerHTML = '<span style="color: #dc3545;">✗ ' + err.message + '</span>';
            });
        });
    }
}

function addRowAndScan() {
    addRow();
    // Scroll to the new row
    const rows = document.querySelectorAll('.participant-row');
    const lastRow = rows[rows.length - 1];
    lastRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
    // Focus the file input
    setTimeout(() => lastRow.querySelector('.bulk-file-input').focus(), 300);
}

function highlightField(el) {
    el.style.transition = 'background 0.6s';
    el.style.background = '#d4edda';
    setTimeout(() => el.style.background = '', 1500);
}

function showSuggestions(input, suggestions, icInput, insiderRadios) {
    // Remove existing
    document.querySelectorAll('.autofill-suggestions').forEach(el => el.remove());
    
    const wrapper = document.createElement('div');
    wrapper.className = 'autofill-suggestions';
    wrapper.style.cssText = 'position:absolute;background:white;border:1px solid #ddd;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);max-height:200px;overflow-y:auto;z-index:1000;min-width:250px;margin-top:2px;';
    
    suggestions.forEach(s => {
        const item = document.createElement('div');
        item.style.cssText = 'padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee;font-size:13px;';
        item.innerHTML = '<strong>' + escapeHtml(s.fullName) + '</strong> <span style="color:#888;font-size:11px;">(' + escapeHtml(s.icNum) + ')</span>';
        
        item.onmouseenter = () => item.style.background = '#f0f7ff';
        item.onmouseleave = () => item.style.background = 'white';
        item.onclick = () => {
            if (icInput) icInput.value = s.icNum;
            input.value = s.fullName;
            insiderRadios.forEach(radio => {
                if (parseInt(radio.value) === s.insider) {
                    radio.checked = true;
                }
            });
            document.querySelectorAll('.autofill-suggestions').forEach(el => el.remove());
        };
        
        wrapper.appendChild(item);
    });
    
    input.parentElement.style.position = 'relative';
    input.parentElement.appendChild(wrapper);
    
    // Close on outside click
    setTimeout(() => {
        document.addEventListener('click', function closeSuggestions(e) {
            if (!e.target.closest('.autofill-suggestions') && e.target !== input) {
                document.querySelectorAll('.autofill-suggestions').forEach(el => el.remove());
                document.removeEventListener('click', closeSuggestions);
            }
        });
    }, 100);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

<?php include 'footer.php'; ?>