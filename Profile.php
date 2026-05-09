<?php
/**
 * HAMLog 后台 - 值机员信息维护页面
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 在输出前，先执行保存逻辑
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/DataAccess.php';
    $da = new HAMLog_DataAccess();
    $da->updateProfile([
        'my_callsign' => htmlspecialchars(trim($_POST['my_callsign'] ?? '')),
        'my_qth' => htmlspecialchars(trim($_POST['my_qth'] ?? '')),
        'my_device' => htmlspecialchars(trim($_POST['my_device'] ?? '')),
        'my_power' => htmlspecialchars(trim($_POST['my_power'] ?? '')),
        'my_antenna' => htmlspecialchars(trim($_POST['my_antenna'] ?? '')),
        'my_grid' => htmlspecialchars(trim($_POST['my_grid'] ?? '')),
        'update_time' => time()
    ]);
    
    $options = Typecho_Widget::widget('Widget_Options');
    $panelPath = Typecho_Common::url('extending.php', $options->adminUrl);
    header('Location: ' . $panelPath . '?panel=' . urlencode('HAMLog/Profile.php') . '&saved=1');
    exit;
}

include __TYPECHO_ROOT_DIR__ . '/admin/header.php';
include __TYPECHO_ROOT_DIR__ . '/admin/menu.php';

require_once __DIR__ . '/DataAccess.php';
$da = new HAMLog_DataAccess();
$options = Typecho_Widget::widget('Widget_Options');
$panelPath = Typecho_Common::url('extending.php', $options->adminUrl);

$saved = isset($_GET['saved']) && $_GET['saved'] == 1;

$profile = $da->getProfile();
?>

<main class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>值机员信息维护</h2>
        </div>
        <div class="row typecho-page-main" role="form">
            <div class="col-mb-12 col-tb-8 col-tb-offset-2">
                <div style="border: 1px solid #e0e0e0; padding: 15px; margin-bottom: 20px; background: #fff;">
                    除呼号会进行调用外，其他信息作为保留设置，后期升级后可能会使用！
                </div>
                <?php if ($saved): ?>
                <div class="typecho-message success fade in" id="success-notice">
                    <p>保存成功！</p>
                </div>
                <script type="text/javascript">
                    setTimeout(function() {
                        var notice = document.getElementById('success-notice');
                        if (notice) {
                            notice.classList.remove('in');
                            notice.classList.add('out');
                            setTimeout(function() { notice.style.display = 'none'; }, 500);
                        }
                    }, 3000);
                </script>
                <?php endif; ?>

                <form method="post" action="<?php echo $panelPath . '?panel=' . urlencode('HAMLog/Profile.php'); ?>">
                    <div class="typecho-option">
                        <label for="my_callsign" class="typecho-label">呼号（Callsign）</label>
                        <input type="text" id="my_callsign" name="my_callsign" class="text" value="<?php echo htmlspecialchars($profile['my_callsign'] ?? ''); ?>" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')">
                        <p class="description">您的业余无线电台呼号。</p>
                    </div>
                    <div class="typecho-option">
                        <label for="my_qth" class="typecho-label">QTH（QTH）</label>
                        <input type="text" id="my_qth" name="my_qth" class="text" value="<?php echo htmlspecialchars($profile['my_qth'] ?? ''); ?>">
                        <p class="description">您的业余无线电台位置。</p>
                    </div>
                    <div class="typecho-option">
                        <label for="my_device" class="typecho-label">设备（Device）</label>
                        <input type="text" id="my_device" name="my_device" class="text" value="<?php echo htmlspecialchars($profile['my_device'] ?? ''); ?>">
                        <p class="description">您使用的业余无线电台设备型号。</p>
                    </div>
                    <div class="typecho-option">
                        <label for="my_power" class="typecho-label">功率（Power）</label>
                        <input type="text" id="my_power" name="my_power" class="text" value="<?php echo htmlspecialchars($profile['my_power'] ?? ''); ?>" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');">
                        <p class="description">业余无线电台的发射功率，单位瓦（W）无需输入。</p>
                    </div>
                    <div class="typecho-option">
                        <label for="my_antenna" class="typecho-label">天线（Antenna）</label>
                        <input type="text" id="my_antenna" name="my_antenna" class="text" value="<?php echo htmlspecialchars($profile['my_antenna'] ?? ''); ?>">
                        <p class="description">您使用的业余无线电台天线类型。</p>
                    </div>
                    <div class="typecho-option">
                        <label for="my_grid" class="typecho-label">网格（Grid）</label>
                        <input type="text" id="my_grid" name="my_grid" class="text" value="<?php echo htmlspecialchars($profile['my_grid'] ?? ''); ?>" oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g, '')">
                        <p class="description">梅登海德网格定位系统(Maidenhead Grid Square Locator)。</p>
                    </div>
                    <div class="typecho-form-actions">
                        <button type="submit" class="btn primary">保存</button>
                        <button type="button" class="btn" onclick="location.href='<?php echo $options->adminUrl; ?>extending.php?panel=HAMLog/Page.php'">返回</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php
include __TYPECHO_ROOT_DIR__ . '/admin/copyright.php';
include __TYPECHO_ROOT_DIR__ . '/admin/common-js.php';
include __TYPECHO_ROOT_DIR__ . '/admin/footer.php';
?>
