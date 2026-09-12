<?php
/**
 * EMAIL AUTOMATION SYSTEM - LIBERTY FOUNDATION TEMPLATE
 * Uses exact PDF template with dynamic fields:
 * - Ref Number: LF/SKILL/2026/XXX
 * - College Name: Auto-fill
 * - Date: DD/MM/YYYY format
 * 
 * Everything else stays same as template!
 */

session_start();

// ==================== CONFIGURATION ====================
// CHOOSE YOUR SMTP (All completely FREE):

// OPTION 1: BREVO (Recommended - 300/day free)
define('SMTP_HOST', 'smtp-relay.brevo.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'xsmetp...'); // Copy from Brevo dashboard

// OPTION 2: MAILGUN (5000/month free - uncomment below)
/*
define('SMTP_HOST', 'smtp.mailgun.org');
define('SMTP_PORT', 587);
define('SMTP_USER', 'postmaster@sandboxxxx.mailgun.org');
define('SMTP_PASS', 'key-xxxxx');
*/

define('SENDER_NAME', 'Liberty Foundation');
define('SENDER_EMAIL', 'support@libertytraining.in');
define('ORGANIZATION_NAME', 'LIBERTY FOUNDATION');
define('ORGANIZATION_PHONE', '81598 32788');
define('ORGANIZATION_EMAIL', 'support@libertytraining.in');
define('ORGANIZATION_WEBSITE', 'www.libertytraining.in');

// ==================== DATABASE CONFIG ====================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'email_system');

// ==================== DATABASE CONNECTION ====================
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection Error: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// ==================== HANDLE REQUESTS ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'upload_excel':
            handle_excel_upload($conn);
            break;
        case 'get_colleges':
            get_all_colleges($conn);
            break;
        case 'send_email':
            send_single_email($_POST['college_id'] ?? null, $conn);
            break;
        case 'send_all':
            send_all_pending_emails($conn);
            break;
        case 'get_stats':
            get_statistics($conn);
            break;
        case 'manual_send':
            manual_send_email($_POST, $conn);
            break;
        case 'upload_template':
            upload_template($conn);
            break;
        case 'save_template_positions':
            save_template_positions($_POST, $conn);
            break;
        case 'get_template':
            get_template($conn);
            break;
        case 'reset_template':
            reset_template($conn);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
    exit;
}

// ==================== FUNCTIONS ====================

function handle_excel_upload($conn) {
    if (!isset($_FILES['file'])) {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
        return;
    }
    
    require 'vendor/autoload.php';
    
    try {
        $file = $_FILES['file']['tmp_name'];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $data = $spreadsheet->getActiveSheet()->toArray();
        
        $imported = 0;
        $errors = 0;
        
        // Skip header row (row 0)
        for ($i = 1; $i < count($data); $i++) {
            if (empty($data[$i][0])) continue;
            
            $college_name = trim($data[$i][0]);
            $email = trim($data[$i][1]);
            $reference_number = trim($data[$i][2]);
            $invitation_date = trim($data[$i][3]);
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors++;
                continue;
            }
            
            $stmt = $conn->prepare(
                "INSERT INTO colleges (college_name, email, reference_number, invitation_date, status) 
                 VALUES (?, ?, ?, ?, 'pending') 
                 ON DUPLICATE KEY UPDATE email=VALUES(email)"
            );
            
            if ($stmt) {
                $stmt->bind_param("ssss", $college_name, $email, $reference_number, $invitation_date);
                if ($stmt->execute()) {
                    $imported++;
                } else {
                    $errors++;
                }
                $stmt->close();
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => "Imported: $imported colleges, Errors: $errors"
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function get_all_colleges($conn) {
    $search = $_POST['search'] ?? '';
    $status = $_POST['status'] ?? '';
    
    $query = "SELECT * FROM colleges WHERE 1=1";
    
    if (!empty($search)) {
        $search = "%$search%";
        $query .= " AND (college_name LIKE ? OR email LIKE ?)";
    }
    
    if (!empty($status)) {
        $query .= " AND status = '$status'";
    }
    
    $query .= " ORDER BY id DESC LIMIT 1000";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($search)) {
        $stmt->bind_param("ss", $search, $search);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $colleges = [];
    
    while ($row = $result->fetch_assoc()) {
        $colleges[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'colleges' => $colleges,
        'count' => count($colleges)
    ]);
}

function manual_send_email($data, $conn) {
    $college_name = trim($data['college_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $ref_number = trim($data['ref_number'] ?? '');
    $invite_date = trim($data['invite_date'] ?? '');
    
    if (empty($college_name) || empty($email) || empty($ref_number) || empty($invite_date)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields required']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        return;
    }
    
    try {
        // Generate PDF with manual data
        $pdf_file = generate_invitation_pdf_manual($college_name, $ref_number, $invite_date, $conn);
        
        // Send Email
        require 'vendor/autoload.php';
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
        $mail->addAddress($email);
        $mail->Subject = 'Proposal for Skill Training Partnership - ' . $college_name;
        
        $mail->isHTML(true);
        $mail->Body = "
            <html>
            <body style='font-family: Arial; line-height: 1.6; color: #333;'>
                <p>Dear Principal/Director,</p>
                <p>Greetings from <strong>Liberty Foundation</strong>!</p>
                
                <p>We are pleased to send you our <strong>Proposal for Skill Training Partnership</strong> for <strong>" . 
                htmlspecialchars($college_name) . "</strong></p>
                
                <p><strong>Details:</strong></p>
                <ul>
                    <li><strong>Reference Number:</strong> " . htmlspecialchars($ref_number) . "</li>
                    <li><strong>Date:</strong> " . $invite_date . "</li>
                </ul>
                
                <p>The detailed proposal letter is attached to this email.</p>
                
                <p>We would be grateful for an opportunity to discuss this proposal with you and explore how we can partner to enhance skill development at your institution.</p>
                
                <p>Thank you for your time and consideration.</p>
                
                <p style='margin-top: 30px;'>Best regards,<br>
                <strong>" . SENDER_NAME . "</strong><br>
                " . ORGANIZATION_PHONE . "<br>
                " . SENDER_EMAIL . "</p>
            </body>
            </html>
        ";
        
        $mail->addAttachment($pdf_file);
        $mail->send();
        
        echo json_encode(['status' => 'success', 'message' => 'Email sent successfully to ' . $email]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function send_single_email($college_id, $conn) {
    require 'vendor/autoload.php';
    
    if (!$college_id) {
        echo json_encode(['status' => 'error', 'message' => 'College ID required']);
        return;
    }
    
    // Fetch college data
    $stmt = $conn->prepare("SELECT * FROM colleges WHERE id = ?");
    $stmt->bind_param("i", $college_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $college = $result->fetch_assoc();
    $stmt->close();
    
    if (!$college) {
        echo json_encode(['status' => 'error', 'message' => 'College not found']);
        return;
    }
    
    try {
        // Generate PDF
        $pdf_file = generate_invitation_pdf_manual(
            $college['college_name'],
            $college['reference_number'],
            $college['invitation_date'],
            $conn
        );
        
        // Send Email
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
        $mail->addAddress($college['email']);
        $mail->Subject = 'Proposal for Skill Training Partnership - ' . $college['college_name'];
        
        $mail->isHTML(true);
        $mail->Body = "
            <html>
            <body style='font-family: Arial; line-height: 1.6; color: #333;'>
                <p>Dear Principal/Director,</p>
                <p>Greetings from <strong>Liberty Foundation</strong>!</p>
                
                <p>We are pleased to send you our <strong>Proposal for Skill Training Partnership</strong> for <strong>" . 
                htmlspecialchars($college['college_name']) . "</strong></p>
                
                <p><strong>Details:</strong></p>
                <ul>
                    <li><strong>Reference Number:</strong> " . htmlspecialchars($college['reference_number']) . "</li>
                    <li><strong>Date:</strong> " . $college['invitation_date'] . "</li>
                </ul>
                
                <p>The detailed proposal letter is attached to this email.</p>
                
                <p>We would be grateful for an opportunity to discuss this proposal with you and explore how we can partner to enhance skill development at your institution.</p>
                
                <p>Thank you for your time and consideration.</p>
                
                <p style='margin-top: 30px;'>Best regards,<br>
                <strong>" . SENDER_NAME . "</strong><br>
                " . ORGANIZATION_PHONE . "<br>
                " . SENDER_EMAIL . "</p>
            </body>
            </html>
        ";
        
        $mail->addAttachment($pdf_file);
        $mail->send();
        
        // Update status
        $update = $conn->prepare("UPDATE colleges SET status = 'sent' WHERE id = ?");
        $update->bind_param("i", $college_id);
        $update->execute();
        $update->close();
        
        // Log
        $log = $conn->prepare("INSERT INTO email_logs (college_id, status, error_message) VALUES (?, 'sent', NULL)");
        $log->bind_param("i", $college_id);
        $log->execute();
        $log->close();
        
        echo json_encode(['status' => 'success', 'message' => 'Email sent to ' . $college['email']]);
        
    } catch (Exception $e) {
        // Log error
        $error_msg = $e->getMessage();
        $log = $conn->prepare("INSERT INTO email_logs (college_id, status, error_message) VALUES (?, 'failed', ?)");
        $log->bind_param("is", $college_id, $error_msg);
        $log->execute();
        $log->close();
        
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function send_all_pending_emails($conn) {
    $result = $conn->query("SELECT id FROM colleges WHERE status = 'pending' LIMIT 100");
    
    $sent = 0;
    $failed = 0;
    
    while ($row = $result->fetch_assoc()) {
        ob_start();
        send_single_email($row['id'], $conn);
        $response = json_decode(ob_get_clean());
        
        if ($response->status === 'success') {
            $sent++;
        } else {
            $failed++;
        }
        
        sleep(1); // Rate limiting
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => "Sent: $sent, Failed: $failed",
        'sent_count' => $sent,
        'failed_count' => $failed
    ]);
}

function get_statistics($conn) {
    $total = $conn->query("SELECT COUNT(*) as count FROM colleges")->fetch_assoc()['count'];
    $sent = $conn->query("SELECT COUNT(*) as count FROM colleges WHERE status = 'sent'")->fetch_assoc()['count'];
    $pending = $conn->query("SELECT COUNT(*) as count FROM colleges WHERE status = 'pending'")->fetch_assoc()['count'];
    $failed = $conn->query("SELECT COUNT(*) as count FROM colleges WHERE status = 'failed'")->fetch_assoc()['count'];
    
    echo json_encode([
        'status' => 'success',
        'total' => $total,
        'sent' => $sent,
        'pending' => $pending,
        'failed' => $failed
    ]);
}

// ==================== CUSTOM TEMPLATE (uploaded PDF/image design) ====================

function get_template_settings_row($conn) {
    $result = $conn->query("SELECT * FROM template_settings WHERE id = 1 LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// Admin uploads a PDF or image of the letter design. If it's a PDF and the
// server has Imagick+Ghostscript, page 1 is converted to a PNG background.
// Otherwise a PNG/JPG upload is used directly. Position markers default to
// the center of the page until the admin places them (save_template_positions).
function upload_template($conn) {
    if (!isset($_FILES['template']) || $_FILES['template']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
        return;
    }

    $tmp = $_FILES['template']['tmp_name'];
    $origName = $_FILES['template']['name'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    @mkdir('assets/template', 0777, true);
    $destPng = 'assets/template/design_' . time() . '.png';

    if ($ext === 'pdf') {
        if (!class_exists('Imagick')) {
            echo json_encode([
                'status' => 'error',
                'message' => 'This server cannot convert PDF to image (Imagick not available). Please export/save your design as a PNG or JPG and upload that instead.'
            ]);
            return;
        }
        try {
            $imagick = new Imagick();
            $imagick->setResolution(200, 200);
            $imagick->readImage($tmp . '[0]'); // first page only
            $imagick->setImageFormat('png');
            $imagick->flattenImages();
            $imagick->writeImage($destPng);
            $imagick->clear();
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'PDF conversion failed: ' . $e->getMessage()]);
            return;
        }
    } elseif (in_array($ext, ['png', 'jpg', 'jpeg'])) {
        move_uploaded_file($tmp, $destPng);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Please upload a PDF, PNG, or JPG file']);
        return;
    }

    $dims = getimagesize($destPng);
    if (!$dims) {
        echo json_encode(['status' => 'error', 'message' => 'Could not read the uploaded image']);
        return;
    }
    [$w, $h] = $dims;

    $stmt = $conn->prepare(
        "INSERT INTO template_settings (id, background_image, image_width, image_height)
         VALUES (1, ?, ?, ?)
         ON DUPLICATE KEY UPDATE background_image = VALUES(background_image), image_width = VALUES(image_width), image_height = VALUES(image_height)"
    );
    $stmt->bind_param("sii", $destPng, $w, $h);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'status' => 'success',
        'image_url' => $destPng,
        'width' => $w,
        'height' => $h
    ]);
}

function save_template_positions($data, $conn) {
    $stmt = $conn->prepare(
        "UPDATE template_settings SET
            college_top = ?, college_left = ?, college_font_size = ?,
            ref_top = ?, ref_left = ?, ref_font_size = ?,
            date_top = ?, date_left = ?, date_font_size = ?
         WHERE id = 1"
    );
    $college_top = floatval($data['college_top'] ?? 20);
    $college_left = floatval($data['college_left'] ?? 6);
    $college_font_size = intval($data['college_font_size'] ?? 16);
    $ref_top = floatval($data['ref_top'] ?? 15.6);
    $ref_left = floatval($data['ref_left'] ?? 20);
    $ref_font_size = intval($data['ref_font_size'] ?? 14);
    $date_top = floatval($data['date_top'] ?? 15.6);
    $date_left = floatval($data['date_left'] ?? 76);
    $date_font_size = intval($data['date_font_size'] ?? 14);

    $stmt->bind_param(
        "ddiddiddi",
        $college_top, $college_left, $college_font_size,
        $ref_top, $ref_left, $ref_font_size,
        $date_top, $date_left, $date_font_size
    );
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success', 'message' => 'Positions saved']);
}

function get_template($conn) {
    $row = get_template_settings_row($conn);
    if (!$row || empty($row['background_image'])) {
        echo json_encode(['status' => 'success', 'has_template' => false]);
        return;
    }
    echo json_encode(['status' => 'success', 'has_template' => true, 'template' => $row]);
}

function reset_template($conn) {
    $conn->query("DELETE FROM template_settings WHERE id = 1");
    echo json_encode(['status' => 'success', 'message' => 'Reverted to default design']);
}

function generate_invitation_pdf_manual($college_name, $ref_number, $invite_date, $conn = null) {
    require 'vendor/autoload.php';

    if ($conn === null) {
        global $conn;
    }

    $date_obj_check = DateTime::createFromFormat('Y-m-d', $invite_date);
    $formatted_date_check = $date_obj_check === false ? $invite_date : $date_obj_check->format('d/m/Y');

    $templateRow = ($conn instanceof mysqli) ? get_template_settings_row($conn) : null;

    if ($templateRow && !empty($templateRow['background_image']) && file_exists($templateRow['background_image'])) {
        return generate_invitation_pdf_custom($college_name, $ref_number, $formatted_date_check, $templateRow);
    }

    return generate_invitation_pdf_default($college_name, $ref_number, $invite_date);
}

// Overlays College Name / Ref No / Date on top of an admin-uploaded background image
// at the positions saved via the Template tab's position picker.
function generate_invitation_pdf_custom($college_name, $ref_number, $formatted_date, $tpl) {
    require 'vendor/autoload.php';

    $ref_suffix = preg_match('/(\d{1,3})\s*$/', $ref_number, $m)
        ? str_pad($m[1], 3, '0', STR_PAD_LEFT)
        : str_pad(substr(md5($college_name), 0, 3), 3, '0', STR_PAD_LEFT);

    $imgPath = __DIR__ . '/' . $tpl['background_image'];
    $w = max(1, (int) $tpl['image_width']);
    $h = max(1, (int) $tpl['image_height']);

    // Fix page width at 210mm (A4 width) and scale height to match the
    // uploaded image's aspect ratio so nothing gets stretched or cropped.
    $page_w_mm = 210;
    $page_h_mm = round($page_w_mm * ($h / $w), 2);

    // mPDF doesn't reliably support % for position:absolute top/left, so the
    // saved percentages (from the click-to-place editor) are converted to mm
    // here, which it does support.
    $ref_top_mm = round($page_h_mm * (floatval($tpl['ref_top']) / 100), 2);
    $ref_left_mm = round($page_w_mm * (floatval($tpl['ref_left']) / 100), 2);
    $date_top_mm = round($page_h_mm * (floatval($tpl['date_top']) / 100), 2);
    $date_left_mm = round($page_w_mm * (floatval($tpl['date_left']) / 100), 2);
    $college_top_mm = round($page_h_mm * (floatval($tpl['college_top']) / 100), 2);
    $college_left_mm = round($page_w_mm * (floatval($tpl['college_left']) / 100), 2);

    // mPDF's line-breaking can wrap short absolutely-positioned text (most
    // noticeably pure numbers, e.g. "123" -> "12" / "3") when the element has
    // no explicit width. Giving it the remaining room to the page's right
    // edge fixes that without affecting where the text starts.
    $ref_width_mm = max(10, $page_w_mm - $ref_left_mm - 5);
    $date_width_mm = max(10, $page_w_mm - $date_left_mm - 5);
    $college_width_mm = max(10, $page_w_mm - $college_left_mm - 5);

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            @page { margin: 0; }
            body {
                margin: 0;
                padding: 0;
                font-family: poppins, sans-serif;
                width: {$page_w_mm}mm;
                height: {$page_h_mm}mm;
            }
            /* mPDF doesn't reliably scale CSS background-image (background-size
               is ignored, so the image gets cropped at natural size instead of
               stretched to the page) — an <img> sized to the page works instead.
               It also only positions position:absolute elements correctly
               relative to the page when they are direct children of <body> —
               nesting them inside another positioned wrapper div breaks the
               offsets (they all collapse to the top-left corner instead). */
            .bg { position: absolute; top: 0; left: 0; width: {$page_w_mm}mm; height: {$page_h_mm}mm; }
            .overlay { position: absolute; color: #1a1a1a; white-space: nowrap; }
        </style>
    </head>
    <body>
        <img class='bg' src='{$imgPath}'>
        <div class='overlay' style='top:{$ref_top_mm}mm; left:{$ref_left_mm}mm; width:{$ref_width_mm}mm; font-size:" . $tpl['ref_font_size'] . "px; font-family: poppinssemibold;'>{$ref_suffix}</div>
        <div class='overlay' style='top:{$date_top_mm}mm; left:{$date_left_mm}mm; width:{$date_width_mm}mm; font-size:" . $tpl['date_font_size'] . "px; font-family: poppinssemibold;'>{$formatted_date}</div>
        <div class='overlay' style='top:{$college_top_mm}mm; left:{$college_left_mm}mm; width:{$college_width_mm}mm; font-size:" . $tpl['college_font_size'] . "px; font-family: poppinssemibold;'>" . htmlspecialchars($college_name) . "</div>
    </body>
    </html>
    ";

    $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];
    $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => [$page_w_mm, $page_h_mm],
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0,
        'fontDir' => array_merge($fontDirs, [__DIR__ . '/assets/fonts']),
        'fontdata' => $fontData + [
            'poppins' => ['R' => 'Poppins-Regular.ttf', 'B' => 'Poppins-Bold.ttf'],
            'poppinsmedium' => ['R' => 'Poppins-Medium.ttf'],
            'poppinssemibold' => ['R' => 'Poppins-SemiBold.ttf'],
        ],
        'default_font' => 'poppins',
    ]);

    $mpdf->WriteHTML($html);

    @mkdir('pdfs', 0777, true);
    $filename = 'pdfs/liberty_' . time() . '_' . md5($college_name) . '.pdf';
    $mpdf->Output($filename, 'F');

    return $filename;
}

function generate_invitation_pdf_default($college_name, $ref_number, $invite_date) {
    require 'vendor/autoload.php';

    // Format date to DD/MM/YYYY if needed
    $date_obj = DateTime::createFromFormat('Y-m-d', $invite_date);
    if ($date_obj === false) {
        $formatted_date = $invite_date; // Use as is if already formatted
    } else {
        $formatted_date = $date_obj->format('d/m/Y');
    }

    $ref_suffix = preg_match('/(\d{1,3})\s*$/', $ref_number, $m)
        ? str_pad($m[1], 3, '0', STR_PAD_LEFT)
        : str_pad(substr(md5($college_name), 0, 3), 3, '0', STR_PAD_LEFT);

    // Generate HTML content - EXACT same template as PDF
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body {
                font-family: poppins, sans-serif;
                margin: 0;
                padding: 0;
            }
            .container {
                background: white;
                padding: 14px 40px;
            }
            .header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 0;
                padding-bottom: 15px;
            }
            .logo-section {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            .logo-img {
                width: 78px;
            }
            .logo-since {
                text-align: center;
                font-size: 9px;
                font-family: poppinssemibold;
                color: #333;
                margin-top: 4px;
                letter-spacing: 1px;
            }
            .org-title {
                font-size: 32px;
                font-family: poppinssemibold;
                color: #c41e3a;
                margin: 0;
                letter-spacing: 1px;
            }
            .org-title-sub {
                font-size: 26px;
                font-family: poppinssemibold;
                color: #c41e3a;
                margin: -4px 0 0 0;
                letter-spacing: 1px;
            }
            .org-subtitle {
                font-size: 11px;
                color: #444;
                letter-spacing: 2px;
                margin: 4px 0 0 0;
                font-family: poppinssemibold;
            }
            .contact-info {
                text-align: right;
                font-size: 12px;
                line-height: 1.9;
            }
            .contact-info p { margin: 0; }
            .contact-info img { width: 13px; vertical-align: middle; margin-right: 5px; }
            .divider {
                height: 6px;
                background: linear-gradient(to right, #c41e3a 0%, #c41e3a 68%, #f4a935 68%, #f4a935 100%);
                clip-path: polygon(0 0, 68% 0, 72% 100%, 0% 100%);
                position: relative;
            }
            .divider-bar {
                height: 6px;
                width: 100%;
                background: #c41e3a;
            }
            .divider2 {
                height: 6px;
                width: 100%;
                background: linear-gradient(to right, #c41e3a 0%, #c41e3a 70%, #f4a935 100%);
            }
            .ref-date {
                display: flex;
                justify-content: space-between;
                margin: 14px 0 12px 0;
                font-size: 13px;
            }
            .recipient {
                margin-bottom: 12px;
                line-height: 1.5;
                font-size: 13px;
            }
            .recipient p { margin: 3px 0; }
            .recipient-college {
                font-weight: bold;
                font-size: 14px;
            }
            .subject {
                font-weight: bold;
                font-size: 13px;
                margin: 8px 0 8px 0;
            }
            .salutation {
                font-weight: bold;
                margin-bottom: 6px;
                font-size: 13px;
            }
            .content {
                font-size: 12.5px;
                line-height: 1.5;
                text-align: justify;
                margin-bottom: 8px;
            }
            .content p { margin: 5px 0; }
            h4.section-title {
                margin: 10px 0 5px 0;
                color: #c41e3a;
                font-size: 14px;
                font-family: poppinssemibold;
                border-bottom: 1.5px solid #c41e3a;
                display: inline-block;
                padding-bottom: 2px;
            }
            .two-col {
                display: flex;
                gap: 20px;
            }
            .two-col > div { flex: 1; }
            .resp-banner {
                background: #c41e3a;
                color: white;
                font-family: poppinssemibold;
                font-size: 12px;
                padding: 6px 12px;
                border-radius: 4px;
                margin: 10px 0 8px 0;
            }
            .closing {
                margin-top: 8px;
                font-size: 13px;
                line-height: 1.8;
            }
            .closing p { margin: 2px 0; }
            .footer-bar {
                text-align: center;
                background: #c41e3a;
                color: white;
                padding: 8px;
                font-size: 11px;
                font-family: poppinssemibold;
                letter-spacing: 1px;
            }
            .vdivider { border-left: 1px solid #c41e3a; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo-section'>
                    <div>
                        <img class='logo-img' src='" . __DIR__ . "/assets/icons/logo.png'>
                        <div class='logo-since'>SINCE - 2008</div>
                    </div>
                    <div>
                        <div class='org-title'>LIBERTY</div>
                        <div class='org-title-sub'>FOUNDATION</div>
                        <div class='org-subtitle'>SKILLING &middot; EMPOWERING &middot; TRANSFORMING</div>
                    </div>
                </div>
                <div class='contact-info'>
                    <p><img src='" . __DIR__ . "/assets/icons/pin.png'><strong>Bardhaman, West Bengal</strong></p>
                    <p><img src='" . __DIR__ . "/assets/icons/phone.png'>" . ORGANIZATION_PHONE . "</p>
                    <p><img src='" . __DIR__ . "/assets/icons/email.png'>" . ORGANIZATION_EMAIL . "</p>
                    <p><img src='" . __DIR__ . "/assets/icons/web.png'>" . ORGANIZATION_WEBSITE . "</p>
                </div>
            </div>
        </div>
        <div class='divider2'></div>
        <div class='container'>
            <div class='ref-date'>
                <div><strong>Ref No: LF/SKILL/2026/{$ref_suffix}</strong></div>
                <div><strong>Date: " . $formatted_date . "</strong></div>
            </div>

            <div class='recipient'>
                <p><strong>To,</strong></p>
                <p>The Principal / Director</p>
                <p class='recipient-college'>" . htmlspecialchars($college_name) . "</p>
            </div>

            <div class='subject'>
                Subject: Proposal for Skill Training Partnership in Share Trading, Content Creation,
                Digital Marketing &amp; Web Development, Competitive Exam
            </div>

            <div class='salutation'>Respected Sir/Madam,</div>

            <div class='content'>
                <p>With due respect, we Liberty Foundation, an educational organization working in skill
                development and training, hereby submit our proposal to collaborate with College and School
                for conducting skill-based training programs for your students.</p>
            </div>

            <table style='width:100%; border-collapse:collapse;' cellpadding='0' cellspacing='0'>
                <tr>
                    <td style='width:55%; vertical-align:top; padding-right:12px;'>
                        <h4 class='section-title'>1. PROPOSED COURSES</h4>
                        <div class='content'>
                            <p>We propose to start the following 6-month skill training programs at your college campus:</p>
                            <p>1. Share Trading &amp; Investment<br>
                            2. Content Creation - YouTube, Instagram, Shorts<br>
                            3. Digital Marketing - SEO, Social Media, Ads<br>
                            4. Web Development - HTML, CSS, JavaScript, WordPress<br>
                            5. Competitive Exam</p>
                        </div>
                    </td>
                    <td style='width:45%; vertical-align:top; padding-left:12px; border-left:1px solid #c41e3a;'>
                        <h4 class='section-title'>2. FEE STRUCTURE &amp; REVENUE SHARING</h4>
                        <div class='content'>
                            <p>1. Course Fee: &#8377;1200 per student for 6 months<br>
                            2. Revenue Sharing:<br>
                            &nbsp;&nbsp;College and School: 20%<br>
                            &nbsp;&nbsp;Liberty Foundation: 80%<br>
                            3. Payment: college and school share to be settled quarterly after deduction of expenses</p>
                        </div>
                    </td>
                </tr>
            </table>

            <h4 class='section-title'>3. RESPONSIBILITIES</h4>
            <table style='width:100%; border-collapse:collapse;' cellpadding='0' cellspacing='0'>
                <tr>
                    <td style='width:50%; vertical-align:top; padding-right:10px;'>
                        <div class='resp-banner'>Liberty Foundation will take responsibility for:</div>
                        <div class='content' style='font-size:11.5px;'>
                            <p>1. Teaching &amp; Faculty: Provide qualified trainers and guest experts<br>
                            2. Curriculum: Design industry-relevant syllabus, study material, and practical assignments<br>
                            3. Certificate: Issue joint certificate from Liberty Foundation after course completion<br>
                            4. Placement Support: Provide internship and freelance project guidance</p>
                        </div>
                    </td>
                    <td style='width:50%; vertical-align:top; padding-left:10px; border-left:1px solid #c41e3a;'>
                        <div class='resp-banner'>College and School will provide:</div>
                        <div class='content' style='font-size:11.5px;'>
                            <p>1. Classroom/Computer Lab for conducting classes<br>
                            2. Student Mobilization: Support in admissions, counseling, and promotion within college<br>
                            3. Basic Infrastructure: Electricity, seating, projector if available</p>
                        </div>
                    </td>
                </tr>
            </table>

            <h4 class='section-title'>4. BENEFITS TO STUDENTS &amp; COLLEGE</h4>
            <div class='content'>
                <p>1. Industry-relevant job and freelance skills for students<br>
                2. Additional revenue for college with zero investment<br>
                3. Enhancement of college profile with modern skill courses<br>
                4. Certificate that adds value to student resume</p>

                <p>We are ready to start the program within 15 days of approval and sign an MOU for the same.</p>

                <p>We kindly request you to grant us permission to conduct these skill training programs at your prestigious
                college on the above terms.</p>

                <p>Please find enclosed:<br>
                1. Liberty Foundation Registration Certificate<br>
                2. Course Curriculum Outline<br>
                3. Organization Profile</p>

                <p>We would be grateful for an opportunity to discuss this proposal with you.</p>
            </div>

            <div class='closing'>
                <p>Thanking you,</p>
                <p>Yours faithfully,</p>
                <p>&nbsp;</p>
                <p><strong>For Liberty Foundation</strong></p>
                <p>Name: Prosenjit Roy<br>
                Designation: Director/Authorised Signatory<br>
                Mobile: +91 8159 832 788<br>
                Email: libertyfoundation4news@gmail.com</p>
                <p>Seal &amp; Signature</p>
            </div>
        </div>
        <div class='divider2'></div>
        <div class='footer-bar'>
            LIBERTY FOUNDATION &ndash; SKILLING TODAY, EMPOWERING TOMORROW.
        </div>
    </body>
    </html>
    ";

    try {
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => [270.93, 420], // matches original design's tall single-page layout
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'fontDir' => array_merge($fontDirs, [__DIR__ . '/assets/fonts']),
            'fontdata' => $fontData + [
                'poppins' => ['R' => 'Poppins-Regular.ttf', 'B' => 'Poppins-Bold.ttf'],
                'poppinsmedium' => ['R' => 'Poppins-Medium.ttf'],
                'poppinssemibold' => ['R' => 'Poppins-SemiBold.ttf'],
            ],
            'default_font' => 'poppins',
        ]);

        $mpdf->WriteHTML($html);

        @mkdir('pdfs', 0777, true);
        $filename = 'pdfs/liberty_' . time() . '_' . md5($college_name) . '.pdf';
        $mpdf->Output($filename, 'F');

        return $filename;
    } catch (Exception $e) {
        throw new Exception("PDF Generation Error: " . $e->getMessage());
    }
}

// If not POST request, show dashboard
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liberty Foundation - Email System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #c41e3a 0%, #8b1428 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            text-align: center;
            border-top: 5px solid #c41e3a;
        }
        
        .header h1 { color: #c41e3a; margin-bottom: 10px; font-size: 32px; }
        .header p { color: #666; font-size: 14px; }
        .header .tag { color: #c41e3a; font-weight: bold; }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 5px solid #c41e3a;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #c41e3a;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid white;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            background: rgba(255,255,255,0.3);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px 5px 0 0;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
        }
        
        .tab-btn.active {
            background: white;
            color: #c41e3a;
        }

        .tpl-marker {
            position: absolute;
            display: none;
            color: white;
            font-size: 11px;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 4px;
            transform: translate(-4px, -4px);
            cursor: move;
            white-space: nowrap;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }
        .tpl-marker.placed { display: block; }
        
        .tab-content {
            background: white;
            padding: 30px;
            border-radius: 0 10px 10px 10px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            display: none;
        }
        
        .tab-content.active { display: block; }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        input[type="file"],
        input[type="text"],
        input[type="email"],
        input[type="date"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="date"]:focus,
        select:focus { border-color: #c41e3a; }
        
        button {
            background: linear-gradient(135deg, #c41e3a 0%, #8b1428 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
            margin-right: 10px;
            margin-top: 10px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(196, 30, 58, 0.4);
        }
        
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
            margin-bottom: 15px;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
            border-top: 2px solid #c41e3a;
        }
        
        tr:hover { background: #f8f9fa; }
        
        .status-sent { color: green; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .status-failed { color: red; font-weight: bold; }
        
        .action-btn {
            background: #17a2b8;
            padding: 6px 12px;
            font-size: 12px;
            margin: 0;
        }
        
        .loader {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #c41e3a;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .two-column { grid-template-columns: 1fr; }
            .tabs { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Liberty Foundation Email System</h1>
            <p>Send personalized skill training proposal letters with <span class="tag">Exact PDF Template</span></p>
            <p style="font-size: 12px; color: #999; margin-top: 10px;">Ref #, College Name & Date auto-fill | Everything else same as template</p>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="number" id="stat-total">0</div>
                <div class="label">Total Colleges</div>
            </div>
            <div class="stat-card">
                <div class="number" id="stat-sent">0</div>
                <div class="label">Emails Sent</div>
            </div>
            <div class="stat-card">
                <div class="number" id="stat-pending">0</div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="number" id="stat-failed">0</div>
                <div class="label">Failed</div>
            </div>
        </div>
        
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('upload')">1. Upload Excel</button>
            <button class="tab-btn" onclick="switchTab('manual')">2. Manual Send</button>
            <button class="tab-btn" onclick="switchTab('manage')">3. Manage Colleges</button>
            <button class="tab-btn" onclick="switchTab('send')">4. Send Emails</button>
            <button class="tab-btn" onclick="switchTab('template')">5. Template Design</button>
        </div>
        
        <!-- UPLOAD TAB -->
        <div id="upload" class="tab-content active">
            <h2>📤 Upload College Data (Excel File)</h2>
            <p style="color: #666; margin-bottom: 20px; font-size: 14px;">
                <strong>Format Required:</strong> Column A - College Name | Column B - Email | Column C - Reference Number | Column D - Date (YYYY-MM-DD)<br>
                Example: ABC College | abc@college.com | LF/SKILL/2026/001 | 2024-01-15
            </p>
            
            <div class="form-group">
                <label>📁 Select Excel File (.xlsx):</label>
                <input type="file" id="excelFile" accept=".xlsx,.xls" onchange="previewFile()">
                <p id="fileInfo" style="font-size: 12px; color: #999; margin-top: 10px;"></p>
            </div>
            
            <button onclick="uploadExcel()">Upload & Import Colleges</button>
            <div id="uploadMessage"></div>
        </div>
        
        <!-- MANUAL SEND TAB -->
        <div id="manual" class="tab-content">
            <h2>✍️ Send Single Email (Manual Entry)</h2>
            <p style="color: #666; margin-bottom: 20px;">Send email without adding to database</p>
            
            <div class="two-column">
                <div class="form-group">
                    <label>College Name:</label>
                    <input type="text" id="manual_college" placeholder="e.g., ABC College">
                </div>
                <div class="form-group">
                    <label>Email Address:</label>
                    <input type="email" id="manual_email" placeholder="e.g., principal@college.com">
                </div>
            </div>
            
            <div class="two-column">
                <div class="form-group">
                    <label>Reference Number:</label>
                    <input type="text" id="manual_ref" placeholder="e.g., LF/SKILL/2026/001">
                </div>
                <div class="form-group">
                    <label>Date (DD/MM/YYYY):</label>
                    <input type="text" id="manual_date" placeholder="e.g., 15/01/2024" onchange="validateDate()">
                </div>
            </div>
            
            <button onclick="sendManualEmail()">Send Email with PDF</button>
            <div id="manualMessage"></div>
        </div>
        
        <!-- MANAGE TAB -->
        <div id="manage" class="tab-content">
            <h2>📋 Manage Colleges</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 150px; gap: 10px; margin-bottom: 20px;">
                <input type="text" id="searchBox" placeholder="Search by college name or email..." onkeyup="searchColleges()">
                <select id="statusFilter" onchange="loadColleges()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="sent">Sent</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            
            <button onclick="loadColleges()">🔄 Refresh</button>
            
            <div id="collegeTableContainer"></div>
        </div>
        
        <!-- SEND TAB -->
        <div id="send" class="tab-content">
            <h2>📧 Send All Pending Emails</h2>
            
            <p style="color: #666; margin-bottom: 20px;">
                Send personalized PDFs with Ref #, College Name & Date to all pending colleges.<br>
                <strong>Rate Limited:</strong> 1 email per second to avoid spam filters (Brevo: 300/day)
            </p>
            
            <button onclick="sendAllEmails()" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); font-size: 16px; padding: 15px 30px;">
                ✉️ Send All Pending Emails
            </button>
            
            <div id="sendMessage"></div>
        </div>

        <!-- TEMPLATE DESIGN TAB -->
        <div id="template" class="tab-content">
            <h2>🎨 Letter Template Design</h2>
            <p style="color: #666; margin-bottom: 20px;">
                Upload a PNG/JPG image of your letter design (export your Canva/PDF design as an image),
                then click on the preview to place where <strong>College Name</strong>, <strong>Ref No</strong>
                and <strong>Date</strong> should appear. Every generated PDF will use this exact design with
                those three fields filled in dynamically.<br>
                <small>PDF upload also works if the server has Imagick installed — otherwise export as PNG/JPG first.</small>
            </p>

            <div class="form-group">
                <label>Upload Design (PNG, JPG, or PDF)</label>
                <input type="file" id="templateFile" accept=".png,.jpg,.jpeg,.pdf">
                <button onclick="uploadTemplate()" style="margin-top:10px;">Upload Design</button>
                <button onclick="resetTemplate()" style="margin-top:10px; background:#999;">Reset to Default Design</button>
            </div>

            <div id="templateMessage"></div>

            <div id="templateEditor" style="display:none; margin-top:20px;">
                <p style="color:#666;">Click on the image below to place each marker. Selected field: <strong id="activeFieldLabel">College Name</strong></p>
                <div style="margin-bottom:10px;">
                    <button onclick="setActiveField('college')" id="btn-college" style="background:#c41e3a;">📍 College Name</button>
                    <button onclick="setActiveField('ref')" id="btn-ref">📍 Ref No</button>
                    <button onclick="setActiveField('date')" id="btn-date">📍 Date</button>
                    <button onclick="saveTemplatePositions()" style="background:#28a745;">💾 Save Positions</button>
                </div>
                <div id="templateCanvasWrap" style="position:relative; display:inline-block; border:1px solid #ddd; max-width:100%;">
                    <img id="templateImg" style="max-width:100%; display:block;">
                    <div id="marker-college" class="tpl-marker" style="background:#c41e3a;">College Name</div>
                    <div id="marker-ref" class="tpl-marker" style="background:#f4a935;">Ref No</div>
                    <div id="marker-date" class="tpl-marker" style="background:#2874a6;">Date</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

            document.getElementById(tab).classList.add('active');
            event.target.classList.add('active');

            if (tab === 'manage') loadColleges();
            if (tab === 'template') loadTemplate();
        }
        
        function previewFile() {
            const file = document.getElementById('excelFile').files[0];
            if (file) {
                document.getElementById('fileInfo').textContent = 
                    `✓ File: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
            }
        }
        
        function uploadExcel() {
            const file = document.getElementById('excelFile').files[0];
            if (!file) {
                showMessage('uploadMessage', 'Please select an Excel file', 'error');
                return;
            }
            
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Uploading...';
            
            const formData = new FormData();
            formData.append('action', 'upload_excel');
            formData.append('file', file);
            
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    showMessage('uploadMessage', data.message, data.status);
                    if (data.status === 'success') {
                        loadStatistics();
                        document.getElementById('excelFile').value = '';
                        document.getElementById('fileInfo').textContent = '';
                    }
                    btn.disabled = false;
                    btn.textContent = 'Upload & Import Colleges';
                });
        }
        
        function validateDate() {
            const dateInput = document.getElementById('manual_date').value;
            const regex = /^\d{2}\/\d{2}\/\d{4}$/;
            if (dateInput && !regex.test(dateInput)) {
                alert('Please use DD/MM/YYYY format');
            }
        }
        
        function sendManualEmail() {
            const college = document.getElementById('manual_college').value.trim();
            const email = document.getElementById('manual_email').value.trim();
            const ref = document.getElementById('manual_ref').value.trim();
            const date = document.getElementById('manual_date').value.trim();
            
            if (!college || !email || !ref || !date) {
                showMessage('manualMessage', 'All fields required!', 'error');
                return;
            }
            
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Sending...';
            
            const formData = new FormData();
            formData.append('action', 'manual_send');
            formData.append('college_name', college);
            formData.append('email', email);
            formData.append('ref_number', ref);
            formData.append('invite_date', date);
            
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    showMessage('manualMessage', data.message, data.status);
                    if (data.status === 'success') {
                        document.getElementById('manual_college').value = '';
                        document.getElementById('manual_email').value = '';
                        document.getElementById('manual_ref').value = '';
                        document.getElementById('manual_date').value = '';
                    }
                    btn.disabled = false;
                    btn.textContent = 'Send Email with PDF';
                });
        }
        
        function loadColleges() {
            const search = document.getElementById('searchBox')?.value || '';
            const status = document.getElementById('statusFilter')?.value || '';
            
            const formData = new FormData();
            formData.append('action', 'get_colleges');
            formData.append('search', search);
            formData.append('status', status);
            
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderTable(data.colleges);
                    }
                });
        }
        
        function searchColleges() {
            loadColleges();
        }
        
        function renderTable(colleges) {
            if (colleges.length === 0) {
                document.getElementById('collegeTableContainer').innerHTML = 
                    '<p style="text-align: center; color: #999; padding: 40px;">No colleges found.</p>';
                return;
            }
            
            let html = `
                <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>College Name</th>
                        <th>Email</th>
                        <th>Ref #</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
            `;
            
            colleges.forEach((c, idx) => {
                html += `
                    <tr>
                        <td>${c.id}</td>
                        <td>${c.college_name}</td>
                        <td>${c.email}</td>
                        <td>${c.reference_number}</td>
                        <td>${c.invitation_date}</td>
                        <td><span class="status-${c.status}">${c.status}</span></td>
                        <td><button class="action-btn" onclick="sendEmail(${c.id})">Send</button></td>
                    </tr>
                `;
            });
            
            html += `</tbody></table>`;
            document.getElementById('collegeTableContainer').innerHTML = html;
        }
        
        function sendEmail(collegeId) {
            const formData = new FormData();
            formData.append('action', 'send_email');
            formData.append('college_id', collegeId);
            
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    alert(data.message);
                    loadColleges();
                    loadStatistics();
                });
        }
        
        function sendAllEmails() {
            if (!confirm('Send emails to ALL pending colleges? This will take time based on rate limit.')) return;
            
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = 'Sending... <span class="loader"></span>';
            
            const formData = new FormData();
            formData.append('action', 'send_all');
            
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    showMessage('sendMessage', `Sent: ${data.sent_count}, Failed: ${data.failed_count}`, data.status);
                    loadStatistics();
                    loadColleges();
                    btn.disabled = false;
                    btn.innerHTML = '✉️ Send All Pending Emails';
                });
        }
        
        function loadStatistics() {
            const formData = new FormData();
            formData.append('action', 'get_stats');
            
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    document.getElementById('stat-total').textContent = data.total;
                    document.getElementById('stat-sent').textContent = data.sent;
                    document.getElementById('stat-pending').textContent = data.pending;
                    document.getElementById('stat-failed').textContent = data.failed;
                });
        }
        
        function showMessage(elementId, message, type) {
            const el = document.getElementById(elementId);
            el.innerHTML = `<div class="message ${type}">💬 ${message}</div>`;
        }

        // ==================== TEMPLATE DESIGN TAB ====================
        let activeField = 'college';
        let templatePositions = {
            college: { top: 20, left: 6 },
            ref: { top: 15.6, left: 20 },
            date: { top: 15.6, left: 76 }
        };

        function setActiveField(field) {
            activeField = field;
            ['college', 'ref', 'date'].forEach(f => {
                document.getElementById('btn-' + f).style.opacity = (f === field) ? '1' : '0.5';
            });
            document.getElementById('activeFieldLabel').textContent =
                field === 'college' ? 'College Name' : (field === 'ref' ? 'Ref No' : 'Date');
        }

        function uploadTemplate() {
            const file = document.getElementById('templateFile').files[0];
            if (!file) { showMessage('templateMessage', 'Please choose a file first', 'error'); return; }

            const formData = new FormData();
            formData.append('action', 'upload_template');
            formData.append('template', file);

            showMessage('templateMessage', 'Uploading...', 'success');

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        showMessage('templateMessage', 'Design uploaded! Now place the 3 markers below.', 'success');
                        showTemplateEditor(data.image_url);
                    } else {
                        showMessage('templateMessage', data.message, 'error');
                    }
                });
        }

        function resetTemplate() {
            if (!confirm('Revert to the built-in default letter design?')) return;
            const formData = new FormData();
            formData.append('action', 'reset_template');
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    showMessage('templateMessage', data.message, 'success');
                    document.getElementById('templateEditor').style.display = 'none';
                });
        }

        function loadTemplate() {
            const formData = new FormData();
            formData.append('action', 'get_template');
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.has_template) {
                        const t = data.template;
                        templatePositions = {
                            college: { top: parseFloat(t.college_top), left: parseFloat(t.college_left) },
                            ref: { top: parseFloat(t.ref_top), left: parseFloat(t.ref_left) },
                            date: { top: parseFloat(t.date_top), left: parseFloat(t.date_left) }
                        };
                        showTemplateEditor(t.background_image);
                    } else {
                        showMessage('templateMessage', 'Using the built-in default design. Upload an image above to customize it.', 'success');
                        document.getElementById('templateEditor').style.display = 'none';
                    }
                });
        }

        function showTemplateEditor(imageUrl) {
            document.getElementById('templateEditor').style.display = 'block';
            const img = document.getElementById('templateImg');
            img.src = imageUrl + '?t=' + Date.now();
            img.onload = () => {
                ['college', 'ref', 'date'].forEach(f => renderMarker(f));
            };
            setActiveField('college');
        }

        function renderMarker(field) {
            const pos = templatePositions[field];
            const marker = document.getElementById('marker-' + field);
            marker.style.left = pos.left + '%';
            marker.style.top = pos.top + '%';
            marker.classList.add('placed');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const wrap = document.getElementById('templateCanvasWrap');
            wrap.addEventListener('click', (e) => {
                if (e.target.id !== 'templateImg') return;
                const img = document.getElementById('templateImg');
                const rect = img.getBoundingClientRect();
                const leftPct = ((e.clientX - rect.left) / rect.width) * 100;
                const topPct = ((e.clientY - rect.top) / rect.height) * 100;
                templatePositions[activeField] = { top: topPct.toFixed(2), left: leftPct.toFixed(2) };
                renderMarker(activeField);
            });
        });

        function saveTemplatePositions() {
            const formData = new FormData();
            formData.append('action', 'save_template_positions');
            formData.append('college_top', templatePositions.college.top);
            formData.append('college_left', templatePositions.college.left);
            formData.append('college_font_size', 16);
            formData.append('ref_top', templatePositions.ref.top);
            formData.append('ref_left', templatePositions.ref.left);
            formData.append('ref_font_size', 14);
            formData.append('date_top', templatePositions.date.top);
            formData.append('date_left', templatePositions.date.left);
            formData.append('date_font_size', 14);

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => showMessage('templateMessage', data.message, 'success'));
        }

        loadStatistics();
        setInterval(loadStatistics, 30000);
    </script>
</body>
</html>
