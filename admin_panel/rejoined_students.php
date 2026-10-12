<?php
// rejoined_students.php - Integrated admin page with Tailwind, CSRF, and prepared statements
$pageTitle = 'Rejoined Students';
include 'includes/config.php';
include 'includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Permission check
if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_rejoined_students')) {
    header("Location: unauthorized.php");
    exit;
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ID Types for dropdown
$id_types = ['Aadhaar', 'PAN', 'Voter ID', 'Passport', 'Other'];

// Fetch courses for dropdown
$courses = [];
$courseQuery = $conn->query("SELECT title, type FROM courses WHERE is_deleted = 0 ORDER BY title");
while ($row = $courseQuery->fetch_assoc()) {
    $courses[] = $row['title'] . ' - ' . ucfirst($row['type']);
}

// Fetch distinct existing students for dropdown (auto-fill) - one row per email
$existingStudents = [];
try { $studentQuery = $conn->query("SELECT email, MAX(full_name) AS full_name, MAX(contact) AS contact FROM registrations WHERE is_deleted = 0 AND email IS NOT NULL AND TRIM(email) != '' GROUP BY email ORDER BY MAX(created_at) DESC"); } catch (Throwable $e) { $studentQuery = false; }
if ($studentQuery) {
    while ($row = $studentQuery->fetch_assoc()) {
        $existingStudents[] = $row;
    }
}

// Helper: Generate unique registration ID (secure)
function generateUniqueRegId($conn) {
    do {
        $reg_id = 'REG' . date('Y') . rand(10000, 99999);
        $check = $conn->prepare("SELECT id FROM registrations WHERE registration_id = ?");
        $check->bind_param('s', $reg_id);
        $check->execute();
        $check->store_result();
    } while ($check->num_rows > 0);
    $check->close();
    return $reg_id;
}

// ==================== ADD STUDENT ====================
if (isset($_POST['add_student'])) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid security token.";
        header("Location: rejoined_students.php");
        exit;
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $contact = preg_replace('/[^0-9]/', '', $_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course_name = trim($_POST['course_name'] ?? '');
    $id_type = $_POST['id_type'] ?? 'Aadhaar';
    $id_number = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id_number'] ?? '');
    $admin_note = trim($_POST['admin_note'] ?? '');

    if (empty($full_name) || empty($contact) || empty($email) || empty($course_name)) {
        $_SESSION['error'] = "All required fields must be filled.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
    } else {
        $reg_id = generateUniqueRegId($conn);
        $stmt = $conn->prepare("
            INSERT INTO registrations (
                registration_id, full_name, contact, email, course_name,
                id_type, id_number, admin_note, status, payment_status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())
        ");
        $stmt->bind_param("ssssssss", $reg_id, $full_name, $contact, $email, $course_name, $id_type, $id_number, $admin_note);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Student added successfully. Registration ID: $reg_id";
        } else {
            $_SESSION['error'] = "Error adding student: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: rejoined_students.php");
    exit;
}

// ==================== UPDATE STUDENT ====================
if (isset($_POST['update_student'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid security token.";
        header("Location: rejoined_students.php");
        exit;
    }

    $reg_id = trim($_POST['reg_id'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $contact = preg_replace('/[^0-9]/', '', $_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course_name = trim($_POST['course_name'] ?? '');
    $id_type = $_POST['id_type'] ?? 'Aadhaar';
    $id_number = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id_number'] ?? '');
    $admin_note = trim($_POST['admin_note'] ?? '');

    if (empty($reg_id) || empty($full_name) || empty($contact) || empty($email) || empty($course_name)) {
        $_SESSION['error'] = "All required fields must be filled.";
    } else {
        $stmt = $conn->prepare("
            UPDATE registrations SET 
                full_name = ?, contact = ?, email = ?, course_name = ?,
                id_type = ?, id_number = ?, admin_note = ?, updated_at = NOW()
            WHERE registration_id = ? AND is_deleted = 0
        ");
        $stmt->bind_param("ssssssss", $full_name, $contact, $email, $course_name, $id_type, $id_number, $admin_note, $reg_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Student updated successfully.";
        } else {
            $_SESSION['error'] = "Error updating student: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: rejoined_students.php");
    exit;
}

// ==================== DELETE STUDENT (soft delete) ====================
if (isset($_GET['delete'])) {
    $reg_id = trim($_GET['delete']);
    if (!empty($reg_id)) {
        $stmt = $conn->prepare("UPDATE registrations SET is_deleted = 1 WHERE registration_id = ?");
        $stmt->bind_param("s", $reg_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Student marked as deleted.";
        } else {
            $_SESSION['error'] = "Error deleting student: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: rejoined_students.php");
    exit;
}

// ==================== FETCH REJOINED STUDENTS ====================
// Students whose email appears in more than one (non-deleted) registration
$sql = "
    SELECT r.*, d.reg_count
    FROM registrations r
    INNER JOIN (
        SELECT LOWER(TRIM(email)) AS email_key, COUNT(*) AS reg_count
        FROM registrations
        WHERE is_deleted = 0 AND email IS NOT NULL AND TRIM(email) != ''
        GROUP BY LOWER(TRIM(email))
        HAVING COUNT(*) > 1
    ) d ON d.email_key = LOWER(TRIM(r.email))
    WHERE r.is_deleted = 0
    ORDER BY r.created_at DESC, r.id DESC
";
$rejoinedRows = [];
$listError = '';
try {
    $result = $conn->query($sql);
    if ($result) {
        while ($r = $result->fetch_assoc()) { $rejoinedRows[] = $r; }
    } else {
        $listError = $conn->error;
    }
} catch (Throwable $e) {
    $listError = $e->getMessage();
}

// ==================== EDIT MODE ====================
$editStudent = null;
if (isset($_GET['edit'])) {
    $reg_id = trim($_GET['edit']);
    if (!empty($reg_id)) {
        $stmt = $conn->prepare("SELECT * FROM registrations WHERE registration_id = ? AND is_deleted = 0");
        $stmt->bind_param("s", $reg_id);
        $stmt->execute();
        $editStudent = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="bg-white rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">🔁 Rejoined Students</h2>
        <a href="registrations.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i> Back to Registrations
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex justify-between items-center">
            <span><?= htmlspecialchars($_SESSION['success']) ?></span>
            <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">&times;</button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex justify-between items-center">
            <span><?= htmlspecialchars($_SESSION['error']) ?></span>
            <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">&times;</button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Add/Edit Form Card -->
    <div class="border rounded-lg p-6 mb-8 bg-gray-50">
        <h3 class="text-lg font-semibold mb-4 text-gray-800">
            <?= $editStudent ? 'Edit Student' : 'Add Rejoined Student' ?>
        </h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <?php if ($editStudent): ?>
                <input type="hidden" name="reg_id" value="<?= htmlspecialchars($editStudent['registration_id']) ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Auto-fill dropdown (only in add mode) -->
                <?php if (!$editStudent && !empty($existingStudents)): ?>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Existing Student (Auto-fill)</label>
                    <select id="existingStudentSelect" class="w-full px-3 py-2 border rounded-lg">
                        <option value="">-- Select a student to auto-fill --</option>
                        <?php foreach ($existingStudents as $student): ?>
                            <option value="<?= htmlspecialchars($student['email']) ?>" 
                                    data-name="<?= htmlspecialchars($student['full_name']) ?>" 
                                    data-contact="<?= htmlspecialchars($student['contact']) ?>">
                                <?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="full_name" id="studentName" required
                           value="<?= htmlspecialchars($editStudent['full_name'] ?? '') ?>"
                           class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact *</label>
                    <input type="text" name="contact" id="studentContact" required maxlength="10" pattern="[0-9]{10}"
                           value="<?= htmlspecialchars($editStudent['contact'] ?? '') ?>"
                           class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" id="studentEmail" required
                           value="<?= htmlspecialchars($editStudent['email'] ?? '') ?>"
                           class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Course *</label>
                    <select name="course_name" id="studentCourse" required class="w-full px-3 py-2 border rounded-lg">
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= htmlspecialchars($course) ?>" <?= (isset($editStudent) && $editStudent['course_name'] == $course) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID Type</label>
                    <select name="id_type" class="w-full px-3 py-2 border rounded-lg">
                        <?php foreach ($id_types as $type): ?>
                            <option value="<?= $type ?>" <?= (isset($editStudent) && ($editStudent['id_type'] ?? '') == $type) ? 'selected' : '' ?>><?= $type ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID Number</label>
                    <input type="text" name="id_number"
                           value="<?= htmlspecialchars($editStudent['id_number'] ?? $editStudent['aadhaar_number'] ?? '') ?>"
                           class="w-full px-3 py-2 border rounded-lg" placeholder="Optional">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Admin Note</label>
                    <textarea name="admin_note" rows="2" class="w-full px-3 py-2 border rounded-lg"
                              placeholder="Any remarks..."><?= htmlspecialchars($editStudent['admin_note'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="mt-4 flex gap-3">
                <?php if ($editStudent): ?>
                    <button type="submit" name="update_student" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-save mr-2"></i> Update Student
                    </button>
                    <a href="rejoined_students.php" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                        Cancel
                    </a>
                <?php else: ?>
                    <button type="submit" name="add_student" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-plus-circle mr-2"></i> Add Student
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Rejoined Students Table -->
    <h3 class="text-lg font-semibold mb-3 text-gray-800">Rejoined Students List <span class="text-sm font-normal text-gray-500">(<?= count($rejoinedRows) ?> records)</span></h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reg ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($listError): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-red-600">Could not load list: <?= htmlspecialchars($listError) ?></td>
                    </tr>
                <?php elseif (empty($rejoinedRows)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">No rejoined students found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rejoinedRows as $row): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono"><?= htmlspecialchars((string)($row['registration_id'] ?? '')) ?></td>
                        <td class="px-4 py-3 text-sm"><?= htmlspecialchars((string)($row['full_name'] ?? '')) ?></td>
                        <td class="px-4 py-3 text-sm"><?= htmlspecialchars((string)($row['contact'] ?? '')) ?></td>
                        <td class="px-4 py-3 text-sm"><?= htmlspecialchars((string)($row['email'] ?? '')) ?></td>
                        <td class="px-4 py-3 text-sm"><?= htmlspecialchars((string)($row['course_name'] ?? '')) ?></td>
                        <td class="px-4 py-3 text-sm"><?= !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '—' ?></td>
                        <td class="px-4 py-3 text-sm space-x-2">
                            <a href="rejoined_students.php?edit=<?= urlencode((string)($row['registration_id'] ?? '')) ?>"
                               class="text-yellow-600 hover:text-yellow-800" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="rejoined_students.php?delete=<?= urlencode((string)($row['registration_id'] ?? '')) ?>"
                               onclick="return confirm('Are you sure you want to delete this record?')"
                               class="text-red-600 hover:text-red-800" title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('existingStudentSelect');
    if (select) {
        select.addEventListener('change', function() {
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption.value) {
                document.getElementById('studentName').value = selectedOption.dataset.name || '';
                document.getElementById('studentContact').value = selectedOption.dataset.contact || '';
                document.getElementById('studentEmail').value = selectedOption.value;
            } else {
                document.getElementById('studentName').value = '';
                document.getElementById('studentContact').value = '';
                document.getElementById('studentEmail').value = '';
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>