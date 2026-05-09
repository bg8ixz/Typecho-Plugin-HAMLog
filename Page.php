<?php
/**
 * HAMLog 后台 - 日志管理页面
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

include __TYPECHO_ROOT_DIR__ . '/admin/header.php';
include __TYPECHO_ROOT_DIR__ . '/admin/menu.php';

use Typecho\Db;
use Utils\Helper;

$db = Db::get();

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
    'RX_PWR' => '对方功率',
    'QTH' => '对方QTH',
    'GRID' => '网格坐标',
    'PROP_MODE' => '传播模式',
    'SAT_NAME' => '卫星名称',
    'REMARK' => '备注',
    'CARD_SEND' => '已发卡片',
    'CARD_RCV' => '已收卡片'
];

function getFieldLabel($field, $labels) {
    return $labels[strtoupper($field)] ?? $field;
}

$adminFieldsRaw = 'CALL_SIGN,QSO_DATE,TIME_ON,BAND,BAND_RX,MODE,FREQ,FREQ_RX,REMARK,CARD_SEND,CARD_RCV';
try {
    $config = $db->fetchRow($db->select('value')->from('table.hamlog_config')->where('name = ?', 'admin_fields'));
    if (!empty($config) && !empty($config['value'])) {
        $adminFieldsRaw = $config['value'];
    }
} catch (\Exception $e) {}

try {
    $opts = \Typecho\Widget::widget('Widget_Options');
    $pluginOpts = $opts->plugin('HAMLog');
    if (!empty($pluginOpts) && isset($pluginOpts['admin_fields'])) {
        $adminFieldsRaw = $pluginOpts['admin_fields'];
    }
} catch (\Exception $e) {}

if (is_array($adminFieldsRaw)) {
    $adminFields = array_filter($adminFieldsRaw);
} else {
    $adminFields = array_filter(explode(',', $adminFieldsRaw));
}

$searchField = isset($_GET['search_field']) ? $_GET['search_field'] : 'CALL_SIGN';
$searchKeyword = isset($_GET['search_keyword']) ? trim($_GET['search_keyword']) : '';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pageSize = 20;
$start = ($page - 1) * $pageSize;

$rows = [];
$total = 0;
$totalPages = 1;
try {
    $prefix = $db->getPrefix();
    $tableName = $prefix . 'hamlog';
    
    $where = '';
    $keyword = '';
    if ((isset($searchKeyword) && $searchKeyword !== '') && !empty($searchField)) {
        $validFields = ['CALL_SIGN', 'BAND', 'BAND_RX', 'MODE', 'FREQ', 'QTH', 'GRID', 'PROP_MODE', 'SAT_NAME', 'CARD_SEND', 'CARD_RCV'];
        if (in_array($searchField, $validFields)) {
            if ($searchField === 'CARD_SEND' || $searchField === 'CARD_RCV') {
                $where = " WHERE `{$searchField}` = '" . addslashes($searchKeyword) . "'";
            } else {
                $keyword = '%' . addslashes($searchKeyword) . '%';
                $where = " WHERE `{$searchField}` LIKE '{$keyword}'";
            }
        }
    }
    
    $totalRow = $db->fetchRow($db->query("SELECT COUNT(*) AS count FROM {$tableName}{$where}"));
    $total = intval($totalRow['count'] ?? 0);
    $totalPages = $total > 0 ? ceil($total / $pageSize) : 1;
    $rows = $db->fetchAll("SELECT * FROM `{$tableName}`{$where} ORDER BY QSO_DATE DESC, TIME_ON DESC LIMIT {$start}, {$pageSize}");
} catch (\Exception $e) {}

$baseUrl = Helper::options()->siteUrl . 'usr/plugins/HAMLog/api.php';
$currentUrl = Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Page.php');
$uploadUrl = Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Upload.php');
$profileUrl = Helper::options()->adminUrl . 'extending.php?panel=' . urlencode('HAMLog/Profile.php');

$allFields = [
    'CALL_SIGN' => ['label' => '呼号', 'type' => 'text'],
    'QSO_DATE' => ['label' => '日期', 'type' => 'date'],
    'TIME_ON' => ['label' => '时间', 'type' => 'time'],
    'BAND' => ['label' => '频段', 'type' => 'text'],
    'BAND_RX' => ['label' => '接收频段', 'type' => 'text'],
    'MODE' => ['label' => '模式', 'type' => 'text'],
    'FREQ' => ['label' => '频率', 'type' => 'text'],
    'FREQ_RX' => ['label' => '接收频率', 'type' => 'text'],
    'RST_SENT' => ['label' => '发送报告', 'type' => 'text'],
    'RST_RCVD' => ['label' => '接收报告', 'type' => 'text'],
    'TX_PWR' => ['label' => '己方功率', 'type' => 'text'],
    'RX_PWR' => ['label' => '对方功率', 'type' => 'text'],
    'QTH' => ['label' => '对方QTH', 'type' => 'text'],
    'GRID' => ['label' => '网格坐标', 'type' => 'text'],
    'PROP_MODE' => ['label' => '传播模式', 'type' => 'text'],
    'SAT_NAME' => ['label' => '卫星名称', 'type' => 'text'],
    'REMARK' => ['label' => '备注', 'type' => 'textarea'],
    'CARD_SEND' => ['label' => '已发卡片', 'type' => 'checkbox'],
    'CARD_RCV' => ['label' => '已收卡片', 'type' => 'checkbox']
];
?>

<div class="main">
    <div class="body container">
        <div class="colgroup">
            <div class="typecho-page-main" role="main">
                <div class="typecho-page-title">
                    <h2>通联日志管理 (共 <?php echo $total; ?> 条记录)</h2>
                </div>

                <div style="margin-bottom:20px;display:flex;align-items:center;gap:10px;">
                    <div style="display:flex;gap:10px;">
                        <button type="button" onclick="location.href='<?php echo $uploadUrl; ?>'" class="btn primary">上传日志</button>
                        <button type="button" onclick="location.href='<?php echo $profileUrl; ?>'" class="btn">信息维护</button>
                        <button type="button" onclick="location.href='<?php echo Helper::options()->adminUrl; ?>options-plugin.php?config=HAMLog'" class="btn">插件设置</button>
                    </div>
                    <span style="flex:1;text-align:center;color:#666;font-size:13px;">
                        第 <?php echo $page; ?>/<?php echo max(1, $totalPages); ?> 页 | 共 <?php echo $total; ?> 条记录<?php if ($total > 0): ?>（第 <?php echo ($page - 1) * $pageSize + 1; ?> - <?php echo min($page * $pageSize, $total); ?> 条）<?php endif; ?>
                    </span>
                    <form method="get" action="" style="display:flex;gap:5px;align-items:center;">
                        <input type="hidden" name="panel" value="HAMLog/Page.php">
                        <select name="search_field" id="searchFieldSelect">
                            <option value="CALL_SIGN" <?php echo $searchField === 'CALL_SIGN' ? 'selected' : ''; ?>>呼号</option>
                            <option value="BAND" <?php echo $searchField === 'BAND' ? 'selected' : ''; ?>>频段</option>
                            <option value="BAND_RX" <?php echo $searchField === 'BAND_RX' ? 'selected' : ''; ?>>接收频段</option>
                            <option value="MODE" <?php echo $searchField === 'MODE' ? 'selected' : ''; ?>>模式</option>
                            <option value="FREQ" <?php echo $searchField === 'FREQ' ? 'selected' : ''; ?>>频率</option>
                            <option value="QTH" <?php echo $searchField === 'QTH' ? 'selected' : ''; ?>>对方QTH</option>
                            <option value="GRID" <?php echo $searchField === 'GRID' ? 'selected' : ''; ?>>网格坐标</option>
                            <option value="PROP_MODE" <?php echo $searchField === 'PROP_MODE' ? 'selected' : ''; ?>>传播模式</option>
                            <option value="SAT_NAME" <?php echo $searchField === 'SAT_NAME' ? 'selected' : ''; ?>>卫星名称</option>
                            <option value="CARD_SEND" <?php echo $searchField === 'CARD_SEND' ? 'selected' : ''; ?>>已发卡片</option>
                            <option value="CARD_RCV" <?php echo $searchField === 'CARD_RCV' ? 'selected' : ''; ?>>已收卡片</option>
                        </select>
                        <input type="text" name="search_keyword" id="searchKeywordInput" placeholder="" value="<?php echo htmlspecialchars($searchKeyword); ?>" class="text-s"/>
                        <button type="submit" class="btn btn-s primary">筛选</button>
                        <?php if ((isset($searchKeyword) && $searchKeyword !== '') || (isset($_GET['search_field']) && $_GET['search_field'] !== 'CALL_SIGN')): ?>
                        <button type="button" onclick="location.href='<?php echo $currentUrl; ?>'" class="btn btn-s">重置</button>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="typecho-table-wrap">
                    <table class="typecho-list-table" style="text-align:center;">
                        <thead>
                            <tr>
                                <?php foreach ($adminFields as $field): ?>
                                <th style="text-align:center;"><?php echo htmlspecialchars(getFieldLabel($field, $fieldLabels)); ?></th>
                                <?php endforeach; ?>
                                <th style="text-align:center;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="<?php echo count($adminFields) + 1; ?>" style="text-align:center;color:#999;">暂无数据，请上传 ADIF 日志文件</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($adminFields as $field): ?>
                                <?php
                                $value = $row[$field] ?? ($row[strtolower($field)] ?? '');
                                if ($field === 'CARD_SEND' || $field === 'CARD_RCV'):
                                    $icon = $value ? '✅' : '⬜';
                                    $dbField = strtolower($field);
                                ?>
                                <td style="text-align:center;"><a href="#" class="card-toggle" data-id="<?php echo $row['id']; ?>" data-field="<?php echo $dbField; ?>" data-value="<?php echo $value; ?>" title="点击切换状态"><?php echo $icon; ?></a></td>
                                <?php elseif ($field === 'TX_PWR' || $field === 'RX_PWR'): ?>
                                <td style="text-align:center;"><?php echo !empty($value) ? htmlspecialchars($value) . 'W' : '/'; ?></td> 
                                <?php else: ?>
                                <td style="text-align:center;"><?php echo htmlspecialchars($value); ?></td>
                                <?php endif; ?>
                                <?php endforeach; ?>
                                <td style="text-align:center;">
                                    <a href="#" class="edit-link" data-id="<?php echo $row['id']; ?>" style="margin-right:8px;">编辑</a>
                                    <a href="#" class="delete-link" data-id="<?php echo $row['id']; ?>">删除</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <?php
                $searchQuery = '';
                if (!empty($searchKeyword)) {
                    $searchQuery = '&search_field=' . urlencode($searchField) . '&search_keyword=' . urlencode($searchKeyword);
                }
                ?>
                <div style="margin-top:15px;text-align:center;">
                    <?php if ($page > 1): ?>
                    <button type="button" onclick="location.href='<?php echo $currentUrl; ?>&page=1<?php echo $searchQuery; ?>'" class="btn">首页</button>
                    <button type="button" onclick="location.href='<?php echo $currentUrl; ?>&page=<?php echo $page - 1; ?><?php echo $searchQuery; ?>'" class="btn">上一页</button>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    if ($startPage > 1): ?>
                    <button type="button" onclick="location.href='<?php echo $currentUrl; ?>&page=1<?php echo $searchQuery; ?>'" class="btn">1</button>
                    <?php if ($startPage > 2): ?>
                    <span style="margin:0 3px;">...</span>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i == $page): ?>
                    <button type="button" class="btn primary" style="cursor:default;pointer-events:none;"><?php echo $i; ?></button>
                    <?php else: ?>
                    <button type="button" onclick="location.href='<?php echo $currentUrl; ?>&page=<?php echo $i; ?><?php echo $searchQuery; ?>'" class="btn"><?php echo $i; ?></button>
                    <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                    <span style="margin:0 3px;">...</span>
                    <?php endif; ?>
                    <button type="button" onclick="location.href='<?php echo $currentUrl; ?>&page=<?php echo $totalPages; ?><?php echo $searchQuery; ?>'" class="btn"><?php echo $totalPages; ?></button>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <button type="button" onclick="location.href='<?php echo $currentUrl; ?>&page=<?php echo $page + 1; ?><?php echo $searchQuery; ?>'" class="btn">下一页</button>
                    <button type="button" onclick="location.href='<?php echo $currentUrl; ?>&page=<?php echo $totalPages; ?><?php echo $searchQuery; ?>'" class="btn">末页</button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
var pendingDeleteId = null;
var pendingDeleteInfo = '';

document.addEventListener('DOMContentLoaded', function() {
    var searchFieldSelect = document.getElementById('searchFieldSelect');
    var searchKeywordInput = document.getElementById('searchKeywordInput');
    
    var placeholderMap = {
        'CALL_SIGN': '请输入呼号',
        'BAND': '请输入频段',
        'BAND_RX': '请输入接收频段',
        'MODE': '请输入模式',
        'FREQ': '请输入频率',
        'QTH': '请输入对方QTH',
        'GRID': '请输入网格坐标',
        'PROP_MODE': '请输入传播模式',
        'SAT_NAME': '请输入卫星名称',
        'CARD_SEND': '0（未发）、1（已发）',
        'CARD_RCV': '0（未收）、1（已收）'
    };
    
    function updatePlaceholder() {
        if (searchFieldSelect && searchKeywordInput) {
            var field = searchFieldSelect.value;
            searchKeywordInput.placeholder = placeholderMap[field] || '请输入搜索内容';
        }
    }
    
    updatePlaceholder();
    if (searchFieldSelect) {
        searchFieldSelect.addEventListener('change', updatePlaceholder);
    }

    var editLinks = document.querySelectorAll('.edit-link');
    editLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var id = this.getAttribute('data-id');
            loadRecord(id);
        });
    });

    var deleteLinks = document.querySelectorAll('.delete-link');
    deleteLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var id = this.getAttribute('data-id');
            var row = this.closest('tr');
            var cells = row.querySelectorAll('td');
            var callSign = cells[0] ? cells[0].textContent.trim() : '未知';
            var qsoDate = cells[1] ? cells[1].textContent.trim() : '未知';
            pendingDeleteId = id;
            pendingDeleteInfo = callSign + ' ' + qsoDate;
            document.getElementById('deleteConfirmInfo').textContent = '呼号: ' + callSign + '　|　通联日期: ' + qsoDate;
            document.getElementById('deleteConfirmModal').style.display = 'block';
        });
    });

    var cardToggles = document.querySelectorAll('.card-toggle');
    cardToggles.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var id = this.getAttribute('data-id');
            var field = this.getAttribute('data-field');
            var value = this.getAttribute('data-value');
            var newVal = value == 1 ? 0 : 1;
            toggleCard(this, id, field, newVal);
        });
    });

    var closeModal = document.getElementById('closeModal');
    var closeModalBtn = document.getElementById('closeModalBtn');
    var editModal = document.getElementById('editModal');
    var editForm = document.getElementById('editForm');

    if (closeModal) {
        closeModal.addEventListener('click', function() {
            editModal.style.display = 'none';
        });
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function() {
            editModal.style.display = 'none';
        });
    }

    if (editModal) {
        editModal.addEventListener('click', function(e) {
            if (e.target === this) {
                editModal.style.display = 'none';
            }
        });
    }

    if (editForm) {
        editForm.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm();
        });
    }

    var closeDeleteConfirmModal = document.getElementById('closeDeleteConfirmModal');
    var cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    var confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    var deleteConfirmModal = document.getElementById('deleteConfirmModal');
    var deleteSuccessModal = document.getElementById('deleteSuccessModal');

    if (closeDeleteConfirmModal) {
        closeDeleteConfirmModal.addEventListener('click', function() {
            deleteConfirmModal.style.display = 'none';
        });
    }

    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function() {
            deleteConfirmModal.style.display = 'none';
        });
    }

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (pendingDeleteId) {
                deleteConfirmModal.style.display = 'none';
                deleteRecord(pendingDeleteId);
            }
        });
    }

    if (deleteConfirmModal) {
        deleteConfirmModal.addEventListener('click', function(e) {
            if (e.target === this) {
                deleteConfirmModal.style.display = 'none';
            }
        });
    }

    if (deleteSuccessModal) {
        deleteSuccessModal.addEventListener('click', function(e) {
            if (e.target === this) {
                deleteSuccessModal.style.display = 'none';
                location.reload();
            }
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (editModal.style.display === 'block') {
                editModal.style.display = 'none';
            }
            if (deleteConfirmModal.style.display === 'block') {
                deleteConfirmModal.style.display = 'none';
            }
        }
    });
});

function loadRecord(id) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '<?php echo $baseUrl; ?>?do=get&id=' + id, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    document.getElementById('editId').value = data.id;
                    <?php foreach ($allFields as $field => $info): ?>
                    var value = data['<?php echo $field; ?>'] || data['<?php echo strtolower($field); ?>'] || '';
                    var elem = document.getElementById('edit_<?php echo $field; ?>');
                    if (elem.type === 'checkbox') {
                        elem.checked = value == 1;
                    } else {
                        elem.value = value;
                    }
                    <?php endforeach; ?>
                    document.getElementById('editModal').style.display = 'block';
                } catch (e) {
                    alert('加载数据失败: ' + e.message);
                }
            } else {
                alert('加载数据失败，HTTP状态: ' + xhr.status);
            }
        }
    };
    xhr.send();
}

function submitForm() {
    var form = document.getElementById('editForm');
    var formData = new FormData(form);
    
    var callSign = document.getElementById('edit_CALL_SIGN').value || '未知';
    var qsoDate = document.getElementById('edit_QSO_DATE').value || '未知';
    var timeOn = document.getElementById('edit_TIME_ON').value || '未知';
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo $baseUrl; ?>?do=update', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        document.getElementById('toastCallSign').textContent = callSign;
                        document.getElementById('toastQsoDate').textContent = qsoDate;
                        document.getElementById('toastTimeOn').textContent = timeOn;
                        document.getElementById('editModal').style.display = 'none';
                        document.getElementById('toastModal').style.display = 'flex';
                        setTimeout(function() {
                            document.getElementById('toastModal').style.display = 'none';
                            location.reload();
                        }, 3000);
                    } else {
                        alert(data.error || '更新失败');
                    }
                } catch (e) {
                    alert('更新失败: ' + e.message);
                }
            } else {
                alert('更新失败，HTTP状态: ' + xhr.status);
            }
        }
    };
    xhr.send(formData);
}

function deleteRecord(id) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '<?php echo $baseUrl; ?>?do=delete&id=' + id, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        document.getElementById('deleteSuccessModal').style.display = 'flex';
                        setTimeout(function() {
                            document.getElementById('deleteSuccessModal').style.display = 'none';
                            location.reload();
                        }, 3000);
                    } else {
                        alert(data.error || '删除失败');
                    }
                } catch (e) {
                    alert('删除失败: ' + e.message);
                }
            } else {
                alert('删除失败，HTTP状态: ' + xhr.status);
            }
        }
    };
    xhr.send();
}

function toggleCard(element, id, field, newVal) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '<?php echo $baseUrl; ?>?do=updateCard&id=' + id + '&field=' + field + '&v=' + newVal, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        element.innerHTML = newVal ? '✅' : '⬜';
                        element.setAttribute('data-value', newVal);
                    } else {
                        alert(data.error || '切换失败');
                    }
                } catch (e) {
                    alert('切换失败: ' + e.message);
                }
            } else {
                alert('切换失败，HTTP状态: ' + xhr.status);
            }
        }
    };
    xhr.send();
}
</script>

<div id="editModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:8px;width:90%;max-width:700px;max-height:85vh;overflow-y:auto;">
        <div style="padding:16px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">
            <h3>通联记录编辑</h3>
            <button type="button" id="closeModal" style="background:none;border:none;font-size:20px;cursor:pointer;color:#999;">&times;</button>
        </div>
        <form id="editForm" method="post" action="<?php echo $baseUrl; ?>">
            <input type="hidden" name="do" value="update">
            <input type="hidden" name="id" id="editId">
            <div style="padding:30px;">
                <!-- 呼号 -->
                <div style="margin-bottom:16px;display:flex;align-items:center;">
                    <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">呼号：</label>
                    <input type="text" name="CALL_SIGN" id="edit_CALL_SIGN" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                </div>
                
                <!-- 日期、时间 -->
                <div style="margin-bottom:16px;display:flex;align-items:center;gap:20px;">
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">日期：</label>
                        <input type="date" name="QSO_DATE" id="edit_QSO_DATE" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">时间：</label>
                        <input type="time" name="TIME_ON" id="edit_TIME_ON" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                </div>
                
                <!-- 频段、接收频段 -->
                <div style="margin-bottom:16px;display:flex;align-items:center;gap:20px;">
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">频段：</label>
                        <input type="text" name="BAND" id="edit_BAND" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">接收频段：</label>
                        <input type="text" name="BAND_RX" id="edit_BAND_RX" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                </div>
                
                <!-- 模式 -->
                <div style="margin-bottom:16px;display:flex;align-items:center;">
                    <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">模式：</label>
                    <input type="text" name="MODE" id="edit_MODE" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                </div>
                
                <!-- 频率、接收频率 -->
                <div style="margin-bottom:16px;display:flex;align-items:center;gap:20px;">
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">频率：</label>
                        <input type="text" name="FREQ" id="edit_FREQ" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">接收频率：</label>
                        <input type="text" name="FREQ_RX" id="edit_FREQ_RX" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                </div>
                
                <!-- 发送报告、接收报告 -->
                <div style="margin-bottom:16px;display:flex;align-items:center;gap:20px;">
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">发送报告：</label>
                        <input type="text" name="RST_SENT" id="edit_RST_SENT" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">接收报告：</label>
                        <input type="text" name="RST_RCVD" id="edit_RST_RCVD" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                </div>
                
                <!-- 己方功率、对方功率 -->
                <div style="margin-bottom:16px;display:flex;align-items:center;gap:20px;">
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">己方功率：</label>
                        <input type="text" name="TX_PWR" id="edit_TX_PWR" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;" onkeydown="if(event.key==='.' && this.value.includes('.')){event.preventDefault();return false;}" oninput="this.value=this.value.replace(/[^0-9.]/g,'').replace(/^0+(?=\d)/,'0').replace(/^0{2,}(?=\.)/,'0')">
                    </div>
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">对方功率：</label>
                        <input type="text" name="RX_PWR" id="edit_RX_PWR" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;" onkeydown="if(event.key==='.' && this.value.includes('.')){event.preventDefault();return false;}" oninput="this.value=this.value.replace(/[^0-9.]/g,'').replace(/^0+(?=\d)/,'0').replace(/^0{2,}(?=\.)/,'0')">
                    </div>
                </div>
                
                <!-- 对方QTH、网格坐标 -->
                <div style="margin-bottom:16px;display:flex;align-items:center;gap:20px;">
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">对方QTH：</label>
                        <input type="text" name="QTH" id="edit_QTH" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">网格坐标：</label>
                        <input type="text" name="GRID" id="edit_GRID" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                </div>
                
                <!-- 传播模式、卫星名称 -->
                <div style="margin-bottom:16px;display:flex;align-items:center;gap:20px;">
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">传播模式：</label>
                        <input type="text" name="PROP_MODE" id="edit_PROP_MODE" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">卫星名称：</label>
                        <input type="text" name="SAT_NAME" id="edit_SAT_NAME" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:4px;">
                    </div>
                </div>
                
                <!-- 备注 -->
                <div style="margin-bottom:16px;">
                    <label style="display:block;margin-bottom:8px;font-weight:bold;color:#333;">备注：</label>
                    <textarea name="REMARK" id="edit_REMARK" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;min-height:60px;box-sizing:border-box;"></textarea>
                </div>
                
                <!-- 已发卡片、已收卡片 -->
                <div style="display:flex;align-items:center;gap:20px;">
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">已发卡片：</label>
                        <input type="checkbox" name="CARD_SEND" id="edit_CARD_SEND" value="1">
                    </div>
                    <div style="display:flex;align-items:center;flex:1;">
                        <label style="width:100px;font-weight:bold;color:#333;flex-shrink:0;">已收卡片：</label>
                        <input type="checkbox" name="CARD_RCV" id="edit_CARD_RCV" value="1">
                    </div>
                </div>
            </div>
            <div style="padding:16px;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:12px;">
                <button type="button" id="closeModalBtn" class="btn" onclick="$('#editModal').hide();">取消</button>
                <button type="submit" class="btn primary">保存</button>
            </div>
        </form>
    </div>
</div>

<div id="toastModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.3);z-index:2000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;padding:30px 40px;box-shadow:0 4px 20px rgba(0,0,0,0.15);text-align:center;">
        <div style="font-size:24px;margin-bottom:10px;">✓</div>
        <div style="color:#333;font-size:16px;">与 <span id="toastCallSign" style="font-weight:bold;color:#007bff;">未知</span>（<span id="toastQsoDate" style="font-weight:bold;color:#007bff;">未知</span> <span id="toastTimeOn" style="font-weight:bold;color:#007bff;">未知</span>）的通联记录更新成功！</div>
        <div style="color:#999;font-size:14px;margin-top:5px;">3秒后自动刷新...</div>
    </div>
</div>

<div id="deleteConfirmModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1500;">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:8px;width:90%;max-width:450px;">
        <div style="padding:20px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;">确认删除</h3>
            <button type="button" id="closeDeleteConfirmModal" style="background:none;border:none;font-size:20px;cursor:pointer;color:#999;">&times;</button>
        </div>
        <div style="padding:30px;text-align:center;">
            <div style="font-size:16px;color:#333;margin-bottom:10px;">确定删除此记录？</div>
            <div id="deleteConfirmInfo" style="color:#666;font-size:14px;"></div>
        </div>
        <div style="padding:16px;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:12px;">
            <button type="button" id="cancelDeleteBtn" class="btn">取消</button>
            <button type="button" id="confirmDeleteBtn" class="btn primary">确定</button>
        </div>
    </div>
</div>

<div id="deleteSuccessModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.3);z-index:2000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:8px;padding:30px 40px;box-shadow:0 4px 20px rgba(0,0,0,0.15);text-align:center;">
        <div style="font-size:24px;margin-bottom:10px;color:#28a745;">✓</div>
        <div style="color:#333;font-size:16px;">删除成功！</div>
        <div style="color:#999;font-size:14px;margin-top:5px;">3秒后自动刷新...</div>
    </div>
</div>

<?php
include __TYPECHO_ROOT_DIR__ . '/admin/copyright.php';
include __TYPECHO_ROOT_DIR__ . '/admin/common-js.php';
include __TYPECHO_ROOT_DIR__ . '/admin/footer.php';
?>