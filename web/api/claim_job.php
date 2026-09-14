<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

$pdo = getDBConnection();

// 简单的 Bearer Auth 校验
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
if (!preg_match('/Bearer\s(\S+)/', $authHeader)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 查找待发送且到期的任务
    $stmt = $pdo->prepare("
        SELECT id, recipient, message 
        FROM message_jobs 
        WHERE status = 'PENDING' 
          AND scheduled_at <= NOW()
        ORDER BY id ASC 
        LIMIT 1 
        FOR UPDATE
    ");
    $stmt->execute();
    $job = $stmt->fetch();

    if ($job) {
        // 更新任务状态为 PROCESSING
        $updateStmt = $pdo->prepare("
            UPDATE message_jobs 
            SET status = 'PROCESSING', attempts = attempts + 1 
            WHERE id = ?
        ");
        $updateStmt->execute([$job['id']]);

        $pdo->commit();
        echo json_encode(['success' => true, 'data' => $job]);
    } else {
        $pdo->commit();
        echo json_encode(['success' => true, 'data' => null, 'message' => '暂无待处理任务']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '队列事务错误: ' . $e->getMessage()]);
}