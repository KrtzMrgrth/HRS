<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (!isAdmin() && !isHrManager()) {
    redirect('dashboard.php');
}

$employee = currentEmployee();
$workspaceHome = isHrManager() ? 'hr_manager_dashboard.php' : 'admin_dashboard.php';
$settingsFile = DATA_DIR . '/system_settings.json';
$settings = loadJson($settingsFile, [
    'organization_name' => 'SmartStaff',
    'timezone' => 'Asia/Manila',
    'date_format' => 'F j, Y',
    'approval_mode' => 'Administrator review',
    'notification_email' => 'admin@hrs.com',
    'maintenance_mode' => false,
]);
$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings['organization_name'] = trim((string) ($_POST['organization_name'] ?? 'SmartStaff')) ?: 'SmartStaff';
    $settings['timezone'] = trim((string) ($_POST['timezone'] ?? 'Asia/Manila')) ?: 'Asia/Manila';
    $settings['date_format'] = trim((string) ($_POST['date_format'] ?? 'F j, Y')) ?: 'F j, Y';
    $settings['approval_mode'] = trim((string) ($_POST['approval_mode'] ?? 'Administrator review')) ?: 'Administrator review';
    $settings['notification_email'] = trim((string) ($_POST['notification_email'] ?? ''));
    $settings['maintenance_mode'] = isset($_POST['maintenance_mode']);
    saveJson($settingsFile, $settings);
    $_SESSION['flash_message'] = 'System settings saved.';
    redirect('admin_settings.php');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | SmartStaff</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="admin-layout">
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand"><span class="brand-mark">SS</span><span>SmartStaff</span></div>
            <nav class="nav-menu" aria-label="Administration workspace">
                <span class="nav-section-label"><?php echo isHrManager() ? 'HR Manager workspace' : 'Administration'; ?></span>
                <a href="<?php echo $workspaceHome; ?>" class="nav-link"><span class="nav-link-icon">DB</span>Dashboard</a>
                <a href="admin_employee_dashboard.php" class="nav-link"><span class="nav-link-icon">EM</span>Employees</a>
                <?php if (isHrManager()): ?>
                    <a href="hr_manager_module.php?module=recruiting" class="nav-link"><span class="nav-link-icon">RC</span>Recruiting</a>
                    <a href="hr_manager_module.php?module=onboarding" class="nav-link"><span class="nav-link-icon">OB</span>Onboarding</a>
                <?php endif; ?>
                <a href="admin_payroll_dashboard.php" class="nav-link"><span class="nav-link-icon">PY</span>Payroll</a>
                <?php if (isHrManager()): ?>
                    <a href="hr_manager_module.php?module=time-off" class="nav-link"><span class="nav-link-icon">TO</span>Time Off</a>
                    <a href="hr_manager_module.php?module=performance" class="nav-link"><span class="nav-link-icon">PF</span>Performance</a>
                    <a href="hr_manager_module.php?module=reports" class="nav-link"><span class="nav-link-icon">RA</span>Reports &amp; Analytics</a>
                    <a href="hr_manager_module.php?module=settings" class="nav-link active" aria-current="page"><span class="nav-link-icon">ST</span>Settings</a>
                <?php else: ?>
                    <a href="admin_settings.php" class="nav-link active" aria-current="page"><span class="nav-link-icon">ST</span>System settings</a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer"><p><?php echo htmlspecialchars($employee['name']); ?></p><a href="logout.php" class="logout-link">Log out</a></div>
        </aside>
        <main class="main-panel">
            <header class="topbar"><div><p class="eyebrow accent"><?php echo isHrManager() ? 'Human resources' : 'Administration'; ?></p><h1>System settings</h1></div><div class="topbar-user"><div class="avatar-circle"><?php echo htmlspecialchars($employee['avatar']); ?></div><span><?php echo htmlspecialchars($employee['role']); ?></span></div></header>
            <?php if ($flashMessage !== ''): ?><div class="alert alert-success dashboard-notice"><?php echo htmlspecialchars($flashMessage); ?></div><?php endif; ?>
            <section class="panel manager-module-heading"><p class="eyebrow accent"><?php echo isHrManager() ? 'HR Manager controls' : 'Administrator controls'; ?></p><h2>Configure the SmartStaff workspace</h2><p>Manage organization-wide preferences used by the HRIS.</p></section>
            <section class="panel form-panel">
                <form method="POST" action="admin_settings.php">
                    <div class="form-section"><h3 class="form-section-title">Organization</h3><div class="field-row"><div><label for="organizationName">Organization name</label><input id="organizationName" name="organization_name" value="<?php echo htmlspecialchars($settings['organization_name'] ?? 'SmartStaff'); ?>" required></div><div><label for="notificationEmail">System notification email</label><input id="notificationEmail" name="notification_email" type="email" value="<?php echo htmlspecialchars($settings['notification_email'] ?? ''); ?>"></div></div></div>
                    <div class="form-section"><h3 class="form-section-title">Regional preferences</h3><div class="field-row"><div><label for="timezone">Timezone</label><select id="timezone" name="timezone"><option<?php echo ($settings['timezone'] ?? '') === 'Asia/Manila' ? ' selected' : ''; ?>>Asia/Manila</option><option<?php echo ($settings['timezone'] ?? '') === 'Asia/Singapore' ? ' selected' : ''; ?>>Asia/Singapore</option><option<?php echo ($settings['timezone'] ?? '') === 'UTC' ? ' selected' : ''; ?>>UTC</option><option<?php echo ($settings['timezone'] ?? '') === 'America/New_York' ? ' selected' : ''; ?>>America/New_York</option></select></div><div><label for="dateFormat">Date format</label><select id="dateFormat" name="date_format"><option value="F j, Y"<?php echo ($settings['date_format'] ?? '') === 'F j, Y' ? ' selected' : ''; ?>>October 23, 2026</option><option value="Y-m-d"<?php echo ($settings['date_format'] ?? '') === 'Y-m-d' ? ' selected' : ''; ?>>2026-10-23</option><option value="d/m/Y"<?php echo ($settings['date_format'] ?? '') === 'd/m/Y' ? ' selected' : ''; ?>>23/10/2026</option></select></div></div></div>
                    <div class="form-section"><h3 class="form-section-title">Workflow</h3><div class="field-row"><div><label for="approvalMode">Approval mode</label><select id="approvalMode" name="approval_mode"><option<?php echo ($settings['approval_mode'] ?? '') === 'Administrator review' ? ' selected' : ''; ?>>Administrator review</option><option<?php echo ($settings['approval_mode'] ?? '') === 'Manager then Administrator' ? ' selected' : ''; ?>>Manager then Administrator</option></select></div><div class="settings-toggle-field"><label for="maintenanceMode">Maintenance mode</label><label class="settings-toggle"><input id="maintenanceMode" name="maintenance_mode" type="checkbox"<?php echo !empty($settings['maintenance_mode']) ? ' checked' : ''; ?>><span>Restrict employee access while updating the system</span></label></div></div></div>
                    <button type="submit" class="primary-btn">Save system settings</button>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
