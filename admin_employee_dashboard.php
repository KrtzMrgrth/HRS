<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (!isAdmin() && !isHrManager()) {
    redirect('dashboard.php');
}

$employee = currentEmployee();
$employees = getEmployees();
$workspaceHome = isHrManager() ? 'hr_manager_dashboard.php' : 'admin_dashboard.php';

$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard | SmartStaff</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="admin-layout">
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">SS</span>
                <span>SmartStaff</span>
            </div>

            <nav class="nav-menu" aria-label="HR workspace">
                <span class="nav-section-label">HR workspace</span>
                <a href="<?php echo $workspaceHome; ?>" class="nav-link"><span class="nav-link-icon">DB</span>Dashboard</a>
                <a href="admin_employee_dashboard.php" class="nav-link active" aria-current="page"><span class="nav-link-icon">EM</span>Employees</a>
                <a href="hr_manager_module.php?module=recruiting" class="nav-link"><span class="nav-link-icon">RC</span>Recruiting</a>
                <a href="hr_manager_module.php?module=onboarding" class="nav-link"><span class="nav-link-icon">OB</span>Onboarding</a>
                <a href="admin_payroll_dashboard.php" class="nav-link"><span class="nav-link-icon">PY</span>Payroll</a>
                <a href="hr_manager_module.php?module=time-off" class="nav-link"><span class="nav-link-icon">TO</span>Time Off</a>
                <a href="hr_manager_module.php?module=performance" class="nav-link"><span class="nav-link-icon">PF</span>Performance</a>
                <a href="hr_manager_module.php?module=reports" class="nav-link"><span class="nav-link-icon">RA</span>Reports &amp; Analytics</a>
                <a href="hr_manager_module.php?module=settings" class="nav-link"><span class="nav-link-icon">ST</span>Settings</a>
            </nav>

            <div class="sidebar-footer">
                <p><?php echo htmlspecialchars($employee['name']); ?></p>
                <a href="logout.php" class="logout-link">Log out</a>
            </div>
        </aside>

        <main class="main-panel">
            <header class="topbar">
                <div>
                    <p class="eyebrow accent">Administration</p>
                    <h1>Employee dashboard</h1>
                </div>
                <div class="topbar-user">
                    <div class="avatar-circle"><?php echo htmlspecialchars($employee['avatar']); ?></div>
                    <span><?php echo htmlspecialchars($employee['role']); ?></span>
                </div>
            </header>

            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-success dashboard-notice"><?php echo htmlspecialchars($flashMessage); ?></div>
            <?php endif; ?>

            <section class="employee-directory-panel panel" id="employee-list">
                    <div class="panel-header employee-list-header">
                        <h3>Employee list</h3>
                        <span class="badge"><?php echo count($employees); ?></span>
                    </div>

                    <div class="employee-directory-toolbar">
                        <label class="employee-search-field" for="employeeSearch">
                            <span>Search employees</span>
                            <input id="employeeSearch" type="search" placeholder="Search by name, position, department, or ID">
                        </label>
                        <label class="employee-filter-field" for="employeeFilter">
                            <span>Filter</span>
                            <select id="employeeFilter">
                                <option value="all">All employees</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <?php
                                $departments = array_unique(array_map(static fn (array $person): string => (string) ($person['department'] ?? 'General'), $employees));
                                sort($departments);
                                foreach ($departments as $department):
                                ?>
                                    <option value="department:<?php echo htmlspecialchars(strtolower($department)); ?>"><?php echo htmlspecialchars($department); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="button" class="export-btn" id="exportEmployees">Export CSV</button>
                    </div>

                    <div class="employee-table-wrap">
                        <table class="employee-table">
                            <thead>
                                <tr>
                                    <th scope="col">ID#</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Position</th>
                                    <th scope="col">Department</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Location</th>
                                    <th scope="col">Contacts</th>
                                    <th scope="col">Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $person): ?>
                                    <?php
                                    $emergencyContact = trim((string) ($person['emergency_contact'] ?? ''));
                                    $phone = trim((string) ($person['phone'] ?? ''));
                                    $email = trim((string) ($person['email'] ?? ''));
                                    ?>
                                    <tr data-employee-search="<?php echo htmlspecialchars(strtolower(implode(' ', [(string) $person['id'], (string) ($person['name'] ?? ''), (string) ($person['role'] ?? ''), (string) ($person['department'] ?? ''), (string) ($person['location'] ?? '')]))); ?>" data-employee-status="<?php echo htmlspecialchars(strtolower((string) ($person['status'] ?? 'Active'))); ?>" data-employee-department="<?php echo htmlspecialchars(strtolower((string) ($person['department'] ?? 'General'))); ?>">
                                        <td data-label="ID#"><span class="employee-id">#<?php echo (int) $person['id']; ?></span></td>
                                        <td data-label="Name"><strong class="employee-name-link"><?php echo htmlspecialchars($person['name']); ?></strong></td>
                                        <td data-label="Position"><?php echo htmlspecialchars($person['role'] ?? 'Employee'); ?></td>
                                        <td data-label="Department"><?php echo htmlspecialchars($person['department'] ?? 'General'); ?></td>
                                        <td data-label="Type"><?php echo htmlspecialchars($person['employment_type'] ?? 'Full-time'); ?></td>
                                        <td data-label="Status"><span class="status-pill status-approved"><?php echo htmlspecialchars($person['status'] ?? 'Active'); ?></span></td>
                                        <td data-label="Location"><?php echo htmlspecialchars($person['location'] ?? 'Office'); ?></td>
                                        <td data-label="Contacts" class="employee-contact-cell">
                                            <?php if ($emergencyContact !== ''): ?><strong>Emergency: <?php echo htmlspecialchars($emergencyContact); ?></strong><?php endif; ?>
                                            <?php if ($phone !== ''): ?><small><?php echo htmlspecialchars($phone); ?></small><?php endif; ?>
                                        </td>
                                        <td data-label="Email" class="employee-email-cell"><?php echo htmlspecialchars($email); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
            </section>

        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('employeeSearch');
            const filterSelect = document.getElementById('employeeFilter');
            const employeeRows = Array.from(document.querySelectorAll('.employee-table tbody tr'));
            const employeeCount = document.querySelector('.employee-list-header .badge');
            const exportButton = document.getElementById('exportEmployees');

            const applyEmployeeFilters = () => {
                const searchTerm = searchInput.value.trim().toLowerCase();
                const filterValue = filterSelect.value;
                let visibleCount = 0;

                employeeRows.forEach((row) => {
                    const matchesSearch = row.dataset.employeeSearch.includes(searchTerm);
                    const matchesFilter = filterValue === 'all'
                        || (filterValue === 'active' && row.dataset.employeeStatus === 'active')
                        || (filterValue === 'inactive' && row.dataset.employeeStatus !== 'active')
                        || (filterValue.startsWith('department:') && row.dataset.employeeDepartment === filterValue.slice(11));
                    const isVisible = matchesSearch && matchesFilter;
                    row.hidden = !isVisible;
                    if (isVisible) {
                        visibleCount += 1;
                    }
                });

                employeeCount.textContent = `${visibleCount} of ${employeeRows.length}`;
            };

            const csvValue = (value) => `"${value.replaceAll('"', '""')}"`;

            searchInput.addEventListener('input', applyEmployeeFilters);
            filterSelect.addEventListener('change', applyEmployeeFilters);
            exportButton.addEventListener('click', () => {
                const headers = ['ID#', 'Name', 'Position', 'Department', 'Type', 'Status', 'Location', 'Contacts', 'Email'];
                const rows = employeeRows.map((row) => Array.from(row.cells).map((cell) => cell.textContent.trim().replace(/\s+/g, ' ')));
                const csv = [headers, ...rows].map((row) => row.map(csvValue).join(',')).join('\r\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const downloadUrl = URL.createObjectURL(blob);
                const downloadLink = document.createElement('a');
                downloadLink.href = downloadUrl;
                downloadLink.download = 'employees.csv';
                downloadLink.click();
                URL.revokeObjectURL(downloadUrl);
            });
        });
    </script>
</body>
</html>
