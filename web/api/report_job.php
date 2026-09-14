<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

$pdo = getDBConnection();

$input = json_decode(file_get_contents('php://input'), true);
$jobId = $input['job_id'] ?? null;
$status = strtoupper($input['status'] ?? ''); // 'SENT' 或 'FAILED'
$error = $input['error'] ?? null;

if (!$jobId || !in_array($status, ['SENT', 'FAILED'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

if ($status === 'SENT') {
    $stmt = $pdo->prepare("
        UPDATE message_jobs 
        SET status = 'SENT', completed_at = NOW(), error_info = NULL 
        WHERE id = ?
    ");
    $stmt->execute([$jobId]);
} else {
    $stmt = $pdo->prepare("
        UPDATE message_jobs 
        SET status = 'FAILED', error_info = ? 
        WHERE id = ?
    ");
    $stmt->execute([$error, $jobId]);
}

echo json_encode(['success' => true, 'message' => '状态上报成功']);