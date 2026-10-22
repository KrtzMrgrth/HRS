<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (!isHrManager()) {
    redirect(isAdmin() ? 'admin_dashboard.php' : 'dashboard.php');
}

$module = strtolower(trim((string) ($_GET['module'] ?? 'recruiting')));
$moduleConfig = [
    'recruiting' => ['label' => 'Recruiting', 'icon' => 'RC', 'description' => 'Track candidates and hiring activity in one place.'],
    'onboarding' => ['label' => 'Onboarding', 'icon' => 'OB', 'description' => 'Organize new-hire setup and onboarding progress.'],
    'time-off' => ['label' => 'Time Off', 'icon' => 'TO', 'description' => 'Review leave and attendance requests from the workforce.'],
    'performance' => ['label' => 'Performance', 'icon' => 'PF', 'description' => 'Record review cycles and employee performance notes.'],
    'reports' => ['label' => 'Reports & Analytics', 'icon' => 'RA', 'description' => 'Review current workforce measures and HR activity.'],
    'settings' => ['label' => 'Settings', 'icon' => 'ST', 'description' => 'Manage HR workspace preferences.'],
];

if (!isset($moduleConfig[$module])) {
    $module = 'recruiting';
}

$moduleFile = DATA_DIR . '/hr_manager_modules.json';
$recruitingFile = DATA_DIR . '/recruiting.json';
$recruitingData = loadJson($recruitingFile, ['requisitions' => [], 'candidates' => [], 'interviews' => []]);
$moduleData = loadJson($moduleFile, [
    'candidates' => [],
    'requisitions' => [],
    'interviews' => [],
    'onboarding' => [],
    'onboarding_plans' => [],
    'performance' => [],
    'settings' => ['workspace_name' => 'SmartStaff HR', 'default_review_cycle' => 'Quarterly'],
]);
$moduleData['requisitions'] = is_array($recruitingData['requisitions'] ?? null) ? $recruitingData['requisitions'] : ($moduleData['requisitions'] ?? []);
$moduleData['candidates'] = is_array($recruitingData['candidates'] ?? null) ? $recruitingData['candidates'] : ($moduleData['candidates'] ?? []);
$moduleData['interviews'] = is_array($recruitingData['interviews'] ?? null) ? $recruitingData['interviews'] : ($moduleData['interviews'] ?? []);
$moduleData['requisitions'] = is_array($moduleData['requisitions'] ?? null) ? $moduleData['requisitions'] : [];
$moduleData['interviews'] = is_array($moduleData['interviews'] ?? null) ? $moduleData['interviews'] : [];
$moduleData['onboarding_plans'] = is_array($moduleData['onboarding_plans'] ?? null) ? $moduleData['onboarding_plans'] : [];

function saveRecruitingData(string $path, array $moduleData): void
{
    saveJson($path, [
        'requisitions' => $moduleData['requisitions'] ?? [],
        'candidates' => $moduleData['candidates'] ?? [],
        'interviews' => $moduleData['interviews'] ?? [],
    ]);
}
$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_requisition') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $openings = max(1, (int) ($_POST['openings'] ?? 1));
        if ($title !== '' && $department !== '') {
            $moduleData['requisitions'][] = ['id' => uniqid('req_', true), 'title' => $title, 'department' => $department, 'openings' => $openings, 'status' => 'Open', 'created_at' => date('Y-m-d')];
            saveRecruitingData($recruitingFile, $moduleData);
            saveJson($moduleFile, $moduleData);
            $_SESSION['flash_message'] = 'Job requisition opened.';
        }
    } elseif ($action === 'update_requisition') {
        $requisitionId = (string) ($_POST['record_id'] ?? '');
        $status = trim((string) ($_POST['status'] ?? 'Open'));
        foreach ($moduleData['requisitions'] as &$requisition) {
            if ((string) ($requisition['id'] ?? '') === $requisitionId) {
                $requisition['status'] = $status;
                break;
            }
        }
        unset($requisition);
        saveRecruitingData($recruitingFile, $moduleData);
        saveJson($moduleFile, $moduleData);
        $_SESSION['flash_message'] = 'Requisition status updated.';
    } elseif ($action === 'add_candidate') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $position = trim((string) ($_POST['position'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $source = trim((string) ($_POST['source'] ?? 'Direct application'));
        $requisitionId = trim((string) ($_POST['requisition_id'] ?? ''));
        $stage = trim((string) ($_POST['stage'] ?? 'Screening'));
        if ($name !== '' && $position !== '') {
            $moduleData['candidates'][] = ['id' => uniqid('candidate_', true), 'name' => $name, 'email' => $email, 'position' => $position, 'source' => $source, 'requisition_id' => $requisitionId, 'stage' => $stage, 'offer_status' => 'Not started', 'created_at' => date('Y-m-d')];
            saveRecruitingData($recruitingFile, $moduleData);
            saveJson($moduleFile, $moduleData);
            $_SESSION['flash_message'] = 'Candidate added.';
        }
    } elseif ($action === 'update_candidate') {
        $candidateId = (string) ($_POST['record_id'] ?? '');
        $stage = trim((string) ($_POST['stage'] ?? 'Screening'));
        foreach ($moduleData['candidates'] as &$candidate) {
            if ((string) ($candidate['id'] ?? '') === $candidateId) {
                $candidate['stage'] = $stage;
                $_SESSION['flash_message'] = 'Candidate stage updated.';
                break;
            }
        }
        unset($candidate);
        saveRecruitingData($recruitingFile, $moduleData);
        saveJson($moduleFile, $moduleData);
    } elseif ($action === 'update_offer') {
        $candidateId = (string) ($_POST['record_id'] ?? '');
        $offerStatus = trim((string) ($_POST['offer_status'] ?? 'Not started'));
        foreach ($moduleData['candidates'] as &$candidate) {
            if ((string) ($candidate['id'] ?? '') === $candidateId) {
                $candidate['offer_status'] = $offerStatus;
                if ($offerStatus === 'Accepted') {
                    $candidate['stage'] = 'Hired';
                }
                break;
            }
        }
        unset($candidate);
        saveRecruitingData($recruitingFile, $moduleData);
        saveJson($moduleFile, $moduleData);
        $_SESSION['flash_message'] = 'Offer status updated.';
    } elseif ($action === 'schedule_interview') {
        $candidateId = (string) ($_POST['candidate_id'] ?? '');
        $interviewDate = trim((string) ($_POST['interview_date'] ?? ''));
        $interviewer = trim((string) ($_POST['interviewer'] ?? ''));
        if ($candidateId !== '' && $interviewDate !== '' && $interviewer !== '') {
            $moduleData['interviews'][] = ['id' => uniqid('interview_', true), 'candidate_id' => $candidateId, 'date' => $interviewDate, 'interviewer' => $interviewer, 'status' => 'Scheduled'];
            saveRecruitingData($recruitingFile, $moduleData);
            saveJson($moduleFile, $moduleData);
            $_SESSION['flash_message'] = 'Interview scheduled.';
        }
    } elseif ($action === 'update_interview') {
        $interviewId = (string) ($_POST['record_id'] ?? '');
        $status = trim((string) ($_POST['status'] ?? 'Scheduled'));
        foreach ($moduleData['interviews'] as &$interview) {
            if ((string) ($interview['id'] ?? '') === $interviewId) {
                $interview['status'] = $status;
                break;
            }
        }
        unset($interview);
        saveRecruitingData($recruitingFile, $moduleData);
        saveJson($moduleFile, $moduleData);
        $_SESSION['flash_message'] = 'Interview status updated.';
    } elseif ($action === 'add_onboarding') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $task = trim((string) ($_POST['task'] ?? ''));
        if ($employeeId > 0 && $task !== '') {
            $person = getEmployeeById($employeeId);
            $moduleData['onboarding'][] = ['id' => uniqid('onboarding_', true), 'employee_id' => $employeeId, 'employee_name' => $person['name'] ?? 'Employee', 'task' => $task, 'status' => 'Not started'];
            saveJson($moduleFile, $moduleData);
            $_SESSION['flash_message'] = 'Onboarding task added.';
        }
    } elseif ($action === 'update_onboarding') {
        $taskId = (string) ($_POST['record_id'] ?? '');
        $status = trim((string) ($_POST['status'] ?? 'Not started'));
        foreach ($moduleData['onboarding'] as &$taskRecord) {
            if ((string) ($taskRecord['id'] ?? '') === $taskId) {
                $taskRecord['status'] = $status;
                $_SESSION['flash_message'] = 'Onboarding status updated.';
                break;
            }
        }
        unset($taskRecord);
        saveJson($moduleFile, $moduleData);
    } elseif ($action === 'create_onboarding_plan') {
        $candidateId = trim((string) ($_POST['candidate_id'] ?? ''));
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $candidate = null;
        foreach ($moduleData['candidates'] as $candidateRecord) {
            if ((string) ($candidateRecord['id'] ?? '') === $candidateId && (($candidateRecord['offer_status'] ?? '') === 'Accepted' || ($candidateRecord['stage'] ?? '') === 'Hired')) {
                $candidate = $candidateRecord;
                break;
            }
        }
        if ($candidate !== null && $startDate !== '') {
            $moduleData['onboarding_plans'][] = [
                'id' => uniqid('onboarding_plan_', true),
                'candidate_id' => $candidateId,
                'employee_name' => $candidate['name'] ?? 'New hire',
                'role' => $candidate['position'] ?? 'Employee',
                'start_date' => $startDate,
                'provisioning' => ['email' => 'Not started', 'equipment' => 'Not started', 'accounts' => 'Not started'],
                'policies' => ['Code of conduct', 'Security and privacy', 'Company culture'],
                'acknowledged_policies' => [],
                'tasks' => [
                    ['id' => uniqid('onboarding_task_', true), 'title' => 'Send welcome information', 'stage' => 'Pre-boarding', 'status' => 'Not started'],
                    ['id' => uniqid('onboarding_task_', true), 'title' => 'Prepare workstation and access', 'stage' => 'Provisioning', 'status' => 'Not started'],
                    ['id' => uniqid('onboarding_task_', true), 'title' => 'Complete team introduction', 'stage' => 'First week', 'status' => 'Not started'],
                ],
            ];
            foreach ($moduleData['candidates'] as &$candidateRecord) {
                if ((string) ($candidateRecord['id'] ?? '') === $candidateId) {
                    $candidateRecord['onboarding_status'] = 'In progress';
                    break;
                }
            }
            unset($candidateRecord);
            saveRecruitingData($recruitingFile, $moduleData);
            saveJson($moduleFile, $moduleData);
            $_SESSION['flash_message'] = 'Onboarding plan created.';
        }
    } elseif ($action === 'update_onboarding_plan') {
        $planId = (string) ($_POST['plan_id'] ?? '');
        $taskId = (string) ($_POST['task_id'] ?? '');
        $status = trim((string) ($_POST['status'] ?? 'Not started'));
        $provisioningKey = trim((string) ($_POST['provisioning_key'] ?? ''));
        $policy = trim((string) ($_POST['policy'] ?? ''));
        foreach ($moduleData['onboarding_plans'] as &$plan) {
            if ((string) ($plan['id'] ?? '') !== $planId) {
                continue;
            }
            if ($taskId !== '') {
                foreach ($plan['tasks'] as &$planTask) {
                    if ((string) ($planTask['id'] ?? '') === $taskId) {
                        $planTask['status'] = $status;
                        break;
                    }
                }
                unset($planTask);
            }
            if ($provisioningKey !== '' && array_key_exists($provisioningKey, $plan['provisioning'])) {
                $plan['provisioning'][$provisioningKey] = $status;
            }
            if ($policy !== '' && in_array($policy, $plan['policies'], true)) {
                $plan['acknowledged_policies'][$policy] = true;
            }
            break;
        }
        unset($plan);
        saveJson($moduleFile, $moduleData);
        $_SESSION['flash_message'] = 'Onboarding progress updated.';
    } elseif ($action === 'add_performance') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $reviewDate = trim((string) ($_POST['review_date'] ?? ''));
        if ($employeeId > 0 && $reviewDate !== '') {
            $person = getEmployeeById($employeeId);
            $moduleData['performance'][] = ['id' => uniqid('review_', true), 'employee_id' => $employeeId, 'employee_name' => $person['name'] ?? 'Employee', 'review_date' => $reviewDate, 'status' => 'Scheduled'];
            saveJson($moduleFile, $moduleData);
            $_SESSION['flash_message'] = 'Performance review scheduled.';
        }
    } elseif ($action === 'update_performance') {
        $reviewId = (string) ($_POST['record_id'] ?? '');
        $status = trim((string) ($_POST['status'] ?? 'Scheduled'));
        foreach ($moduleData['performance'] as &$review) {
            if ((string) ($review['id'] ?? '') === $reviewId) {
                $review['status'] = $status;
                $_SESSION['flash_message'] = 'Performance review status updated.';
                break;
            }
        }
        unset($review);
        saveJson($moduleFile, $moduleData);
    } elseif (in_array($action, ['approve_leave', 'reject_leave', 'approve_sick_leave', 'reject_sick_leave', 'approve_attendance', 'reject_attendance'], true)) {
        $requestId = (string) ($_POST['request_id'] ?? '');
        $status = str_starts_with($action, 'approve') ? 'Approved' : 'Rejected';
        $updated = match ($action) {
            'approve_leave', 'reject_leave' => updateLeaveStatus($requestId, $status),
            'approve_sick_leave', 'reject_sick_leave' => updateSickLeaveStatus($requestId, $status),
            default => updateAttendanceRequestStatus($requestId, $status),
        };
        $_SESSION['flash_message'] = $updated ? 'Request status updated.' : 'Request could not be found.';
    } elseif ($action === 'save_settings') {
        $moduleData['settings'] = ['workspace_name' => trim((string) ($_POST['workspace_name'] ?? 'SmartStaff HR')), 'default_review_cycle' => trim((string) ($_POST['default_review_cycle'] ?? 'Quarterly'))];
        saveJson($moduleFile, $moduleData);
        $_SESSION['flash_message'] = 'HR workspace settings saved.';
    }

    redirect('hr_manager_module.php?module=' . urlencode($module));
}

$employee = currentEmployee();
$employees = getEmployees();
$onboardingCandidates = array_values(array_filter($moduleData['candidates'], static fn (array $candidate): bool => ($candidate['offer_status'] ?? '') === 'Accepted' || ($candidate['stage'] ?? '') === 'Hired'));
$config = $moduleConfig[$module];
$leaveRequests = array_merge(getLeaveApplications(), getSickLeaveApplications());
$attendanceRequests = getAttendanceRequests();
$pendingRequests = count(array_filter($leaveRequests, static fn (array $request): bool => ($request['status'] ?? '') === 'Pending')) + count(array_filter($attendanceRequests, static fn (array $request): bool => ($request['status'] ?? '') === 'Pending'));
$interviewCandidates = [];
foreach ($moduleData['candidates'] as $candidateRecord) {
    $interviewCandidates[(string) ($candidateRecord['id'] ?? '')] = $candidateRecord;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config['label']); ?> | SmartStaff</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="admin-layout">
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand"><span class="brand-mark">SS</span><span>SmartStaff</span></div>
            <nav class="nav-menu" aria-label="HR Manager workspace">
                <span class="nav-section-label">HR Manager workspace</span>
                <a href="hr_manager_dashboard.php" class="nav-link"><span class="nav-link-icon">DB</span>Dashboard</a>
                <a href="admin_employee_dashboard.php" class="nav-link"><span class="nav-link-icon">EM</span>Employees</a>
                <?php foreach ($moduleConfig as $key => $item): ?>
                    <a href="hr_manager_module.php?module=<?php echo urlencode($key); ?>" class="nav-link<?php echo $key === $module ? ' active' : ''; ?>"<?php echo $key === $module ? ' aria-current="page"' : ''; ?>><span class="nav-link-icon"><?php echo $item['icon']; ?></span><?php echo htmlspecialchars($item['label']); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer"><p><?php echo htmlspecialchars($employee['name']); ?></p><a href="logout.php" class="logout-link">Log out</a></div>
        </aside>
        <main class="main-panel">
            <header class="topbar"><div><p class="eyebrow accent">HR Manager workspace</p><h1><?php echo htmlspecialchars($config['label']); ?></h1></div><div class="topbar-user"><div class="avatar-circle"><?php echo htmlspecialchars($employee['avatar']); ?></div><span><?php echo htmlspecialchars($employee['role']); ?></span></div></header>
            <?php if ($flashMessage !== ''): ?><div class="alert alert-success dashboard-notice"><?php echo htmlspecialchars($flashMessage); ?></div><?php endif; ?>
            <section class="panel manager-module-heading"><p class="eyebrow accent"><?php echo htmlspecialchars($config['icon']); ?> module</p><h2><?php echo htmlspecialchars($config['label']); ?></h2><p><?php echo htmlspecialchars($config['description']); ?></p></section>

            <?php if ($module === 'recruiting'): ?>
                <section class="stats-grid">
                    <div class="stat-card green"><span class="stat-label">Open requisitions</span><strong><?php echo count(array_filter($moduleData['requisitions'], static fn (array $item): bool => ($item['status'] ?? '') === 'Open')); ?></strong><small>Active hiring requests</small></div>
                    <div class="stat-card"><span class="stat-label">Candidates</span><strong><?php echo count($moduleData['candidates']); ?></strong><small>In the hiring pipeline</small></div>
                    <div class="stat-card"><span class="stat-label">Interviews</span><strong><?php echo count($moduleData['interviews']); ?></strong><small>Scheduled or completed</small></div>
                    <div class="stat-card dark"><span class="stat-label">Offers accepted</span><strong><?php echo count(array_filter($moduleData['candidates'], static fn (array $item): bool => ($item['offer_status'] ?? '') === 'Accepted')); ?></strong><small>Ready for onboarding</small></div>
                </section>
                <section class="dashboard-main-row request-dashboard-row">
                    <div class="panel form-panel"><div class="panel-header"><h3>Open a requisition</h3></div><form method="POST"><input type="hidden" name="action" value="add_requisition"><label for="requisitionTitle">Position</label><input id="requisitionTitle" name="title" placeholder="e.g. Frontend Developer" required><label for="requisitionDepartment">Department</label><input id="requisitionDepartment" name="department" required><label for="requisitionOpenings">Openings</label><input id="requisitionOpenings" name="openings" type="number" min="1" value="1" required><button type="submit" class="primary-btn">Open requisition</button></form></div>
                    <div class="panel"><div class="panel-header"><h3>Hiring requests</h3><span class="badge"><?php echo count($moduleData['requisitions']); ?></span></div><div class="list-table"><?php if (empty($moduleData['requisitions'])): ?><p class="empty-state">No requisitions opened yet.</p><?php else: foreach ($moduleData['requisitions'] as $requisition): ?><div class="list-row"><div><strong><?php echo htmlspecialchars($requisition['title']); ?></strong><small><?php echo htmlspecialchars($requisition['department']); ?> · <?php echo (int) $requisition['openings']; ?> opening(s)</small></div><form method="POST"><input type="hidden" name="action" value="update_requisition"><input type="hidden" name="record_id" value="<?php echo htmlspecialchars($requisition['id']); ?>"><select name="status" aria-label="Requisition status"><option<?php echo ($requisition['status'] ?? '') === 'Open' ? ' selected' : ''; ?>>Open</option><option<?php echo ($requisition['status'] ?? '') === 'Paused' ? ' selected' : ''; ?>>Paused</option><option<?php echo ($requisition['status'] ?? '') === 'Closed' ? ' selected' : ''; ?>>Closed</option></select><button type="submit" class="mini-btn approve">Save</button></form></div><?php endforeach; endif; ?></div></div>
                </section>
                <section class="panel"><div class="panel-header"><div><h3>Interview scheduler</h3><p class="panel-helper">Schedule interviews and keep the hiring team aligned.</p></div><span class="badge"><?php echo count($moduleData['interviews']); ?></span></div><form method="POST" class="onboarding-plan-form"><input type="hidden" name="action" value="schedule_interview"><div><label for="interviewCandidate">Candidate</label><select id="interviewCandidate" name="candidate_id" required><?php foreach ($moduleData['candidates'] as $candidate): ?><option value="<?php echo htmlspecialchars($candidate['id']); ?>"><?php echo htmlspecialchars($candidate['name']); ?> · <?php echo htmlspecialchars($candidate['position']); ?></option><?php endforeach; ?></select></div><div><label for="interviewDate">Date and time</label><input id="interviewDate" name="interview_date" type="datetime-local" required></div><div><label for="interviewer">Interviewer</label><input id="interviewer" name="interviewer" placeholder="Hiring manager" required></div><button type="submit" class="primary-btn">Schedule interview</button></form><div class="list-table"><?php foreach ($moduleData['interviews'] as $interview): ?><?php $matchingCandidates = array_values(array_filter($moduleData['candidates'], static fn (array $candidate): bool => (string) ($candidate['id'] ?? '') === (string) ($interview['candidate_id'] ?? ''))); $interviewCandidate = $matchingCandidates[0] ?? ['name' => 'Candidate']; ?><div class="list-row"><div><strong><?php echo htmlspecialchars($interviewCandidate['name']); ?></strong><small><?php echo htmlspecialchars($interview['date']); ?> · <?php echo htmlspecialchars($interview['interviewer']); ?></small></div><form method="POST"><input type="hidden" name="action" value="update_interview"><input type="hidden" name="record_id" value="<?php echo htmlspecialchars($interview['id']); ?>"><select name="status" aria-label="Interview status"><option<?php echo ($interview['status'] ?? '') === 'Scheduled' ? ' selected' : ''; ?>>Scheduled</option><option<?php echo ($interview['status'] ?? '') === 'Completed' ? ' selected' : ''; ?>>Completed</option><option<?php echo ($interview['status'] ?? '') === 'Cancelled' ? ' selected' : ''; ?>>Cancelled</option></select><button type="submit" class="mini-btn approve">Save</button></form></div><?php endforeach; ?></div></section>
                <section class="panel"><div class="panel-header"><div><h3>Candidate pipeline and offers</h3><p class="panel-helper">Move candidates through screening, interviews, offers, and hiring.</p></div></div><div class="list-table"><?php foreach ($moduleData['candidates'] as $candidate): ?><div class="list-row"><div><strong><?php echo htmlspecialchars($candidate['name']); ?></strong><small><?php echo htmlspecialchars($candidate['position']); ?> · <?php echo htmlspecialchars($candidate['email'] ?? 'No email'); ?> · Source: <?php echo htmlspecialchars($candidate['source'] ?? 'Direct application'); ?></small></div><span class="status-pill status-pending"><?php echo htmlspecialchars($candidate['stage']); ?></span><form method="POST"><input type="hidden" name="action" value="update_offer"><input type="hidden" name="record_id" value="<?php echo htmlspecialchars($candidate['id']); ?>"><select name="offer_status" aria-label="Offer status"><option<?php echo ($candidate['offer_status'] ?? '') === 'Not started' ? ' selected' : ''; ?>>Not started</option><option<?php echo ($candidate['offer_status'] ?? '') === 'Sent' ? ' selected' : ''; ?>>Sent</option><option<?php echo ($candidate['offer_status'] ?? '') === 'Accepted' ? ' selected' : ''; ?>>Accepted</option><option<?php echo ($candidate['offer_status'] ?? '') === 'Declined' ? ' selected' : ''; ?>>Declined</option></select><button type="submit" class="mini-btn approve">Save offer</button></form></div><?php endforeach; ?></div></section>
                <section class="dashboard-main-row request-dashboard-row"><div class="panel form-panel"><div class="panel-header"><h3>Add candidate</h3></div><form method="POST"><input type="hidden" name="action" value="add_candidate"><label for="candidateName">Candidate name</label><input id="candidateName" name="name" required><label for="candidatePosition">Position</label><input id="candidatePosition" name="position" required><label for="candidateStage">Stage</label><select id="candidateStage" name="stage"><option>Screening</option><option>Interview</option><option>Offer</option><option>Hired</option></select><button type="submit" class="primary-btn">Add candidate</button></form></div><div class="panel"><div class="panel-header"><h3>Candidate pipeline</h3><span class="badge"><?php echo count($moduleData['candidates']); ?></span></div><div class="list-table"><?php if (empty($moduleData['candidates'])): ?><p class="empty-state">No candidates added yet.</p><?php else: foreach ($moduleData['candidates'] as $candidate): ?><div class="list-row"><div><strong><?php echo htmlspecialchars($candidate['name']); ?></strong><small><?php echo htmlspecialchars($candidate['position']); ?></small></div><form method="POST"><input type="hidden" name="action" value="update_candidate"><input type="hidden" name="record_id" value="<?php echo htmlspecialchars($candidate['id']); ?>"><select name="stage" aria-label="Candidate stage"><option<?php echo $candidate['stage'] === 'Screening' ? ' selected' : ''; ?>>Screening</option><option<?php echo $candidate['stage'] === 'Interview' ? ' selected' : ''; ?>>Interview</option><option<?php echo $candidate['stage'] === 'Offer' ? ' selected' : ''; ?>>Offer</option><option<?php echo $candidate['stage'] === 'Hired' ? ' selected' : ''; ?>>Hired</option></select><button type="submit" class="mini-btn approve">Update</button></form></div><?php endforeach; endif; ?></div></div></section>
            <?php elseif ($module === 'onboarding'): ?>
                <section class="panel onboarding-summary-panel">
                    <div class="panel-header"><div><h3>New hire onboarding</h3><p class="panel-helper">Plan pre-boarding, provisioning, guided tasks, policy access, and progress from one record.</p></div><span class="badge"><?php echo count($moduleData['onboarding_plans']); ?> plans</span></div>
                    <?php if (empty($onboardingCandidates)): ?><p class="empty-state">No hired candidates are ready for onboarding. Accept an offer in Recruiting first.</p><?php else: ?><form method="POST" class="onboarding-plan-form"><input type="hidden" name="action" value="create_onboarding_plan"><div><label for="planCandidate">New hire</label><select id="planCandidate" name="candidate_id" required><?php foreach ($onboardingCandidates as $candidate): ?><option value="<?php echo htmlspecialchars($candidate['id']); ?>"><?php echo htmlspecialchars($candidate['name']); ?> · <?php echo htmlspecialchars($candidate['position']); ?></option><?php endforeach; ?></select></div><div><label for="planStartDate">Start date</label><input id="planStartDate" name="start_date" type="date" required></div><button type="submit" class="primary-btn">Create onboarding plan</button></form><?php endif; ?>
                </section>
                <?php if (empty($moduleData['onboarding_plans'])): ?><section class="panel"><p class="empty-state">Create a plan to begin tracking a new hire.</p></section><?php else: foreach ($moduleData['onboarding_plans'] as $plan): ?>
                    <?php $planItems = array_merge($plan['tasks'] ?? [], array_map(static fn (string $policy): array => ['id' => 'policy_' . md5($policy), 'title' => $policy, 'stage' => 'Policy & culture', 'status' => !empty($plan['acknowledged_policies'][$policy]) ? 'Complete' : 'Not started'], $plan['policies'] ?? [])); $completeItems = count(array_filter($planItems, static fn (array $item): bool => ($item['status'] ?? '') === 'Complete')); $progress = count($planItems) > 0 ? (int) round(($completeItems / count($planItems)) * 100) : 0; ?>
                    <section class="panel onboarding-plan-card">
                        <div class="panel-header"><div><p class="eyebrow accent">Start date <?php echo htmlspecialchars($plan['start_date']); ?></p><h3><?php echo htmlspecialchars($plan['employee_name']); ?></h3><p class="panel-helper"><?php echo htmlspecialchars($plan['role']); ?> · <?php echo $progress; ?>% complete</p></div><strong class="onboarding-progress-value"><?php echo $progress; ?>%</strong></div>
                        <div class="onboarding-progress-track" aria-label="<?php echo $progress; ?> percent complete"><span style="width: <?php echo $progress; ?>%"></span></div>
                        <div class="onboarding-plan-grid">
                            <div><h4>Provisioning</h4><?php foreach ($plan['provisioning'] as $key => $status): ?><form method="POST" class="onboarding-update-row"><input type="hidden" name="action" value="update_onboarding_plan"><input type="hidden" name="plan_id" value="<?php echo htmlspecialchars($plan['id']); ?>"><input type="hidden" name="provisioning_key" value="<?php echo htmlspecialchars($key); ?>"><strong><?php echo htmlspecialchars(ucfirst($key)); ?></strong><select name="status" aria-label="<?php echo htmlspecialchars($key); ?> provisioning status"><option<?php echo $status === 'Not started' ? ' selected' : ''; ?>>Not started</option><option<?php echo $status === 'In progress' ? ' selected' : ''; ?>>In progress</option><option<?php echo $status === 'Complete' ? ' selected' : ''; ?>>Complete</option></select><button type="submit" class="mini-btn approve">Save</button></form><?php endforeach; ?></div>
                            <div><h4>Guided checklist</h4><?php foreach ($plan['tasks'] ?? [] as $task): ?><form method="POST" class="onboarding-update-row"><input type="hidden" name="action" value="update_onboarding_plan"><input type="hidden" name="plan_id" value="<?php echo htmlspecialchars($plan['id']); ?>"><input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task['id']); ?>"><strong><?php echo htmlspecialchars($task['title']); ?><small><?php echo htmlspecialchars($task['stage']); ?></small></strong><select name="status" aria-label="Task status"><option<?php echo $task['status'] === 'Not started' ? ' selected' : ''; ?>>Not started</option><option<?php echo $task['status'] === 'In progress' ? ' selected' : ''; ?>>In progress</option><option<?php echo $task['status'] === 'Complete' ? ' selected' : ''; ?>>Complete</option></select><button type="submit" class="mini-btn approve">Save</button></form><?php endforeach; ?></div>
                            <div><h4>Policies &amp; culture</h4><?php foreach ($plan['policies'] ?? [] as $policy): ?><form method="POST" class="onboarding-policy-row"><input type="hidden" name="action" value="update_onboarding_plan"><input type="hidden" name="plan_id" value="<?php echo htmlspecialchars($plan['id']); ?>"><input type="hidden" name="policy" value="<?php echo htmlspecialchars($policy); ?>"><span><?php echo htmlspecialchars($policy); ?></span><button type="submit" class="mini-btn <?php echo !empty($plan['acknowledged_policies'][$policy]) ? 'approve' : ''; ?>"><?php echo !empty($plan['acknowledged_policies'][$policy]) ? 'Acknowledged' : 'Mark read'; ?></button></form><?php endforeach; ?></div>
                        </div>
                    </section>
                <?php endforeach; endif; ?>
            <?php elseif ($module === 'onboarding-legacy'): ?>
                <section class="dashboard-main-row request-dashboard-row"><div class="panel form-panel"><div class="panel-header"><h3>Add onboarding task</h3></div><form method="POST"><input type="hidden" name="action" value="add_onboarding"><label for="onboardingEmployee">Employee</label><select id="onboardingEmployee" name="employee_id" required><?php foreach ($employees as $person): ?><option value="<?php echo (int) $person['id']; ?>"><?php echo htmlspecialchars($person['name']); ?></option><?php endforeach; ?></select><label for="onboardingTask">Task</label><input id="onboardingTask" name="task" placeholder="e.g. Complete orientation" required><button type="submit" class="primary-btn">Add task</button></form></div><div class="panel"><div class="panel-header"><h3>Onboarding tracker</h3><span class="badge"><?php echo count($moduleData['onboarding']); ?></span></div><div class="list-table"><?php if (empty($moduleData['onboarding'])): ?><p class="empty-state">No onboarding tasks added yet.</p><?php else: foreach ($moduleData['onboarding'] as $task): ?><div class="list-row"><div><strong><?php echo htmlspecialchars($task['employee_name']); ?></strong><small><?php echo htmlspecialchars($task['task']); ?></small></div><form method="POST"><input type="hidden" name="action" value="update_onboarding"><input type="hidden" name="record_id" value="<?php echo htmlspecialchars($task['id']); ?>"><select name="status" aria-label="Onboarding status"><option<?php echo $task['status'] === 'Not started' ? ' selected' : ''; ?>>Not started</option><option<?php echo $task['status'] === 'In progress' ? ' selected' : ''; ?>>In progress</option><option<?php echo $task['status'] === 'Complete' ? ' selected' : ''; ?>>Complete</option></select><button type="submit" class="mini-btn approve">Update</button></form></div><?php endforeach; endif; ?></div></div></section>
            <?php elseif ($module === 'time-off'): ?>
                <section class="panel"><div class="panel-header"><div><h3>Requests requiring attention</h3><p class="panel-helper">Review submitted leave and attendance requests.</p></div><span class="badge"><?php echo $pendingRequests; ?> pending</span></div><div class="list-table"><?php foreach ($leaveRequests as $request): ?><?php $requestType = stripos((string) ($request['leave_type'] ?? ''), 'sick') !== false ? 'sick' : 'leave'; ?><div class="list-row"><div><strong><?php echo htmlspecialchars($request['employee_name'] ?? 'Employee'); ?></strong><small><?php echo htmlspecialchars($request['leave_type'] ?? 'Leave'); ?> · <?php echo htmlspecialchars($request['start_date'] ?? $request['request_date'] ?? 'Date not specified'); ?></small></div><span class="status-pill <?php echo ($request['status'] ?? '') === 'Approved' ? 'status-approved' : (($request['status'] ?? '') === 'Rejected' ? 'status-rejected' : 'status-pending'); ?>"><?php echo htmlspecialchars($request['status'] ?? 'Pending'); ?></span><?php if (($request['status'] ?? '') === 'Pending'): ?><form method="POST"><input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id'] ?? ''); ?>"><input type="hidden" name="action" value="approve_<?php echo $requestType === 'sick' ? 'sick_leave' : 'leave'; ?>"><button type="submit" class="mini-btn approve">Approve</button></form><form method="POST"><input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id'] ?? ''); ?>"><input type="hidden" name="action" value="reject_<?php echo $requestType === 'sick' ? 'sick_leave' : 'leave'; ?>"><button type="submit" class="mini-btn reject">Reject</button></form><?php endif; ?></div><?php endforeach; ?><?php foreach ($attendanceRequests as $request): ?><div class="list-row"><div><strong><?php echo htmlspecialchars($request['employee_name'] ?? 'Employee'); ?></strong><small><?php echo htmlspecialchars($request['request_type'] ?? 'Attendance request'); ?> · <?php echo htmlspecialchars($request['request_date'] ?? 'Date not specified'); ?></small></div><span class="status-pill <?php echo ($request['status'] ?? '') === 'Approved' ? 'status-approved' : (($request['status'] ?? '') === 'Rejected' ? 'status-rejected' : 'status-pending'); ?>"><?php echo htmlspecialchars($request['status'] ?? 'Pending'); ?></span><?php if (($request['status'] ?? '') === 'Pending'): ?><form method="POST"><input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id'] ?? ''); ?>"><input type="hidden" name="action" value="approve_attendance"><button type="submit" class="mini-btn approve">Approve</button></form><form method="POST"><input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id'] ?? ''); ?>"><input type="hidden" name="action" value="reject_attendance"><button type="submit" class="mini-btn reject">Reject</button></form><?php endif; ?></div><?php endforeach; ?></div></section>
            <?php elseif ($module === 'performance'): ?>
                <section class="dashboard-main-row request-dashboard-row"><div class="panel form-panel"><div class="panel-header"><h3>Schedule review</h3></div><form method="POST"><input type="hidden" name="action" value="add_performance"><label for="reviewEmployee">Employee</label><select id="reviewEmployee" name="employee_id" required><?php foreach ($employees as $person): ?><option value="<?php echo (int) $person['id']; ?>"><?php echo htmlspecialchars($person['name']); ?></option><?php endforeach; ?></select><label for="reviewDate">Review date</label><input id="reviewDate" name="review_date" type="date" required><button type="submit" class="primary-btn">Schedule review</button></form></div><div class="panel"><div class="panel-header"><h3>Review calendar</h3><span class="badge"><?php echo count($moduleData['performance']); ?></span></div><div class="list-table"><?php if (empty($moduleData['performance'])): ?><p class="empty-state">No reviews scheduled yet.</p><?php else: foreach ($moduleData['performance'] as $review): ?><div class="list-row"><div><strong><?php echo htmlspecialchars($review['employee_name']); ?></strong><small>Review date: <?php echo htmlspecialchars($review['review_date']); ?></small></div><form method="POST"><input type="hidden" name="action" value="update_performance"><input type="hidden" name="record_id" value="<?php echo htmlspecialchars($review['id']); ?>"><select name="status" aria-label="Review status"><option<?php echo $review['status'] === 'Scheduled' ? ' selected' : ''; ?>>Scheduled</option><option<?php echo $review['status'] === 'In progress' ? ' selected' : ''; ?>>In progress</option><option<?php echo $review['status'] === 'Complete' ? ' selected' : ''; ?>>Complete</option></select><button type="submit" class="mini-btn approve">Update</button></form></div><?php endforeach; endif; ?></div></div></section>
            <?php elseif ($module === 'reports'): ?>
                <section class="stats-grid"><div class="stat-card green"><span class="stat-label">Employees</span><strong><?php echo count($employees); ?></strong><small>Total workforce records</small></div><div class="stat-card"><span class="stat-label">Active employees</span><strong><?php echo count(array_filter($employees, static fn (array $person): bool => ($person['status'] ?? '') === 'Active')); ?></strong><small>Current active records</small></div><div class="stat-card"><span class="stat-label">Pending requests</span><strong><?php echo $pendingRequests; ?></strong><small>Leave and attendance</small></div><div class="stat-card dark"><span class="stat-label">Managers</span><strong><?php echo count(array_filter($employees, static fn (array $person): bool => ($person['account_role'] ?? '') === 'manager')); ?></strong><small>Manager accounts</small></div></section><section class="panel"><div class="panel-header"><h3>Workforce report</h3></div><div class="list-table"><?php foreach ($employees as $person): ?><div class="list-row"><div><strong><?php echo htmlspecialchars($person['name']); ?></strong><small><?php echo htmlspecialchars($person['department']); ?> · <?php echo htmlspecialchars($person['role']); ?></small></div><span><?php echo htmlspecialchars($person['status']); ?></span></div><?php endforeach; ?></div></section>
            <?php else: ?>
                <section class="panel form-panel"><div class="panel-header"><h3>Workspace preferences</h3></div><form method="POST"><input type="hidden" name="action" value="save_settings"><label for="workspaceName">Workspace name</label><input id="workspaceName" name="workspace_name" value="<?php echo htmlspecialchars($moduleData['settings']['workspace_name'] ?? 'SmartStaff HR'); ?>" required><label for="reviewCycle">Default review cycle</label><select id="reviewCycle" name="default_review_cycle"><option<?php echo ($moduleData['settings']['default_review_cycle'] ?? '') === 'Monthly' ? ' selected' : ''; ?>>Monthly</option><option<?php echo ($moduleData['settings']['default_review_cycle'] ?? '') === 'Quarterly' ? ' selected' : ''; ?>>Quarterly</option><option<?php echo ($moduleData['settings']['default_review_cycle'] ?? '') === 'Annually' ? ' selected' : ''; ?>>Annually</option></select><button type="submit" class="primary-btn">Save settings</button></form></section>
            <?php endif; ?>
        </main>
    </div>
    <?php if ($module === 'recruiting'): ?>
        <script>
            const recruitingSections = [...document.querySelectorAll('.main-panel > section')];
            const candidateSection = recruitingSections.find((section) => section.querySelector('h3')?.textContent.trim() === 'Add candidate');
            const requisitionSection = recruitingSections.find((section) => section.querySelector('h3')?.textContent.trim() === 'Open a requisition');
            if (candidateSection && requisitionSection) {
                requisitionSection.before(candidateSection);
            }
        </script>
    <?php endif; ?>
</body>
</html>