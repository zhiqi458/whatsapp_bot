<?php
require_once __DIR__ . '/includes/db.php';
$pdo = getDBConnection();

$page = $_GET['page'] ?? 'queue';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_job') {
        $recipient = trim($_POST['recipient'] ?? '');
        $scheduled = $_POST['scheduled_at'] ?: date('Y-m-d H:i:s');
        $message = trim($_POST['message'] ?? '');
        
        if ($recipient && $message) {
            $stmt = $pdo->prepare("INSERT INTO message_jobs (recipient, message, scheduled_at) VALUES (?, ?, ?)");
            $stmt->execute([$recipient, $message, $scheduled]);
        }
    } elseif ($action === 'retry_failed') {
        $pdo->query("UPDATE message_jobs SET status = 'PENDING', attempts = 0, error_info = NULL WHERE status = 'FAILED'");
    } elseif ($action === 'clear_failed') {
        $pdo->query("DELETE FROM message_jobs WHERE status = 'FAILED'");
    } elseif ($action === 'add_worker') {
        $name = trim($_POST['worker_name'] ?? '');
        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO workers (name, status) VALUES (?, 'Online')");
            $stmt->execute([$name]);
        }
    } elseif ($action === 'revoke_worker') {
        $id = (int)($_POST['worker_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE workers SET status = 'Revoked' WHERE id = ?");
        $stmt->execute([$id]);
    } elseif ($action === 'save_settings') {
        $api_url = trim($_POST['api_base_url'] ?? '');
        $timezone = trim($_POST['app_timezone'] ?? '');
        
        $stmt1 = $pdo->prepare("REPLACE INTO system_settings (setting_key, setting_value) VALUES ('api_base_url', ?)");
        $stmt1->execute([$api_url]);
        $stmt2 = $pdo->prepare("REPLACE INTO system_settings (setting_key, setting_value) VALUES ('app_timezone', ?)");
        $stmt2->execute([$timezone]);
    }
    header("Location: index.php?page=" . $page);
    exit;
}

// 统计数据计算
$total = $pdo->query("SELECT COUNT(*) FROM message_jobs")->fetchColumn();
$pending = $pdo->query("SELECT COUNT(*) FROM message_jobs WHERE status = 'PENDING'")->fetchColumn();
$processing = $pdo->query("SELECT COUNT(*) FROM message_jobs WHERE status = 'PROCESSING'")->fetchColumn();
$sent = $pdo->query("SELECT COUNT(*) FROM message_jobs WHERE status = 'SENT'")->fetchColumn();
$failed = $pdo->query("SELECT COUNT(*) FROM message_jobs WHERE status = 'FAILED'")->fetchColumn();

// 读取 Worker 与配置
$jobs = $pdo->query("SELECT * FROM message_jobs ORDER BY id DESC")->fetchAll();
$workers = $pdo->query("SELECT * FROM workers ORDER BY id DESC")->fetchAll();

$settingsRaw = $pdo->query("SELECT * FROM system_settings")->fetchAll();
$settings = [];
foreach ($settingsRaw as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>WhatsApp Suite 管理后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .sidebar { width: 260px; height: 100vh; background-color: #045d4d; position: fixed; color: white; padding-top: 20px; }
        .sidebar .brand { font-size: 1.25rem; font-weight: bold; padding: 0 20px 20px; display: flex; align-items: center; gap: 10px; }
        .sidebar .nav-link { color: #cfdcd8; padding: 12px 20px; border-radius: 8px; margin: 0 10px 5px; font-weight: 500; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: #0b7361; color: white; }
        .main-content { margin-left: 260px; padding: 30px; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); background: white; }
        .stat-card { border: none; border-radius: 10px; padding: 15px 20px; background: white; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .stat-card .label { font-size: 0.75rem; font-weight: 700; color: #6c757d; text-transform: uppercase; }
        .stat-card .value { font-size: 1.75rem; font-weight: 700; margin-top: 5px; }
        .btn-theme { background-color: #0b7361; color: white; }
        .btn-theme:hover { background-color: #045d4d; color: white; }
        .badge-sent { background-color: #d1e7dd; color: #0f5132; font-size: 0.75rem; }
        .badge-pending { background-color: #fff3cd; color: #664d03; font-size: 0.75rem; }
        .badge-failed { background-color: #f8d7da; color: #842029; font-size: 0.75rem; }
        .badge-online { color: #198754; font-weight: 600; }
        .badge-revoked { color: #dc3545; font-weight: 600; }
    </style>
</head>
<body>

    <!-- 侧边栏 -->
    <div class="sidebar">
        <div class="brand">
            <i class="bi bi-grid-3x3-gap-fill"></i> WhatsApp 套件
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $page==='queue'?'active':'' ?>" href="index.php?page=queue">
                    <i class="bi bi-window-stack me-2"></i> 队列监控
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $page==='api'?'active':'' ?>" href="index.php?page=api">
                    <i class="bi bi-key me-2"></i> API 节点管理
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $page==='settings'?'active':'' ?>" href="index.php?page=settings">
                    <i class="bi bi-gear me-2"></i> 系统设置
                </a>
            </li>
        </ul>
    </div>

    <!-- 主内容区 -->
    <div class="main-content">

        <?php if ($page === 'queue'): ?>
        <!-- 页面 1：队列监控 -->
        <div class="card-custom p-4 mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">消息队列与发送控制</h4>
                <div class="text-muted small">实时自动化监控与快捷队列管理</div>
            </div>
            <div>
                Worker 运行状态: <span class="badge-online">● 运行中 (Operational)</span>
            </div>
        </div>

        <!-- 统计指标卡片组 -->
        <div class="row g-3 mb-4">
            <div class="col">
                <div class="stat-card">
                    <div class="label">任务总数 (TOTAL JOBS)</div>
                    <div class="value"><?= $total ?></div>
                </div>
            </div>
            <div class="col">
                <div class="stat-card">
                    <div class="label">等待中 (PENDING)</div>
                    <div class="value text-warning"><?= $pending ?></div>
                </div>
            </div>
            <div class="col">
                <div class="stat-card">
                    <div class="label">处理中 (PROCESSING)</div>
                    <div class="value text-primary"><?= $processing ?></div>
                </div>
            </div>
            <div class="col">
                <div class="stat-card">
                    <div class="label">已发送 (SENT)</div>
                    <div class="value text-success"><?= $sent ?></div>
                </div>
            </div>
            <div class="col">
                <div class="stat-card">
                    <div class="label">已失败 (FAILED)</div>
                    <div class="value text-danger"><?= $failed ?></div>
                </div>
            </div>
        </div>

        <!-- 快捷添加队列表单 -->
        <div class="card-custom p-4 mb-4">
            <form method="POST" class="row g-2 align-items-center">
                <input type="hidden" name="action" value="add_job">
                <div class="col-md-3">
                    <input type="text" name="recipient" class="form-control" placeholder="接收号码 (例: 60123456789)" required>
                </div>
                <div class="col-md-3">
                    <input type="datetime-local" name="scheduled_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div class="col-md-4">
                    <input type="text" name="message" class="form-control" placeholder="输入快捷消息文本..." required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-theme w-100 fw-bold"><i class="bi bi-plus-lg"></i> 加入队列</button>
                </div>
            </form>
        </div>

        <!-- 快捷操作按钮 -->
        <div class="d-flex gap-2 mb-3">
            <form method="POST">
                <input type="hidden" name="action" value="retry_failed">
                <button class="btn btn-secondary btn-sm"><i class="bi bi-arrow-clockwise"></i> 重试所有失败任务</button>
            </form>
            <form method="POST">
                <input type="hidden" name="action" value="clear_failed">
                <button class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> 清空失败日志</button>
            </form>
        </div>

        <!-- 任务明细表格 -->
        <div class="card-custom p-3">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>接收人</th>
                        <th>消息内容</th>
                        <th>状态</th>
                        <th>尝试次数</th>
                        <th>计划发送时间</th>
                        <th>完成时间</th>
                        <th>错误信息</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td class="text-muted">#<?= $job['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($job['recipient']) ?></td>
                        <td><?= htmlspecialchars($job['message']) ?></td>
                        <td>
                            <span class="badge badge-<?= strtolower($job['status']) ?>">
                                <?= $job['status'] ?>
                            </span>
                        </td>
                        <td><?= $job['attempts'] ?></td>
                        <td><?= $job['scheduled_at'] ?></td>
                        <td><?= $job['completed_at'] ?: '—' ?></td>
                        <td class="text-danger small"><?= htmlspecialchars($job['error_info'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($page === 'api'): ?>
        <!-- 页面 2：API 节点管理 -->
        <div class="mb-4">
            <h4 class="fw-bold mb-1">Worker & API 节点管理</h4>
            <div class="text-muted small">为 Python worker 节点生成并管理安全身份验证 Token。</div>
        </div>

        <div class="card-custom p-4 mb-4">
            <h6 class="fw-bold mb-3">注册新 Worker 节点</h6>
            <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="add_worker">
                <div class="col-md-4">
                    <input type="text" name="worker_name" class="form-control" placeholder="Worker 名称 (例: Office PC)" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-theme fw-bold"><i class="bi bi-plus-lg"></i> 生成 Worker Token</button>
                </div>
            </form>
        </div>

        <div class="card-custom p-4">
            <h6 class="fw-bold mb-3">已注册活跃节点列表</h6>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>WORKER 名称</th>
                        <th>状态</th>
                        <th>最后心跳 (LAST SEEN)</th>
                        <th>注册时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workers as $w): ?>
                    <tr>
                        <td class="text-muted">#<?= $w['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($w['name']) ?></td>
                        <td>
                            <span class="badge-<?= strtolower($w['status']) ?>">● <?= $w['status'] ?></span>
                        </td>
                        <td><?= $w['last_seen'] ?: 'Never' ?></td>
                        <td><?= $w['registered_at'] ?></td>
                        <td>
                            <?php if ($w['status'] !== 'Revoked'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="revoke_worker">
                                <input type="hidden" name="worker_id" value="<?= $w['id'] ?>">
                                <button class="btn btn-danger btn-sm">撤销 (Revoke)</button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted small">已废弃</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($page === 'settings'): ?>
        <!-- 页面 3：系统设置 -->
        <div class="mb-4">
            <h4 class="fw-bold mb-1">系统全局配置</h4>
            <div class="text-muted small">管理全局环境配置与核心 API 端点。</div>
        </div>

        <div class="card-custom p-4" style="max-width: 600px;">
            <form method="POST">
                <input type="hidden" name="action" value="save_settings">
                <div class="mb-3">
                    <label class="form-label font-monospace small fw-bold">API Base URL Endpoint</label>
                    <input type="text" name="api_base_url" class="form-control" value="<?= htmlspecialchars($settings['api_base_url'] ?? '') ?>">
                </div>
                <div class="mb-4">
                    <label class="form-label font-monospace small fw-bold">Application Timezone</label>
                    <input type="text" name="app_timezone" class="form-control" value="<?= htmlspecialchars($settings['app_timezone'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-theme fw-bold">保存修改 (Save Changes)</button>
            </form>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>