<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (!isAdmin()) {
    redirect('dashboard.php');
}

$employee = currentEmployee();
$employees = getEmployees();
$leaveRequests = getLeaveApplications();
$sickLeaveRequests = getSickLeaveApplications();
$attendanceRequests = getAttendanceRequests();
$today = date('Y-m-d');

$dashboardLeaveCounts = [
    'vacation' => [],
    'day_off' => [],
    'sick' => [],
];
foreach (array_merge($leaveRequests, $sickLeaveRequests) as $leaveRequest) {
    if (strtolower((string) ($leaveRequest['status'] ?? '')) !== 'approved') {
        continue;
    }

    $startDate = (string) ($leaveRequest['start_date'] ?? '');
    $endDate = (string) ($leaveRequest['end_date'] ?? $startDate);
    if ($startDate !== '' && $endDate !== '' && $today >= $startDate && $today <= $endDate) {
        $leaveEmployeeId = (int) ($leaveRequest['employee_id'] ?? 0);
        $leaveType = strtolower((string) ($leaveRequest['leave_type'] ?? $leaveRequest['request_type'] ?? ''));
        $leaveCategory = stripos($leaveType, 'sick') !== false
            ? 'sick'
            : (stripos($leaveType, 'day off') !== false || stripos($leaveType, 'day-off') !== false ? 'day_off' : 'vacation');
        $dashboardLeaveCounts[$leaveCategory][$leaveEmployeeId] = true;
    }
}

$workFromHomeCount = count(array_filter(
    $employees,
    static fn (array $person): bool => strtolower((string) ($person['status'] ?? 'Active')) === 'active'
        && in_array(strtolower(trim((string) ($person['location'] ?? ''))), ['remote', 'work from home', 'home'], true)
));

$dashboardRequests = [];
foreach ($leaveRequests as $request) {
    $startDate = (string) ($request['start_date'] ?? '');
    $endDate = (string) ($request['end_date'] ?? $startDate);
    $dashboardRequests[] = [
        'id' => (string) ($request['id'] ?? ''),
        'name' => (string) ($request['employee_name'] ?? 'Employee'),
        'period' => $startDate !== '' && $endDate !== '' ? $startDate . ' to ' . $endDate : ($startDate !== '' ? $startDate : 'Not specified'),
        'type' => (string) ($request['leave_type'] ?? 'Leave'),
        'status' => (string) ($request['status'] ?? 'Pending'),
        'date' => (string) ($request['request_date'] ?? $startDate),
        'notes' => (string) ($request['reason'] ?? $request['notes'] ?? 'No notes'),
        'approve_action' => 'approve_leave',
        'reject_action' => 'reject_leave',
    ];
}

foreach ($sickLeaveRequests as $request) {
    $startDate = (string) ($request['start_date'] ?? '');
    $endDate = (string) ($request['end_date'] ?? $startDate);
    $dashboardRequests[] = [
        'id' => (string) ($request['id'] ?? ''),
        'name' => (string) ($request['employee_name'] ?? 'Employee'),
        'period' => $startDate !== '' && $endDate !== '' ? $startDate . ' to ' . $endDate : ($startDate !== '' ? $startDate : 'Not specified'),
        'type' => (string) ($request['leave_type'] ?? 'Sick leave'),
        'status' => (string) ($request['status'] ?? 'Pending'),
        'date' => (string) ($request['request_date'] ?? $startDate),
        'notes' => (string) ($request['reason'] ?? $request['notes'] ?? 'No notes'),
        'approve_action' => 'approve_sick_leave',
        'reject_action' => 'reject_sick_leave',
    ];
}

foreach ($attendanceRequests as $request) {
    $requestDate = (string) ($request['request_date'] ?? '');
    $dashboardRequests[] = [
        'id' => (string) ($request['id'] ?? ''),
        'name' => (string) ($request['employee_name'] ?? 'Employee'),
        'period' => $requestDate !== '' ? $requestDate : 'Not specified',
        'type' => (string) ($request['request_type'] ?? 'Attendance request'),
        'status' => (string) ($request['status'] ?? 'Pending'),
        'date' => (string) ($request['submitted_at'] ?? $requestDate),
        'notes' => (string) ($request['reason'] ?? $request['notes'] ?? 'No notes'),
        'approve_action' => 'approve_attendance_request',
        'reject_action' => 'reject_attendance_request',
    ];
}

$scheduleEvents = loadJson(SCHEDULE_EVENTS_FILE, []);
$attendanceRecords = [];

foreach ($employees as $index => $person) {
    $attendanceRecords[] = [
        'id' => (int) ($person['id'] ?? 0),
        'name' => $person['name'] ?? 'Employee',
        'role' => $person['role'] ?? 'Employee',
        'department' => $person['department'] ?? 'General',
        'status' => ($index % 2 === 0) ? 'In office' : 'Out of office',
        'time_in' => ['08:15 AM', '08:45 AM', '09:00 AM', '08:30 AM'][$index % 4],
        'time_out' => ['05:30 PM', '06:00 PM', '04:45 PM', '05:15 PM'][$index % 4],
    ];
}

$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_schedule_event') {
        $eventId = trim((string) ($_POST['event_id'] ?? ''));
        $eventDate = trim((string) ($_POST['event_date'] ?? ''));
        $eventTitle = trim((string) ($_POST['event_title'] ?? ''));
        $eventType = trim((string) ($_POST['event_type'] ?? 'Other'));
        $eventTime = trim((string) ($_POST['event_time'] ?? ''));
        $eventNotes = trim((string) ($_POST['event_notes'] ?? ''));
        $allowedEventTypes = ['Meeting', 'Interview', 'Deadline', 'Other'];
        $eventDateObject = DateTimeImmutable::createFromFormat('Y-m-d', $eventDate);

        if ($eventTitle !== '' && $eventDateObject !== false && $eventDateObject->format('Y-m-d') === $eventDate) {
            $eventType = in_array($eventType, $allowedEventTypes, true) ? $eventType : 'Other';
            $eventRecord = [
                'id' => $eventId !== '' ? $eventId : uniqid('event_', true),
                'date' => $eventDate,
                'title' => $eventTitle,
                'type' => $eventType,
                'time' => $eventTime,
                'notes' => $eventNotes,
            ];
            $eventSaved = false;

            foreach ($scheduleEvents as $index => $scheduleEvent) {
                if ((string) ($scheduleEvent['id'] ?? '') === $eventRecord['id']) {
                    $scheduleEvents[$index] = $eventRecord;
                    $eventSaved = true;
                    break;
                }
            }

            if (!$eventSaved) {
                $scheduleEvents[] = $eventRecord;
            }

            saveJson(SCHEDULE_EVENTS_FILE, $scheduleEvents);
            $_SESSION['flash_message'] = $eventSaved ? 'Schedule event updated.' : 'Schedule event added.';
        } else {
            $_SESSION['flash_message'] = 'Please provide a valid date and activity title.';
        }

        redirect('admin_dashboard.php#schedule-calendar');
    }

    if ($action === 'delete_schedule_event') {
        $eventId = trim((string) ($_POST['event_id'] ?? ''));
        $scheduleEvents = array_values(array_filter(
            $scheduleEvents,
            static fn (array $scheduleEvent): bool => (string) ($scheduleEvent['id'] ?? '') !== $eventId
        ));
        saveJson(SCHEDULE_EVENTS_FILE, $scheduleEvents);
        $_SESSION['flash_message'] = 'Schedule event removed.';
        redirect('admin_dashboard.php#schedule-calendar');
    }

    if ($action === 'add_employee') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = trim((string) ($_POST['password'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $birthday = trim((string) ($_POST['birthday'] ?? ''));
        $salary = (float) ($_POST['salary'] ?? 0);
        $address = trim((string) ($_POST['address'] ?? ''));
        $civil_status = trim((string) ($_POST['civil_status'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $manager = trim((string) ($_POST['manager'] ?? ''));
        $accountRole = trim((string) ($_POST['account_role'] ?? 'employee'));
        $accountRole = in_array($accountRole, ['admin', 'manager', 'employee'], true) ? $accountRole : 'employee';
        $managerId = (int) ($_POST['manager_id'] ?? 0);
        $managerRecord = $managerId > 0 ? getEmployeeById($managerId) : null;
        $manager = $managerRecord !== null ? (string) $managerRecord['name'] : 'Pending Assignment';
        $location = trim((string) ($_POST['location'] ?? ''));
        $employment_type = trim((string) ($_POST['employment_type'] ?? 'Full-time'));
        $employment_type = $employment_type !== '' ? $employment_type : 'Full-time';
        $sss_number = trim((string) ($_POST['sss_number'] ?? ''));
        $philhealth_number = trim((string) ($_POST['philhealth_number'] ?? ''));
        $pagibig_number = trim((string) ($_POST['pagibig_number'] ?? ''));
        $tin_number = trim((string) ($_POST['tin_number'] ?? ''));
        $tax_status = trim((string) ($_POST['tax_status'] ?? 'Single'));
        $emergency_contact = trim((string) ($_POST['emergency_contact'] ?? ''));
        $join_date = trim((string) ($_POST['join_date'] ?? ''));
        $leaveEntitlements = getLeaveEntitlements($employment_type);
        $schedule_type = trim((string) ($_POST['schedule_type'] ?? 'Regular Day Shift'));
        $shift_time = trim((string) ($_POST['shift_time'] ?? '09:00 AM - 06:00 PM'));
        $work_days = trim((string) ($_POST['work_days'] ?? 'Mon - Fri'));
        $rest_day = trim((string) ($_POST['rest_day'] ?? 'Saturday'));
        $attendance_notes = trim((string) ($_POST['attendance_notes'] ?? 'Standard office schedule'));

        if ($name !== '' && $email !== '' && $password !== '' && $department !== '' && $role !== '') {
            if (getEmployeeByEmail($email) === null) {
                $newId = 2000 + count($employees);
                $employees[] = [
                    'id' => $newId,
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'department' => $department,
                    'role' => $role,
                    'manager' => $manager !== '' ? $manager : 'Pending Assignment',
                    'location' => $location !== '' ? $location : 'Office',
                    'employment_type' => $employment_type,
                    'leave_entitlements' => $leaveEntitlements,
                    'status' => 'Active',
                    'phone' => $phone !== '' ? $phone : '+1 (000) 000-0000',
                    'join_date' => $join_date !== '' ? $join_date : date('Y-m-d'),
                    'birthday' => $birthday !== '' ? $birthday : date('Y-m-d'),
                    'address' => $address,
                    'civil_status' => $civil_status,
                    'sss_number' => $sss_number,
                    'philhealth_number' => $philhealth_number,
                    'pagibig_number' => $pagibig_number,
                    'tin_number' => $tin_number,
                    'tax_status' => $tax_status,
                    'emergency_contact' => $emergency_contact,
                    'salary' => $salary,
                    'schedule_type' => $schedule_type,
                    'shift_time' => $shift_time,
                    'work_days' => $work_days,
                    'rest_day' => $rest_day,
                    'attendance_notes' => $attendance_notes,
                    'avatar' => strtoupper(substr($name, 0, 2)),
                    'is_admin' => $accountRole === 'admin',
                    'account_role' => $accountRole,
                    'manager_id' => $managerId,
                ];

                saveJson(EMPLOYEES_FILE, $employees);
                $_SESSION['flash_message'] = 'New employee account added successfully. You can now view all account details from the employee list below.';
            } else {
                $_SESSION['flash_message'] = 'This email is already in use.';
            }
        } else {
            $_SESSION['flash_message'] = 'Please complete all required fields.';
        }

        $returnTo = (string) ($_POST['return_to'] ?? 'admin_dashboard.php');
        $returnTo = in_array($returnTo, ['admin_dashboard.php', 'admin_employee_dashboard.php'], true)
            ? $returnTo
            : 'admin_dashboard.php';
        redirect($returnTo);
    }

    if ($action === 'approve_leave') {
        $requestId = (string) ($_POST['request_id'] ?? '');
        if ($requestId !== '') {
            updateLeaveStatus($requestId, 'Approved');
            $_SESSION['flash_message'] = 'Leave request approved.';
        }
        redirect('admin_dashboard.php');
    }

    if ($action === 'reject_leave') {
        $requestId = (string) ($_POST['request_id'] ?? '');
        if ($requestId !== '') {
            updateLeaveStatus($requestId, 'Rejected');
            $_SESSION['flash_message'] = 'Leave request rejected.';
        }
        redirect('admin_dashboard.php');
    }

    if ($action === 'approve_sick_leave') {
        $requestId = (string) ($_POST['request_id'] ?? '');
        if ($requestId !== '') {
            updateSickLeaveStatus($requestId, 'Approved');
            $_SESSION['flash_message'] = 'Sick leave request approved.';
        }
        redirect('admin_dashboard.php');
    }

    if ($action === 'reject_sick_leave') {
        $requestId = (string) ($_POST['request_id'] ?? '');
        if ($requestId !== '') {
            updateSickLeaveStatus($requestId, 'Rejected');
            $_SESSION['flash_message'] = 'Sick leave request rejected.';
        }
        redirect('admin_dashboard.php');
    }

    if ($action === 'approve_attendance_request') {
        $requestId = (string) ($_POST['request_id'] ?? '');
        if ($requestId !== '') {
            updateAttendanceRequestStatus($requestId, 'Approved');
            $_SESSION['flash_message'] = 'Attendance request approved.';
        }
        redirect('admin_dashboard.php');
    }

    if ($action === 'reject_attendance_request') {
        $requestId = (string) ($_POST['request_id'] ?? '');
        if ($requestId !== '') {
            updateAttendanceRequestStatus($requestId, 'Rejected');
            $_SESSION['flash_message'] = 'Attendance request rejected.';
        }
        redirect('admin_dashboard.php');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SmartStaff</title>
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
                <a href="admin_dashboard.php" class="nav-link active" aria-current="page"><span class="nav-link-icon">DB</span>Dashboard</a>
                <a href="admin_employee_dashboard.php" class="nav-link"><span class="nav-link-icon">EM</span>Employees</a>
                <a href="admin_payroll_dashboard.php" class="nav-link"><span class="nav-link-icon">PY</span>Payroll</a>
                <a href="admin_settings.php" class="nav-link"><span class="nav-link-icon">ST</span>System settings</a>
            </nav>

            <div class="sidebar-footer">
                <p><?php echo htmlspecialchars($employee['name']); ?></p>
                <a href="logout.php" class="logout-link">Log out</a>
            </div>
        </aside>

        <main class="main-panel">
            <header class="topbar">
                <div>
                    <p class="eyebrow accent">HR operations</p>
                    <h1>Workforce overview</h1>
                </div>
                <div class="topbar-user">
                    <div class="avatar-circle"><?php echo htmlspecialchars($employee['avatar']); ?></div>
                    <span><?php echo htmlspecialchars($employee['role']); ?></span>
                </div>
            </header>

            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-success dashboard-notice"><?php echo htmlspecialchars($flashMessage); ?></div>
            <?php endif; ?>

            <section class="stats-grid hr-status-grid" aria-label="Today's workforce status">
                <div class="stat-card green">
                    <span class="stat-label">Total employees</span>
                    <strong><?php echo count($employees); ?></strong>
                    <small>All employee records</small>
                </div>
                <div class="stat-card stat-card-home">
                    <span class="stat-label">Work from home</span>
                    <strong><?php echo $workFromHomeCount; ?></strong>
                    <small>Active remote employees</small>
                </div>
                <div class="stat-card stat-card-vacation">
                    <span class="stat-label">On vacation</span>
                    <strong><?php echo count($dashboardLeaveCounts['vacation']); ?></strong>
                    <small>Approved for today</small>
                </div>
                <div class="stat-card stat-card-day-off">
                    <span class="stat-label">Day off</span>
                    <strong><?php echo count($dashboardLeaveCounts['day_off']); ?></strong>
                    <small>Approved for today</small>
                </div>
                <div class="stat-card stat-card-sick">
                    <span class="stat-label">Sick leave</span>
                    <strong><?php echo count($dashboardLeaveCounts['sick']); ?></strong>
                    <small>Approved for today</small>
                </div>
            </section>

            <section class="schedule-calendar-section panel" id="schedule-calendar">
                <div class="panel-header schedule-calendar-header">
                    <div>
                        <p class="eyebrow accent">HR planning</p>
                        <h2>Schedule calendar</h2>
                        <p class="panel-helper">Plan meetings, interviews, deadlines, and other activities.</p>
                    </div>
                    <div class="schedule-legend" aria-label="Calendar legend">
                        <span><i class="legend-dot meeting"></i>Meeting</span>
                        <span><i class="legend-dot interview"></i>Interview</span>
                        <span><i class="legend-dot deadline"></i>Deadline</span>
                        <span><i class="legend-dot other"></i>Other</span>
                    </div>
                </div>

                <div class="schedule-calendar-layout editor-hidden" id="scheduleCalendarLayout">
                    <div class="calendar-board">
                        <div class="calendar-toolbar">
                            <button type="button" class="calendar-nav-btn" id="previousMonth" aria-label="Previous month">&lt;</button>
                            <h3 id="calendarMonthLabel"></h3>
                            <button type="button" class="calendar-nav-btn" id="nextMonth" aria-label="Next month">&gt;</button>
                            <button type="button" class="mini-btn approve calendar-today-btn" id="calendarToday">Today</button>
                        </div>
                        <div class="calendar-weekdays" aria-hidden="true">
                            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                        </div>
                        <div class="calendar-grid" id="calendarGrid" role="grid" aria-label="Schedule calendar"></div>
                    </div>

                    <div class="schedule-modal-backdrop" id="scheduleModalBackdrop" hidden></div>
                    <aside class="schedule-editor" id="scheduleEditor" hidden>
                        <div class="schedule-editor-heading">
                            <div>
                                <p class="eyebrow accent" id="scheduleEditorEyebrow">New schedule</p>
                                <h3 id="scheduleEditorTitle">Add activity</h3>
                            </div>
                            <button type="button" class="calendar-clear-btn" id="closeScheduleModal">Close</button>
                        </div>
                        <div class="schedule-details" id="scheduleDetails" hidden>
                            <span class="schedule-details-type" id="scheduleDetailsType"></span>
                            <h3 id="scheduleDetailsTitle"></h3>
                            <dl>
                                <div><dt>Date</dt><dd id="scheduleDetailsDate"></dd></div>
                                <div><dt>Time</dt><dd id="scheduleDetailsTime"></dd></div>
                                <div><dt>Notes</dt><dd id="scheduleDetailsNotes"></dd></div>
                            </dl>
                            <button type="button" class="primary-btn schedule-edit-btn" id="editScheduleButton">Edit activity</button>
                        </div>
                        <form method="POST" action="admin_dashboard.php" id="scheduleEventForm" hidden>
                            <input type="hidden" name="action" value="save_schedule_event">
                            <input type="hidden" name="event_id" id="scheduleEventId">
                            <label for="scheduleEventDate">Date</label>
                            <input type="date" name="event_date" id="scheduleEventDate" required>
                            <label for="scheduleEventTitle">Activity</label>
                            <input type="text" name="event_title" id="scheduleEventTitle" placeholder="e.g. Candidate interview" required>
                            <label for="scheduleEventType">Type</label>
                            <select name="event_type" id="scheduleEventType">
                                <option value="Meeting">Meeting</option>
                                <option value="Interview">Interview</option>
                                <option value="Deadline">Deadline</option>
                                <option value="Other">Other</option>
                            </select>
                            <label for="scheduleEventTime">Time</label>
                            <input type="time" name="event_time" id="scheduleEventTime">
                            <label for="scheduleEventNotes">Notes</label>
                            <textarea name="event_notes" id="scheduleEventNotes" rows="3" placeholder="Add a location, attendees, or reminders"></textarea>
                            <button type="submit" class="primary-btn schedule-save-btn">Save activity</button>
                        </form>
                        <form method="POST" action="admin_dashboard.php" id="deleteScheduleForm" hidden>
                            <input type="hidden" name="action" value="delete_schedule_event">
                            <input type="hidden" name="event_id" id="deleteScheduleEventId">
                            <button type="submit" class="schedule-delete-btn">Delete activity</button>
                        </form>
                    </aside>
                </div>
            </section>

            <section class="dashboard-main-row request-dashboard-row" id="approval-queue">
                <details class="panel form-panel add-employee-panel overview-add-employee-legacy">
                    <summary class="panel-header add-employee-summary">
                        <span class="add-employee-button">+ Add employee</span>
                    </summary>

                    <form method="POST" action="admin_dashboard.php">
                        <input type="hidden" name="action" value="add_employee">

                        <div class="add-employee-layout">
                            <div class="form-column">
                                <div class="form-section">
                                    <h4 class="form-section-title">Basic Information</h4>
                                    <div class="field-row">
                                        <div>
                                            <label for="name">Full name</label>
                                            <input id="name" name="name" type="text" required>
                                        </div>
                                        <div>
                                            <label for="email">Email</label>
                                            <input id="email" name="email" type="email" required>
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="password">Password</label>
                                            <input id="password" name="password" type="text" required>
                                        </div>
                                        <div>
                                            <label for="phone">Phone number</label>
                                            <input id="phone" name="phone" type="text" placeholder="e.g. +63 912 345 6789">
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="birthday">Birthday</label>
                                            <input id="birthday" name="birthday" type="date">
                                        </div>
                                        <div>
                                            <label for="civil_status">Civil status</label>
                                            <select id="civil_status" name="civil_status">
                                                <option value="Single">Single</option>
                                                <option value="Married">Married</option>
                                                <option value="Widowed">Widowed</option>
                                                <option value="Separated">Separated</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="address">Address</label>
                                        <input id="address" name="address" type="text" placeholder="Employee home address">
                                    </div>
                                </div>

                                <div class="form-section government-information-section">
                                    <h4 class="form-section-title">Government Information</h4>
                                    <div class="field-row">
                                        <div>
                                            <label for="sss_number">SSS number</label>
                                            <input id="sss_number" name="sss_number" type="text">
                                        </div>
                                        <div>
                                            <label for="philhealth_number">PhilHealth number</label>
                                            <input id="philhealth_number" name="philhealth_number" type="text">
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="pagibig_number">Pag-IBIG number</label>
                                            <input id="pagibig_number" name="pagibig_number" type="text">
                                        </div>
                                        <div>
                                            <label for="tin_number">TIN</label>
                                            <input id="tin_number" name="tin_number" type="text">
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="tax_status">Tax status</label>
                                            <select id="tax_status" name="tax_status">
                                                <option value="Single">Single</option>
                                                <option value="Married">Married</option>
                                                <option value="Head of Family">Head of Family</option>
                                                <option value="Qualified Dependent">Qualified Dependent</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="emergency_contact">Emergency contact</label>
                                            <input id="emergency_contact" name="emergency_contact" type="text" placeholder="Name and contact number">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-column">
                                <div class="form-section">
                                    <h4 class="form-section-title">Work Information</h4>
                                    <div class="field-row">
                                        <div>
                                            <label for="department">Department</label>
                                            <input id="department" name="department" type="text" required>
                                        </div>
                                        <div>
                                            <label for="role">Role</label>
                                            <input id="role" name="role" type="text" placeholder="e.g. Developer" required>
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="location">Work location</label>
                                            <input id="location" name="location" type="text" placeholder="e.g. Manila, Philippines">
                                        </div>
                                        <div></div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="account_role">Account role</label>
                                            <select id="account_role" name="account_role">
                                                <option value="employee">Employee</option>
                                                <option value="manager">Manager</option>
                                                <option value="admin">Admin</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="manager_id">Reports to</label>
                                            <select id="manager_id" name="manager_id">
                                                <option value="0">Pending Assignment</option>
                                                <?php foreach (getManagerOptions() as $managerOption): ?>
                                                    <option value="<?php echo (int) $managerOption['id']; ?>"><?php echo htmlspecialchars($managerOption['name']); ?> (<?php echo htmlspecialchars(ucfirst((string) $managerOption['account_role'])); ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="employment_type">Employment type</label>
                                            <select id="employment_type" name="employment_type">
                                                <option value="Full-time">Full-time</option>
                                                <option value="Part-time">Part-time</option>
                                                <option value="Contract">Contract</option>
                                                <option value="Probationary">Probationary</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="join_date">Join date</label>
                                            <input id="join_date" name="join_date" type="date">
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="salary">Monthly salary</label>
                                            <input id="salary" name="salary" type="number" min="0" step="0.01" placeholder="e.g. 5000">
                                        </div>
                                        <div></div>
                                    </div>
                                </div>

                                <div class="form-section work-schedule-section">
                                    <h4 class="form-section-title">Work Schedule</h4>
                                    <div class="field-row">
                                        <div>
                                            <label for="schedule_type">Schedule type</label>
                                            <select id="schedule_type" name="schedule_type">
                                                <option value="Regular Day Shift">Regular Day Shift</option>
                                                <option value="Regular Night Shift">Regular Night Shift</option>
                                                <option value="Flexible Schedule">Flexible Schedule</option>
                                                <option value="Hybrid Schedule">Hybrid Schedule</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="shift_time">Shift time</label>
                                            <input id="shift_time" name="shift_time" type="text" value="09:00 AM - 06:00 PM">
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="work_days">Work days</label>
                                            <input id="work_days" name="work_days" type="text" value="Mon - Fri">
                                        </div>
                                        <div>
                                            <label for="rest_day">Rest day</label>
                                            <input id="rest_day" name="rest_day" type="text" value="Saturday">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="attendance_notes">Attendance notes</label>
                                        <textarea id="attendance_notes" name="attendance_notes" rows="3" placeholder="e.g. Standard office schedule">Standard office schedule</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="add-employee-actions">
                            <button type="submit" class="primary-btn">Add employee</button>
                            <button type="button" class="mini-btn add-employee-back-btn" id="closeAddEmployeeBtn">Back to admin dashboard</button>
                        </div>
                    </form>
                </details>

                <div class="approval-column request-directory-column">
                    <div class="panel request-directory-panel">
                        <div class="panel-header">
                            <div>
                                <p class="eyebrow accent">HR requests</p>
                                <h3>Requests</h3>
                            </div>
                            <span class="badge"><?php echo count($dashboardRequests); ?></span>
                        </div>

                        <div class="request-table-wrap">
                            <table class="request-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Name</th>
                                            <th scope="col">Period</th>
                                            <th scope="col">Type</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Date</th>
                                            <th scope="col">Notes</th>
                                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($dashboardRequests)): ?>
                                            <tr><td colspan="7" class="empty-state">No requests have been submitted.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($dashboardRequests as $request): ?>
                                                <?php $statusClass = strtolower((string) $request['status']) === 'approved' ? 'status-approved' : (strtolower((string) $request['status']) === 'rejected' ? 'status-rejected' : 'status-pending'); ?>
                                                <tr>
                                                    <td data-label="Name"><strong><?php echo htmlspecialchars($request['name']); ?></strong></td>
                                                    <td data-label="Period"><?php echo htmlspecialchars($request['period']); ?></td>
                                                    <td data-label="Type"><?php echo htmlspecialchars($request['type']); ?></td>
                                                    <td data-label="Status"><span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($request['status']); ?></span></td>
                                                    <td data-label="Date"><?php echo htmlspecialchars($request['date'] !== '' ? $request['date'] : 'Not specified'); ?></td>
                                                    <td data-label="Notes" class="request-notes-cell"><?php echo htmlspecialchars($request['notes']); ?></td>
                                                    <td data-label="Actions">
                                                        <?php if (strtolower((string) $request['status']) === 'pending'): ?>
                                                            <div class="approval-actions">
                                                                <form method="POST" action="admin_dashboard.php">
                                                                    <input type="hidden" name="action" value="<?php echo htmlspecialchars($request['approve_action']); ?>">
                                                                    <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id']); ?>">
                                                                    <button type="submit" class="mini-btn approve">Approve</button>
                                                                </form>
                                                                <form method="POST" action="admin_dashboard.php">
                                                                    <input type="hidden" name="action" value="<?php echo htmlspecialchars($request['reject_action']); ?>">
                                                                    <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id']); ?>">
                                                                    <button type="submit" class="mini-btn reject">Reject</button>
                                                                </form>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="request-complete-label">Complete</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script>
        const closeAddEmployeeButton = document.getElementById('closeAddEmployeeBtn');
        const addEmployeePanel = document.querySelector('.add-employee-panel');

        if (closeAddEmployeeButton && addEmployeePanel) {
            closeAddEmployeeButton.addEventListener('click', () => {
                addEmployeePanel.open = false;
            });
        }

        const scheduleEvents = <?php echo json_encode(array_values($scheduleEvents), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const calendarGrid = document.getElementById('calendarGrid');
        const calendarMonthLabel = document.getElementById('calendarMonthLabel');
        const scheduleEventForm = document.getElementById('scheduleEventForm');
        const scheduleEventId = document.getElementById('scheduleEventId');
        const scheduleEventDate = document.getElementById('scheduleEventDate');
        const scheduleEventTitle = document.getElementById('scheduleEventTitle');
        const scheduleEventType = document.getElementById('scheduleEventType');
        const scheduleEventTime = document.getElementById('scheduleEventTime');
        const scheduleEventNotes = document.getElementById('scheduleEventNotes');
        const scheduleEditorTitle = document.getElementById('scheduleEditorTitle');
        const scheduleEditorEyebrow = document.getElementById('scheduleEditorEyebrow');
        const scheduleEditor = document.getElementById('scheduleEditor');
        const scheduleCalendarLayout = document.getElementById('scheduleCalendarLayout');
        const scheduleModalBackdrop = document.getElementById('scheduleModalBackdrop');
        const scheduleDetails = document.getElementById('scheduleDetails');
        const scheduleDetailsType = document.getElementById('scheduleDetailsType');
        const scheduleDetailsTitle = document.getElementById('scheduleDetailsTitle');
        const scheduleDetailsDate = document.getElementById('scheduleDetailsDate');
        const scheduleDetailsTime = document.getElementById('scheduleDetailsTime');
        const scheduleDetailsNotes = document.getElementById('scheduleDetailsNotes');
        const editScheduleButton = document.getElementById('editScheduleButton');
        const deleteScheduleForm = document.getElementById('deleteScheduleForm');
        const deleteScheduleEventId = document.getElementById('deleteScheduleEventId');
        let calendarDate = new Date();
        let selectedDateKey = '';
        let selectedScheduleEvent = null;

        const dateKey = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        const formatTime = (time) => {
            if (!time) return '';
            const [hours, minutes] = time.split(':');
            const hour = Number(hours);
            return `${hour % 12 || 12}:${minutes} ${hour >= 12 ? 'PM' : 'AM'}`;
        };

        const clearScheduleForm = (selectedDate = dateKey(new Date())) => {
            selectedScheduleEvent = null;
            scheduleEventForm.reset();
            scheduleEventId.value = '';
            scheduleEventDate.value = selectedDate;
            scheduleEventType.value = 'Meeting';
            scheduleEditorEyebrow.textContent = 'New schedule';
            scheduleEditorTitle.textContent = 'Add activity';
            scheduleDetails.hidden = true;
            scheduleEventForm.hidden = false;
            deleteScheduleForm.hidden = true;
        };

        const selectScheduleDate = (selectedDate, dateEvents = []) => {
            selectedDateKey = selectedDate;
            scheduleEditor.hidden = false;
            scheduleModalBackdrop.hidden = false;
            scheduleCalendarLayout.classList.remove('editor-hidden');
            if (dateEvents.length > 0) {
                showScheduleDetails(dateEvents[0]);
            } else {
                clearScheduleForm(selectedDate);
                scheduleEventTitle.focus();
            }
            renderCalendar();
        };

        const deselectScheduleDate = () => {
            selectedDateKey = '';
            selectedScheduleEvent = null;
            scheduleEditor.hidden = true;
            scheduleModalBackdrop.hidden = true;
            scheduleCalendarLayout.classList.add('editor-hidden');
            clearScheduleForm();
            renderCalendar();
        };

        const showScheduleDetails = (event) => {
            selectedDateKey = event.date;
            selectedScheduleEvent = event;
            scheduleEditor.hidden = false;
            scheduleModalBackdrop.hidden = false;
            scheduleCalendarLayout.classList.remove('editor-hidden');
            scheduleEditorEyebrow.textContent = 'Schedule details';
            scheduleEditorTitle.textContent = 'Schedule details';
            scheduleDetailsType.textContent = event.type;
            scheduleDetailsTitle.textContent = event.title;
            scheduleDetailsDate.textContent = event.date;
            scheduleDetailsTime.textContent = event.time ? formatTime(event.time) : 'All day';
            scheduleDetailsNotes.textContent = event.notes || 'No notes added.';
            scheduleDetails.hidden = false;
            scheduleEventForm.hidden = true;
            deleteScheduleForm.hidden = true;
        };

        const editScheduleEvent = (event) => {
            selectedDateKey = event.date;
            selectedScheduleEvent = event;
            scheduleEditor.hidden = false;
            scheduleModalBackdrop.hidden = false;
            scheduleCalendarLayout.classList.remove('editor-hidden');
            scheduleEditorEyebrow.textContent = 'Edit schedule';
            scheduleEditorTitle.textContent = 'Edit activity';
            scheduleDetails.hidden = true;
            scheduleEventForm.hidden = false;
            scheduleEventId.value = event.id;
            scheduleEventDate.value = event.date;
            scheduleEventTitle.value = event.title;
            scheduleEventType.value = event.type;
            scheduleEventTime.value = event.time || '';
            scheduleEventNotes.value = event.notes || '';
            scheduleEditorTitle.textContent = 'Edit activity';
            deleteScheduleEventId.value = event.id;
            deleteScheduleForm.hidden = false;
            scheduleEventTitle.focus();
        };

        const renderCalendar = () => {
            const year = calendarDate.getFullYear();
            const month = calendarDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const todayKey = dateKey(new Date());
            const eventsByDate = scheduleEvents.reduce((groups, event) => {
                (groups[event.date] ||= []).push(event);
                return groups;
            }, {});

            calendarMonthLabel.textContent = calendarDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            calendarGrid.innerHTML = '';

            for (let index = 0; index < firstDay.getDay(); index += 1) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'calendar-day calendar-day-empty';
                calendarGrid.appendChild(emptyCell);
            }

            for (let day = 1; day <= daysInMonth; day += 1) {
                const cellDate = new Date(year, month, day);
                const cellKey = dateKey(cellDate);
                const cell = document.createElement('div');
                cell.className = `calendar-day${cellKey === todayKey ? ' calendar-day-today' : ''}${cellKey === selectedDateKey ? ' calendar-day-selected' : ''}`;
                cell.tabIndex = 0;
                cell.setAttribute('role', 'gridcell');
                cell.setAttribute('aria-label', cellKey);

                const dayNumber = document.createElement('button');
                dayNumber.type = 'button';
                dayNumber.className = 'calendar-day-number';
                dayNumber.textContent = day;
                dayNumber.addEventListener('click', () => {
                    if (selectedDateKey === cellKey) {
                        deselectScheduleDate();
                    } else {
                        selectScheduleDate(cellKey, eventsByDate[cellKey] || []);
                    }
                });
                cell.appendChild(dayNumber);

                (eventsByDate[cellKey] || []).forEach((event) => {
                    const eventButton = document.createElement('button');
                    eventButton.type = 'button';
                    eventButton.className = `calendar-event calendar-event-${event.type.toLowerCase()}`;
                    eventButton.title = event.notes || event.title;
                    const eventTime = document.createElement('span');
                    eventTime.textContent = event.time ? `${formatTime(event.time)} ` : '';
                    eventButton.appendChild(eventTime);
                    eventButton.appendChild(document.createTextNode(event.title));
                    eventButton.addEventListener('click', (clickEvent) => {
                        clickEvent.stopPropagation();
                        showScheduleDetails(event);
                        renderCalendar();
                    });
                    cell.appendChild(eventButton);
                });

                calendarGrid.appendChild(cell);
            }
        };

        if (calendarGrid) {
            document.getElementById('previousMonth').addEventListener('click', () => {
                calendarDate = new Date(calendarDate.getFullYear(), calendarDate.getMonth() - 1, 1);
                renderCalendar();
            });
            document.getElementById('nextMonth').addEventListener('click', () => {
                calendarDate = new Date(calendarDate.getFullYear(), calendarDate.getMonth() + 1, 1);
                renderCalendar();
            });
            document.getElementById('calendarToday').addEventListener('click', () => {
                calendarDate = new Date();
                renderCalendar();
                selectScheduleDate(dateKey(new Date()), []);
            });
            document.getElementById('closeScheduleModal').addEventListener('click', deselectScheduleDate);
            editScheduleButton.addEventListener('click', () => {
                if (selectedScheduleEvent) {
                    editScheduleEvent(selectedScheduleEvent);
                }
            });
            scheduleModalBackdrop.addEventListener('click', deselectScheduleDate);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !scheduleEditor.hidden) {
                    deselectScheduleDate();
                }
            });
            renderCalendar();
        }
    </script>
</body>
</html>