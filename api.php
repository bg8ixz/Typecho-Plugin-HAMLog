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

switch ($do) {
    case 'get':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            echo json_encode(['error' => '无效的记录ID']);
            exit;
        }
        
        $prefix = $db->getPrefix();
        $tableName = $prefix . 'hamlog';
        $query = $db->query("SELECT * FROM `{$tableName}` WHERE id = " . $id);
        $row = $db->fetchRow($query);
        
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

        $prefix = $db->getPrefix();
        $tableName = $prefix . 'hamlog';
        
        $cardSend = isset($_POST['CARD_SEND']) ? 1 : 0;
        $cardRcv = isset($_POST['CARD_RCV']) ? 1 : 0;
        
        $call = htmlspecialchars(trim($_POST['CALL_SIGN'] ?? ''));
        $qsoDate = $_POST['QSO_DATE'] ?? '';
        $timeOn = $_POST['TIME_ON'] ?? '';
        $band = htmlspecialchars(trim($_POST['BAND'] ?? ''));
        $bandRx = htmlspecialchars(trim($_POST['BAND_RX'] ?? ''));
        $mode = htmlspecialchars(trim($_POST['MODE'] ?? ''));
        $freq = htmlspecialchars(trim($_POST['FREQ'] ?? ''));
        $freqRx = htmlspecialchars(trim($_POST['FREQ_RX'] ?? ''));
        $rstSent = htmlspecialchars(trim($_POST['RST_SENT'] ?? ''));
        $rstRcvd = htmlspecialchars(trim($_POST['RST_RCVD'] ?? ''));
        $txPwr = htmlspecialchars(trim($_POST['TX_PWR'] ?? ''));
        $rxPwr = htmlspecialchars(trim($_POST['RX_PWR'] ?? ''));
        $qth = htmlspecialchars(trim($_POST['QTH'] ?? ''));
        $grid = htmlspecialchars(trim($_POST['GRID'] ?? ''));
        $propMode = htmlspecialchars(trim($_POST['PROP_MODE'] ?? ''));
        $satName = htmlspecialchars(trim($_POST['SAT_NAME'] ?? ''));
        $remark = htmlspecialchars(trim($_POST['REMARK'] ?? ''));
        
        try {
            $sql = "UPDATE `{$tableName}` SET 
                `CALL_SIGN` = '{$call}', 
                `QSO_DATE` = '{$qsoDate}', 
                `TIME_ON` = '{$timeOn}', 
                `BAND` = '{$band}', 
                `BAND_RX` = '{$bandRx}', 
                `MODE` = '{$mode}', 
                `FREQ` = '{$freq}', 
                `FREQ_RX` = '{$freqRx}', 
                `RST_SENT` = '{$rstSent}', 
                `RST_RCVD` = '{$rstRcvd}', 
                `TX_PWR` = '{$txPwr}', 
                `RX_PWR` = '{$rxPwr}', 
                `QTH` = '{$qth}', 
                `GRID` = '{$grid}', 
                `PROP_MODE` = '{$propMode}', 
                `SAT_NAME` = '{$satName}', 
                `REMARK` = '{$remark}', 
                `CARD_SEND` = {$cardSend}, 
                `CARD_RCV` = {$cardRcv} 
                WHERE id = {$id}";
            
            $db->query($sql);

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
    
    case 'delete':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id > 0) {
            $prefix = $db->getPrefix();
            $tableName = $prefix . 'hamlog';
            $db->query("DELETE FROM `{$tableName}` WHERE id = " . $id);
        }
        echo json_encode(['success' => true]);
        break;
    
    case 'updateCard':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $field = isset($_GET['field']) ? $_GET['field'] : '';
        $value = isset($_GET['v']) ? intval($_GET['v']) : 0;

        if ($id > 0 && in_array($field, ['card_send', 'card_rcv'])) {
            $prefix = $db->getPrefix();
            $tableName = $prefix . 'hamlog';
            $db->query("UPDATE `{$tableName}` SET `{$field}` = " . $value . " WHERE id = " . $id);
        }
        echo json_encode(['success' => true]);
        break;
    
    default:
        echo json_encode(['error' => '未知操作']);
}
?>