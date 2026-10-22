<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (!isHrManager()) {
    if (isAdmin()) {
        redirect('admin_dashboard.php');
    }
    redirect('dashboard.php');
}

$employee = currentEmployee();
$employees = getEmployees();
$pendingLeave = count(array_filter(getLeaveApplications(), static fn (array $request): bool => ($request['status'] ?? '') === 'Pending'));
$pendingSick = count(array_filter(getSickLeaveApplications(), static fn (array $request): bool => ($request['status'] ?? '') === 'Pending'));
$pendingAttendance = count(array_filter(getAttendanceRequests(), static fn (array $request): bool => ($request['status'] ?? '') === 'Pending'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Manager Dashboard | SmartStaff</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="admin-layout">
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand"><span class="brand-mark">SS</span><span>SmartStaff</span></div>
            <nav class="nav-menu" aria-label="HR Manager workspace">
                <span class="nav-section-label">HR Manager workspace</span>
                <a href="hr_manager_dashboard.php" class="nav-link active" aria-current="page"><span class="nav-link-icon">DB</span>Dashboard</a>
                <a href="admin_employee_dashboard.php" class="nav-link"><span class="nav-link-icon">EM</span>Employees</a>
                <a href="hr_manager_module.php?module=recruiting" class="nav-link"><span class="nav-link-icon">RC</span>Recruiting</a>
                <a href="hr_manager_module.php?module=onboarding" class="nav-link"><span class="nav-link-icon">OB</span>Onboarding</a>
                <a href="admin_payroll_dashboard.php" class="nav-link"><span class="nav-link-icon">PY</span>Payroll</a>
                <a href="hr_manager_module.php?module=time-off" class="nav-link"><span class="nav-link-icon">TO</span>Time Off</a>
                <a href="hr_manager_module.php?module=performance" class="nav-link"><span class="nav-link-icon">PF</span>Performance</a>
                <a href="hr_manager_module.php?module=reports" class="nav-link"><span class="nav-link-icon">RA</span>Reports &amp; Analytics</a>
                <a href="hr_manager_module.php?module=settings" class="nav-link"><span class="nav-link-icon">ST</span>Settings</a>
            </nav>
            <div class="sidebar-footer"><p><?php echo htmlspecialchars($employee['name']); ?></p><a href="logout.php" class="logout-link">Log out</a></div>
        </aside>
        <main class="main-panel">
            <header class="topbar">
                <div><p class="eyebrow accent">Human resources</p><h1>HR Manager dashboard</h1></div>
                <div class="topbar-user"><div class="avatar-circle"><?php echo htmlspecialchars($employee['avatar']); ?></div><span><?php echo htmlspecialchars($employee['role']); ?></span></div>
            </header>

            <section class="stats-grid hr-status-grid">
                <div class="stat-card green"><span class="stat-label">Total employees</span><strong><?php echo count($employees); ?></strong><small>All employee records</small></div>
                <div class="stat-card"><span class="stat-label">Pending leave</span><strong><?php echo $pendingLeave + $pendingSick; ?></strong><small>Leave requests to review</small></div>
                <div class="stat-card"><span class="stat-label">Attendance requests</span><strong><?php echo $pendingAttendance; ?></strong><small>Requests to review</small></div>
                <div class="stat-card dark"><span class="stat-label">Workforce operations</span><strong>8</strong><small>HR modules</small></div>
            </section>

            <section class="panel">
                <div class="panel-header"><div><p class="eyebrow accent">HR operations</p><h2>Manage the employee lifecycle</h2><p class="panel-helper">Recruiting, onboarding, time off, performance, reporting, and settings belong to the HR Manager workspace.</p></div></div>
                <div class="manager-module-grid">
                    <a href="hr_manager_module.php?module=recruiting" class="manager-module-card"><span class="nav-link-icon">RC</span><strong>Recruiting</strong><small>Track candidates and open hiring activities.</small><span class="module-link">Open module</span></a>
                    <a href="hr_manager_module.php?module=onboarding" class="manager-module-card"><span class="nav-link-icon">OB</span><strong>Onboarding</strong><small>Coordinate new-hire setup and onboarding tasks.</small><span class="module-link">Open module</span></a>
                    <a href="admin_payroll_dashboard.php" class="manager-module-card"><span class="nav-link-icon">PY</span><strong>Payroll</strong><small>Review employee payroll and payslip records.</small><span class="module-link">Open module</span></a>
                    <a href="hr_manager_module.php?module=time-off" class="manager-module-card"><span class="nav-link-icon">TO</span><strong>Time Off</strong><small>Review leave balances and time-off requests.</small><span class="module-link">Open module</span></a>
                    <a href="hr_manager_module.php?module=performance" class="manager-module-card"><span class="nav-link-icon">PF</span><strong>Performance</strong><small>Maintain performance review activities.</small><span class="module-link">Open module</span></a>
                    <a href="hr_manager_module.php?module=reports" class="manager-module-card"><span class="nav-link-icon">RA</span><strong>Reports &amp; Analytics</strong><small>Review workforce metrics and HR reports.</small><span class="module-link">Open module</span></a>
                    <a href="hr_manager_module.php?module=settings" class="manager-module-card"><span class="nav-link-icon">ST</span><strong>Settings</strong><small>Manage HR workspace configuration.</small><span class="module-link">Open module</span></a>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
