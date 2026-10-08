<?php

function sendEmployeeWelcomeEmail(array $employee) {
    $serviceId = trim((string) getSetting('emailjs_service_id', ''));
    $templateId = trim((string) getSetting('emailjs_template_id', ''));
    $publicKey = trim((string) getSetting('emailjs_public_key', ''));
    $privateKey = trim((string) getSetting('emailjs_private_key', ''));

    if ($serviceId === '' || $templateId === '' || $publicKey === '') {
        error_log('EmailJS is not fully configured; employee welcome email was not sent.');
        return ['success' => false, 'message' => 'EmailJS is not configured.'];
    }

    if (empty($employee['email']) || !filter_var($employee['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Employee email address is missing or invalid.'];
    }

    if (!function_exists('curl_init')) {
        error_log('EmailJS cannot send email because the PHP cURL extension is unavailable.');
        return ['success' => false, 'message' => 'The PHP cURL extension is unavailable.'];
    }

    $payload = [
        'service_id' => $serviceId,
        'template_id' => $templateId,
        'user_id' => $publicKey,
        'accessToken' => $privateKey,
        'template_params' => [
            'to_email' => $employee['email'],
            'to_name' => trim($employee['first_name'] . ' ' . $employee['last_name']),
            'employee_id' => $employee['employee_id'],
            'position' => $employee['position'] ?? '',
            'department' => $employee['department'] ?? '',
            'start_date' => $employee['start_date'] ?? '',
        ],
    ];

    $curl = curl_init('https://api.emailjs.com/api/v1.0/email/send');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false || $httpStatus < 200 || $httpStatus >= 300) {
        $details = $response !== false ? trim((string) $response) : $curlError;
        error_log('EmailJS request failed (HTTP ' . $httpStatus . '): ' . $details);
        $userDetails = $response !== false
            ? trim(strip_tags($details))
            : 'Could not connect to EmailJS: ' . $curlError;
        $userDetails = preg_replace('/\s+/', ' ', $userDetails);
        return [
            'success' => false,
            'message' => 'EmailJS rejected the request (HTTP ' . $httpStatus . '): ' . substr($userDetails, 0, 240)
        ];
    }

    return ['success' => true, 'message' => 'Welcome email sent.'];
}
