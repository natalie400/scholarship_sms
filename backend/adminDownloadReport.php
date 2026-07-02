<?php
session_start();
require '../config.php';
require_once 'security.php';
require_login(2);

$period = isset($_GET['period']) ? strtolower(trim((string) $_GET['period'])) : 'weekly';
if ($period !== 'monthly') {
    $period = 'weekly';
}

$periodDays = ($period === 'monthly') ? 30 : 7;
$periodLabel = ($period === 'monthly') ? 'Last 30 Days' : 'Last 7 Days';
$rangeStart = date('Y-m-d 00:00:00', strtotime('-' . ($periodDays - 1) . ' days'));
$rangeEnd = date('Y-m-d 23:59:59');

$prevRangeEndTs = strtotime($rangeStart . ' -1 second');
$prevRangeStartTs = strtotime('-' . ($periodDays - 1) . ' days', $prevRangeEndTs);
$prevRangeStart = date('Y-m-d 00:00:00', $prevRangeStartTs);
$prevRangeEnd = date('Y-m-d 23:59:59', $prevRangeEndTs);

$conn = getDbConnection();
if (!$conn || $conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
ensureScholarshipApprovedAtColumn($conn);

$conn->query(
    "CREATE TABLE IF NOT EXISTS sms_dispatch_log (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        recipient VARCHAR(32) NOT NULL,
        message_preview VARCHAR(255) NOT NULL,
        message_type VARCHAR(50) NOT NULL DEFAULT 'general',
        trigger_source VARCHAR(50) NOT NULL DEFAULT 'manual',
        provider_http_code INT NOT NULL DEFAULT 0,
        provider_status VARCHAR(20) NOT NULL DEFAULT 'failed',
        provider_message VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sms_dispatch_log_created_at (created_at),
        INDEX idx_sms_dispatch_log_status (provider_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

function fetchSingleInt($conn, $sql, $types = '', $params = array()) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $value = 0;
    if ($result && ($row = $result->fetch_assoc())) {
        $first = array_values($row);
        $value = isset($first[0]) ? (int) $first[0] : 0;
    }
    $stmt->close();
    return $value;
}

$activeStudents = fetchSingleInt($conn, "SELECT COUNT(*) FROM student WHERE status = 'active'");
$activeSignatories = fetchSingleInt($conn, "SELECT COUNT(*) FROM signatory WHERE status = 'active'");
$activeAdmins = fetchSingleInt($conn, "SELECT COUNT(*) FROM admin WHERE status = 'active'");
$activeTotal = $activeStudents + $activeSignatories + $activeAdmins;

$usersWithActivity = fetchSingleInt(
    $conn,
    "SELECT COUNT(DISTINCT studentID) FROM application WHERE appDate BETWEEN ? AND ?",
    'ss',
    array($rangeStart, $rangeEnd)
);

$verifiedListingsPeriod = fetchSingleInt(
    $conn,
    "SELECT COUNT(*) FROM scholarship WHERE adminapproval = 'Approved' AND approved_at BETWEEN ? AND ?",
    'ss',
    array($rangeStart, $rangeEnd)
);
$verifiedListingsPrevious = fetchSingleInt(
    $conn,
    "SELECT COUNT(*) FROM scholarship WHERE adminapproval = 'Approved' AND approved_at BETWEEN ? AND ?",
    'ss',
    array($prevRangeStart, $prevRangeEnd)
);

$smsDispatchedPeriod = fetchSingleInt(
    $conn,
    "SELECT COUNT(*) FROM sms_dispatch_log WHERE provider_status = 'sent' AND created_at BETWEEN ? AND ?",
    'ss',
    array($rangeStart, $rangeEnd)
);
$smsDispatchedPrevious = fetchSingleInt(
    $conn,
    "SELECT COUNT(*) FROM sms_dispatch_log WHERE provider_status = 'sent' AND created_at BETWEEN ? AND ?",
    'ss',
    array($prevRangeStart, $prevRangeEnd)
);

$smsSourceBreakdown = array();
$sourceSql = "
    SELECT trigger_source, COUNT(*) AS total
    FROM sms_dispatch_log
    WHERE provider_status = 'sent' AND created_at BETWEEN ? AND ?
    GROUP BY trigger_source
    ORDER BY total DESC
";
$sourceStmt = $conn->prepare($sourceSql);
if ($sourceStmt) {
    $sourceStmt->bind_param('ss', $rangeStart, $rangeEnd);
    $sourceStmt->execute();
    $sourceRes = $sourceStmt->get_result();
    if ($sourceRes) {
        while ($row = $sourceRes->fetch_assoc()) {
            $smsSourceBreakdown[] = $row;
        }
    }
    $sourceStmt->close();
}

$currentAdminId = (int) ($_SESSION['currentUserID'] ?? 0);
$generatedBy = (string) ($_SESSION['email'] ?? 'Administrator');

if ($currentAdminId > 0) {
    $adminStmt = $conn->prepare("SELECT firstName, lastName, upMail FROM admin WHERE adminID = ? LIMIT 1");
    if ($adminStmt) {
        $adminStmt->bind_param('i', $currentAdminId);
        $adminStmt->execute();
        $adminRes = $adminStmt->get_result();
        if ($adminRes && ($adminRow = $adminRes->fetch_assoc())) {
            $displayName = trim((string) (($adminRow['firstName'] ?? '') . ' ' . ($adminRow['lastName'] ?? '')));
            $displayEmail = trim((string) ($adminRow['upMail'] ?? ''));
            if ($displayName !== '' && $displayEmail !== '') {
                $generatedBy = $displayName . ' (' . $displayEmail . ')';
            } elseif ($displayName !== '') {
                $generatedBy = $displayName;
            } elseif ($displayEmail !== '') {
                $generatedBy = $displayEmail;
            }
        }
        $adminStmt->close();
    }
}

$conn->close();

function pdfEscape($text) {
    $text = (string) $text;
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace('(', '\\(', $text);
    $text = str_replace(')', '\\)', $text);
    return preg_replace('/[^\x20-\x7E]/', '?', $text);
}

function buildSimplePdf($lines, $generatedBy, $periodLabel, $windowLabel, $generatedAt) {
    $linesPerPage = 48;
    $chunks = array_chunk($lines, $linesPerPage);
    $totalPages = count($chunks);

    $objects = array();
    $addObject = function($body) use (&$objects) {
        $objects[] = $body;
        return count($objects);
    };

    $fontObjId = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
    $pagesObjId = $addObject('<< /Type /Pages /Count 0 /Kids [] >>');

    $pageObjIds = array();

    foreach ($chunks as $pageIndex => $pageLines) {
        $content = "BT\n/F1 11 Tf\n50 800 Td\n";
        $first = true;
        foreach ($pageLines as $line) {
            if (!$first) {
                $content .= "0 -15 Td\n";
            }
            $content .= '(' . pdfEscape($line) . ") Tj\n";
            $first = false;
        }

        $pageNumber = ($pageIndex + 1) . '/' . $totalPages;
        $footerLine1 = 'Generated By: ' . $generatedBy;
        $footerLine2 = 'Period: ' . $periodLabel . ' | Window: ' . $windowLabel . ' | Generated: ' . $generatedAt;

        $content .= "ET\n";
        $content .= "0.65 w\n50 42 m\n545 42 l\nS\n";
        $content .= "BT\n/F1 8 Tf\n";
        $content .= "1 0 0 1 50 30 Tm (" . pdfEscape($footerLine1) . ") Tj\n";
        $content .= "1 0 0 1 50 18 Tm (" . pdfEscape($footerLine2) . ") Tj\n";
        $content .= "1 0 0 1 525 18 Tm (" . pdfEscape($pageNumber) . ") Tj\n";
        $content .= "ET";

        $stream = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        $contentObjId = $addObject($stream);

        $pageObjId = $addObject(
            "<< /Type /Page /Parent " . $pagesObjId . " 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 " . $fontObjId . " 0 R >> >> /Contents " . $contentObjId . " 0 R >>"
        );
        $pageObjIds[] = $pageObjId;
    }

    $kidsRefs = array();
    foreach ($pageObjIds as $pageObjId) {
        $kidsRefs[] = $pageObjId . ' 0 R';
    }

    $objects[$pagesObjId - 1] = '<< /Type /Pages /Count ' . count($pageObjIds) . ' /Kids [' . implode(' ', $kidsRefs) . '] >>';

    $catalogObjId = $addObject('<< /Type /Catalog /Pages ' . $pagesObjId . ' 0 R >>');

    $pdf = "%PDF-1.4\n";
    $offsets = array(0);

    for ($i = 1; $i <= count($objects); $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . $objects[$i - 1] . "\nendobj\n";
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root " . $catalogObjId . " 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

    return $pdf;
}

$lines = array();
$lines[] = 'Scholarship Management System - Admin Usage Report';
$lines[] = 'Generated on: ' . date('Y-m-d H:i:s');
$lines[] = 'Period: ' . $periodLabel;
$lines[] = 'Window: ' . date('M d, Y', strtotime($rangeStart)) . ' - ' . date('M d, Y', strtotime($rangeEnd));
$lines[] = str_repeat('-', 82);
$lines[] = 'Core Metrics';
$lines[] = 'Active User Accounts: ' . number_format($activeTotal);
$lines[] = '  - Admins: ' . number_format($activeAdmins);
$lines[] = '  - Signatories: ' . number_format($activeSignatories);
$lines[] = '  - Students: ' . number_format($activeStudents);
$lines[] = 'Verified Listings: ' . number_format($verifiedListingsPeriod);
$lines[] = 'SMS Notifications Dispatched: ' . number_format($smsDispatchedPeriod);
$lines[] = 'Users With Activity: ' . number_format($usersWithActivity);
$lines[] = str_repeat('-', 82);
$lines[] = 'Period Comparison';
$lines[] = 'Verified Listings - Current: ' . number_format($verifiedListingsPeriod) . ' | Previous: ' . number_format($verifiedListingsPrevious) . ' | Delta: ' . (($verifiedListingsPeriod - $verifiedListingsPrevious) >= 0 ? '+' : '') . number_format($verifiedListingsPeriod - $verifiedListingsPrevious);
$lines[] = 'SMS Dispatched    - Current: ' . number_format($smsDispatchedPeriod) . ' | Previous: ' . number_format($smsDispatchedPrevious) . ' | Delta: ' . (($smsDispatchedPeriod - $smsDispatchedPrevious) >= 0 ? '+' : '') . number_format($smsDispatchedPeriod - $smsDispatchedPrevious);
$lines[] = str_repeat('-', 82);
$lines[] = 'SMS Source Breakdown';

if (empty($smsSourceBreakdown)) {
    $lines[] = 'No sent SMS logs found in this period.';
} else {
    foreach ($smsSourceBreakdown as $row) {
        $lines[] = '- ' . (string) $row['trigger_source'] . ': ' . number_format((int) $row['total']);
    }
}

$lines[] = str_repeat('-', 82);
$lines[] = 'Note: Save location is selected in your browser download dialog.';

$windowLabel = date('M d, Y', strtotime($rangeStart)) . ' - ' . date('M d, Y', strtotime($rangeEnd));
$generatedAt = date('Y-m-d H:i:s');
$pdfBinary = buildSimplePdf($lines, $generatedBy, $periodLabel, $windowLabel, $generatedAt);
$filename = 'admin-usage-report-' . $period . '-' . date('Ymd-His') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfBinary));

// Disable caching so each download reflects latest stats.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo $pdfBinary;
exit;
