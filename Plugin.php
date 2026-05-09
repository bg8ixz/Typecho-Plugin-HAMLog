<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 适配Typecho的业余无线电通联日志插件，用于管理和前台展示、查询通联日志。
 * 
 * @package HAMLog
 * @author BG8IXZ
 * @version 1.0.1
 * @link https://imkee.com
 */

class HAMLog_Plugin implements Typecho_Plugin_Interface
{
    const VERSION = '1.0.1';
    const TABLE_NAME = 'hamlog_config';

    public static function activate()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $tableName = $prefix . self::TABLE_NAME;

        try {
            $db->query("SELECT 1 FROM `{$tableName}` LIMIT 1");
        } catch (\Exception $e) {
            $db->query("CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL DEFAULT '',
                `value` TEXT DEFAULT NULL,
                `timestamp` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                UNIQUE KEY `uk_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}hamlog` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `CALL_SIGN` VARCHAR(20) NOT NULL DEFAULT '',
            `QSO_DATE` DATE NOT NULL,
            `TIME_ON` TIME NOT NULL,
            `BAND` VARCHAR(10) NOT NULL DEFAULT '',
            `BAND_RX` VARCHAR(10) NOT NULL DEFAULT '',
            `MODE` VARCHAR(10) NOT NULL DEFAULT '',
            `FREQ` VARCHAR(20) NOT NULL DEFAULT '',
            `FREQ_RX` VARCHAR(20) NOT NULL DEFAULT '',
            `RST_SENT` VARCHAR(10) NOT NULL DEFAULT '',
            `RST_RCVD` VARCHAR(10) NOT NULL DEFAULT '',
            `TX_PWR` VARCHAR(20) NOT NULL DEFAULT '',
            `RX_PWR` VARCHAR(20) NOT NULL DEFAULT '',
            `QTH` VARCHAR(100) NOT NULL DEFAULT '',
            `GRID` VARCHAR(10) NOT NULL DEFAULT '',
            `PROP_MODE` VARCHAR(20) NOT NULL DEFAULT '',
            `SAT_NAME` VARCHAR(50) NOT NULL DEFAULT '',
            `REMARK` TEXT NULL,
            `CARD_SEND` TINYINT(1) NOT NULL DEFAULT 0,
            `CARD_RCV` TINYINT(1) NOT NULL DEFAULT 0,
            `CREATED` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $db->query($sql);

        try {
            $result = $db->fetchRow($db->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = '{$prefix}hamlog' AND column_name = 'BAND_RX'"));
            if ($result['cnt'] == 0) {
                $db->query("ALTER TABLE `{$prefix}hamlog` ADD COLUMN `BAND_RX` VARCHAR(10) NOT NULL DEFAULT '' AFTER `BAND`");
            }
        } catch (\Exception $e) {}

        try {
            $db->query("ALTER TABLE `{$prefix}hamlog` CHANGE COLUMN `CALL` `CALL_SIGN` VARCHAR(20) NOT NULL DEFAULT ''");
        } catch (\Exception $e) {}

        try {
            $result = $db->fetchRow($db->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = '{$prefix}hamlog' AND column_name = 'TX_PWR'"));
            if ($result['cnt'] == 0) {
                $db->query("ALTER TABLE `{$prefix}hamlog` ADD COLUMN `TX_PWR` VARCHAR(20) NOT NULL DEFAULT '' AFTER `RST_RCVD`");
            }
        } catch (\Exception $e) {}

        try {
            $result = $db->fetchRow($db->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = '{$prefix}hamlog' AND column_name = 'RX_PWR'"));
            if ($result['cnt'] == 0) {
                $db->query("ALTER TABLE `{$prefix}hamlog` ADD COLUMN `RX_PWR` VARCHAR(20) NOT NULL DEFAULT '' AFTER `TX_PWR`");
            }
        } catch (\Exception $e) {}

        try {
            $result = $db->fetchRow($db->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = '{$prefix}hamlog_profile' AND column_name = 'my_call'"));
            if ($result['cnt'] > 0) {
                $db->query("ALTER TABLE `{$prefix}hamlog_profile` CHANGE COLUMN `my_call` `my_callsign` VARCHAR(20) NOT NULL DEFAULT ''");
            }
        } catch (\Exception $e) {}

        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}hamlog_profile` (
            `id` INT UNSIGNED NOT NULL DEFAULT 1,
            `my_callsign` VARCHAR(20) NOT NULL DEFAULT '',
            `my_qth` VARCHAR(100) NOT NULL DEFAULT '',
            `my_device` VARCHAR(100) NOT NULL DEFAULT '',
            `my_power` VARCHAR(20) NOT NULL DEFAULT '',
            `my_antenna` VARCHAR(100) NOT NULL DEFAULT '',
            `my_grid` VARCHAR(10) NOT NULL DEFAULT '',
            `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $db->query($sql);

        self::_restoreToOptions($db, $prefix, $tableName);

        try {
            $profileExists = $db->fetchObject($db->select('id')->from('table.hamlog_profile')->where('id = ?', 1));
            if (!$profileExists) {
                $db->query($db->insert('table.hamlog_profile')->rows(array('id' => 1, 'update_time' => time())));
            }
        } catch (\Exception $e) {}

        // ======================
        // 注册菜单
        // ======================
        $menuIndex = Utils\Helper::addMenu('通联日志'); // 创建一级菜单
        Utils\Helper::addPanel($menuIndex, 'HAMLog/Upload.php', '日志上传', '上传ADIF文件', 'administrator');
        Utils\Helper::addPanel($menuIndex, 'HAMLog/Page.php', '通联日志', '管理通联日志', 'administrator');
        Utils\Helper::addPanel($menuIndex, 'HAMLog/Profile.php', '信息维护', '值机员信息', 'administrator');
        
        Typecho_Plugin::factory('Widget_Abstract_Contents')->content = __CLASS__ . '::parseShortcode';
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerpt = __CLASS__ . '::parseShortcode';

        return '插件启用成功！';
    }

    private static function _restoreToOptions($db, $prefix, $tableName)
    {
        $pluginName = 'plugin:HAMLog';

        $config = [];
        try {
            $rows = $db->fetchAll("SELECT name, value FROM `{$tableName}`");
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $config[$row['name']] = $row['value'];
                }
            }
        } catch (\Exception $e) {}

        if (!empty($config)) {
            $existing = $db->fetchRow(
                $db->select()->from('table.options')->where('name = ?', $pluginName)
            );
            if (empty($existing)) {
                $db->query($db->insert('table.options')->rows([
                    'name' => $pluginName,
                    'value' => json_encode($config),
                    'user' => 0,
                ]));
            } else {
                $db->query($db->update('table.options')->rows([
                    'value' => json_encode($config),
                ])->where('name = ?', $pluginName)->where('user = ?', $existing['user']));
            }
            return;
        }

        try {
            $optsRow = $db->fetchRow(
                $db->select()->from('table.options')->where('name = ?', $pluginName)
            );
            if (!empty($optsRow)) {
                $oldConfig = json_decode($optsRow['value'], true) ?: [];
                if (!empty($oldConfig)) {
                    $now = time();
                    foreach ($oldConfig as $key => $value) {
                        try {
                            $db->query($db->insert($tableName)->rows([
                                'name' => $key,
                                'value' => is_array($value) ? implode(',', $value) : $value,
                                'timestamp' => $now,
                            ]));
                        } catch (\Exception $e) {
                            $db->query($db->update($tableName)->rows([
                                'value' => is_array($value) ? implode(',', $value) : $value,
                                'timestamp' => $now,
                            ])->where('name = ?', $key));
                        }
                    }
                }
            }
        } catch (\Exception $e) {}

        try {
            $existing = $db->fetchRow(
                $db->select()->from('table.options')->where('name = ?', $pluginName)
            );
            if (empty($existing)) {
                $defaultConfig = [
                    'save_type' => 'local',
                    'supabase_api' => '',
                    'supabase_key' => '',
                    'supabase_table' => '',
                    'supabase_note' => '',
                    'front_fields' => 'CALL_SIGN,QSO_DATE,TIME_ON,BAND,MODE,FREQ',
                    'admin_fields' => 'CALL_SIGN,QSO_DATE,TIME_ON,BAND,MODE,FREQ,REMARK,CARD_SEND,CARD_RCV'
                ];
                $db->query($db->insert('table.options')->rows([
                    'name' => $pluginName,
                    'value' => json_encode($defaultConfig),
                    'user' => 0,
                ]));
            }
        } catch (\Exception $e) {}
    }

    public static function deactivate(): string
    {
        try {
            // 移除一级菜单
            $menuIndex = \Utils\Helper::removeMenu('通联日志');
            
            // 如果菜单存在，就删除下面的三个面板
            if ($menuIndex !== null) {
                \Utils\Helper::removePanel($menuIndex, 'HAMLog/Page.php');
                \Utils\Helper::removePanel($menuIndex, 'HAMLog/Upload.php');
                \Utils\Helper::removePanel($menuIndex, 'HAMLog/Profile.php');
            }
        } catch (\Throwable $e) {
            // 静默忽略错误
        }

        return '插件已禁用';
    }

    private static function getFormValue(string $key, string $default = '')
    {
        try {
            $opts = Typecho_Widget::widget('Widget_Options');
            $pluginOpts = $opts->plugin('HAMLog');
            if (!empty($pluginOpts) && isset($pluginOpts[$key])) {
                return $pluginOpts[$key];
            }
        } catch (\Throwable $e) {}

        try {
            $db = Typecho_Db::get();
            $tableName = $db->getPrefix() . self::TABLE_NAME;
            $row = $db->fetchRow($db->select('value')->from($tableName)->where('name = ?', $key));
            if (!empty($row)) {
                return $row['value'];
            }
        } catch (\Throwable $e) {}

        return $default;
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        ?>
        <!-- 版本检测 -->
        <div style="border: 1px solid #e0e0e0; padding: 15px; margin-bottom: 20px; background: #fff;">
            <div id="hamlog_version_check">版本检测中...</div>
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
        <div style="border: 1px solid #e0e0e0; padding: 15px; margin-bottom: 20px; background: #fff;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-weight:bold;">Supabase 连接状态：</span>
                <span style="<?php echo $statusClass; ?>padding:4px 12px;border-radius:4px;font-size:13px;"><?php echo $statusText; ?></span>
            </div>
        </div>
        <script>
            var hamlog_version = "v<?php echo self::VERSION; ?>";
            function hamlog_check_update() {
                var container = document.getElementById("hamlog_version_check");
                if (!container) {
                    return;
                }
                var ajax = new XMLHttpRequest();
                ajax.open("get", "https://api.github.com/repos/bg8ixz/Typecho-Plugin-HAMLog/releases/latest");
                ajax.send();
                ajax.onreadystatechange = function() {
                    if (ajax.readyState === 4 && ajax.status === 200) {
                        var obj = JSON.parse(ajax.responseText);
                        var newest = obj.tag_name;
                        if (newest > hamlog_version) {
                            container.innerHTML = "<div style='display: flex; justify-content: space-between; align-items: center;'><span>发现新版本：<strong>" + obj.name + "</strong> | 当前版本：" + hamlog_version + "</span><div style='display: flex; gap: 8px;'><button type='button' onclick=\"location.href='" + obj.zipball_url + "'\" class='btn primary'>点击下载</button><button type='button' onclick=\"window.open('" + obj.html_url + "', '_blank')\" class='btn'>更新日志</button></div></div>";
                        } else {
                            container.innerHTML = "当前插件版本：<strong>" + hamlog_version + "</strong> 已是最新版，无需更新。";
                        }
                    } else if (ajax.readyState === 4 && ajax.status !== 200) {
                        container.innerHTML = "版本检测失败，请稍后重试。";
                    }
                }
            }
            hamlog_check_update();
        </script>
        <?php

        $fieldOptions = [
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
            'RX_PWR' => '对方功率',
            'QTH' => '对方QTH',
            'GRID' => '网格坐标',
            'PROP_MODE' => '传播模式',
            'SAT_NAME' => '卫星名称',
            'REMARK' => '备注',
            'CARD_SEND' => '已发卡片',
            'CARD_RCV' => '已收卡片'
        ];

        $saveTypeValue = self::getFormValue('save_type', 'local');
        $supabaseApiValue = self::getFormValue('supabase_api', '');
        $supabaseKeyValue = self::getFormValue('supabase_key', '');
        $supabaseTableValue = self::getFormValue('supabase_table', '');
        $supabaseNoteValue = self::getFormValue('supabase_note', '');
        $frontFieldsValue = self::getFormValue('front_fields', 'CALL_SIGN,QSO_DATE,TIME_ON,BAND,MODE,FREQ');
        $adminFieldsValue = self::getFormValue('admin_fields', 'CALL_SIGN,QSO_DATE,TIME_ON,BAND,MODE,FREQ,RST_SENT,RST_RCVD,REMARK,CARD_SEND,CARD_RCV');

        $frontFieldsArr = is_array($frontFieldsValue) ? $frontFieldsValue : explode(',', $frontFieldsValue);
        $adminFieldsArr = is_array($adminFieldsValue) ? $adminFieldsValue : explode(',', $adminFieldsValue);

        $saveType = new Typecho_Widget_Helper_Form_Element_Radio(
            'save_type',
            ['local' => '本地数据库', 'supabase' => 'Supabase'],
            $saveTypeValue,
            '数据存储位置',
            '选择通联日志数据的存储位置，本地数据库或 Supabase 云数据库（保留功能）。'
        );
        $form->addInput($saveType);

        $supabaseApi = new Typecho_Widget_Helper_Form_Element_Text(
            'supabase_api', null, $supabaseApiValue,
            'Supabase API 地址',
            'Supabase 项目的 API 端点地址，格式如：https://xxxxxx.supabase.co，无账户 <a href="https://supabase.com/" target="_blank">Supabase 注册>></a>。'
        );
        $form->addInput($supabaseApi);

        $supabaseKey = new Typecho_Widget_Helper_Form_Element_Text(
            'supabase_key', null, $supabaseKeyValue,
            'Supabase API Key',
            'Supabase 项目的 API Key，可在项目设置中获取。'
        );
        $form->addInput($supabaseKey);

        $supabaseTable = new Typecho_Widget_Helper_Form_Element_Text(
            'supabase_table', null, $supabaseTableValue,
            'Supabase 数据表名',
            '存储通联日志的数据表名称，需提前在 Supabase 中创建。'
        );
        $form->addInput($supabaseTable);

        $supabaseNote = new Typecho_Widget_Helper_Form_Element_Text(
            'supabase_note', null, $supabaseNoteValue,
            'Supabase 备注',
            '可填写额外的说明信息或配置备注（给自己看的，无其他作用）。'
        );
        $form->addInput($supabaseNote);

        $frontFields = new Typecho_Widget_Helper_Form_Element_Checkbox(
            'front_fields',
            $fieldOptions,
            $frontFieldsArr,
            '前台页面显示字段',
            '选择前台页面要显示的字段，创建页面后使用[HAMLog]标签即可调用。'
        );
        $form->addInput($frontFields);

        $adminFields = new Typecho_Widget_Helper_Form_Element_Checkbox(
            'admin_fields',
            $fieldOptions,
            $adminFieldsArr,
            '后台日志列表显示字段',
            '选择后台日志列表要显示的字段，按需选择即可。'
        );
        $form->addInput($adminFields);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {} 

    public static function configHandle(array $settings, bool $isInit): bool
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $tableName = $prefix . self::TABLE_NAME;
        $pluginName = 'plugin:HAMLog';
        $now = time();

        foreach ($settings as $key => $value) {
            $saveValue = is_array($value) ? implode(',', $value) : $value;
            try {
                $db->query($db->insert($tableName)->rows([
                    'name' => $key,
                    'value' => $saveValue,
                    'timestamp' => $now,
                ]));
            } catch (\Exception $e) {
                $db->query($db->update($tableName)->rows([
                    'value' => $saveValue,
                    'timestamp' => $now,
                ])->where('name = ?', $key));
            }
        }

        $options = $db->fetchAll(
            $db->select()->from('table.options')->where('name = ?', $pluginName)
        );

        if (empty($options)) {
            $db->query($db->insert('table.options')->rows([
                'name' => $pluginName,
                'value' => json_encode($settings),
                'user' => 0,
            ]));
        } else {
            foreach ($options as $option) {
                $value = json_decode($option['value'], true) ?: [];
                $value = array_merge($value, $settings);
                $db->query($db->update('table.options')->rows([
                    'value' => json_encode($value),
                ])->where('name = ?', $pluginName)->where('user = ?', $option['user']));
            }
        }

        return true;
    }

    public static function getConfig()
    {
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

    private static function getShowFields()
    {
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $table = $prefix . 'hamlog_config';

            $row = $db->fetchRow($db->query("SELECT value FROM {$table} WHERE name='front_fields' LIMIT 1"));
            
            if (!empty($row['value'])) {
                $fields = trim($row['value']);
                return array_filter(array_map('trim', explode(',', $fields)));
            }
        } catch (Exception $e) {}

        return ['CALL_SIGN'];
    }

    private static function fieldLabels()
    {
        return [
            'CALL_SIGN' => '呼号',
            'QSO_DATE'  => '日期',
            'TIME_ON'   => '时间',
            'BAND'      => '频段',
            'BAND_RX'   => '接收频段',
            'MODE'      => '模式',
            'FREQ'      => '频率',
            'FREQ_RX'   => '接收频率',
            'RST_SENT'  => '发送报告',
            'RST_RCVD'  => '接收报告',
            'TX_PWR'    => '己方功率',
            'RX_PWR'    => '对方功率',
            'QTH'       => '对方QTH',
            'GRID'      => '网格坐标',
            'PROP_MODE' => '传播模式',
            'SAT_NAME'  => '卫星名称',
            'REMARK'    => '备注',
            'CARD_SEND' => '已发卡片',
            'CARD_RCV'  => '已收卡片',
        ];
    }

    private static function getPage()
    {
        return max(1, intval($_GET['page'] ?? 1));
    }

    private static function getSearch()
    {
        return trim($_GET['call'] ?? '');
    }

    public static function renderList()
    {
        require_once __DIR__ . '/DataAccess.php';
        
        $da = new HAMLog_DataAccess();
        $page = self::getPage();
        $call = self::getSearch();
        $limit = 15;    // 每页显示15条记录
        $showFields = self::getShowFields();
        $labels = self::fieldLabels();

        try {
            $searchField = $call ? 'CALL_SIGN' : null;
            $searchKeyword = $call;
            
            $total = $da->getTotalRecords($searchField, $searchKeyword);
            $totalPage = ceil($total / $limit);
            $rows = $da->getRecords($page, $limit, $searchField, $searchKeyword);

            $myCall = '';
            try {
                $profileRow = $da->getProfile();
                if (!empty($profileRow['my_callsign'])) {
                    $myCall = trim($profileRow['my_callsign']);
                }
            } catch (Exception $e) {}

            $headerText = '根据《业余无线电台管理办法》第三十六条之规定，业余无线电爱好者需要记录通联日志且至少保留两年';
            if (!empty($myCall)) {
                $headerText .= '，本页面公布业余无线电台 ' . htmlspecialchars($myCall) . ' 的通联日志';
            }
            $headerText .= '。';

            $searchHtml = '
            <h3 style="margin:15px 0;padding:12px 15px;background:#e7f3ff;border-left:4px solid #007bff;color:#0c63e4;font-weight:normal;font-size:14px;">' . $headerText . '</h3>
            <form method="get" style="margin:15px 0;display:flex;gap:10px;flex-wrap:wrap;">
                <input type="text" name="call" value="'.htmlspecialchars($call).'" placeholder="输入您想搜索的呼号..." style="flex:1;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
                <button type="submit" style="padding:8px 32px;min-width:100px;background:#007bff;color:white;border:none;border-radius:6px;cursor:pointer;">搜索</button>
                '.($call ? '<button type="button" onclick="window.location.href=window.location.pathname" style="padding:8px 32px;background:#6c757d;color:white;border:none;border-radius:6px;cursor:pointer;">重置</button>' : '').'
            </form>';

            if (empty($rows)) {
                return $searchHtml . '<div style="padding:20px;text-align:center;background:#f8f9fa;border-radius:8px;">暂无记录</div>';
            }

            $paginationInfo = '<div style="text-align:right;margin-bottom:10px;color:#666;font-size:13px;">第 ' . $page . '/' . max(1, $totalPage) . ' 页 | 共 ' . $total . ' 条记录';
            if ($total > 0) {
                $paginationInfo .= '（第 ' . (($page - 1) * $limit + 1) . ' - ' . min($page * $limit, $total) . ' 条）';
            }
            $paginationInfo .= '</div>';

            $html = $searchHtml . $paginationInfo . '<div style="overflow-x:auto;margin:15px 0;">
                <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);text-align:center;">
                <tr style="background:#f7f9fc;text-align:center;">';
            
            foreach ($showFields as $f) {
                $name = $labels[$f] ?? $f;
                $html .= '<th style="padding:10px;border-bottom:1px solid #eee;text-align:center;">'.$name.'</th>';
            }
            $html .= '</tr>';

            foreach ($rows as $r) {
                $html .= '<tr>';
                foreach ($showFields as $f) {
                    if ($f === 'CARD_SEND' || $f === 'CARD_RCV') {
                        $val = $r[$f] ? '✔' : '✖';
                    } elseif ($f === 'TX_PWR' || $f === 'RX_PWR') {
                        $val = !empty($r[$f]) ? htmlspecialchars($r[$f]) . 'W' : '/';
                    } else {
                        $val = !empty($r[$f]) ? htmlspecialchars($r[$f]) : '/';
                    }
                    $html .= '<td style="padding:10px;border-bottom:1px solid #eee;text-align:center;">'.$val.'</td>';
                }
                $html .= '</tr>';
            }

            $html .= '</table></div>';

            $pageHtml = '<form method="get" style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin:20px 0;align-items:center;">';
            if($call) $pageHtml .= '<input type="hidden" name="call" value="'.htmlspecialchars($call).'">';
            
            if ($page > 1) {
                $pageHtml .= '<button type="submit" name="page" value="1" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;background:#fff;cursor:pointer;">首页</button>';
                $pageHtml .= '<button type="submit" name="page" value="'.($page-1).'" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;background:#fff;cursor:pointer;">上一页</button>';
            }
            
            $startPage = max(1, $page - 2);
            $endPage = min($totalPage, $page + 2);
            
            if ($startPage > 1) {
                $pageHtml .= '<button type="submit" name="page" value="1" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;background:#fff;cursor:pointer;">1</button>';
                if ($startPage > 2) {
                    $pageHtml .= '<span style="margin:0 3px;">...</span>';
                }
            }
            
            for ($i = $startPage; $i <= $endPage; $i++) {
                $active = $i == $page ? 'background:#007bff;color:white;border-color:#007bff;' : 'background:#fff;';
                $pageHtml .= '<button type="submit" name="page" value="'.$i.'" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;'.$active.'cursor:pointer;">'.$i.'</button>';
            }
            
            if ($endPage < $totalPage) {
                if ($endPage < $totalPage - 1) {
                    $pageHtml .= '<span style="margin:0 3px;">...</span>';
                }
                $pageHtml .= '<button type="submit" name="page" value="'.$totalPage.'" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;background:#fff;cursor:pointer;">'.$totalPage.'</button>';
            }
            
            if ($page < $totalPage) {
                $pageHtml .= '<button type="submit" name="page" value="'.($page+1).'" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;background:#fff;cursor:pointer;">下一页</button>';
                $pageHtml .= '<button type="submit" name="page" value="'.$totalPage.'" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;background:#fff;cursor:pointer;">末页</button>';
            }
            $pageHtml .= '</form>';

            return $html.$pageHtml;

        } catch (Exception $e) {
            return '<div style="color:red;padding:15px;">错误：'.$e->getMessage().'</div>';
        }
    }

    public static function parseShortcode($content)
    {
        return str_replace('[HAMLog]', self::renderList(), $content);
    }
}
