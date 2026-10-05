<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
include 'nav_stack.php';
push_nav_stack();

if (!isset($_SESSION['pending_certificate'])) {
    header('Location: add_certificate.php');
    exit;
}

$data = $_SESSION['pending_certificate'];

$page_title = 'Confirm Override - eCert BPMI';
include 'header.php';
include 'nav.php';
?>

<div class="container">
    <div style="background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); overflow: hidden; max-width: 700px; margin: 0 auto;">
        
        <!-- Warning Header -->
        <div style="background: #dc3545; color: white; padding: 15px 20px; font-size: 16px; font-weight: 600;">
            <span class="glyphicon glyphicon-warning-sign"></span> Name Conflict Detected
        </div>
        
        <div style="padding: 30px;">
            
            <!-- Warning Message -->
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; border-radius: 6px; margin-bottom: 25px;">
                <div style="font-weight: 700; color: #856404; font-size: 15px; margin-bottom: 8px;">
                    <span class="glyphicon glyphicon-alert"></span> IC Number Already Exists With A Different Name
                </div>
                <div style="color: #856404; font-size: 14px; line-height: 1.6;">
                    The IC number you entered is already registered with a different name in the system.
                    Please review carefully before proceeding.
                </div>
            </div>
            
            <!-- Comparison Table -->
            <table class="table table-bordered" style="margin-bottom: 25px;">
                <tr>
                    <th style="background: #f7f9fc; width: 40%; padding: 12px 15px;">IC Number</th>
                    <td style="padding: 12px 15px;"><strong><?php echo htmlspecialchars($data['nokp']); ?></strong></td>
                </tr>
                <tr>
                    <th style="background: #f7f9fc; padding: 12px 15px;">Existing Name</th>
                    <td style="padding: 12px 15px;">
                        <span style="background: #e8f0fe; color: #1a3c5e; padding: 4px 10px; border-radius: 4px; font-weight: 600;">
                            <?php echo htmlspecialchars($data['existing_name']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th style="background: #fff3cd; padding: 12px 15px;">New Name</th>
                    <td style="padding: 12px 15px;">
                        <span style="background: #ffe8cc; color: #d9534f; padding: 4px 10px; border-radius: 4px; font-weight: 700;">
                            <?php echo htmlspecialchars($data['name']); ?>
                        </span>
                    </td>
                </tr>
            </table>
            
            <!-- Certificate Details -->
            <div style="background: #f8f9fa; padding: 15px 20px; border-radius: 6px; margin-bottom: 25px;">
                <div style="font-weight: 600; color: #555; margin-bottom: 10px; font-size: 14px;">Certificate to be added:</div>
                <div style="font-size: 13px; color: #666; line-height: 1.8;">
                    <div><strong>Serial No:</strong> <?php echo htmlspecialchars($data['serialNum']); ?></div>
                    <div><strong>Course:</strong> <?php echo htmlspecialchars($data['course_name']); ?></div>
                    <div><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($data['course_date'])); ?></div>
                    <div><strong>Type:</strong> <?php echo $data['insider'] ? 'Insider (Perikanan Staff)' : 'Public'; ?></div>
                    <div><strong>Certificate:</strong> <?php echo isset($data['cert_type']) && $data['cert_type'] == 'physical' ? 'Physical' : 'E-Certificate'; ?></div>
                </div>
            </div>
            
            <!-- Warning Note -->
            <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 12px 18px; border-radius: 6px; margin-bottom: 25px;">
                <div style="color: #721c24; font-size: 13px; line-height: 1.6;">
                    <strong><span class="glyphicon glyphicon-info-sign"></span> Please note:</strong>
                    If you confirm, the participant's name in the system will be <strong>updated</strong> from
                    "<?php echo htmlspecialchars($data['existing_name']); ?>" to "<?php echo htmlspecialchars($data['name']); ?>".
                    This will affect all their existing certificates.
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div style="display: flex; gap: 12px; flex-wrap: wrap; justify-content: center;">
                
                <!-- Confirm -->
                <form method="POST" action="add_certificate.php" style="display: inline;">
                    <input type="hidden" name="confirm_override" value="yes">
                    <input type="hidden" name="serialNum" value="<?php echo htmlspecialchars($data['serialNum']); ?>">
                    <input type="hidden" name="nokp" value="<?php echo htmlspecialchars($data['nokp']); ?>">
                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($data['name']); ?>">
                    <input type="hidden" name="course_name" value="<?php echo htmlspecialchars($data['course_name']); ?>">
                    <input type="hidden" name="course_date" value="<?php echo htmlspecialchars($data['course_date']); ?>">
                    <input type="hidden" name="insider" value="<?php echo htmlspecialchars($data['insider']); ?>">
                    <input type="hidden" name="cert_file_temp" value="<?php echo htmlspecialchars($data['cert_file']); ?>">
                    <input type="hidden" name="cert_type" value="<?php echo htmlspecialchars($data['cert_type'] ?? 'e-cert'); ?>">
                    
                    <button type="submit" style="background: #dc3545; border: none; padding: 12px 30px; font-weight: 600; border-radius: 6px; color: white; font-size: 15px; cursor: pointer;">
                        <span class="glyphicon glyphicon-ok"></span> Confirm & Update Name
                    </button>
                </form>
                
                <!-- Cancel -->
                <form method="POST" action="cancel_override.php" style="display: inline;">
                    <button type="submit" style="background: #6c757d; border: none; padding: 12px 30px; font-weight: 600; border-radius: 6px; color: white; font-size: 15px; cursor: pointer;">
                        <span class="glyphicon glyphicon-remove"></span> Cancel
                    </button>
                </form>
                
            </div>
            
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>