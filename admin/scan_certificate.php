<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;

// ============================================
// CONFIGURATION — UPDATE THESE PATHS IF NEEDED
// ============================================
$tesseract_path = 'C:\Program Files\Tesseract-OCR\tesseract.exe';
$ghostscript_path = 'C:\Program Files\gs\gs10.07.1\bin\gswin64c.exe';

if (!file_exists($ghostscript_path)) {
    $gs_dirs = glob('C:\Program Files\gs\gs*\bin\gswin64c.exe');
    if (!empty($gs_dirs)) $ghostscript_path = $gs_dirs[0];
}

// ============================================
// SERIAL PREFIX → INSIDER MAP
// ============================================
$prefix_map_path = __DIR__ . '/../serial_prefixes.php';
$serial_prefix_map = ['JPS' => 1, 'PN' => 0];
if (file_exists($prefix_map_path)) {
    $loaded_prefix_map = require $prefix_map_path;
    if (is_array($loaded_prefix_map)) {
        $serial_prefix_map = $loaded_prefix_map;
    }
}

// ============================================
// TEMPLATES CONFIG (for A + B + D)
// ============================================
$templates_path = __DIR__ . '/../cert_template.php';
$cert_templates = [];
if (file_exists($templates_path)) {
    $loaded_templates = require $templates_path;
    if (is_array($loaded_templates)) {
        $cert_templates = $loaded_templates;
    }
}

/**
 * Given a serial number, return 1 (insider), 0 (public), or null (unknown).
 */
function insider_from_serial($serial, array $map) {
    if (empty($serial)) return null;

    $s = strtoupper(trim($serial));

    if (strpos($s, '-') !== false) {
        $prefix = substr($s, 0, strpos($s, '-'));
    } else {
        preg_match('/^([A-Z]+)/', $s, $m);
        $prefix = $m[1] ?? '';
    }

    $prefix = strtoupper(trim($prefix));
    if ($prefix === '') return null;

    return array_key_exists($prefix, $map) ? (int) $map[$prefix] : null;
}

/**
 * Run a list of patterns against text, return the first valid match.
 */
function try_patterns($text, array $patterns, ?callable $validator = null) {
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $candidate = $m[1];
            if (isset($m[2], $m[3]) && ctype_digit($m[1]) && ctype_digit($m[2]) && ctype_digit($m[3])) {
                $candidate = $m[1] . $m[2] . $m[3];
            }
            $candidate = trim($candidate, " \t\n\r.,:;\"'");
            if ($validator === null || $validator($candidate)) {
                return $candidate;
            }
        }
    }
    return null;
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

$extracted = null;
$debug = [];

try {
    $text = '';

    // ============================================
    // STEP 1: Extract text
    // ============================================
    if ($ext === 'pdf') {
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
                    $all_text .= ' ' . $tess->run();
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
    // STEP 1.5: NORMALIZE TEXT
    // ============================================
    $raw_text_for_debug = $text;

    $text = str_replace(
        ["\u{2012}", "\u{2013}", "\u{2014}", "\u{2015}", "\u{2212}"],
        '-',
        $text
    );
    $text = preg_replace('/(?<=\d)[Oo](?=\d)/', '0', $text);
    $text = preg_replace('/(?<=\d)[lI](?=\d)/', '1', $text);
    $text = preg_replace('/\s*\(\s*/', ' (', $text);
    $text = preg_replace('/\s*\)\s*/', ') ', $text);
    $text = preg_replace('/\s+/', ' ', $text);

    // ============================================
    // STEP 2: Parse extracted text
    // ============================================
    $extracted = [
        'serialNum'           => null,
        'nokp'                => null,
        'name'                => null,
        'course_name'         => null,
        'course_date'         => null,
        'name_from_db'        => false,
        'serial_exists_in_db' => false,
        'insider'             => null,
        'insider_source'      => null,
        'confidence'          => [],
        'raw_text'            => null,
        'normalized_text'     => null,
        'no_ic'               => true,
        'debug'               => []
    ];

    // ---- Template-driven parsing ----
    $v_ic = function($v) { return strlen($v) === 12 && ctype_digit($v); };
    $v_name = function($v) {
        return strlen($v) >= 3 && strlen($v) <= 100
            && !preg_match('/\d/', $v)
            && preg_match('/\s/', $v);
    };
    $v_course = function($v) { return strlen($v) >= 3 && strlen($v) <= 200; };

    $template_results = [];

    foreach ($cert_templates as $tpl_key => $tpl) {
        $tpl_result = [
            'serialNum' => null,
            'nokp'      => null,
            'name'      => null,
            'course'    => null,
            'score'     => 0,
        ];

        if (!empty($tpl['nokp'])) {
            $v = try_patterns($text, $tpl['nokp'], $v_ic);
            if ($v) { $tpl_result['nokp'] = $v; $tpl_result['score']++; }
        }
        if (!empty($tpl['name'])) {
            $v = try_patterns($text, $tpl['name'], $v_name);
            if ($v) { $tpl_result['name'] = strtoupper($v); $tpl_result['score']++; }
        }
        if (!empty($tpl['course'])) {
            $v = try_patterns($text, $tpl['course'], $v_course);
            if ($v) { $tpl_result['course'] = $v; $tpl_result['score']++; }
        }

        $template_results[$tpl_key] = $tpl_result;
    }

    // Pick the best-scoring template
    $best_template = null;
    $best_score = -1;
    foreach ($template_results as $k => $r) {
        if ($r['score'] > $best_score) {
            $best_score = $r['score'];
            $best_template = $k;
        }
    }

    if ($best_template !== null && $best_score > 0) {
        $used_tpl = $template_results[$best_template];
        $extracted['nokp']        = $used_tpl['nokp'];
        $extracted['name']        = $used_tpl['name'];
        $extracted['course_name'] = $used_tpl['course'];
        $debug['template_used']   = $best_template;
        $debug['template_score']  = $best_score;
        $debug['template_scores'] = array_map(function($r){ return $r['score']; }, $template_results);
    }

    // ---- Serial Number (generic) ----
    $serial_patterns = [
        '/(?:serial\s*(?:no\.?|number)?|no\.?\s*siri|siri\s*(?:no\.?)?|certificate\s*(?:no\.?|number)?|cert\.?\s*no\.?|ref(?:erence)?\.?\s*no\.?)\s*[:\-]?\s*([A-Z]{1,6}[\-\s]?\d{2,6})/i',
        '/\b([A-Z]{2,6}-\d{2,6})\b/',
        '/\b([A-Z]{2,6}\d{3,6})\b/',
        '/\b(\d{2,4}-\d{2,6})\b/',
    ];
    foreach ($serial_patterns as $idx => $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $candidate = strtoupper(trim($m[1]));
            $candidate = preg_replace('/\s+/', '', $candidate);

            if (strlen($candidate) >= 3 && strlen($candidate) <= 10) {
                $test = str_replace('-', '', $candidate);
                if (!ctype_digit($test)) {
                    if (!$extracted['nokp'] || $test !== $extracted['nokp']) {
                        $extracted['serialNum'] = $candidate;
                        $debug['serial_pattern_matched'] = $idx;
                        break;
                    }
                }
            }
        }
    }

    // ---- Insider from serial prefix ----
    if ($extracted['serialNum']) {
        $insiderFromSerial = insider_from_serial($extracted['serialNum'], $serial_prefix_map);
        if ($insiderFromSerial !== null) {
            $extracted['insider'] = $insiderFromSerial;
            $extracted['insider_source'] = 'serial';
            $debug['insider_from_serial'] = [
                'serial'   => $extracted['serialNum'],
                'resolved' => $insiderFromSerial
            ];
        }
    }

    // ============================================
    // D — LOOSE / GUESSED FALLBACK
    // ============================================
    $guessed = [];

    if (empty($extracted['nokp'])) {
        $loose_ic = [
            '/\b(\d{6})[\s\-]?(\d{2})[\s\-]?(\d{4})\b/',
        ];
        foreach ($loose_ic as $p) {
            if (preg_match($p, $text, $m)) {
                $cand = $m[1] . $m[2] . $m[3];
                if (strlen($cand) === 12 && ctype_digit($cand)) {
                    $extracted['nokp'] = $cand;
                    $guessed['nokp'] = true;
                    $debug['guessed_ic'] = true;
                    break;
                }
            }
        }
    }

    if (empty($extracted['name'])) {
        $loose_name = [
            '/([A-Z][A-Za-z\.\@\/\']+(?:\s+[A-Z][A-Za-z\.\@\/\']+){1,5})\s*(?:\(|NRIC|IC\b)/',
            '/([A-Z][A-Za-z\.\@\/\']+(?:\s+(?:BIN|BINTI|A\/L|A\/P)\s+[A-Z][A-Za-z\.\@\/\']+){1,4})/i',
        ];
        foreach ($loose_name as $p) {
            if (preg_match($p, $text, $m)) {
                $cand = trim($m[1], " \t\n\r.,:;");
                if (strlen($cand) >= 3 && strlen($cand) <= 100
                    && !preg_match('/\d/', $cand)
                    && preg_match('/\s/', $cand)) {
                    $extracted['name'] = strtoupper($cand);
                    $guessed['name'] = true;
                    $debug['guessed_name'] = true;
                    break;
                }
            }
        }
    }

    // ---- Date (generic patterns) ----
    $months = [
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
        'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
        'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'januari' => 1, 'februari' => 2, 'mac' => 3,
        'mei' => 5, 'jun' => 6, 'julai' => 7, 'ogos' => 8,
        'oktober' => 10, 'disember' => 12
    ];

    if (preg_match('/(?:held\s+on|on\s+the|dated|tarikh)\s+(\d{1,2})(?:st|nd|rd|th)?\s+(?:day\s+of\s+)?([A-Z][a-z]+)\s+(\d{4})/i', $text, $m)) {
        $day = intval($m[1]); $month = $months[strtolower($m[2])] ?? null; $year = intval($m[3]);
        if ($month && checkdate($month, $day, $year)) {
            $extracted['course_date'] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }
    if (!$extracted['course_date'] && preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(January|February|March|April|May|June|July|August|September|October|November|December|Januari|Februari|Mac|Mei|Jun|Julai|Ogos|Oktober|Disember)\s+(\d{4})\b/i', $text, $m)) {
        $day = intval($m[1]); $month = $months[strtolower($m[2])] ?? null; $year = intval($m[3]);
        if ($month && checkdate($month, $day, $year)) {
            $extracted['course_date'] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }
    if (!$extracted['course_date'] && preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $text, $m)) {
        $day = intval($m[1]); $month = intval($m[2]); $year = intval($m[3]);
        if (checkdate($month, $day, $year)) {
            $extracted['course_date'] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }
    if (!$extracted['course_date'] && preg_match('/\b(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/', $text, $m)) {
        $year = intval($m[1]); $month = intval($m[2]); $day = intval($m[3]);
        if (checkdate($month, $day, $year)) {
            $extracted['course_date'] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }

    // ============================================
    // DB lookups: participant name + serial duplicate check
    // ============================================
    if (isset($conn) && $conn instanceof mysqli) {

        if ($extracted['nokp']) {
            $stmt = $conn->prepare("SELECT fullName, insider FROM participant WHERE icNum = ?");
            $stmt->bind_param("s", $extracted['nokp']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                $extracted['name'] = $row['fullName'];
                $extracted['name_from_db'] = true;
                $extracted['insider'] = (int) $row['insider'];
                $extracted['insider_source'] = 'db';
            }
        }

        if ($extracted['serialNum']) {
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM certificates WHERE serialNum = ?");
            $stmt->bind_param("s", $extracted['serialNum']);
            $stmt->execute();
            $cnt = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();

            $extracted['serial_exists_in_db'] = ($cnt > 0);
        }
    }

    // ============================================
    // Debug info
    // ============================================
    $debug['preview_raw']        = substr($raw_text_for_debug, 0, 1500);
    $debug['preview_normalized'] = substr($text, 0, 1500);
    $debug['text_length_final']  = strlen($text);

    // Flag when no IC could be extracted
    $extracted['no_ic'] = empty($extracted['nokp']);

    // ============================================
    // A — CONFIDENCE SCORES PER FIELD
    // ============================================
    $extracted['confidence'] = [
        'serialNum'   => !empty($extracted['serialNum'])   ? 'medium' : 'none',
        'nokp'        => !empty($extracted['nokp'])        ? (!empty($guessed['nokp']) ? 'low' : 'high') : 'none',
        'name'        => !empty($extracted['name'])        ? (!empty($guessed['name']) ? 'low' : ($extracted['name_from_db'] ? 'high' : 'medium')) : 'none',
        'course_name' => !empty($extracted['course_name']) ? 'medium' : 'none',
        'course_date' => !empty($extracted['course_date']) ? 'medium' : 'none',
    ];

    // ============================================
    // C — RAW OCR TEXT
    // ============================================
    $extracted['raw_text']        = $raw_text_for_debug;
    $extracted['normalized_text'] = $text;

    $extracted['debug'] = $debug;

    echo json_encode([
        'success'   => true,
        'extracted' => $extracted
    ]);

    // ============================================
    // Log
    // ============================================
    try {
        if (function_exists('log_activity') && isset($conn)) {
            log_activity($conn, 'scanner', 'extract',
                $extracted['serialNum'] ? 'success' : 'warning',
                "PDF scanned: " . ($extracted['serialNum'] ?? 'no serial'),
                $extracted['serialNum'] ?? null,
                [
                    'method'      => $debug['method'] ?? 'unknown',
                    'text_length' => $debug['text_length'] ?? 0,
                    'fields_found' => array_filter([
                        'serial' => !empty($extracted['serialNum']),
                        'ic'     => !empty($extracted['nokp']),
                        'name'   => !empty($extracted['name']),
                        'course' => !empty($extracted['course_name']),
                        'date'   => !empty($extracted['course_date']),
                    ]),
                    'serial_exists_in_db' => !empty($extracted['serial_exists_in_db']),
                    'insider'             => $extracted['insider'],
                    'insider_source'      => $extracted['insider_source'],
                    'no_ic'               => !empty($extracted['no_ic']),
                    'confidence'          => $extracted['confidence'],
                    'template_used'       => $debug['template_used'] ?? null,
                    'template_score'      => $debug['template_score'] ?? 0
                ]);
        }
    } catch (Throwable $logErr) {
        // swallow
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}