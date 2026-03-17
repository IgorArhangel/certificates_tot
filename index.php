<?php
// Налаштування лімітів для великих файлів
ini_set('memory_limit', '256M');
set_time_limit(120);

// --- КОНФІГУРАЦІЯ ---
$onec_url_base = "http://192.168.3.14/erp_main/hs/site/certificate";
$log_file      = "error_log.txt";
$max_log_size  = 5 * 1024 * 1024;
$username      = "web_service";    
$password      = "44332211"; 

$guid = isset($_GET['GUID']) ? trim($_GET['GUID']) : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$error_message = "";
$success_message = "";

// 1. Отримання IP користувача
function get_client_ip() {
    $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            return trim($ips[0]);
        }
    }
    return 'UNKNOWN';
}

// 2. Логування
function write_to_log($message) {
    global $log_file, $max_log_size;
    if (file_exists($log_file) && filesize($log_file) > $max_log_size) {
        rename($log_file, $log_file . ".bak");
    }
    $date = date("Y-m-d H:i:s");
    $ip = get_client_ip();
    file_put_contents($log_file, "[$date] [IP: $ip] $message" . PHP_EOL, FILE_APPEND);
}

// 3. Валідація та нормалізація GUID
if (preg_match('/^[a-f\d]{32}$/i', $guid)) {
    $guid = substr($guid, 0, 8) . '-' . substr($guid, 8, 4) . '-' . substr($guid, 12, 4) . '-' . substr($guid, 16, 4) . '-' . substr($guid, 20);
}
$is_valid_guid = preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){3}-[a-f\d]{12}$/i', $guid);

// 4. Логіка отримання архіву
if ($guid && $is_valid_guid && $action === 'download') {
    $url = $onec_url_base . "?GUID=" . $guid;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password"); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Real-IP: " . get_client_ip()]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90); 

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $file_size = strlen($response);
    curl_close($ch);

    if ($http_code == 200 && $file_size > 0) {
        if (strpos($content_type, 'zip') !== false || $file_size > 5000) {
            $success_message = "Сертифікати успішно сформовані";
            $download_payload = base64_encode($response);
            $trigger_download = true;
        } else {
            $error_message = "Помилка бази сертифікатів: " . strip_tags($response);
            write_to_log("База сертифікатів повернула текст: " . $response);
        }
    } else {
        $error_message = "Не вдалося отримати файл. Спробуйте пізніше або зверніться до менеджера.";
        write_to_log("Помилка бази сертифікатів: HTTP $http_code | GUID: $guid");
    }
} elseif ($guid && !$is_valid_guid) {
    $error_message = "Невірний формат ідентифікатора (GUID).";
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Завантаження сертифікатів</title>
    <link rel="icon" type="image/png" href="https://tot.biz.ua/favicon.png">
    
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); text-align: center; max-width: 420px; width: 90%; }
        
        /* --- ЗМІНЕНО РОЗМІР ЛОГОТИПУ --- */
        .logo { width: 113px; height: 50px; margin-bottom: 30px; object-fit: contain; }
        
        .icon-box { font-size: 55px; margin-bottom: 15px; display: block; }
        h1 { font-size: 24px; color: #1a202c; margin-bottom: 10px; }
        p { color: #4a5568; font-size: 16px; line-height: 1.5; margin-bottom: 30px; }
        
        .btn { display: block; background: #3182ce; color: white; padding: 18px; border-radius: 14px; text-decoration: none; font-weight: bold; transition: all 0.3s ease; border: none; width: 100%; cursor: pointer; font-size: 16px; box-sizing: border-box; }
        .btn:hover { background: #2b6cb0; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(49, 130, 206, 0.3); }
        
        .error-msg { background: #fff5f5; border-left: 5px solid #f56565; color: #c53030; padding: 15px; border-radius: 8px; margin-bottom: 25px; text-align: left; font-size: 14px; line-height: 1.4; }
        .success-msg { background: #f0fff4; border-left: 5px solid #48bb78; color: #22543d; padding: 15px; border-radius: 8px; margin-bottom: 25px; text-align: left; font-size: 14px; font-weight: 600; }
        
        .footer-text { color: #a0aec0; font-size: 13px; margin-top: 20px; }
    </style>
</head>
<body>

<div class="card">
    <img src="https://tot.biz.ua/local/templates/tot/img/logo_tot.png" alt="TOT Logo" class="logo">
    
    <span class="icon-box">📁</span>
    <h1>Сертифікати</h1>
    <p>В архіві сертифікати по вашій видатковій накладній</p>

    <?php if ($success_message): ?>
        <div class="success-msg">
            ✅ <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="error-msg">
            <strong>Увага:</strong><br>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($guid && $is_valid_guid): ?>
        <a href="?GUID=<?php echo htmlspecialchars($guid); ?>&action=download" class="btn">
            <?php echo $success_message ? 'Завантажити ще раз' : 'Завантажити архів (ZIP)'; ?>
        </a>
    <?php else: ?>
        <p class="footer-text">Будь ласка, скористайтеся QR кодом, вказаним на видатковій накладній</p>
    <?php endif; ?>
</div>

<?php if (isset($trigger_download) && $trigger_download): ?>
<script>
    // Скрипт для автоматичного запуску завантаження
    const binaryData = atob("<?php echo $download_payload; ?>");
    const arrayBuffer = new ArrayBuffer(binaryData.length);
    const uint8Array = new Uint8Array(arrayBuffer);
    for (let i = 0; i < binaryData.length; i++) uint8Array[i] = binaryData.charCodeAt(i);
    
    const blob = new Blob([uint8Array], { type: 'application/zip' });
    const link = document.createElement('a');
    link.href = window.URL.createObjectURL(blob);
    link.download = "certificates_<?php echo $guid; ?>.zip";
    link.click();
</script>
<?php endif; ?>

</body>
</html>