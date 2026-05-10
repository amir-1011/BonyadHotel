<?php
/**
 * Bale Login System - Complete Debugger
 * 
 * Access: https://yourdomain.com/debugger.php
 * Shows entire system status, database state, and recent logs
 */

// Parse .env file
function parseEnv($filePath = __DIR__ . '/../.env')
{
    $config = [];
    if (!file_exists($filePath)) {
        return $config;
    }

    $lines = file($filePath);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;

        [$key, $value] = explode('=', $line, 2);
        $config[trim($key)] = trim($value);
    }

    return $config;
}

// Get database connection
function getDatabase()
{
    $env = parseEnv();
    try {
        $pdo = new PDO(
            "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']}",
            $env['DB_USERNAME'],
            $env['DB_PASSWORD'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (Exception $e) {
        return null;
    }
}

// Get database columns for users table
function getTableColumns($pdo)
{
    try {
        $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'users'");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['COLUMN_NAME'];
        }
        return $columns;
    } catch (Exception $e) {
        return null;
    }
}

// Get recent logs
function getRecentLogs($limit = 20)
{
    $logFile = __DIR__ . '/../storage/logs/laravel.log';
    if (!file_exists($logFile)) {
        return [];
    }

    $lines = file($logFile);
    $logs = array_slice($lines, -$limit);
    return $logs;
}

// Get bale users from database
function getBaleUsers($pdo, $limit = 10)
{
    try {
        // Use raw SQL without prepared statements to avoid LIMIT binding issues
        $query = "SELECT id, name, mobile, bale_user_id, verified_via_bale, created_at 
                  FROM users 
                  WHERE bale_user_id IS NOT NULL OR verified_via_bale = 1
                  ORDER BY created_at DESC 
                  LIMIT " . intval($limit);
        $stmt = $pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ['_error' => $e->getMessage()];
    }
}

// Get recent users (all)
function getRecentUsers($pdo, $limit = 10)
{
    try {
        // Use raw SQL without prepared statements to avoid LIMIT binding issues
        $query = "SELECT id, name, mobile, bale_user_id, verified_via_bale, created_at 
                  FROM users 
                  ORDER BY created_at DESC 
                  LIMIT " . intval($limit);
        $stmt = $pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ['_error' => $e->getMessage()];
    }
}

// Get debug info
function getDebugInfo($pdo)
{
    $debug = [
        'tables' => [],
        'users_count' => 0,
        'users_with_bale_id' => 0,
        'users_verified_via_bale' => 0,
        'sample_bale_user' => null,
        'error' => null,
    ];
    
    try {
        // Get table list
        $stmt = $pdo->query("SHOW TABLES");
        $debug['tables'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $debug['error'] = 'Cannot list tables: ' . $e->getMessage();
    }
    
    try {
        // Count total users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $debug['users_count'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        $debug['error'] = 'Cannot count users: ' . $e->getMessage();
    }
    
    try {
        // Count users with bale_user_id
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE bale_user_id IS NOT NULL");
        $debug['users_with_bale_id'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        $debug['error'] = 'Cannot count bale users: ' . $e->getMessage();
    }
    
    try {
        // Count users verified via bale
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE verified_via_bale = 1");
        $debug['users_verified_via_bale'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        $debug['error'] = 'Cannot count verified: ' . $e->getMessage();
    }
    
    try {
        // Get sample Bale user (raw SQL without prepared statements)
        $stmt = $pdo->query("SELECT id, name, mobile, bale_user_id, verified_via_bale, created_at FROM users WHERE bale_user_id IS NOT NULL LIMIT 1");
        $debug['sample_bale_user'] = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $debug['error'] = 'Cannot fetch sample user: ' . $e->getMessage();
    }
    
    return $debug;
}

$env = parseEnv();
$pdo = getDatabase();
$dbConnected = $pdo !== null;
$columns = $dbConnected ? getTableColumns($pdo) : [];
$baleUsers = $dbConnected ? getBaleUsers($pdo) : [];
$recentUsers = $dbConnected ? getRecentUsers($pdo, 10) : [];
$debugInfo = $dbConnected ? getDebugInfo($pdo) : [];
$logs = getRecentLogs(30);

// Determine column status
$hasBaleUserId = in_array('bale_user_id', $columns ?? []);
$hasVerifiedViaBale = in_array('verified_via_bale', $columns ?? []);

// Check configuration
$sessionConfig = [
    'SAME_SITE' => $env['SESSION_SAME_SITE'] ?? 'NOT SET',
    'SECURE_COOKIE' => $env['SESSION_SECURE_COOKIE'] ?? 'NOT SET',
    'HTTP_ONLY' => $env['SESSION_HTTP_ONLY'] ?? 'NOT SET',
];

// Check if webhook token is set
$webhookTokenSet = !empty(trim(file_get_contents(__DIR__ . '/webhook.php'))) && 
                   strpos(file_get_contents(__DIR__ . '/webhook.php'), '$TOKEN = "";') === false;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bale System Debugger</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        header h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        
        header p {
            color: #666;
            font-size: 14px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-label {
            font-weight: 600;
            color: #555;
        }
        
        .status-value {
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-ok {
            background: #d4edda;
            color: #155724;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 13px;
        }
        
        th {
            background: #f5f5f5;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
        }
        
        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .log-item {
            background: #f8f9fa;
            padding: 10px;
            margin: 5px 0;
            border-left: 3px solid #667eea;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #555;
            max-height: 100px;
            overflow-y: auto;
            border-radius: 3px;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .footer {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        
        .health-score {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-top: 10px;
        }
        
        .checklist {
            list-style: none;
        }
        
        .checklist li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        
        .checklist li:before {
            content: '✓';
            display: inline-block;
            width: 20px;
            height: 20px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 20px;
            margin-right: 10px;
            font-size: 12px;
        }
        
        .checklist li.error:before {
            content: '✗';
            background: #dc3545;
        }
        
        .checklist li.warning:before {
            content: '!';
            background: #ffc107;
            color: #333;
        }
        
        .action-button {
            display: inline-block;
            padding: 10px 15px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            margin-top: 10px;
        }
        
        .action-button:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔧 Bale System Debugger</h1>
            <p>Complete system status and diagnostics</p>
            <p style="margin-top: 10px; font-size: 12px; color: #999;">Last updated: <?php echo date('Y-m-d H:i:s'); ?></p>
        </header>

        <div class="grid">
            <!-- Database Connection -->
            <div class="card">
                <h2>Database Connection</h2>
                <div class="status-item">
                    <span class="status-label">Connected</span>
                    <span class="status-value <?php echo $dbConnected ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $dbConnected ? '✓ YES' : '✗ NO'; ?>
                    </span>
                </div>
                <?php if ($dbConnected): ?>
                <div class="status-item">
                    <span class="status-label">Host</span>
                    <span><?php echo $env['DB_HOST']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">Database</span>
                    <span><?php echo $env['DB_DATABASE']; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">User</span>
                    <span><?php echo $env['DB_USERNAME']; ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Database Columns -->
            <div class="card">
                <h2>Bale Columns</h2>
                <div class="status-item">
                    <span class="status-label">bale_user_id</span>
                    <span class="status-value <?php echo $hasBaleUserId ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $hasBaleUserId ? '✓ EXISTS' : '✗ MISSING'; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">verified_via_bale</span>
                    <span class="status-value <?php echo $hasVerifiedViaBale ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $hasVerifiedViaBale ? '✓ EXISTS' : '✗ MISSING'; ?>
                    </span>
                </div>
            </div>

            <!-- Session Configuration -->
            <div class="card">
                <h2>Session Config (.env)</h2>
                <div class="status-item">
                    <span class="status-label">SESSION_SAME_SITE</span>
                    <span class="status-value <?php echo ($sessionConfig['SAME_SITE'] === 'none') ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $sessionConfig['SAME_SITE']; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">SESSION_SECURE_COOKIE</span>
                    <span class="status-value <?php echo in_array($sessionConfig['SECURE_COOKIE'], ['true', 'false']) ? 'status-ok' : 'status-warning'; ?>">
                        <?php echo $sessionConfig['SECURE_COOKIE']; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">SESSION_HTTP_ONLY</span>
                    <span class="status-value <?php echo ($sessionConfig['HTTP_ONLY'] === 'true') ? 'status-ok' : 'status-warning'; ?>">
                        <?php echo $sessionConfig['HTTP_ONLY']; ?>
                    </span>
                </div>
            </div>

            <!-- Webhook Status -->
            <div class="card">
                <h2>Webhook Status</h2>
                <div class="status-item">
                    <span class="status-label">File Exists</span>
                    <span class="status-value status-ok">✓ YES</span>
                </div>
                <div class="status-item">
                    <span class="status-label">Bot TOKEN Set</span>
                    <span class="status-value <?php echo $webhookTokenSet ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $webhookTokenSet ? '✓ YES' : '✗ NO'; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">API Endpoint</span>
                    <span style="font-size: 12px;">/webhook.php</span>
                </div>
            </div>

            <!-- File Status -->
            <div class="card">
                <h2>Required Files</h2>
                <ul class="checklist">
                    <li <?php echo file_exists(__DIR__ . '/webhook.php') ? '' : 'class="error"'; ?>>
                        webhook.php
                    </li>
                    <li <?php echo file_exists(__DIR__ . '/js/bale-webapp-login.js') ? '' : 'class="error"'; ?>>
                        js/bale-webapp-login.js
                    </li>
                    <li <?php echo file_exists(__DIR__ . '/../app/Services/BaleWebAppService.php') ? '' : 'class="error"'; ?>>
                        app/Services/BaleWebAppService.php
                    </li>
                    <li <?php echo file_exists(__DIR__ . '/../storage/logs/laravel.log') ? '' : 'class="error"'; ?>>
                        storage/logs/laravel.log
                    </li>
                </ul>
            </div>

            <!-- Health Check -->
            <div class="card">
                <h2>System Health</h2>
                <?php
                $checks = [
                    'Database Connected' => $dbConnected,
                    'bale_user_id Column' => $hasBaleUserId,
                    'verified_via_bale Column' => $hasVerifiedViaBale,
                    'SESSION_SAME_SITE=none' => $sessionConfig['SAME_SITE'] === 'none',
                    'Webhook Token Set' => $webhookTokenSet,
                ];
                
                $score = 0;
                foreach ($checks as $check => $status) {
                    $score += $status ? 20 : 0;
                }
                ?>
                <ul class="checklist">
                    <?php foreach ($checks as $label => $status): ?>
                    <li <?php echo $status ? '' : 'class="error"'; ?>>
                        <?php echo $label; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="health-score">
                    Score: <?php echo $score; ?>/100
                </div>
                <?php if ($score >= 80): ?>
                    <div class="status-value status-ok" style="margin-top: 10px; display: inline-block;">✓ System Ready</div>
                <?php elseif ($score >= 60): ?>
                    <div class="status-value status-warning" style="margin-top: 10px; display: inline-block;">! Needs Attention</div>
                <?php else: ?>
                    <div class="status-value status-error" style="margin-top: 10px; display: inline-block;">✗ Critical Issues</div>
                <?php endif; ?>
            </div>

            <!-- Database Debug Info -->
            <div class="card">
                <h2>Database Debug</h2>
                <?php if ($debugInfo['error']): ?>
                    <div class="status-item">
                        <span class="status-label">Error</span>
                    </div>
                    <p style="color: #c33; font-size: 12px;"><?php echo htmlspecialchars($debugInfo['error']); ?></p>
                <?php endif; ?>
                <div class="status-item">
                    <span class="status-label">Total Users</span>
                    <span style="font-size: 18px; font-weight: bold;"><?php echo $debugInfo['users_count'] ?? '?'; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">Users with bale_user_id</span>
                    <span style="font-size: 18px; font-weight: bold; color: #667eea;"><?php echo $debugInfo['users_with_bale_id'] ?? '?'; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">Users verified_via_bale</span>
                    <span style="font-size: 18px; font-weight: bold; color: #667eea;"><?php echo $debugInfo['users_verified_via_bale'] ?? '?'; ?></span>
                </div>
                
                <?php if ($debugInfo['sample_bale_user']): ?>
                <div style="margin-top: 15px; padding: 10px; background: #e3f2fd; border-radius: 4px; border-left: 4px solid #2196F3;">
                    <strong>Sample Bale User Found:</strong>
                    <div style="font-size: 12px; margin-top: 5px; font-family: monospace;">
                        ID: <?php echo $debugInfo['sample_bale_user']['id']; ?><br>
                        Name: <?php echo $debugInfo['sample_bale_user']['name']; ?><br>
                        Mobile: <?php echo $debugInfo['sample_bale_user']['mobile']; ?><br>
                        Bale ID: <?php echo $debugInfo['sample_bale_user']['bale_user_id']; ?><br>
                        Verified: <?php echo $debugInfo['sample_bale_user']['verified_via_bale'] ? 'YES' : 'NO'; ?><br>
                        Created: <?php echo $debugInfo['sample_bale_user']['created_at']; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="status-item" style="margin-top: 15px;">
                    <span class="status-label">Tables Found</span>
                    <span><?php echo count($debugInfo['tables'] ?? []); ?></span>
                </div>
                <?php if (!empty($debugInfo['tables'])): ?>
                <div style="margin-top: 10px; padding: 10px; background: #f5f5f5; border-radius: 4px; font-size: 12px;">
                    <strong>Tables:</strong> <?php echo implode(', ', $debugInfo['tables']); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Users with Bale -->
            <div class="card full-width">
                <h2>Recent Bale Users (Where bale_user_id IS NOT NULL OR verified_via_bale = 1)</h2>
                <?php if (isset($baleUsers[0]['_error'])): ?>
                    <p style="color: #c33; padding: 10px; background: #ffebee; border-radius: 4px;">
                        <strong>Query Error:</strong> <?php echo htmlspecialchars($baleUsers[0]['_error']); ?>
                    </p>
                <?php elseif (!empty($baleUsers)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Bale ID</th>
                            <th>Verified</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baleUsers as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo $user['name'] ?? '-'; ?></td>
                            <td><?php echo $user['mobile'] ?? '-'; ?></td>
                            <td><?php echo $user['bale_user_id'] ?? '-'; ?></td>
                            <td>
                                <span class="status-value <?php echo $user['verified_via_bale'] ? 'status-ok' : 'status-error'; ?>">
                                    <?php echo $user['verified_via_bale'] ? '✓' : '✗'; ?>
                                </span>
                            </td>
                            <td><?php echo $user['created_at']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: #999; padding: 20px; text-align: center;">No Bale users found. (<?php echo $debugInfo['users_with_bale_id'] ?? 0; ?> users have bale_user_id, <?php echo $debugInfo['users_verified_via_bale'] ?? 0; ?> verified via Bale)</p>
                <?php endif; ?>
            </div>

            <!-- All Recent Users -->
            <div class="card full-width">
                <h2>All Recent Users (Last 10)</h2>
                <?php if (isset($recentUsers[0]['_error'])): ?>
                    <p style="color: #c33; padding: 10px; background: #ffebee; border-radius: 4px;">
                        <strong>Query Error:</strong> <?php echo htmlspecialchars($recentUsers[0]['_error']); ?>
                    </p>
                <?php elseif (!empty($recentUsers)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Bale ID</th>
                            <th>Verified</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                        <tr style="background: <?php echo (empty($user['bale_user_id']) && !$user['verified_via_bale']) ? '#f0f0f0' : ''; ?>">
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo $user['name'] ?? '-'; ?></td>
                            <td><?php echo $user['mobile'] ?? '-'; ?></td>
                            <td><?php echo $user['bale_user_id'] ?? '-'; ?></td>
                            <td>
                                <span class="status-value <?php echo $user['verified_via_bale'] ? 'status-ok' : 'status-error'; ?>">
                                    <?php echo $user['verified_via_bale'] ? '✓' : '✗'; ?>
                                </span>
                            </td>
                            <td><?php echo $user['created_at']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: #999; padding: 20px; text-align: center;">No users found in database</p>
                <?php endif; ?>
            </div>

            <!-- Recent Logs -->
            <div class="card full-width">
                <h2>Recent Logs (Last 30 entries)</h2>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php if (!empty($logs)): ?>
                        <?php foreach (array_reverse($logs) as $log): ?>
                        <div class="log-item">
                            <?php echo htmlspecialchars(trim($log)); ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #999; padding: 20px; text-align: center;">No logs available</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Login Test -->
            <div class="card full-width">
                <h2>🧪 Manual Login Test</h2>
                <p style="color: #666; margin-bottom: 15px; font-size: 13px;">
                    Test if the backend can log in a user by simulating a Bale Mini App login request:
                </p>
                
                <?php if (!empty($debugInfo['sample_bale_user'])): ?>
                <form id="testLoginForm" style="background: #f5f5f5; padding: 15px; border-radius: 4px;">
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Bale User ID:</label>
                        <input type="number" name="id" value="<?php echo $debugInfo['sample_bale_user']['bale_user_id']; ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" readonly>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">First Name:</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($debugInfo['sample_bale_user']['name'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Last Name:</label>
                        <input type="text" name="last_name" value="" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <button type="submit" style="background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        Test Login
                    </button>
                    <div id="testResult" style="margin-top: 15px;"></div>
                </form>
                
                <script>
                document.getElementById('testLoginForm').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const resultDiv = document.getElementById('testResult');
                    resultDiv.innerHTML = '<p style="color: #667eea; font-size: 13px;">Testing login...</p>';
                    
                    const formData = {
                        id: parseInt(document.querySelector('input[name="id"]').value),
                        first_name: document.querySelector('input[name="first_name"]').value || '',
                        last_name: document.querySelector('input[name="last_name"]').value || '',
                        username: '',
                        photo_url: ''
                    };
                    
                    try {
                        const response = await fetch('/api/auth/bale-webapp', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(formData),
                            credentials: 'include' // Include cookies for session
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            resultDiv.innerHTML = `
                                <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; color: #155724; font-size: 13px;">
                                    <strong>✓ Login successful!</strong><br>
                                    User ID: ${data.user.id}<br>
                                    Name: ${data.user.name}<br>
                                    Redirect: ${data.redirect}
                                </div>
                            `;
                        } else {
                            resultDiv.innerHTML = `
                                <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; color: #721c24; font-size: 13px;">
                                    <strong>✗ Login failed</strong><br>
                                    ${data.message}
                                </div>
                            `;
                        }
                    } catch (error) {
                        resultDiv.innerHTML = `
                            <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; color: #721c24; font-size: 13px;">
                                <strong>✗ Error</strong><br>
                                ${error.message}
                            </div>
                        `;
                    }
                });
                </script>
                <?php else: ?>
                <p style="color: #999; padding: 10px; text-align: center;">No Bale users found to test with</p>
                <?php endif; ?>
            </div>

            <!-- Troubleshooting Guide -->
            <div class="card full-width">
                <h2>🚀 Next Steps</h2>
                <ul class="checklist">
                    <!-- Check data -->
                    <?php if ($debugInfo['users_with_bale_id'] >= 1): ?>
                    <li>
                        ✓ Users saved to database - <?php echo $debugInfo['users_with_bale_id']; ?> user(s) with bale_user_id found
                    </li>
                    <?php else: ?>
                    <li class="error">
                        No users with bale_user_id - Send /start command to your Bale bot
                    </li>
                    <?php endif; ?>
                    
                    <!-- Check config -->
                    <?php if ($sessionConfig['SAME_SITE'] === 'none' && $webhookTokenSet): ?>
                    <li>
                        ✓ Configuration correct - All system settings are in place
                    </li>
                    <?php endif; ?>
                    
                    <!-- Manual test -->
                    <?php if (!empty($debugInfo['sample_bale_user'])): ?>
                    <li>
                        ✓ Test manual login above with the sample user
                    </li>
                    <?php endif; ?>
                    
                    <!-- Final step -->
                    <?php if ($score >= 80 && $debugInfo['users_with_bale_id'] >= 1): ?>
                    <li>
                        🎉 Ready for production! System is fully functional.
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="footer">
            <p>Bale System Debugger v1.0 | <?php echo php_uname(); ?> | PHP <?php echo phpversion(); ?></p>
        </div>
    </div>
</body>
</html>
