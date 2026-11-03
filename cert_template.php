<?php
/**
 * cert_templates.php
 *
 * Each template defines regexes for one known certificate format.
 * The scanner tries all templates and picks the one with the most
 * fields matched.
 */
return [

    // =========================================================
    // 1. BPMI Kursus (Malay) — the original template
    // =========================================================
    'bpmi_kursus' => [
        'label'  => 'BPMI Kursus (Malay)',
        'nokp'   => [
            '/\(?\b(\d{6})\s*[-\s]\s*(\d{2})\s*[-\s]\s*(\d{4})\b\)?/',
        ],
        'name'   => [
            '/(?:adalah\s+)?diakui\s+bahawa\s+([A-Z][A-Za-z\s\.\@\/\']{3,80}?)\s*\(\s*(?=\d{6}\s*[-\s]\s*\d{2}\s*[-\s]\s*\d{4})/i',
            '/(?:adalah\s+)?diakui\s+bahawa\s+([A-Z][A-Za-z\s\.\@\/\']{3,80}?)\s+(?=\d{6}\s*[-\s]\s*\d{2}\s*[-\s]\s*\d{4}|\d{12})/i',
        ],
        'course' => [
            '/telah\s+(?:menghadiri|mengikuti|menyertai|menamatkan)\s+(?:kursus\s+)?([A-Z][A-Za-z0-9\s\-\&\.\,\(\)\'"]{3,200}?)(?:\s+(?:bertempat|di|pada|yang\s+diadakan|dianjurkan|anjuran)\b|$)/i',
            '/\bkursus\s+([A-Z][A-Za-z0-9\s\-\&\.\,\(\)\'"]{3,200}?)(?:\s+(?:bertempat|di|pada|untuk|yang|kepada|dianjurkan|anjuran)\b|$)/i',
        ],
    ],

    // =========================================================
    // 2. English Standard
    // =========================================================
    'english_standard' => [
        'label'  => 'English Standard',
        'nokp'   => [
            '/NRIC[:\s]+(\d{6}[-\s]?\d{2}[-\s]?\d{4})/i',
            '/IC\s*No\.?[:\s]+(\d{6}[-\s]?\d{2}[-\s]?\d{4})/i',
        ],
        'name'   => [
            '/This\s+is\s+to\s+certify\s+that\s+([A-Z][A-Za-z\s\.\@\/\']{3,60}?)(?:\s+(?:has|have|for|with|who|in|on|bearing|NRIC|IC)\b|,|$)/i',
            '/(?:awarded|presented|conferred)\s+to\s+([A-Z][A-Za-z\s\.\@\/\']{3,60}?)(?:\s+(?:has|have|for|with|who|in|on)\b|,|$)/i',
        ],
        'course' => [
            '/successfully\s+completing\s+(?:the\s+)?(?:course\s+)?["\']?([A-Za-z][A-Za-z0-9\s\-\&\.\,\(\)\'"]{3,150}?)["\']?(?:\s+(?:held|on|from|organized|organised|at)\b|$)/i',
            '/for\s+completing\s+(?:the\s+)?(?:course\s+)?["\']?([A-Za-z][A-Za-z0-9\s\-\&\.\,\(\)\'"]{3,150}?)["\']?(?:\s+(?:held|on|from|at)\b|$)/i',
        ],
    ],

    // =========================================================
    // 3. Sijil / Diakui Bahawa (Sabah DOF internal)
    //    Covers:
    //      - "Sijil Penyertaan" — participant attends a session
    //      - "Dijil Penghargaan" — speaker / presenter
    //    Both use the pattern: "... Diakui Bahawa NAME ... Telah
    //    Menghadiri / Sebagai Penceramah ... Anjuran ... Bertempat ...
    //    Pada <dates>"
    // =========================================================
    'sijil_diakui' => [
        'label'  => 'Sijil Diakui Bahawa (Sabah DOF)',
        'nokp'   => [
            // Same IC patterns — this format usually has no IC, but
            // if one is present, catch it.
            '/\(?\b(\d{6})\s*[-\s]\s*(\d{2})\s*[-\s]\s*(\d{4})\b\)?/',
        ],
        'name'   => [
            // "Adalah Diakui Bahawa NAME Telah ..." or "... NAME Sebagai ..."
            // Name is stopped by common following keywords.
            '/(?:adalah\s+)?diakui\s+bahawa\s+([A-Z][A-Za-z\s\.\@\/\']{3,80}?)\s+(?=telah|sebagai|bertempat|pada|anjuran|$)/i',
            // Fallback: name at end of line / before capital word boundary
            '/(?:adalah\s+)?diakui\s+bahawa\s+([A-Z][A-Za-z\s\.\@\/\']{3,80}?)(?=\s+[A-Z]{4,})/i',
        ],
        'course' => [
            // Participant: "Telah Menghadiri <COURSE> Anjuran <ORGANIZER>"
            '/telah\s+menghadiri\s+([A-Z][A-Za-z0-9\s\-\&\.\,\(\)\'"]{3,200}?)\s+(?:anjuran|bertempat|pada)\b/i',
            // Speaker: "Sebagai Penceramah <ROLE> Anjuran <ORGANIZER>"
            '/sebagai\s+penceramah\s+([A-Z][A-Za-z0-9\s\-\&\.\,\(\)\'"]{3,200}?)\s+(?:anjuran|bertempat|pada)\b/i',
            // Fallback: any "Anjuran <ORGANIZER>" as the course
            '/(?:sesi|kursus|program)\s+([A-Z][A-Za-z0-9\s\-\&\.\,\(\)\'"]{3,200}?)\s+(?:anjuran|bertempat|pada)\b/i',
        ],
    ],

];