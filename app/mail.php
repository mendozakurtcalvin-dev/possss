<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function getMailConfig() {
    $config = [
        'enabled' => getenv('MAIL_ENABLED') !== false
            ? filter_var(getenv('MAIL_ENABLED'), FILTER_VALIDATE_BOOLEAN)
            : (bool) getSetting('mail_enabled', false),
        'host' => getenv('MAIL_HOST') ?: getSetting('smtp_host', 'localhost'),
        'port' => getenv('MAIL_PORT') !== false ? (int) getenv('MAIL_PORT') : (int) getSetting('smtp_port', 587),
        'username' => getenv('MAIL_USERNAME') ?: getSetting('smtp_username', ''),
        'password' => getenv('MAIL_PASSWORD') ?: getSetting('smtp_password', ''),
        'encryption' => getenv('MAIL_ENCRYPTION') ?: getSetting('smtp_encryption', 'tls'),
        'from_name' => getenv('MAIL_FROM_NAME') ?: getSetting('mail_from_name', 'Smart Market POS'),
        'from_email' => getenv('MAIL_FROM_ADDRESS') ?: getSetting('mail_from_address', 'noreply@localhost'),
    ];

    return $config;
}

function sendApplicationMail($to, $subject, $body, $altBody = '', $replyTo = null) {
    $config = getMailConfig();

    if (empty($config['enabled']) || empty($config['host']) || empty($config['from_email'])) {
        error_log('PHPMailer skipped because email is not configured for ' . $to);
        return ['success' => false, 'message' => 'Email is not configured.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = !empty($config['username']);
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'] ?: false;
        $mail->Port = (int) $config['port'];
        $mail->SMTPAutoTLS = true;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 15;
        $mail->setFrom($config['from_email'], $config['from_name']);

        if ($replyTo) {
            $mail->addReplyTo($replyTo);
        }

        $addressList = is_array($to) ? $to : [$to];
        foreach ($addressList as $recipient) {
            if (!empty($recipient)) {
                $mail->addAddress(trim($recipient));
            }
        }

        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully.'];
    } catch (Exception $e) {
        error_log('PHPMailer error: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function sendProcurementStatusEmail($requestId, $status) {
    global $pdo;

    try {
        $stmt = $pdo->prepare(
            "SELECT pr.*, s.name AS supplier_name, s.email AS supplier_email, u.full_name AS created_by_name
             FROM procurement_requests pr
             LEFT JOIN suppliers s ON pr.supplier_id = s.id
             LEFT JOIN users u ON pr.created_by = u.id
             WHERE pr.id = ?"
        );
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();

        if (!$request) {
            return ['success' => false, 'message' => 'Procurement request not found.'];
        }

        if (empty($request['supplier_email'])) {
            return ['success' => false, 'message' => 'No supplier email configured for this request.'];
        }

        $statusText = ucfirst(str_replace('_', ' ', $status));
        $subject = "Procurement Update: {$request['req_number']} - {$statusText}";
        $body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #111827;'>
                <h2 style='margin-bottom: 12px;'>Procurement Update</h2>
                <p>Hello <strong>{$request['supplier_name']}</strong>,</p>
                <p>Your purchase request <strong>{$request['req_number']}</strong> has been marked as <strong>{$statusText}</strong>.</p>
                <p><strong>Item:</strong> {$request['item_name']}<br>
                <strong>Quantity:</strong> {$request['quantity']}<br>
                <strong>Requested by:</strong> {$request['created_by_name']}<br>
                <strong>Department:</strong> {$request['department']}<br>
                <strong>Notes:</strong> " . (!empty($request['notes']) ? nl2br(htmlspecialchars($request['notes'])) : 'N/A') . "</p>
                <p>Thank you,<br>Smart Market POS</p>
            </body>
            </html>
        ";

        return sendApplicationMail($request['supplier_email'], $subject, $body, strip_tags($body));
    } catch (Exception $e) {
        error_log('sendProcurementStatusEmail error: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
