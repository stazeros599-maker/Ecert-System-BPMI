<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include '../db.php';
require_once 'logger.php';
require 'check_role.php';
require_role('admin');

$admin_id = $_SESSION['admin_id'];
$message = '';
$message_type = '';
$serialNum = $nokp = $name = $course_name = $course_date = '';
$insider = 0;
$cert_type = 'e-cert';
include 'nav_stack.php';
push_nav_stack();

// ============================================
// STEP 1: Confirm override submission
// ============================================
if (isset($_POST['confirm_override']) && $_POST['confirm_override'] == 'yes') {
    $serialNum = trim($_POST['serialNum']);
    $nokp = trim($_POST['nokp']);
    $name = trim($_POST['name']);
    $course_name = trim($_POST['course_name']);
    $course_date = $_POST['course_date'];
    $insider = isset($_POST['insider']) ? intval($_POST['insider']) : 0;
    $cert_type = isset($_POST['cert_type']) && in_array($_POST['cert_type'], ['e-cert', 'physical']) 
        ? $_POST['cert_type'] : 'e-cert';
    $cert_file = isset($_POST['cert_file_temp']) ? $_POST['cert_file_temp'] : '';
    
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
    
    $stmt = $conn->prepare("INSERT INTO certificates (serialNum, admin_id, nokp, name, course_name, course_date, certificate_file, cert_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissssss", $serialNum, $admin_id, $nokp, $name, $course_name, $course_date, $cert_file, $cert_type);
    
    if ($stmt->execute()) {
        log_activity($conn, 'certificate', 'add', 'success', 
            "Certificate added", $serialNum, 
            ['serialNum' => $serialNum, 'nokp' => $nokp, 'course' => $course_name]);
        $_SESSION['admin_message'] = 'Certificate added successfully! Serial: ' . $serialNum;
        $_SESSION['admin_message_type'] = 'success';
        header('Location: index.php');
        exit;
    } else {
        $message = 'Database error: ' . $stmt->error;
        $message_type = 'danger';
        log_activity($conn, 'certificate', 'add', 'failure', 
            "Insert failed: " . $stmt->error, $serialNum);
    }
    $stmt->close();
}

// ============================================
// STEP 2: Normal form submission
// ============================================
elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $serialNum = trim($_POST['serialNum']);
    $nokp = trim($_POST['nokp']);
    $name = trim($_POST['name']);
    $course_name = trim($_POST['course_name']);
    $course_date = $_POST['course_date'];
    $insider = isset($_POST['insider']) ? intval($_POST['insider']) : 0;
    $cert_type = isset($_POST['cert_type']) && in_array($_POST['cert_type'], ['e-cert', 'physical']) 
        ? $_POST['cert_type'] : 'e-cert';
    
    $errors = [];
    if (empty($serialNum)) {
        $errors[] = 'Serial Number is required.';
    } elseif (strlen($serialNum) > 10) {
        $errors[] = 'Serial Number must be 10 characters or less.';
    }
    if (empty($nokp)) $errors[] = 'IC number is required.';
    if (strlen($nokp) != 12 || !ctype_digit($nokp)) $errors[] = 'IC number must be 12 digits.';
    if (empty($name)) $errors[] = 'Name is required.';
    if (empty($course_name)) $errors[] = 'Course name is required.';
    if (empty($course_date)) $errors[] = 'Course date is required.';
    
    if (empty($errors)) {
        $check_sn = $conn->prepare("SELECT serialNum FROM certificates WHERE serialNum = ?");
        $check_sn->bind_param("s", $serialNum);
        $check_sn->execute();
        if ($check_sn->get_result()->num_rows > 0) {
            $errors[] = 'Serial Number "' . htmlspecialchars($serialNum) . '" already exists. Please use a different one.';
        }
        $check_sn->close();
    }
    
    $cert_file = '';
    if (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] == 0) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['certificate_file']['name'], PATHINFO_EXTENSION));
        $max_size = 5 * 1024 * 1024;
        
        if ($_FILES['certificate_file']['size'] > $max_size) {
            $errors[] = 'File size exceeds 5MB limit.';
        } elseif (in_array($ext, $allowed)) {
            $cert_file = 'cert_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $upload_path = '../certificates/' . $cert_file;
            
            if (!is_dir('../certificates')) {
                mkdir('../certificates', 0777, true);
            }
            
            if (!move_uploaded_file($_FILES['certificate_file']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload file. Please check folder permissions.';
                $cert_file = '';
            }
        } else {
            $errors[] = 'File must be PDF, JPG, JPEG, PNG, or GIF.';
        }
    } else {
        $errors[] = 'Certificate file is required.';
    }
    
    if (empty($errors)) {
        $check_ic = $conn->prepare("SELECT fullName FROM participant WHERE icNum = ?");
        $check_ic->bind_param("s", $nokp);
        $check_ic->execute();
        $ic_result = $check_ic->get_result();
        
        if ($ic_result->num_rows > 0) {
            $existing = $ic_result->fetch_assoc();
            if (strcasecmp(trim($existing['fullName']), trim($name)) !== 0) {
                $_SESSION['pending_certificate'] = [
                    'serialNum' => $serialNum,
                    'nokp' => $nokp,
                    'name' => $name,
                    'course_name' => $course_name,
                    'course_date' => $course_date,
                    'insider' => $insider,
                    'cert_type' => $cert_type,
                    'cert_file' => $cert_file,
                    'existing_name' => $existing['fullName']
                ];
                $check_ic->close();
                $conn->close();
                header('Location: confirm_override.php');
                exit;
            }
        }
        $check_ic->close();
    }
    
    if (empty($errors)) {
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
        
        $stmt = $conn->prepare("INSERT INTO certificates (serialNum, admin_id, nokp, name, course_name, course_date, certificate_file, cert_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sissssss", $serialNum, $admin_id, $nokp, $name, $course_name, $course_date, $cert_file, $cert_type);
        
        if ($stmt->execute()) {
            log_activity($conn, 'certificate', 'add', 'success', 
                "Certificate added", $serialNum, 
                ['serialNum' => $serialNum, 'nokp' => $nokp, 'course' => $course_name]);

            $_SESSION['admin_message'] = 'Certificate added successfully! Serial: ' . $serialNum;
            $_SESSION['admin_message_type'] = 'success';
            header('Location: index.php');
            exit;
        } else {
            log_activity($conn, 'certificate', 'add', 'failure', 
                "Insert failed: " . $stmt->error, $serialNum);

            $message = 'Database error: ' . $stmt->error;
            $message_type = 'danger';
        }
        $stmt->close();
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'danger';
    }
}

$page_title = 'Add Certificate - eCert BPMI';
include 'header.php';
include 'nav.php';
?>

<div class="container" style="padding-top: 20px;">
    <div style="background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: none; overflow: hidden; max-width: 700px; margin: 0 auto;">
        <div style="background: #1a3c5e; color: white; padding: 15px 20px; font-size: 16px; font-weight: 600;">
            <span class="glyphicon glyphicon-plus"></span> Add New Certificate
        </div>
        <div style="padding: 25px 30px;">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade in" style="border-radius: 10px; padding: 12px 20px; margin-bottom: 20px;">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px; font-size: 14px;">Serial Number <span style="color: #d9534f;">*</span></label>
                    <input type="text" name="serialNum" class="form-control" 
                        placeholder="Enter serial number (e.g., PN-0074, JPS-0001)"
                        maxlength="10"
                        value="<?php echo htmlspecialchars($serialNum); ?>" required>
                    <small style="color: #888; font-size: 12px; margin-top: 5px; display: block;">
                        Any format up to 10 characters. Examples: PN-0074, JPS-0001, CERT-2024
                    </small>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px; font-size: 14px;">IC Number <span style="color: #d9534f;">*</span></label>
                    <input type="text" name="nokp" class="form-control" maxlength="12" 
                           pattern="[0-9]{12}" title="Must be 12 digits"
                           value="<?php echo htmlspecialchars($nokp); ?>" required>
                    <small style="color: #888; font-size: 12px; margin-top: 5px; display: block;">12-digit identification card number</small>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px; font-size: 14px;">Full Name <span style="color: #d9534f;">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required>
                </div>
                
                <div class="participant-type-box">
                    <label class="participant-type-label">
                        Participant Type <span style="color: #d9534f;">*</span>
                    </label>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500; color: #333; margin: 0;">
                            <input type="radio" name="insider" value="1" 
                                   <?php echo (isset($_POST['insider']) && $_POST['insider'] == 1) ? 'checked' : ''; ?>
                                   style="width: 18px; height: 18px; cursor: pointer; margin: 0;">
                            <span style="font-size: 14px;">
                                <span class="glyphicon glyphicon-user" style="color: #28a745;"></span>
                                Perikanan Staff (Insider)
                            </span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500; color: #333; margin: 0;">
                            <input type="radio" name="insider" value="0" 
                                   <?php echo (!isset($_POST['insider']) || $_POST['insider'] == 0) ? 'checked' : ''; ?>
                                   style="width: 18px; height: 18px; cursor: pointer; margin: 0;">
                            <span style="font-size: 14px;">
                                <span class="glyphicon glyphicon-user" style="color: #6c757d;"></span>
                                Non-Insider (Public)
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="certificate-type-box" style="margin-bottom: 20px; padding: 15px; background: #f0f7ff; border-radius: 6px; border: 1px solid #b8d4f0;">
                    <label style="font-weight: 600; color: #555; display: block; margin-bottom: 10px; font-size: 14px;">
                        Certificate Type <span style="color: #d9534f;">*</span>
                    </label>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500; color: #333; margin: 0;">
                            <input type="radio" name="cert_type" value="e-cert" 
                                   <?php echo (!isset($_POST['cert_type']) || $_POST['cert_type'] == 'e-cert') ? 'checked' : ''; ?>
                                   style="width: 18px; height: 18px; cursor: pointer; margin: 0;">
                            <span style="font-size: 14px;">
                                <span class="glyphicon glyphicon-cloud" style="color: #17a2b8;"></span>
                                E-Certificate
                                <small style="color: #888; font-weight: 400;">(digital only)</small>
                            </span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500; color: #333; margin: 0;">
                            <input type="radio" name="cert_type" value="physical"
                                   <?php echo (isset($_POST['cert_type']) && $_POST['cert_type'] == 'physical') ? 'checked' : ''; ?>
                                   style="width: 18px; height: 18px; cursor: pointer; margin: 0;">
                            <span style="font-size: 14px;">
                                <span class="glyphicon glyphicon-file" style="color: #ffc107;"></span>
                                Physical Certificate
                                <small style="color: #888; font-weight: 400;">(printed copy)</small>
                            </span>
                        </label>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px; font-size: 14px;">Course Name <span style="color: #d9534f;">*</span></label>
                    <input type="text" name="course_name" class="form-control" value="<?php echo htmlspecialchars($course_name); ?>" required>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px; font-size: 14px;">Course Date <span style="color: #d9534f;">*</span></label>
                    <input type="date" name="course_date" class="form-control" value="<?php echo htmlspecialchars($course_date); ?>" required>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px; font-size: 14px;">Certificate File <span style="color: #d9534f;">*</span></label>
                    <input type="file" id="certificateFileInput" name="certificate_file" class="form-control" 
                        style="height: auto; padding: 10px; border: 2px dashed #e1e5eb; background: #fafafa; cursor: pointer;" 
                        accept=".pdf,.jpg,.jpeg,.png,.gif" required>
                    <small style="color: #888; font-size: 12px; margin-top: 5px; display: block;">Supported: PDF, JPG, JPEG, PNG, GIF (Max 5MB)</small>
                    
                    <div id="scanArea" style="margin-top: 12px; display: none;">
                        <button type="button" id="scanBtn" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 10px 22px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer;">
                            <span class="glyphicon glyphicon-search"></span> Auto-fill from PDF
                        </button>
                        <span id="scanStatus" style="margin-left: 12px; font-size: 13px; color: #666;"></span>
                    </div>
                </div>
                
                <div class="certificate-form-actions" style="margin-top: 25px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" style="background: #1a3c5e; border: none; padding: 12px 30px; font-weight: 600; border-radius: 6px; color: white; font-size: 15px; transition: all 0.3s;">
                        <span class="glyphicon glyphicon-save"></span> Save Certificate
                    </button>
                    <a href="index.php" class="certificate-cancel-btn" style="text-decoration: none;">
                        <span class="glyphicon glyphicon-remove"></span> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const icInput = document.querySelector('input[name="nokp"]');
    const nameInput = document.querySelector('input[name="name"]');
    const serialInput = document.querySelector('input[name="serialNum"]');
    const insiderRadios = document.querySelectorAll('input[name="insider"]');
    const fileInput = document.getElementById('certificateFileInput');
    const scanArea = document.getElementById('scanArea');
    const scanBtn = document.getElementById('scanBtn');
    const scanStatus = document.getElementById('scanStatus');
    
    if (icInput) {
        icInput.addEventListener('input', function() {
            if (this.value.trim().length === 12) {
                this.style.border = '';
                this.style.background = '';
            }
        });
        
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
                        showNotice(icInput, '✓ Found existing participant: ' + data.fullName);
                    }
                })
                .catch(err => console.error('Autofill error:', err));
        });
        
        icInput.addEventListener('paste', function() {
            setTimeout(() => this.dispatchEvent(new Event('blur')), 50);
        });
    }
    
    if (nameInput) {
        let debounceTimer;
        nameInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const name = this.value.trim();
            
            if (name.length < 3) {
                removeSuggestions();
                return;
            }
            
            debounceTimer = setTimeout(() => {
                fetch('get_participant.php?type=name&q=' + encodeURIComponent(name))
                    .then(r => r.json())
                    .then(data => {
                        if (data.found && data.suggestions.length > 0) {
                            showSuggestions(nameInput, data.suggestions);
                        } else {
                            removeSuggestions();
                        }
                    })
                    .catch(err => console.error('Suggest error:', err));
            }, 300);
        });
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.autofill-suggestions')) {
                removeSuggestions();
            }
        });
    }
    
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
                scanStatus.textContent = '';
            } else {
                scanArea.style.display = 'none';
            }
        });
    }
    
    if (scanBtn) {
        scanBtn.addEventListener('click', function() {
            const file = fileInput.files[0];
            if (!file) return;
            
            scanStatus.innerHTML = '<span class="glyphicon glyphicon-refresh spin"></span> Scanning PDF... Please wait.';
            scanBtn.disabled = true;
            scanBtn.style.opacity = '0.6';
            
            const formData = new FormData();
            formData.append('certificate_file', file);
            
            fetch('scan_certificate.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                scanBtn.disabled = false;
                scanBtn.style.opacity = '1';
                
                if (!data.success) {
                    scanStatus.innerHTML = '<span style="color:#dc3545;">✗ ' + data.error + '</span>';
                    return;
                }
                
                const ext = data.extracted;
                let filled = [];
                
                if (icInput) {
                    if (!ext.nokp) {
                        icInput.style.border = '2px solid #fd7e14';
                        icInput.style.background = '#fff7ef';
                    } else {
                        icInput.style.border = '';
                        icInput.style.background = '';
                    }
                }
                
                if (ext.serialNum) {
                    if (serialInput && !serialInput.value.trim()) {
                        serialInput.value = ext.serialNum;
                        highlightField(serialInput);
                        filled.push('Serial');
                    }
                }
                
                if (ext.nokp) {
                    if (icInput && !icInput.value.trim()) {
                        icInput.value = ext.nokp;
                        highlightField(icInput);
                        filled.push('IC');
                    }
                }
                
                if (ext.name) {
                    if (nameInput && !nameInput.value.trim()) {
                        nameInput.value = ext.name;
                        highlightField(nameInput);
                        filled.push('Name');
                    }
                }
                
                const courseInput = document.querySelector('input[name="course_name"]');
                if (ext.course_name && courseInput && !courseInput.value.trim()) {
                    courseInput.value = ext.course_name;
                    highlightField(courseInput);
                    filled.push('Course');
                }
                
                const dateInput = document.querySelector('input[name="course_date"]');
                if (ext.course_date && dateInput && !dateInput.value.trim()) {
                    dateInput.value = ext.course_date;
                    highlightField(dateInput);
                    filled.push('Date');
                }
                
                if (ext.insider !== null && ext.insider !== undefined) {
                    insiderRadios.forEach(radio => {
                        if (parseInt(radio.value) === ext.insider) radio.checked = true;
                    });
                }
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
                
                if (!ext.nokp) {
                    const partial = filled.length > 0 ? ('Partial: ' + filled.join(', ') + '. ') : '';
                    scanStatus.innerHTML = '<span style="color:#856404;font-weight:600;">⚠ ' + partial + 'No IC found in PDF — please enter the IC manually.</span>';
                } else if (filled.length > 0) {
                    scanStatus.innerHTML = '<span style="color:#155724;font-weight:600;">✓ Auto-filled: ' + filled.join(', ') + '</span>';
                } else {
                    scanStatus.innerHTML = '<span style="color:#856404;">⚠ Could not detect fields automatically. Please fill manually.</span>';
                }
            })
            .catch(err => {
                scanBtn.disabled = false;
                scanBtn.style.opacity = '1';
                scanStatus.innerHTML = '<span style="color:#dc3545;">✗ Error: ' + err.message + '</span>';
            });
        });
    }
    
    function highlightField(el) {
        el.style.transition = 'background 0.6s';
        el.style.background = '#d4edda';
        setTimeout(() => el.style.background = '', 1500);
    }
    
    function showSuggestions(input, suggestions) {
        removeSuggestions();
        const wrapper = document.createElement('div');
        wrapper.className = 'autofill-suggestions';
        wrapper.style.cssText = 'position:absolute;background:white;border:1px solid #ddd;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);max-height:220px;overflow-y:auto;z-index:100;width:100%;margin-top:4px;';
        
        suggestions.forEach(s => {
            const item = document.createElement('div');
            item.style.cssText = 'padding:10px 15px;cursor:pointer;border-bottom:1px solid #eee;font-size:14px;transition:background 0.15s;';
            item.innerHTML = '<strong>' + escapeHtml(s.fullName) + '</strong> <span style="color:#888;font-size:12px;">(' + escapeHtml(s.icNum) + ')</span>';
            
            item.onmouseenter = () => item.style.background = '#f0f7ff';
            item.onmouseleave = () => item.style.background = 'white';
            item.onclick = () => {
                if (icInput) {
                    icInput.value = s.icNum;
                    highlightField(icInput);
                }
                input.value = s.fullName;
                insiderRadios.forEach(radio => {
                    if (parseInt(radio.value) === s.insider) {
                        radio.checked = true;
                    }
                });
                removeSuggestions();
            };
            
            wrapper.appendChild(item);
        });
        
        input.parentElement.style.position = 'relative';
        input.parentElement.appendChild(wrapper);
    }
    
    function removeSuggestions() {
        document.querySelectorAll('.autofill-suggestions').forEach(el => el.remove());
    }
    
    function showNotice(element, message) {
        const existing = element.parentElement.querySelector('.autofill-notice');
        if (existing) existing.remove();
        
        const notice = document.createElement('div');
        notice.className = 'autofill-notice';
        notice.style.cssText = 'margin-top:6px;padding:6px 12px;background:#d4edda;color:#155724;border-radius:4px;font-size:12px;font-weight:600;display:inline-block;';
        notice.textContent = message;
        element.parentElement.appendChild(notice);
        
        setTimeout(() => notice.remove(), 3000);
    }
    
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
</script>

<style>
.spin {
    animation: spin 1s linear infinite;
    display: inline-block;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* ===== Participant Type box ===== */
.participant-type-box {
    margin-bottom: 20px;
    padding: 15px;
    background: #f0f7ff;
    border-radius: 6px;
    border: 1px solid #b8d4f0;
}
.participant-type-label {
    font-weight: 600;
    color: #555;
    display: block;
    margin-bottom: 10px;
    font-size: 14px;
}

.certificate-form-actions {
    justify-content: center;
    align-items: center;
}
.certificate-form-actions button,
.certificate-cancel-btn {
    min-height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 30px;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
}
.certificate-cancel-btn {
    background: #6c757d;
    border: 1px solid #6c757d;
    color: white;
}
.certificate-cancel-btn:hover,
.certificate-cancel-btn:focus {
    background: #5a6268;
    border-color: #545b62;
    color: white;
}

/* Dark mode overrides */
[data-theme="dark"] .participant-type-box,
[data-theme="dark"] .certificate-type-box {
    background: #242830 !important;
    border-color: #454b56 !important;
    color: #e8eaed !important;
}
[data-theme="dark"] .participant-type-label,
[data-theme="dark"] .participant-type-box label,
[data-theme="dark"] .participant-type-box label span,
[data-theme="dark"] .certificate-type-box label,
[data-theme="dark"] .certificate-type-box label span {
    color: #e8eaed !important;
}
</style>

<?php include 'footer.php'; ?>