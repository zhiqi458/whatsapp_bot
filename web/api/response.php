<?php
function sendJsonResponse(bool $success, string $message = '', $data = null, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}