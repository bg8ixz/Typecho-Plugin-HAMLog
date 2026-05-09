<?php

namespace TypechoPlugin\HAMLog;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * HAMLog 后台业务逻辑
 */
class HAMLog_Action extends \Typecho_Widget
{
    /**
     * 入口方法
     */
    public function action()
    {
        $user = \Typecho_Widget::widget('Widget_User');
        if (!$user->pass('administrator', true)) {
            \Typecho_Response::setStatus(403);
            echo _t('没有权限');
            return;
        }

        $do = isset($_GET['do']) ? $_GET['do'] : 'list';

        switch ($do) {
            case 'parse':
                $this->parseAdif();
                break;
            case 'import':
                $this->importData();
                break;
            case 'list':
                $this->listPage();
                break;
            case 'delete':
                $this->deleteLog();
                break;
            case 'updateCard':
                $this->updateCard();
                break;
            case 'saveProfile':
                $this->saveProfile();
                break;
            case 'get':
                $this->getRecord();
                break;
            case 'update':
                $this->updateRecord();
                break;
            default:
                \Typecho_Response::getInstance()->redirect(\Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Page.php'));
        }
    }

    /**
     * 解析 ADIF 文件
     */
    private function parseAdif()
    {
        if (!isset($_FILES['adif']) || $_FILES['adif']['error'] !== UPLOAD_ERR_OK) {
            $this->showError('文件上传失败，请重试');
            return;
        }

        $file = $_FILES['adif']['tmp_name'];
        $content = file_get_contents($file);
        $records = $this->parseAdifContent($content);

        if (empty($records)) {
            $this->showError('解析失败，未找到有效记录。请确保文件是标准 ADIF 格式且包含必填字段（CALL_SIGN, QSO_DATE, TIME_ON, BAND, MODE）');
            return;
        }

        $this->renderHeader('解析预览 (' . count($records) . ' 条记录)');

        echo '<form method="post" action="' . $this->getActionUrl('do=import') . '">';
        echo '<div class="typecho-table-wrap">';
        echo '<table class="typecho-list-table">';
        echo '<thead><tr>';
        echo '<th>呼号</th><th>日期</th><th>时间</th><th>频段</th><th>模式</th><th>频率</th>';
        echo '</tr></thead><tbody>';

        foreach ($records as $record) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($record['CALL'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($record['QSO_DATE'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($record['TIME_ON'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($record['BAND'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($record['MODE'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($record['FREQ'] ?? '') . '</td>';
            echo '</tr>';
            echo '<input type="hidden" name="records[]" value="' . htmlspecialchars(json_encode($record), ENT_QUOTES) . '">';
        }

        echo '</tbody></table>';
        echo '</div>';
        echo '<p style="margin-top:20px;">';
        echo '<button type="submit" class="btn primary">确认导入</button> ';
        echo '<a class="btn" href="' . Helper::options()->adminUrl . 'extending.php?panel=HAMLog/Upload.php">返回</a>';
        echo '</p>';
        echo '</form>';

        $this->renderFooter();
    }

    /**
     * 解析 ADIF 内容
     */
    private function parseAdifContent($content)
    {
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

    /**
     * 导入数据
     */
    private function importData()
    {
        $db = \Typecho_Db::get();
        $records = isset($_POST['records']) ? $_POST['records'] : [];
        $imported = 0;
        $skipped = 0;

        foreach ($records as $json) {
            $record = json_decode($json, true);
            if (!$record) continue;

            $exists = $db->fetchRow($db->select('id')->from('table.hamlog')
                ->where('`CALL_SIGN` = ?', $record['CALL'])
                ->where('`QSO_DATE` = ?', $record['QSO_DATE'])
                ->where('`TIME_ON` = ?', $record['TIME_ON']));

            if ($exists) { $skipped++; continue; }

            $db->query($db->insert('table.hamlog')->rows([
                'CALL_SIGN' => $record['CALL'] ?? '',
                'QSO_DATE' => $record['QSO_DATE'] ?? '',
                'TIME_ON' => $record['TIME_ON'] ?? '',
                'BAND' => $record['BAND'] ?? '',
                'MODE' => $record['MODE'] ?? '',
                'FREQ' => $record['FREQ'] ?? '',
                'BAND_RX' => $record['BAND_RX'] ?? '',
                'FREQ_RX' => $record['FREQ_RX'] ?? '',
                'PROP_MODE' => $record['PROP_MODE'] ?? '',
                'SAT_NAME' => $record['SAT_NAME'] ?? '',
                'RST_SENT' => $record['RST_SENT'] ?? '',
                'RST_RCVD' => $record['RST_RCVD'] ?? '',
                'TX_PWR' => $record['TX_PWR'] ?? $record['TX_POWER'] ?? '',
                'RX_PWR' => $record['RX_PWR'] ?? $record['RX_POWER'] ?? '',
                'QTH' => $record['QTH'] ?? '',
                'GRID' => $record['GRID'] ?? '',
                'REMARK' => $record['REMARK'] ?? '',
                'CARD_SEND' => 0,
                'CARD_RCV' => 0,
                'CREATED' => time()
            ]));
            $imported++;
        }

        \Typecho_Cookie::set('__typecho_notice', "导入完成：导入 {$imported} 条，跳过 {$skipped} 条重复记录");
        \Typecho_Response::getInstance()->redirect(\Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Page.php'));
    }

    /**
     * 日志列表页面
     */
    private function listPage()
    {
        \Typecho_Response::getInstance()->redirect(\Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Page.php'));
    }

    /**
     * 删除日志
     */
    private function deleteLog()
    {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id > 0) {
            $db = \Typecho_Db::get();
            $db->query($db->delete('table.hamlog')->where('id = ?', $id));
        }
        \Typecho_Cookie::set('__typecho_notice', '删除成功');
        \Typecho_Response::getInstance()->redirect(\Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Page.php'));
    }

    /**
     * 更新收发卡状态
     */
    private function updateCard()
    {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $field = isset($_GET['field']) ? $_GET['field'] : '';
        $value = isset($_GET['v']) ? intval($_GET['v']) : 0;

        if ($id > 0 && in_array($field, ['card_send', 'card_rcv'])) {
            $db = \Typecho_Db::get();
            $db->query($db->update('table.hamlog')->rows([$field => $value])->where('id = ?', $id));
        }

        \Typecho_Response::getInstance()->redirect(\Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Page.php'));
    }

    /**
     * 保存值机员信息
     */
    private function saveProfile()
    {
        $db = Typecho_Db::get();
        $db->query($db->update('table.hamlog_profile')->rows([
            'my_callsign' => isset($_POST['my_callsign']) ? htmlspecialchars($_POST['my_callsign']) : '',
            'my_qth' => isset($_POST['my_qth']) ? htmlspecialchars($_POST['my_qth']) : '',
            'my_device' => isset($_POST['my_device']) ? htmlspecialchars($_POST['my_device']) : '',
            'my_antenna' => isset($_POST['my_antenna']) ? htmlspecialchars($_POST['my_antenna']) : '',
            'my_grid' => isset($_POST['my_grid']) ? htmlspecialchars($_POST['my_grid']) : '',
            'update_time' => time()
        ])->where('id = ?', 1));

        \Typecho_Cookie::set('__typecho_notice', '保存成功');
        \Typecho_Response::getInstance()->redirect(\Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Profile.php'));
    }

    /**
     * 获取单条记录
     */
    private function getRecord()
    {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            echo json_encode(['error' => '无效的记录ID']);
            return;
        }

        $db = \Typecho_Db::get();
        $row = $db->fetchRow($db->select()->from('table.hamlog')->where('id = ?', $id));
        
        if ($row) {
            echo json_encode($row);
        } else {
            echo json_encode(['error' => '记录不存在']);
        }
    }

    /**
     * 更新记录
     */
    private function updateRecord()
    {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) {
            \Typecho_Cookie::set('__typecho_notice', '无效的记录ID');
            \Typecho_Response::getInstance()->redirect(\Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Page.php'));
            return;
        }

        $db = \Typecho_Db::get();
        
        $data = [
            'CALL_SIGN' => isset($_POST['CALL_SIGN']) ? htmlspecialchars(trim($_POST['CALL_SIGN'])) : '',
            'QSO_DATE' => isset($_POST['QSO_DATE']) ? $_POST['QSO_DATE'] : '',
            'TIME_ON' => isset($_POST['TIME_ON']) ? $_POST['TIME_ON'] : '',
            'BAND' => isset($_POST['BAND']) ? htmlspecialchars(trim($_POST['BAND'])) : '',
            'BAND_RX' => isset($_POST['BAND_RX']) ? htmlspecialchars(trim($_POST['BAND_RX'])) : '',
            'MODE' => isset($_POST['MODE']) ? htmlspecialchars(trim($_POST['MODE'])) : '',
            'FREQ' => isset($_POST['FREQ']) ? htmlspecialchars(trim($_POST['FREQ'])) : '',
            'FREQ_RX' => isset($_POST['FREQ_RX']) ? htmlspecialchars(trim($_POST['FREQ_RX'])) : '',
            'RST_SENT' => isset($_POST['RST_SENT']) ? htmlspecialchars(trim($_POST['RST_SENT'])) : '',
            'RST_RCVD' => isset($_POST['RST_RCVD']) ? htmlspecialchars(trim($_POST['RST_RCVD'])) : '',
            'TX_PWR' => isset($_POST['TX_PWR']) ? htmlspecialchars(trim($_POST['TX_PWR'])) : '',
            'RX_PWR' => isset($_POST['RX_PWR']) ? htmlspecialchars(trim($_POST['RX_PWR'])) : '',
            'QTH' => isset($_POST['QTH']) ? htmlspecialchars(trim($_POST['QTH'])) : '',
            'GRID' => isset($_POST['GRID']) ? htmlspecialchars(trim($_POST['GRID'])) : '',
            'PROP_MODE' => isset($_POST['PROP_MODE']) ? htmlspecialchars(trim($_POST['PROP_MODE'])) : '',
            'SAT_NAME' => isset($_POST['SAT_NAME']) ? htmlspecialchars(trim($_POST['SAT_NAME'])) : '',
            'REMARK' => isset($_POST['REMARK']) ? htmlspecialchars(trim($_POST['REMARK'])) : '',
            'CARD_SEND' => isset($_POST['CARD_SEND']) ? 1 : 0,
            'CARD_RCV' => isset($_POST['CARD_RCV']) ? 1 : 0
        ];

        $db->query($db->update('table.hamlog')->rows($data)->where('id = ?', $id));

        \Typecho_Cookie::set('__typecho_notice', '更新成功');
        \Typecho_Response::getInstance()->redirect(\Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Page.php'));
    }

    /**
     * 显示错误页面
     */
    private function showError($message)
    {
        $this->renderHeader('错误');
        echo '<div class="error" style="color:#c00;padding:20px;background:#fee;border:1px solid #c00;margin:20px 0;">' . htmlspecialchars($message) . '</div>';
        echo '<p><a class="btn" href="' . Helper::options()->adminUrl . 'extending.php?panel=HAMLog/Upload.php">返回上传</a></p>';
        $this->renderFooter();
    }

    /**
     * 获取 Action URL
     */
    private function getActionUrl($params = '')
    {
        return Helper::options()->adminUrl . 'action/hamlog' . ($params ? '&' . $params : '');
    }

    /**
     * 渲染页面头部
     */
    private function renderHeader($title)
    {
        require __TYPECHO_ROOT_DIR__ . '/admin/header.php';
        require __TYPECHO_ROOT_DIR__ . '/admin/menu.php';

        echo '<div class="main">';
        echo '<div class="body container">';
        echo '<div class="colgroup">';
        echo '<div class="typecho-page-main" role="main">';
        echo '<div class="typecho-page-title"><h2>' . htmlspecialchars($title) . '</h2></div>';
    }

    /**
     * 渲染页面底部
     */
    private function renderFooter()
    {
        echo '</div></div></div></div>';
        require __TYPECHO_ROOT_DIR__ . '/admin/copyright.php';
        require __TYPECHO_ROOT_DIR__ . '/admin/common-js.php';
        require __TYPECHO_ROOT_DIR__ . '/admin/footer.php';
    }
}
