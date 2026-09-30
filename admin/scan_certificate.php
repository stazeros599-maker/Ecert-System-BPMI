<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;

// ============================================
// CONFIGURATION — UPDATE THESE PATHS IF NEEDED
// ============================================
$tesseract_path = 'C:\Program Files\Tesseract-OCR\tesseract.exe';
$ghostscript_path = 'C:\Program Files\gs\gs10.07.1\bin\gswin64c.exe';

// Auto-detect Ghostscript if path is wrong
if (!file_exists($ghostscript_path)) {
    $gs_dirs = glob('C:\Program Files\gs\gs*\bin\gswin64c.exe');
    if (!empty($gs_dirs)) {
        $ghostscript_path = $gs_dirs[0];
    }
}

// ============================================
// VALIDATE UPLOAD
// ============================================
if (!isset($_FILES['certificate_file']) || $_FILES['certificate_file']['error'] != 0) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['certificate_file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Only PDF, JPG, JPEG, and PNG files can be scanned.'
    ]);
    exit;
}

try {
    $text = '';
    $debug = [];
    
    // ============================================
    // STEP 1: Extract text — try multiple methods
    // ============================================
    
    if ($ext === 'pdf') {
        // ---- Method 1: Fast native text extraction ----
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($file['tmp_name']);
            $text = $pdf->getText();
            $text = preg_replace('/\s+/', ' ', $text);
            $debug['method'] = 'pdfparser';
            $debug['text_length'] = strlen($text);
        } catch (Exception $e) {
            $text = '';
            $debug['pdfparser_error'] = $e->getMessage();
        }
        
        // ---- Method 2: If no text, fall back to OCR ----
        if (strlen(trim($text)) < 20) {
            $debug['fallback'] = 'Using OCR (native text extraction was empty)';
            
            $temp_dir = sys_get_temp_dir() . '/ecert_scan_' . uniqid();
            if (!is_dir($temp_dir)) mkdir($temp_dir, 0777, true);
            
            $output_pattern = $temp_dir . '/page-%03d.png';
            
            $gs_cmd = sprintf(
                '"%s" -dNOPAUSE -dBATCH -sDEVICE=png16m -r300 -dFirstPage=1 -dLastPage=3 -sOutputFile="%s" "%s" 2>&1',
                $ghostscript_path,
                $output_pattern,
                $file['tmp_name']
            );
            
            exec($gs_cmd, $gs_output, $gs_return);
            
            if ($gs_return !== 0) {
                $debug['ghostscript_error'] = implode("\n", $gs_output);
            }
            
            $page_images = glob($temp_dir . '/page-*.png');
            $debug['pages_rendered'] = count($page_images);
            
            if (empty($page_images)) {
                throw new Exception('OCR failed: could not render PDF pages. Check Ghostscript path.');
            }
            
            $all_text = '';
            foreach ($page_images as $img) {
                try {
                    $tess = new TesseractOCR($img);
                    $tess->executable($tesseract_path);
                    $tess->lang('eng');
                    $page_text = $tess->run();
                    $all_text .= ' ' . $page_text;
                } catch (Exception $e) {
                    $debug['ocr_error'] = $e->getMessage();
                }
            }
            
            foreach ($page_images as $img) @unlink($img);
            @rmdir($temp_dir);
            
            $text = preg_replace('/\s+/', ' ', $all_text);
            $debug['method'] = 'ocr';
            $debug['text_length'] = strlen($text);
        }
    } else {
        // ---- Direct image (JPG/PNG) → OCR ----
        $debug['method'] = 'ocr_direct';
        
        $tess = new TesseractOCR($file['tmp_name']);
        $tess->executable($tesseract_path);
        $tess->lang('eng');
        $text = $tess->run();
        $text = preg_replace('/\s+/', ' ', $text);
        $debug['text_length'] = strlen($text);
    }
    
    if (strlen(trim($text)) < 5) {
        echo json_encode([
            'success' => false,
            'error' => 'Could not extract any text from the document. It may be blank or an unsupported format.',
            'debug' => $debug
        ]);
        exit;
    }
    
    // ============================================
    // STEP 2: Parse extracted text
    // ============================================
    
    $extracted = [
        'serialNum' => null,
        'nokp' => null,
        'name' => null,
        'course_name' => null,
        'course_date' => null,
        'name_from_db' => false,
        'debug' => $debug
    ];
    
    // ---- Serial Number ----
    // Matches formats like: PN-0074, BT-0126, BPMI-001, CERT-2024, etc.
    $serial_patterns = [
        // Explicit labels: "Serial No: PN-0074" / "No. Siri: BT-0126" / "Certificate No: BPMI-001"
        '/(?:serial\s*(?:no\.?|number)?|no\.?\s*siri|siri\s*(?:no\.?)?|certificate\s*(?:no\.?|number)?|cert\.?\s*no\.?|ref(?:erence)?\.?\s*no\.?)\s*[:\-]?\s*([A-Z]{1,6}[\-\s]?\d{2,6})/i',
        
        // Standalone pattern: 2-6 uppercase letters, dash, 2-6 digits (e.g., PN-0074, BPMI-001)
        '/\b([A-Z]{2,6}-\d{2,6})\b/',
        
        // Standalone pattern: uppercase letters + digits with no dash (e.g., CERT2024)
        '/\b([A-Z]{2,6}\d{3,6})\b/',
        
        // Standalone pattern: number + dash + number (e.g., 001-2024)
        '/\b(\d{2,4}-\d{2,6})\b/',
    ];
    
    foreach ($serial_patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $candidate = strtoupper(trim($m[1]));
            $candidate = preg_replace('/\s+/', '', $candidate);
            
            // Validation: length 3-10 (matches your serialNum column)
            if (strlen($candidate) >= 3 && strlen($candidate) <= 10) {
                // Skip if it's actually the IC number pattern (all digits or XXXX-XXXX IC style)
                $test = str_replace('-', '', $candidate);
                if (!ctype_digit($test)) {
                    $extracted['serialNum'] = $candidate;
                    break;
                }
            }
        }
    }
    
    // ---- IC Number ----
    $ic_patterns = [
        '/\b(\d{6})[-\s](\d{2})[-\s](\d{4})\b/',
        '/\b(\d{6})(\d{2})(\d{4})\b/',
    ];
    foreach ($ic_patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $extracted['nokp'] = $m[1] . $m[2] . $m[3];
            break;
        }
    }
    
    // ---- Name ----
    $name_patterns = [
        // MALAY: "Diakui Bahawa [NAME] [IC]" or "Adalah Diakui Bahawa [NAME] [IC]"
        '/(?:adalah\s+)?diakui\s+bahawa\s+([A-Z][A-Za-z\s\.\@\/\']{3,80}?)\s+(?=\d{6}[\s\-]?\d{2}[\s\-]?\d{4}|\d{12})/i',
        
        // MALAY: "[NAME] [IC number]" — name directly before IC
        '/([A-Z][A-Za-z\s\.\@\/\']{3,80}?)\s+\d{6}[\s\-]\d{2}[\s\-]\d{4}/',
        
        // ENGLISH: standard patterns
        '/(?:awarded|presented|certify|conferred|given)\s+(?:to|that)\s+([A-Z][A-Za-z\s\.\@\/\']{3,60}?)(?:\s+(?:has|have|for|with|who|in|on|the|bearing|NRIC|IC)\b|\s*,|$)/i',
        '/this\s+is\s+to\s+certify\s+that\s+([A-Z][A-Za-z\s\.\@\/\']{3,60}?)(?:\s+(?:has|have|for|with|who|in|on)\b|,|$)/i',
        '/nama\s*[:\-]\s*([A-Z][A-Za-z\s\.\@\/\']{3,60}?)(?:\s*$|\s+(?:IC|No|Kursus|Tarikh))/i',
    ];
    foreach ($name_patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $candidate = trim($m[1], " \t\n\r.,:;");
            if (strlen($candidate) >= 3 && strlen($candidate) <= 100) {
                $extracted['name'] = strtoupper($candidate);
                break;
            }
        }
    }
    
    // ---- Course Name ----
    $course_patterns = [
        // MALAY: "Telah Menghadiri [COURSE] Bertempat" or "Telah Mengikuti [COURSE] Di" or "... Pada"
        '/telah\s+(?:menghadiri|mengikuti|menyertai|menamatkan)\s+(?:kursus\s+)?([A-Z][A-Za-z0-9\s\-\&\.\,\(\)\'"]{3,200}?)(?:\s+(?:bertempat|di|pada|yang\s+diadakan|dianjurkan)\b|$)/i',
        
        // MALAY: "Kursus [COURSE]" — anywhere
        '/\bkursus\s+([A-Z][A-Za-z0-9\s\-\&\.\,\(\)\'"]{3,200}?)(?:\s+(?:bertempat|di|pada|untuk|yang|kepada|dianjurkan)\b|$)/i',
        
        // ENGLISH: standard patterns
        '/successfully\s+completing\s+(?:the\s+)?(?:course\s+)?["\']?([A-Za-z][A-Za-z0-9\s\-\&\.\,\(\)\'"]{3,150}?)["\']?(?:\s+(?:held|on|from|organized|organised|at)\b|$)/i',
        '/for\s+completing\s+(?:the\s+)?(?:course\s+)?["\']?([A-Za-z][A-Za-z0-9\s\-\&\.\,\(\)\'"]{3,150}?)["\']?(?:\s+(?:held|on|from|at)\b|$)/i',
        '/(?:course|course\s+name|course\s+title)\s*[:\-]\s*([A-Za-z][A-Za-z0-9\s\-\&\.\,\(\)\'"]{3,150}?)(?:\s*$|\s+(?:held|on|from|date|at)\b)/i',
];
    foreach ($course_patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $candidate = trim($m[1], " \t\n\r.,:;\"'");
            if (strlen($candidate) >= 3 && strlen($candidate) <= 200) {
                $extracted['course_name'] = $candidate;
                break;
            }
        }
    }
    
    // ---- Date ----
    $months = [
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
        'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
        'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'januari' => 1, 'februari' => 2, 'mac' => 3,
        'mei' => 5, 'jun' => 6, 'julai' => 7, 'ogos' => 8,
        'oktober' => 10, 'disember' => 12
    ];
    
    if (preg_match('/(?:held\s+on|on\s+the|dated|tarikh)\s+(\d{1,2})(?:st|nd|rd|th)?\s+(?:day\s+of\s+)?([A-Z][a-z]+)\s+(\d{4})/i', $text, $m)) {
        $day = intval($m[1]);
        $month = $months[strtolower($m[2])] ?? null;
        $year = intval($m[3]);
        if ($month && checkdate($month, $day, $year)) {
            $extracted['course_date'] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }
    
    if (!$extracted['course_date'] && preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(January|February|March|April|May|June|July|August|September|October|November|December|Januari|Februari|Mac|Mei|Jun|Julai|Ogos|Oktober|Disember)\s+(\d{4})\b/i', $text, $m)) {
        $day = intval($m[1]);
        $month = $months[strtolower($m[2])] ?? null;
        $year = intval($m[3]);
        if ($month && checkdate($month, $day, $year)) {
            $extracted['course_date'] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }
    
    if (!$extracted['course_date'] && preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $text, $m)) {
        $day = intval($m[1]);
        $month = intval($m[2]);
        $year = intval($m[3]);
        if (checkdate($month, $day, $year)) {
            $extracted['course_date'] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }
    
    if (!$extracted['course_date'] && preg_match('/\b(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/', $text, $m)) {
        $year = intval($m[1]);
        $month = intval($m[2]);
        $day = intval($m[3]);
        if (checkdate($month, $day, $year)) {
            $extracted['course_date'] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }
    
    // ============================================
    // Look up participant name if IC found
    // ============================================
    if ($extracted['nokp']) {
        include_once __DIR__ . '/../db.php';
        if (isset($conn) && $conn instanceof mysqli) {
            $stmt = $conn->prepare("SELECT fullName FROM participant WHERE icNum = ?");
            $stmt->bind_param("s", $extracted['nokp']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($row) {
                $extracted['name'] = $row['fullName'];
                $extracted['name_from_db'] = true;
            }
        }
    }
    
    $extracted['debug']['preview'] = substr($text, 0, 500);
    
    echo json_encode([
        'success' => true,
        'extracted' => $extracted
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>