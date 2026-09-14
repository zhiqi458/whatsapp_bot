<?php
require_once __DIR__ . '/includes/db.php';
$pdo = getDBConnection();

header('Content-Type: text/plain; charset=utf-8');

try {
    // 1. 恢复超时未完成的任务（例如超过 5 分钟处理中且未超最大重试次数）
    $stmtRecover = $pdo->prepare("
        UPDATE message_jobs 
        SET status = 'PENDING', error_info = 'Worker 响应超时，已重置队列' 
        WHERE status = 'PROCESSING' 
          AND scheduled_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
          AND attempts < 3
    ");
    $stmtRecover->execute();
    $recoveredCount = $stmtRecover->rowCount();

    // 2. 超过最大重试次数的任务直接标记为 FAILED
    $stmtFailed = $pdo->prepare("
        UPDATE message_jobs 
        SET status = 'FAILED', error_info = '达到最大重试次数，Worker 异常终止' 
        WHERE status = 'PROCESSING' 
          AND scheduled_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
          AND attempts >= 3
    ");
    $stmtFailed->execute();
    $failedCount = $stmtFailed->rowCount();

    echo "[" . date('Y-m-d H:i:s') . "] 恢复任务数: {$recoveredCount}, 标记失败任务数: {$failedCount}\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] 恢复脚本运行失败: " . $e->getMessage() . "\n";
}