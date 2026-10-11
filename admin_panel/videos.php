<?php
// videos.php - Single file with embedded AJAX handlers, CSRF, and prepared statements
$pageTitle = 'YouTube Videos';
include 'includes/config.php';
include 'includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Permission check for the whole page
if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_content')) {
    header("Location: unauthorized.php");
    exit;
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper function to extract YouTube video ID (supports all URL formats)
function getYouTubeId($url) {
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i';
    if (preg_match($pattern, $url, $match)) {
        return $match[1];
    }
    return '';
}

// ==================== AJAX HANDLER (embedded) ====================
if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    // Re-check permission for AJAX (safety)
    if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_content')) {
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

        // Delete video
        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("DELETE FROM youtube_videos WHERE id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Video deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
            exit;
        }

        // Get video data for editing
        if ($action === 'get') {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("SELECT * FROM youtube_videos WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                echo json_encode(['success' => true, 'video' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Video not found']);
            }
            exit;
        }

        // Add or edit video
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $youtube_url = trim($_POST['youtube_url'] ?? '');
            $duration = trim($_POST['duration'] ?? '');
            $views = trim($_POST['views'] ?? '');
            $status = $_POST['status'] ?? 'active';
            $thumbnail = trim($_POST['thumbnail'] ?? '');

            // Validate required fields
            if (empty($title) || empty($category) || empty($youtube_url)) {
                echo json_encode(['success' => false, 'message' => 'Title, category and YouTube URL are required']);
                exit;
            }

            // Validate YouTube URL
            $videoId = getYouTubeId($youtube_url);
            if (empty($videoId)) {
                echo json_encode(['success' => false, 'message' => 'Invalid YouTube URL']);
                exit;
            }

            // Auto-generate thumbnail if not provided
            if (empty($thumbnail)) {
                $thumbnail = "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg";
            }

            if ($id > 0) {
                // Update existing video
                $sql = "UPDATE youtube_videos SET 
                        title = ?, category = ?, youtube_url = ?, duration = ?, views = ?, status = ?, thumbnail = ? 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sssssssi', $title, $category, $youtube_url, $duration, $views, $status, $thumbnail, $id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Video updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                }
            } else {
                // Insert new video
                $sql = "INSERT INTO youtube_videos (title, category, youtube_url, duration, views, status, thumbnail, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sssssss', $title, $category, $youtube_url, $duration, $views, $status, $thumbnail);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Video added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                }
            }
            exit;
        }
    }
}

// ==================== MAIN PAGE LOGIC ====================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = '';
$params = [];
$types = '';

if (!empty($search)) {
    $where = "WHERE title LIKE ? OR category LIKE ?";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

// Count total for pagination
$countSql = "SELECT COUNT(*) as cnt FROM youtube_videos $where";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total_rows = $countStmt->get_result()->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $limit);

// Fetch videos
$sql = "SELECT * FROM youtube_videos $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $allParams = array_merge($params, [$limit, $offset]);
    $types .= 'ii';
    $stmt->bind_param($types, ...$allParams);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$videos = $stmt->get_result();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Toast style (matching dashboard) -->
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
    #videoModal.hidden { display: none; }
</style>

<div class="bg-white rounded-lg shadow-lg p-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">YouTube Videos</h2>
        <button onclick="openVideoModal('add')" class="mt-3 md:mt-0 inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
            <i class="fas fa-plus-circle mr-2"></i> Add New Video
        </button>
    </div>

    <!-- Search -->
    <div class="mb-6">
        <form method="GET" class="flex gap-2">
            <input type="text" name="search" placeholder="Search by title or category..." value="<?php echo htmlspecialchars($search); ?>" 
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-search"></i>
            </button>
            <?php if ($search): ?>
                <a href="?" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Videos Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thumbnail</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Views</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($videos && $videos->num_rows > 0): ?>
                    <?php while ($row = $videos->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition" data-id="<?php echo $row['id']; ?>">
                        <td class="px-4 py-3 text-sm"><?php echo $row['id']; ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php
                            $thumb = $row['thumbnail'] ?: 'https://img.youtube.com/vi/' . getYouTubeId($row['youtube_url']) . '/default.jpg';
                            ?>
                            <img src="<?php echo htmlspecialchars($thumb); ?>" class="h-10 w-10 object-cover rounded" alt="thumbnail">
                        </td>
                        <td class="px-4 py-3 text-sm font-medium"><?php echo htmlspecialchars($row['title']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($row['category']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($row['duration'] ?: '—'); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($row['views'] ?: '—'); ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full <?php echo $row['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm space-x-2">
                            <button onclick="openVideoModal('edit', <?php echo $row['id']; ?>)" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteVideo(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-800" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">No videos found.</td>
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

<!-- Video Modal (Add/Edit) -->
<div id="videoModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeVideoModal()"></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
            <h3 class="text-lg font-bold mb-4" id="modalTitle">Add New Video</h3>
            <form id="videoForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" id="videoId">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                    <input type="text" name="title" id="title" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                    <input type="text" name="category" id="category" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">YouTube URL *</label>
                    <input type="url" name="youtube_url" id="youtube_url" required class="w-full px-3 py-2 border rounded-lg" placeholder="https://youtube.com/watch?v=...">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Duration (optional)</label>
                    <input type="text" name="duration" id="duration" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., 10:30">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Views (optional)</label>
                    <input type="text" name="views" id="views" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., 1.2K">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Thumbnail URL (optional)</label>
                    <input type="url" name="thumbnail" id="thumbnail" class="w-full px-3 py-2 border rounded-lg" placeholder="Leave blank to auto-generate from YouTube">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="status" class="w-full px-3 py-2 border rounded-lg">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-2 mt-4">
                    <button type="button" onclick="closeVideoModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for AJAX -->
<script>
// CSRF token from PHP
const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

// Toast notification
function showToast(msg, type) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

// Open modal for add/edit
function openVideoModal(action, id = null) {
    const modal = document.getElementById('videoModal');
    const form = document.getElementById('videoForm');
    const modalTitle = document.getElementById('modalTitle');

    // Reset form
    form.reset();
    document.getElementById('videoId').value = '';

    if (action === 'add') {
        modalTitle.innerText = 'Add New Video';
        document.getElementById('status').value = 'active';
    } else if (action === 'edit' && id) {
        modalTitle.innerText = 'Edit Video';
        // Fetch video data via AJAX
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 
                ajax: 1, 
                action: 'get', 
                id: id,
                csrf_token: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const v = data.video;
                document.getElementById('videoId').value = v.id;
                document.getElementById('title').value = v.title;
                document.getElementById('category').value = v.category;
                document.getElementById('youtube_url').value = v.youtube_url;
                document.getElementById('duration').value = v.duration || '';
                document.getElementById('views').value = v.views || '';
                document.getElementById('thumbnail').value = v.thumbnail || '';
                document.getElementById('status').value = v.status;
            } else {
                showToast(data.message, 'error');
            }
        });
    }
    modal.classList.remove('hidden');
}

function closeVideoModal() {
    document.getElementById('videoModal').classList.add('hidden');
}

// Handle form submission
document.getElementById('videoForm').addEventListener('submit', function(e) {
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
            closeVideoModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    });
});

// Delete video
function deleteVideo(id) {
    if (!confirm('Are you sure you want to delete this video?')) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 
            ajax: 1, 
            action: 'delete', 
            id: id,
            csrf_token: csrfToken
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