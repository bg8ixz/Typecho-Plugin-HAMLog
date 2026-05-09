<?php
define('__TYPECHO_ROOT_DIR__', dirname(dirname(dirname(dirname(__FILE__)))));
define('__TYPECHO_DEBUG__', false);

require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';
require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';
require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Db.php';
require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Cookie.php';

Typecho\Common::init();

$db = Typecho\Db::get();

$do = isset($_GET['do']) ? $_GET['do'] : '';

function getPluginConfig() {
    $defaults = [
        'save_type' => 'local',
        'supabase_api' => '',
        'supabase_key' => '',
        'supabase_table' => '',
        'supabase_note' => '',
        'front_fields' => 'CALL_SIGN,QSO_DATE,TIME_ON,BAND,MODE,FREQ',
        'admin_fields' => 'CALL_SIGN,QSO_DATE,TIME_ON,BAND,MODE,FREQ,REMARK,CARD_SEND,CARD_RCV'
    ];

    try {
        $opts = Typecho_Widget::widget('Widget_Options');
        $pluginConfig = $opts->plugin('HAMLog');

        foreach ($defaults as $key => &$val) {
            if (isset($pluginConfig[$key]) && !empty($pluginConfig[$key])) {
                $val = $pluginConfig[$key];
            }
        }
    } catch (\Exception $e) {}

    return $defaults;
}

require_once __DIR__ . '/DataAccess.php';

$config = getPluginConfig();
define('HAMLOG_CONFIG', serialize($config));

class HAMLog_Plugin {
    public static function getConfig() {
        return unserialize(HAMLOG_CONFIG);
    }
}

$da = new HAMLog_DataAccess();

switch ($do) {
    case 'get':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            echo json_encode(['error' => '无效的记录ID']);
            exit;
        }
        
        $row = $da->getRecord($id);
        
        if ($row) {
            echo json_encode($row);
        } else {
            echo json_encode(['error' => '记录不存在']);
        }
        break;
    
    case 'update':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) {
            echo json_encode(['error' => '无效的记录ID']);
            exit;
        }

        $cardSend = isset($_POST['CARD_SEND']) ? 1 : 0;
        $cardRcv = isset($_POST['CARD_RCV']) ? 1 : 0;
        
        $data = [
            'CALL_SIGN' => htmlspecialchars(trim($_POST['CALL_SIGN'] ?? '')),
            'QSO_DATE' => $_POST['QSO_DATE'] ?? '',
            'TIME_ON' => $_POST['TIME_ON'] ?? '',
            'BAND' => htmlspecialchars(trim($_POST['BAND'] ?? '')),
            'BAND_RX' => htmlspecialchars(trim($_POST['BAND_RX'] ?? '')),
            'MODE' => htmlspecialchars(trim($_POST['MODE'] ?? '')),
            'FREQ' => htmlspecialchars(trim($_POST['FREQ'] ?? '')),
            'FREQ_RX' => htmlspecialchars(trim($_POST['FREQ_RX'] ?? '')),
            'RST_SENT' => htmlspecialchars(trim($_POST['RST_SENT'] ?? '')),
            'RST_RCVD' => htmlspecialchars(trim($_POST['RST_RCVD'] ?? '')),
            'TX_PWR' => htmlspecialchars(trim($_POST['TX_PWR'] ?? '')),
            'RX_PWR' => htmlspecialchars(trim($_POST['RX_PWR'] ?? '')),
            'QTH' => htmlspecialchars(trim($_POST['QTH'] ?? '')),
            'GRID' => htmlspecialchars(trim($_POST['GRID'] ?? '')),
            'PROP_MODE' => htmlspecialchars(trim($_POST['PROP_MODE'] ?? '')),
            'SAT_NAME' => htmlspecialchars(trim($_POST['SAT_NAME'] ?? '')),
            'REMARK' => htmlspecialchars(trim($_POST['REMARK'] ?? '')),
            'CARD_SEND' => $cardSend,
            'CARD_RCV' => $cardRcv
        ];
        
        try {
            $da->updateRecord($id, $data);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
    
    case 'checkExists':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data) || !is_array($data)) {
            echo json_encode(['success' => false, 'error' => '无效数据']);
            exit;
        }
        
        $existsList = [];
        foreach ($data as $item) {
            $callSign = $item['call'] ?? '';
            $qsoDate = $item['date'] ?? '';
            $timeOn = $item['time'] ?? '';
            
            if (!empty($callSign) && !empty($qsoDate) && !empty($timeOn)) {
                $exists = $da->checkRecordExists($callSign, $qsoDate, $timeOn);
                $key = $callSign . '|' . $qsoDate . '|' . $timeOn;
                $existsList[$key] = $exists;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $existsList]);
        exit;
        break;
    
    case 'insert':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data)) {
            echo json_encode(['success' => false, 'error' => '无效数据']);
            exit;
        }
        
        $callSign = $data['CALL_SIGN'] ?? '';
        $qsoDate = $data['QSO_DATE'] ?? '';
        $timeOn = $data['TIME_ON'] ?? '';
        
        if (empty($callSign) || empty($qsoDate) || empty($timeOn)) {
            echo json_encode(['success' => false, 'error' => '缺少必填字段']);
            exit;
        }
        
        if ($da->checkRecordExists($callSign, $qsoDate, $timeOn)) {
            echo json_encode(['success' => false, 'skipped' => true, 'message' => '记录已存在']);
            exit;
        }
        
        $insertData = [
            'CALL_SIGN' => $callSign,
            'QSO_DATE' => $qsoDate,
            'TIME_ON' => $timeOn,
            'BAND' => $data['BAND'] ?? '',
            'BAND_RX' => $data['BAND_RX'] ?? '',
            'MODE' => $data['MODE'] ?? '',
            'FREQ' => $data['FREQ'] ?? '',
            'FREQ_RX' => $data['FREQ_RX'] ?? '',
            'RST_SENT' => $data['RST_SENT'] ?? '',
            'RST_RCVD' => $data['RST_RCVD'] ?? '',
            'TX_PWR' => $data['TX_PWR'] ?? '',
            'RX_PWR' => $data['RX_PWR'] ?? '',
            'QTH' => $data['QTH'] ?? '',
            'GRID' => $data['GRID'] ?? '',
            'PROP_MODE' => $data['PROP_MODE'] ?? '',
            'SAT_NAME' => $data['SAT_NAME'] ?? '',
            'REMARK' => $data['REMARK'] ?? '',
            'CARD_SEND' => $data['CARD_SEND'] ?? 0,
            'CARD_RCV' => $data['CARD_RCV'] ?? 0,
            'CREATED' => $data['CREATED'] ?? time()
        ];
        
        if ($da->insertRecord($insertData)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => '插入失败']);
        }
        break;
    
    case 'delete':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id > 0) {
            $da->deleteRecord($id);
        }
        echo json_encode(['success' => true]);
        break;
    
    case 'updateCard':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $field = isset($_GET['field']) ? $_GET['field'] : '';
        $value = isset($_GET['v']) ? intval($_GET['v']) : 0;

        if ($id > 0 && in_array($field, ['card_send', 'card_rcv'])) {
            $data = [strtoupper($field) => $value];
            $da->updateRecord($id, $data);
        }
        echo json_encode(['success' => true]);
        break;
    
    case 'clearSession':
        session_start();
        unset($_SESSION['hamlog_records']);
        unset($_SESSION['hamlog_fields']);
        echo json_encode(['success' => true]);
        break;
    
    default:
        echo json_encode(['error' => '未知操作']);
}
?>
