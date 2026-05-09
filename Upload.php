<?php
/**
 * HAMLog 后台 - ADIF 日志上传页面
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

use Typecho\Db;
use Utils\Helper;

$db = Db::get();

if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    session_start();
    unset($_SESSION['hamlog_records']);
    unset($_SESSION['hamlog_fields']);
    header('Location: ' . Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Upload.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    require_once __DIR__ . '/DataAccess.php';
    
    session_start();
    $records = $_SESSION['hamlog_records'] ?? [];
    $imported = 0;
    $skipped = 0;
    $da = new HAMLog_DataAccess();
    
    foreach ($records as $record) {
        $exists = $da->checkRecordExists($record['CALL'], $record['QSO_DATE'], $record['TIME_ON']);
        
        if ($exists) { $skipped++; continue; }
        
        $da->insertRecord([
            'CALL_SIGN' => $record['CALL'] ?? '',
            'QSO_DATE' => $record['QSO_DATE'] ?? '',
            'TIME_ON' => $record['TIME_ON'] ?? '',
            'BAND' => $record['BAND'] ?? '',
            'BAND_RX' => $record['BAND_RX'] ?? '',
            'MODE' => $record['MODE'] ?? '',
            'FREQ' => $record['FREQ'] ?? '',
            'FREQ_RX' => $record['FREQ_RX'] ?? '',
            'RST_SENT' => $record['RST_SENT'] ?? '',
            'RST_RCVD' => $record['RST_RCVD'] ?? '',
            'TX_PWR' => $record['TX_PWR'] ?? $record['TX_POWER'] ?? '',
            'RX_PWR' => $record['RX_PWR'] ?? $record['RX_POWER'] ?? '',
            'QTH' => $record['QTH'] ?? '',
            'GRID' => $record['GRID'] ?? '',
            'PROP_MODE' => $record['PROP_MODE'] ?? '',
            'SAT_NAME' => $record['SAT_NAME'] ?? '',
            'REMARK' => $record['REMARK'] ?? '',
            'CARD_SEND' => 0,
            'CARD_RCV' => 0,
            'CREATED' => time()
        ]);
        $imported++;
    }
    
    unset($_SESSION['hamlog_records']);
    Typecho_Cookie::set('__typecho_notice', "导入完成：导入 {$imported} 条，跳过 {$skipped} 条重复记录");
    header('Location: ' . Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Page.php'));
    exit;
}

include __TYPECHO_ROOT_DIR__ . '/admin/header.php';
include __TYPECHO_ROOT_DIR__ . '/admin/menu.php';

$baseUrl = Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Upload.php');

$fieldLabels = [
    'CALL_SIGN' => '呼号',
    'QSO_DATE' => '日期',
    'TIME_ON' => '时间',
    'BAND' => '频段',
    'BAND_RX' => '接收频段',
    'MODE' => '模式',
    'FREQ' => '频率',
    'FREQ_RX' => '接收频率',
    'RST_SENT' => '发送报告',
    'RST_RCVD' => '接收报告',
    'TX_PWR' => '己方功率',
    'TX_POWER' => '己方功率',
    'RX_PWR' => '对方功率',
    'RX_POWER' => '对方功率',
    'QTH' => '对方QTH',
    'GRID' => '网格坐标',
    'PROP_MODE' => '传播模式',
    'SAT_NAME' => '卫星名称',
    'REMARK' => '备注'
];

function getFieldLabel($field, $labels) {
    return $labels[strtoupper($field)] ?? $field;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['adif'])) {
    if ($_FILES['adif']['error'] !== UPLOAD_ERR_OK) {
        $error = '文件上传失败，请重试';
    } else {
        $file = $_FILES['adif']['tmp_name'];
        $content = file_get_contents($file);
        $records = parseAdifContent($content);
        
        if (empty($records)) {
            $error = '解析失败，未找到有效记录';
        } else {
            session_start();
            $_SESSION['hamlog_records'] = $records;
            
            $allFields = [];
            foreach ($records as $record) {
                foreach (array_keys($record) as $field) {
                    if (!in_array($field, $allFields)) {
                        $allFields[] = $field;
                    }
                }
            }
            $_SESSION['hamlog_fields'] = $allFields;
            $previewFields = $allFields;
            
            $perPageOptions = [20, 50, 100, 200, 500];
            $perPage = isset($_POST['per_page']) && in_array(intval($_POST['per_page']), $perPageOptions) 
                ? intval($_POST['per_page']) 
                : 20;
            $page = max(1, isset($_POST['page']) ? intval($_POST['page']) : 1);
            
            $totalRecords = count($records);
            $totalPages = ceil($totalRecords / $perPage);
            $offset = ($page - 1) * $perPage;
            $previewRecords = array_slice($records, $offset, $perPage);
        }
    }
} elseif (isset($_SESSION['hamlog_records']) && !isset($_POST['confirm_import'])) {
    session_start();
    $records = $_SESSION['hamlog_records'];
    $allFields = $_SESSION['hamlog_fields'] ?? [];
    $previewFields = $allFields;
    
    $perPageOptions = [20, 50, 100, 200, 500];
    $perPage = isset($_GET['per_page']) && in_array(intval($_GET['per_page']), $perPageOptions) 
        ? intval($_GET['per_page']) 
        : (isset($_POST['per_page']) && in_array(intval($_POST['per_page']), $perPageOptions) 
            ? intval($_POST['per_page']) 
            : 20);
    $page = max(1, isset($_GET['page']) ? intval($_GET['page']) : (isset($_POST['page']) ? intval($_POST['page']) : 1));
    
    $totalRecords = count($records);
    $totalPages = ceil($totalRecords / $perPage);
    $offset = ($page - 1) * $perPage;
    $previewRecords = array_slice($records, $offset, $perPage);
}

function parseAdifContent($content) {
    $content = preg_replace('/[\r\n]+/', '', $content);
    
    $eohPos = stripos($content, '<eoh>');
    if ($eohPos !== false) {
        $content = substr($content, $eohPos + 5);
    }
    
    $records = preg_split('/<eor>/i', $content);
    $result = [];
    
    foreach ($records as $record) {
        $record = trim($record);
        if (empty($record)) continue;
        
        $fields = [];
        
        if (preg_match_all('/<([a-zA-Z0-9_]+):(\d+):?([a-zA-Z]*)>([^<]*)/i', $record, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fieldName = strtoupper($match[1]);
                $length = intval($match[2]);
                $value = substr($match[4], 0, $length);
                
                if ($fieldName === 'QSO_DATE' && strlen($value) === 8) {
                    $value = substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
                }
                if ($fieldName === 'TIME_ON' && strlen($value) >= 4) {
                    if (strlen($value) === 6) {
                        $value = substr($value, 0, 2) . ':' . substr($value, 2, 2) . ':' . substr($value, 4, 2);
                    } elseif (strlen($value) === 4) {
                        $value = substr($value, 0, 2) . ':' . substr($value, 2, 2) . ':00';
                    }
                }
                
                $fields[$fieldName] = $value;
            }
        }
        
        $required = ['CALL', 'QSO_DATE', 'TIME_ON', 'BAND', 'MODE'];
        $valid = true;
        foreach ($required as $field) {
            if (empty($fields[$field])) { $valid = false; break; }
        }
        
        if ($valid) { $result[] = $fields; }
    }
    
    return $result;
}
?>

<?php if (isset($previewRecords)): ?>
    <div class="main">
        <div class="body container">
            <div class="colgroup">
                <div class="typecho-page-main" role="main">
                    <div class="typecho-page-title">
                        <h2>通联日志解析预览　(共 <?php echo $totalRecords; ?> 条记录)</h2>
                    </div>
                    
                    <div style="margin-bottom:15px; padding:10px; background:#f5f5f5; border-radius:4px; display:flex; justify-content:space-between; align-items:center;">
                        <form method="get" action="<?php echo $baseUrl; ?>" style="display:inline;">
                            <input type="hidden" name="panel" value="HAMLog/Upload.php">
                            <label style="margin-right:10px;">每页显示：</label>
                            <select name="per_page" onchange="this.form.submit()">
                                <?php foreach ($perPageOptions as $option): ?>
                                <option value="<?php echo $option; ?>" <?php echo $perPage == $option ? 'selected' : ''; ?>>
                                    <?php echo $option; ?> 条
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($page > 1): ?>
                            <input type="hidden" name="page" value="<?php echo $page; ?>">
                            <?php endif; ?>
                        </form>
                        <div style="position:absolute; left:50%; transform:translateX(-50%);">
                            第 <?php echo $page; ?> / <?php echo $totalPages; ?> 页，
                            显示 <?php echo $offset + 1; ?> - <?php echo min($offset + $perPage, $totalRecords); ?> 条
                        </div>
                        <?php
                        require_once __DIR__ . '/DataAccess.php';
                        $da = new HAMLog_DataAccess();
                        $connStatus = $da->testSupabaseConnection();
                        $statusClass = '';
                        $statusText = '';
                        if ($connStatus['status'] === 'connected') {
                            $statusClass = 'background:#d4edda;color:#155724;';
                            $statusText = 'Supabase 已连接';
                        } elseif ($connStatus['status'] === 'disabled') {
                            $statusClass = 'background:#fff3cd;color:#856404;';
                            $statusText = '使用本地数据库';
                        } else {
                            $statusClass = 'background:#f8d7da;color:#721c24;';
                            $statusText = 'Supabase 未连接';
                            if (isset($connStatus['details'])) {
                                $statusText .= ' (' . $connStatus['details'] . ')';
                            }
                        }
                        ?>
                        <span style="<?php echo $statusClass; ?>padding:4px 12px;border-radius:4px;font-size:13px;"><?php echo $statusText; ?></span>
                    </div>
                    
                    <form method="post" action="<?php echo $baseUrl; ?>">
                        <input type="hidden" name="confirm_import" value="1">
                        <div class="typecho-table-wrap">
                            <table class="typecho-list-table">
                                <thead><tr>
                                    <?php foreach ($previewFields as $field): ?>
                                    <th style="text-align:center;"><?php echo htmlspecialchars(getFieldLabel($field, $fieldLabels)); ?></th>
                                    <?php endforeach; ?>
                                </tr></thead><tbody>
                                    <?php foreach ($previewRecords as $record): ?>
                                <tr>
                                    <?php foreach ($previewFields as $field): ?>
                                    <td style="text-align:center;"><?php echo htmlspecialchars($record[$field] ?? ''); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                    <?php endforeach; ?>
                            </tbody></table>
                        </div>
                        
                        <div style="margin-top:15px; text-align:center;">
                            <?php if ($page > 1): ?>
                            <button type="button" onclick="location.href='<?php echo $baseUrl; ?>&page=<?php echo $page - 1; ?>&per_page=<?php echo $perPage; ?>'" class="btn">上一页</button>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            if ($startPage > 1): ?>
                            <button type="button" onclick="location.href='<?php echo $baseUrl; ?>&page=1&per_page=<?php echo $perPage; ?>'" class="btn">1</button>
                            <?php if ($startPage > 2): ?>
                            <span style="margin:0 3px;">...</span>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $page): ?>
                            <button type="button" class="btn primary" style="cursor:default; pointer-events:none;"><?php echo $i; ?></button>
                            <?php else: ?>
                            <button type="button" onclick="location.href='<?php echo $baseUrl; ?>&page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>'" class="btn"><?php echo $i; ?></button>
                            <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                            <span style="margin:0 3px;">...</span>
                            <?php endif; ?>
                            <button type="button" onclick="location.href='<?php echo $baseUrl; ?>&page=<?php echo $totalPages; ?>&per_page=<?php echo $perPage; ?>'" class="btn"><?php echo $totalPages; ?></button>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <button type="button" onclick="location.href='<?php echo $baseUrl; ?>&page=<?php echo $page + 1; ?>&per_page=<?php echo $perPage; ?>'" class="btn">下一页</button>
                            <?php endif; ?>
                        </div>
                        
                        <p style="margin-top:20px;">
                            <button type="button" id="confirmImportBtn" class="btn primary">确认导入</button>
                            <button type="button" onclick="location.href='<?php echo $baseUrl; ?>&action=clear'" class="btn">返回上传</button>
                        </p>
                    </form>
                    
                    <div id="uploadProgress" style="display:none;position:fixed;bottom:20px;right:20px;background:#333;color:#fff;padding:15px 20px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:1000;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <span style="font-weight:bold;">上传进度</span>
                            <div id="controlButtons" style="display:flex;gap:8px;">
                                <button id="pauseBtn" style="padding:4px 10px;font-size:12px;border:none;border-radius:4px;background:#007bff;color:#fff;cursor:pointer;">暂停</button>
                                <button id="cancelBtn" style="padding:4px 10px;font-size:12px;border:none;border-radius:4px;background:#dc3545;color:#fff;cursor:pointer;">取消</button>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:200px;height:8px;background:#555;border-radius:4px;overflow:hidden;">
                                <div id="progressBar" style="height:100%;background:#007bff;width:0%;transition:width 0.3s ease;"></div>
                            </div>
                            <span id="progressText">0/0</span>
                        </div>
                        <div id="progressDetail" style="margin-top:8px;font-size:13px;color:#ccc;"></div>
                    </div>
                    
                    <script type="text/javascript">
                    document.getElementById('confirmImportBtn').addEventListener('click', function() {
                        var btn = this;
                        btn.disabled = true;
                        btn.innerHTML = '上传中...';
                        
                        var progressDiv = document.getElementById('uploadProgress');
                        var progressBar = document.getElementById('progressBar');
                        var progressText = document.getElementById('progressText');
                        var progressDetail = document.getElementById('progressDetail');
                        var controlButtons = document.getElementById('controlButtons');
                        var pauseBtn = document.getElementById('pauseBtn');
                        var cancelBtn = document.getElementById('cancelBtn');
                        
                        progressDiv.style.display = 'block';
                        progressBar.style.width = '0%';
                        controlButtons.style.display = 'none';
                        
                        var allRecords = <?php echo json_encode($records); ?>;
                        var uniqueRecords = [];
                        var seen = new Set();
                        
                        console.log('Records array:', allRecords);
                        if (allRecords.length > 0) {
                            console.log('First record keys:', Object.keys(allRecords[0]));
                            console.log('First record:', allRecords[0]);
                        }
                        
                        for (var i = 0; i < allRecords.length; i++) {
                            var record = allRecords[i];
                            var call = record.CALL || record.call || 'unknown';
                            var qsoDate = record.QSO_DATE || record.qso_date || 'unknown';
                            var timeOn = record.TIME_ON || record.time_on || 'unknown';
                            var key = call + '|' + qsoDate + '|' + timeOn;
                            console.log('Record ' + i + ' key:', key);
                            if (!seen.has(key)) {
                                seen.add(key);
                                uniqueRecords.push(record);
                            }
                        }
                        
                        var isPaused = false;
                        var isCancelled = false;
                        var currentIndex = 0;
                        var uploadedCount = 0;
                        var frontendSkipped = allRecords.length - uniqueRecords.length;
                        var backendSkipped = 0;
                        var currentXhr = null;
                        
                        pauseBtn.onclick = function() {
                            if (isPaused) {
                                isPaused = false;
                                pauseBtn.innerHTML = '暂停';
                                progressDetail.innerHTML = '继续上传...';
                            } else {
                                isPaused = true;
                                pauseBtn.innerHTML = '继续';
                                progressDetail.innerHTML = '已暂停，点击继续';
                            }
                        };
                        
                        cancelBtn.onclick = function() {
                            isCancelled = true;
                            isPaused = false;
                            if (currentXhr) {
                                currentXhr.abort();
                                currentXhr = null;
                            }
                            progressDiv.style.display = 'none';
                            btn.disabled = false;
                            btn.innerHTML = '确认导入';
                        };
                        
                        progressDetail.innerHTML = '正在检查重复记录...';
                        console.log('Total records:', allRecords.length);
                        console.log('Unique records:', uniqueRecords.length);
                        
                        var checkData = [];
                        for (var i = 0; i < uniqueRecords.length; i++) {
                            var record = uniqueRecords[i];
                            checkData.push({
                                call: record.CALL || '',
                                date: record.QSO_DATE || '',
                                time: record.TIME_ON || ''
                            });
                        }
                        
                        var checkXhr = new XMLHttpRequest();
                        checkXhr.open('POST', '<?php echo Helper::options()->siteUrl; ?>usr/plugins/HAMLog/api.php?do=checkExists&_=' + Date.now(), true);
                        checkXhr.setRequestHeader('Content-Type', 'application/json');
                        checkXhr.onload = function() {
                            if (isCancelled) return;
                            
                            var serverExists = {};
                            try {
                                var response = JSON.parse(checkXhr.responseText);
                                if (response.success && response.data) {
                                    serverExists = response.data;
                                }
                            } catch (e) {
                                console.error('Check exists error:', e);
                            }
                            
                            var newRecords = [];
                            var dbSkipped = 0;
                            
                            for (var i = 0; i < uniqueRecords.length; i++) {
                                var record = uniqueRecords[i];
                                var key = (record.CALL || '') + '|' + (record.QSO_DATE || '') + '|' + (record.TIME_ON || '');
                                if (serverExists[key]) {
                                    dbSkipped++;
                                } else {
                                    newRecords.push(record);
                                }
                            }
                            
                            uniqueRecords = newRecords;
                            var totalSkipped = frontendSkipped + dbSkipped;
                            
                            console.log('DB skipped:', dbSkipped);
                            console.log('Final unique records:', uniqueRecords.length);
                            
                            if (uniqueRecords.length === 0) {
                                progressBar.style.width = '100%';
                                progressText.innerHTML = '0/0';
                                progressDetail.innerHTML = '所有记录均已存在，无需上传';
                                pauseBtn.style.display = 'none';
                                cancelBtn.style.display = 'none';
                                
                                var clearXhr = new XMLHttpRequest();
                                clearXhr.open('GET', '<?php echo Helper::options()->siteUrl; ?>usr/plugins/HAMLog/api.php?do=clearSession&_=' + Date.now(), true);
                                clearXhr.onload = function() {
                                    setTimeout(function() {
                                        window.location.href = '<?php echo Helper::options()->adminUrl; ?>extending.php?panel=' + encodeURIComponent('HAMLog/Page.php');
                                    }, 1500);
                                };
                                clearXhr.send();
                                return;
                            }
                            
                            controlButtons.style.display = 'flex';
                            progressDetail.innerHTML = '准备上传 ' + uniqueRecords.length + ' 条新记录（文件内跳过 ' + frontendSkipped + ' 条，数据库已存在 ' + dbSkipped + ' 条）';
                            setTimeout(uploadNext, 500);
                        };
                        checkXhr.onerror = function() {
                            if (isCancelled) return;
                            
                            controlButtons.style.display = 'flex';
                            progressDetail.innerHTML = '数据库检查失败，继续上传...';
                            setTimeout(uploadNext, 500);
                        };
                        checkXhr.send(JSON.stringify(checkData));
                        
                        function uploadNext() {
                            if (isCancelled) {
                                return;
                            }
                            
                            if (isPaused) {
                                setTimeout(uploadNext, 500);
                                return;
                            }
                            
                            if (currentIndex >= uniqueRecords.length) {
                                progressBar.style.width = '100%';
                                progressText.innerHTML = uniqueRecords.length + '/' + uniqueRecords.length;
                                var totalSkipped = frontendSkipped + backendSkipped;
                                progressDetail.innerHTML = '上传完成！导入 ' + uploadedCount + ' 条，跳过 ' + totalSkipped + ' 条重复记录';
                                
                                pauseBtn.style.display = 'none';
                                cancelBtn.style.display = 'none';
                                
                                var clearXhr = new XMLHttpRequest();
                                clearXhr.open('GET', '<?php echo Helper::options()->siteUrl; ?>usr/plugins/HAMLog/api.php?do=clearSession', true);
                                clearXhr.onload = function() {
                                    setTimeout(function() {
                                        window.location.href = '<?php echo Helper::options()->adminUrl; ?>extending.php?panel=' + encodeURIComponent('HAMLog/Page.php');
                                    }, 500);
                                };
                                clearXhr.send();
                                return;
                            }
                            
                            var record = uniqueRecords[currentIndex];
                            var callSign = record.CALL || '未知';
                            
                            progressDetail.innerHTML = '正在上传: ' + callSign;
                            
                            currentXhr = new XMLHttpRequest();
                            currentXhr.open('POST', '<?php echo Helper::options()->siteUrl; ?>usr/plugins/HAMLog/api.php?do=insert&_=' + Date.now(), true);
                            currentXhr.setRequestHeader('Content-Type', 'application/json');
                            
                            currentXhr.onload = function() {
                                currentXhr = null;
                                if (isCancelled) return;
                                
                                var thisIndex = currentIndex;
                                currentIndex++;
                                
                                try {
                                    var response = JSON.parse(currentXhr.responseText);
                                    if (response.success) {
                                        uploadedCount++;
                                    } else if (response.skipped) {
                                        backendSkipped++;
                                    }
                                } catch (e) {
                                    backendSkipped++;
                                }
                                
                                var percent = Math.round((currentIndex / uniqueRecords.length) * 100);
                                progressBar.style.width = percent + '%';
                                progressText.innerHTML = currentIndex + '/' + uniqueRecords.length;
                                
                                uploadNext();
                            };
                            
                            currentXhr.onerror = function() {
                                currentXhr = null;
                                if (isCancelled) return;
                                currentIndex++;
                                backendSkipped++;
                                uploadNext();
                            };
                            
                            currentXhr.onabort = function() {
                                currentXhr = null;
                            };
                            
                            var data = {
                                CALL_SIGN: record.CALL || '',
                                QSO_DATE: record.QSO_DATE || '',
                                TIME_ON: record.TIME_ON || '',
                                BAND: record.BAND || '',
                                BAND_RX: record.BAND_RX || '',
                                MODE: record.MODE || '',
                                FREQ: record.FREQ || '',
                                FREQ_RX: record.FREQ_RX || '',
                                RST_SENT: record.RST_SENT || '',
                                RST_RCVD: record.RST_RCVD || '',
                                TX_PWR: record.TX_PWR || record.TX_POWER || '',
                                RX_PWR: record.RX_PWR || record.RX_POWER || '',
                                QTH: record.QTH || '',
                                GRID: record.GRID || '',
                                PROP_MODE: record.PROP_MODE || '',
                                SAT_NAME: record.SAT_NAME || '',
                                REMARK: record.REMARK || '',
                                CARD_SEND: 0,
                                CARD_RCV: 0,
                                CREATED: Math.floor(Date.now() / 1000)
                            };
                            
                            currentXhr.send(JSON.stringify(data));
                        }
                    });
                    </script>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <main class="main">
        <div class="body container">
            <div class="typecho-page-title">
                <h2>ADIF 日志上传</h2>
            </div>
            <div class="row typecho-page-main" role="form">
                <div class="col-mb-12 col-tb-8 col-tb-offset-2">
                    <div style="border: 1px solid #e0e0e0; padding: 15px; margin-bottom: 20px; background: #fff;">
                        仅支持上传解析 .adi 和 .adif 格式的 ADIF 标准日志文件，若字段不匹配可能导致解析失败。
                    </div>
                    <div class="typecho-table-wrap">
                        <form method="post" enctype="multipart/form-data" action="<?php echo $baseUrl; ?>">
                            <p><label class="typecho-label">选择 ADIF 文件：</label> <input type="file" name="adif" accept=".adi,.adif" required style="padding:5px;"></p>
                            <p>
                                <label class="typecho-label">每页显示：</label>
                                <select name="per_page">
                                    <option value="20" selected>20 条</option>
                                    <option value="50">50 条</option>
                                    <option value="100">100 条</option>
                                    <option value="200">200 条</option>
                                    <option value="500">500 条</option>
                                </select>
                            </p>
                            <?php if (isset($error)): ?>
                            <p style="color:#c00;"><?php echo htmlspecialchars($error); ?></p>
                            <?php endif; ?>
                            <p style="margin-top:20px;"><button type="submit" class="btn primary">解析日志文件</button></p>
                        </form>
                    </div>
                    <div style="border: 1px solid #e0e0e0; padding: 15px; margin-top: 20px; background: #fff;">
                        <p style="margin: 0 0 10px 0; font-weight: bold;">支持解析的 ADIF 字段：</p>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
                            <span>呼号：CALL</span>
                            <span>日期：QSO_DATE</span>
                            <span>时间：TIME_ON</span>
                            <span>频段：BAND</span>
                            <span>接收频段：BAND_RX</span>
                            <span>模式：MODE</span>
                            <span>频率：FREQ</span>
                            <span>接收频率：FREQ_RX</span>
                            <span>发送报告：RST_SENT</span>
                            <span>接收报告：RST_RCVD</span>
                            <span>己方功率：TX_PWR 或 TX_POWER</span>
                            <span>对方功率：RX_PWR 或 RX_POWER</span>
                            <span>对方QTH：QTH</span>
                            <span>网格坐标：GRID</span>
                            <span>传播模式：PROP_MODE</span>
                            <span>卫星名称：SAT_NAME</span>
                            <span>备注：REMARK</span>
                        </div>
                        <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">注：呼号在ADIF中为 CALL，存储到数据库时转换为 CALL_SIGN</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
<?php endif; ?>

<?php
include __TYPECHO_ROOT_DIR__ . '/admin/copyright.php';
include __TYPECHO_ROOT_DIR__ . '/admin/common-js.php';
include __TYPECHO_ROOT_DIR__ . '/admin/footer.php';
?>
