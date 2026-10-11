<?php
// courses.php - Admin panel for course management (full CRUD with AJAX, soft delete)
$pageTitle = 'Course Management';
include 'includes/config.php';
include 'includes/functions.php';   // include the permission helper

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Permission check for the whole page (viewing and actions)
if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_courses')) {
    header("Location: unauthorized.php");
    exit;
}

// Ensure uploads directory exists
$upload_dir = '../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==================== AJAX HANDLER (embedded) ====================
if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    // Re-check permission for AJAX (safety)
    if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_courses')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token for state-changing actions
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }

        $action = $_POST['action'] ?? '';

        // Soft delete course (set is_deleted = 1)
        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            $conn->query("UPDATE courses SET is_deleted = 1 WHERE id = $id");
            if ($conn->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Course moved to trash']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Course not found']);
            }
            exit;
        }

        // Get course data for editing
        if ($action === 'get') {
            $id = (int)$_POST['id'];
            $result = $conn->query("SELECT * FROM courses WHERE id = $id");
            if ($row = $result->fetch_assoc()) {
                echo json_encode(['success' => true, 'course' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Course not found']);
            }
            exit;
        }

        // Add or edit course
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $type = $_POST['type'] ?? 'Online';
            $mode = $_POST['mode'] ?? 'online';
            $fee = (float)($_POST['fee'] ?? 0);
            $fee_display = trim($_POST['fee_display'] ?? '');
            $duration = trim($_POST['duration'] ?? '');
            $batch_time = trim($_POST['batch_time'] ?? '');
            $seats = trim($_POST['seats'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $full_description = trim($_POST['full_description'] ?? '');
            $highlights = trim($_POST['highlights'] ?? '');
            $syllabus = trim($_POST['syllabus'] ?? '');
            $requirements = trim($_POST['requirements'] ?? '');

            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Title is required']);
                exit;
            }

            // Handle image upload with validation
            $image = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $size = $_FILES['image']['size'];
                if (!in_array($ext, $allowed)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid image type. Allowed: jpg, jpeg, png, gif, webp']);
                    exit;
                }
                if ($size > 5 * 1024 * 1024) { // 5MB
                    echo json_encode(['success' => false, 'message' => 'Image size must be less than 5MB']);
                    exit;
                }
                $new_name = time() . '_' . uniqid() . '.' . $ext;
                $upload_path = '../uploads/' . $new_name;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image = $new_name;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
                    exit;
                }
            }

            // Escape all data for SQL
            $title = $conn->real_escape_string($title);
            $type = $conn->real_escape_string($type);
            $mode = $conn->real_escape_string($mode);
            $fee_display = $conn->real_escape_string($fee_display);
            $duration = $conn->real_escape_string($duration);
            $batch_time = $conn->real_escape_string($batch_time);
            $seats = $conn->real_escape_string($seats);
            $description = $conn->real_escape_string($description);
            $full_description = $conn->real_escape_string($full_description);
            $highlights = $conn->real_escape_string($highlights);
            $syllabus = $conn->real_escape_string($syllabus);
            $requirements = $conn->real_escape_string($requirements);

            if ($id > 0) {
                // Update existing course
                $old = $conn->query("SELECT image_path FROM courses WHERE id = $id")->fetch_assoc();
                $sql = "UPDATE courses SET 
                        title='$title', type='$type', mode='$mode', fee=$fee, fee_display='$fee_display',
                        duration='$duration', batch_time='$batch_time', seats='$seats',
                        description='$description', full_description='$full_description',
                        highlights='$highlights', syllabus='$syllabus', requirements='$requirements',
                        updated_at = NOW()";
                if ($image) {
                    $sql .= ", image_path='$image'";
                    // Delete old image if exists
                    if ($old && $old['image_path'] && file_exists("../uploads/" . $old['image_path'])) {
                        unlink("../uploads/" . $old['image_path']);
                    }
                }
                $sql .= " WHERE id=$id";
                if ($conn->query($sql)) {
                    echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                }
            } else {
                // Insert new course (is_deleted defaults to 0)
                $sql = "INSERT INTO courses (title, type, mode, fee, fee_display, duration, batch_time, seats, description, full_description, highlights, syllabus, requirements, image_path, created_at) 
                        VALUES ('$title', '$type', '$mode', $fee, '$fee_display', '$duration', '$batch_time', '$seats', '$description', '$full_description', '$highlights', '$syllabus', '$requirements', " . ($image ? "'$image'" : "NULL") . ", NOW())";
                if ($conn->query($sql)) {
                    echo json_encode(['success' => true, 'message' => 'Course added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                }
            }
            exit;
        }
    }
}

// ==================== MAIN PAGE LOGIC ====================
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "WHERE is_deleted = 0";
if ($search) {
    $search_esc = $conn->real_escape_string($search);
    $where .= " AND (title LIKE '%$search_esc%' OR type LIKE '%$search_esc%')";
}

$total_result = $conn->query("SELECT COUNT(*) as cnt FROM courses $where");
$total_rows = $total_result->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $limit);

$courses = $conn->query("SELECT * FROM courses $where ORDER BY id DESC LIMIT $limit OFFSET $offset");

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Toast notification style (already in dashboard, but added for consistency) -->
<style>
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 50px;
        color: white;
        font-weight: 500;
        z-index: 2000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease;
    }
    .toast.success { background: #06d6a0; }
    .toast.error { background: #ef476f; }
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    #courseModal.hidden { display: none; }
</style>

<div class="bg-white rounded-lg shadow-lg p-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Course Management</h2>
        <button onclick="openCourseModal('add')" class="mt-3 md:mt-0 inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
            <i class="fas fa-plus-circle mr-2"></i> Add New Course
        </button>
    </div>

    <!-- Search -->
    <div class="mb-6">
        <form method="GET" class="flex gap-2">
            <input type="text" name="search" placeholder="Search by title or type..." value="<?php echo htmlspecialchars($search); ?>" 
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-search"></i>
            </button>
            <?php if ($search): ?>
                <a href="?" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Courses Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fee (₹)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Batch Time</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($courses && $courses->num_rows > 0): ?>
                    <?php while ($row = $courses->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition" data-id="<?php echo $row['id']; ?>">
                        <td class="px-4 py-3 text-sm"><?php echo $row['id']; ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php if ($row['image_path']): ?>
                                <img src="../uploads/<?php echo $row['image_path']; ?>" class="h-10 w-10 object-cover rounded" alt="course">
                            <?php else: ?>
                                <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm font-medium"><?php echo htmlspecialchars($row['title']); ?></td>
                        <td class="px-4 py-3 text-sm">
                            <span class="px-2 py-1 text-xs rounded-full <?php echo $row['type'] == 'Online' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                <?php echo $row['type']; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php echo ucfirst($row['mode']); ?>
                        </td>
                        <td class="px-4 py-3 text-sm">₹<?php echo number_format($row['fee'], 2); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo $row['duration'] ?: '—'; ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo $row['batch_time'] ?: '—'; ?></td>
                        <td class="px-4 py-3 text-sm space-x-2">
                            <button onclick="openCourseModal('edit', <?php echo $row['id']; ?>)" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteCourse(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-800" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">No courses found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex justify-center mt-6">
        <nav class="inline-flex rounded-md shadow">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-l-md">Previous</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                <span class="px-3 py-2 bg-indigo-600 border border-indigo-600 text-sm font-medium text-white"><?php echo $i; ?></span>
                <?php else: ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-r-md">Next</a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Course Modal (Add/Edit) -->
<div id="courseModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeCourseModal()"></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-3xl p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-lg font-bold mb-4" id="modalTitle">Add New Course</h3>
            <form id="courseForm" enctype="multipart/form-data">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" id="courseId">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course Title *</label>
                        <input type="text" name="title" id="title" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="type" id="type" class="w-full px-3 py-2 border rounded-lg">
                            <option value="Online">Online</option>
                            <option value="Offline">Offline</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mode</label>
                        <select name="mode" id="mode" class="w-full px-3 py-2 border rounded-lg">
                            <option value="online">Online</option>
                            <option value="offline">Offline</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fee (numeric) *</label>
                        <input type="number" step="0.01" name="fee" id="fee" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fee Display (e.g., ₹5,500)</label>
                        <input type="text" name="fee_display" id="fee_display" class="w-full px-3 py-2 border rounded-lg" placeholder="Leave blank to auto-format">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Duration</label>
                        <input type="text" name="duration" id="duration" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., 2 Months">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Batch Time</label>
                        <input type="text" name="batch_time" id="batch_time" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., 7:00 PM">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Seats</label>
                        <input type="text" name="seats" id="seats" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., Only 5 seats">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Short Description</label>
                    <textarea name="description" id="description" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Description</label>
                    <textarea name="full_description" id="full_description" rows="4" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Highlights (one per line)</label>
                    <textarea name="highlights" id="highlights" rows="3" class="w-full px-3 py-2 border rounded-lg" placeholder="Daily Live Conversation&#10;Vocabulary Building&#10;Pronunciation"></textarea>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Syllabus (one per line)</label>
                    <textarea name="syllabus" id="syllabus" rows="3" class="w-full px-3 py-2 border rounded-lg" placeholder="Week 1-2: ...&#10;Week 3-4: ..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Requirements</label>
                    <textarea name="requirements" id="requirements" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Course Image (optional, max 5MB)</label>
                    <input type="file" name="image" id="image" accept="image/*" class="w-full px-3 py-2 border rounded-lg">
                    <div id="currentImage" class="mt-1 text-sm text-gray-500"></div>
                </div>
                <div class="flex justify-end space-x-2 mt-4">
                    <button type="button" onclick="closeCourseModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for AJAX -->
<script>
// Toast notification
function showToast(msg, type) {
    const msgDiv = document.createElement('div');
    msgDiv.className = `toast ${type}`;
    msgDiv.textContent = msg;
    document.body.appendChild(msgDiv);
    setTimeout(() => {
        msgDiv.style.opacity = '0';
        setTimeout(() => msgDiv.remove(), 500);
    }, 3000);
}

// Open modal for add/edit
function openCourseModal(action, id = null) {
    const modal = document.getElementById('courseModal');
    const form = document.getElementById('courseForm');
    const modalTitle = document.getElementById('modalTitle');
    const currentImageDiv = document.getElementById('currentImage');

    // Reset form
    form.reset();
    document.getElementById('courseId').value = '';
    currentImageDiv.innerHTML = '';

    if (action === 'add') {
        modalTitle.innerText = 'Add New Course';
        document.getElementById('type').value = 'Online';
        document.getElementById('mode').value = 'online';
    } else if (action === 'edit' && id) {
        modalTitle.innerText = 'Edit Course';
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 
                ajax: 1, 
                action: 'get', 
                id: id,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const c = data.course;
                document.getElementById('courseId').value = c.id;
                document.getElementById('title').value = c.title;
                document.getElementById('type').value = c.type;
                document.getElementById('mode').value = c.mode;
                document.getElementById('fee').value = c.fee;
                document.getElementById('fee_display').value = c.fee_display || '';
                document.getElementById('duration').value = c.duration || '';
                document.getElementById('batch_time').value = c.batch_time || '';
                document.getElementById('seats').value = c.seats || '';
                document.getElementById('description').value = c.description || '';
                document.getElementById('full_description').value = c.full_description || '';
                document.getElementById('highlights').value = c.highlights || '';
                document.getElementById('syllabus').value = c.syllabus || '';
                document.getElementById('requirements').value = c.requirements || '';
                if (c.image_path) {
                    currentImageDiv.innerHTML = `Current image: <img src="../uploads/${c.image_path}" class="h-10 inline-block ml-2"> (upload new to replace)`;
                }
            } else {
                showToast(data.message, 'error');
            }
        });
    }
    modal.classList.remove('hidden');
}

function closeCourseModal() {
    document.getElementById('courseModal').classList.add('hidden');
}

// Handle form submission
document.getElementById('courseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('ajax', '1');
    formData.append('action', 'save');

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeCourseModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    });
});

// Soft delete course
function deleteCourse(id) {
    if (!confirm('Are you sure you want to move this course to trash?')) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 
            ajax: 1, 
            action: 'delete', 
            id: id,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            document.querySelector(`tr[data-id="${id}"]`).remove();
        } else {
            showToast(data.message, 'error');
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>