<?php
// blogs.php - Enhanced with Quill rich text editor, auto-slug, duplicate check, and file validation (secured)
$pageTitle = 'StudyHub (Blogs)';
include 'includes/config.php';
include 'includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Permission check
if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_content')) {
    header("Location: unauthorized.php");
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==================== AJAX HANDLER ====================
if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    if (!hasPermission($conn, $_SESSION['admin_id'], 'manage_content')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }

        $action = $_POST['action'] ?? '';

        // Delete blog
        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            // Fetch files to delete
            $stmt = $conn->prepare("SELECT image, pdf_file FROM blogs WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($blog = $result->fetch_assoc()) {
                if ($blog['image'] && file_exists("../uploads/" . $blog['image'])) {
                    unlink("../uploads/" . $blog['image']);
                }
                if ($blog['pdf_file'] && file_exists("../uploads/" . $blog['pdf_file'])) {
                    unlink("../uploads/" . $blog['pdf_file']);
                }
            }
            $stmt = $conn->prepare("DELETE FROM blogs WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Blog deleted successfully']);
            exit;
        }

        // Get blog for edit
        if ($action === 'get') {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("SELECT * FROM blogs WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                echo json_encode(['success' => true, 'blog' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Blog not found']);
            }
            exit;
        }

        // Add or edit blog
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $content = $_POST['content'] ?? '';
            $video_url = trim($_POST['video_url'] ?? '');

            if (empty($title) || empty($category) || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Title, category and content are required']);
                exit;
            }

            // Auto-generate slug if empty
            if (empty($slug)) {
                $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($title));
                $slug = trim($slug, '-');
            } else {
                $slug = preg_replace('/[^a-z0-9\-]+/i', '', strtolower($slug));
                $slug = trim($slug, '-');
            }

            // Check duplicate slug
            $slug_check = $conn->prepare("SELECT id FROM blogs WHERE slug = ?" . ($id ? " AND id != ?" : ""));
            if ($id) {
                $slug_check->bind_param("si", $slug, $id);
            } else {
                $slug_check->bind_param("s", $slug);
            }
            $slug_check->execute();
            $slug_check->store_result();
            if ($slug_check->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Slug already exists. Please choose another.']);
                exit;
            }

            // Image upload
            $image = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $size = $_FILES['image']['size'];
                if (!in_array($ext, $allowed)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid image type. Allowed: jpg, jpeg, png, gif, webp']);
                    exit;
                }
                if ($size > 5 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'Image size must be less than 5MB']);
                    exit;
                }
                // Check MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
                finfo_close($finfo);
                $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($mime, $allowed_mimes)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid image file (MIME type not allowed)']);
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

            // PDF upload
            $pdf = null;
            if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
                $allowed = ['pdf'];
                $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
                $size = $_FILES['pdf_file']['size'];
                if (!in_array($ext, $allowed)) {
                    echo json_encode(['success' => false, 'message' => 'Only PDF files are allowed']);
                    exit;
                }
                if ($size > 10 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'PDF size must be less than 10MB']);
                    exit;
                }
                // Check MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['pdf_file']['tmp_name']);
                finfo_close($finfo);
                if ($mime !== 'application/pdf') {
                    echo json_encode(['success' => false, 'message' => 'File is not a valid PDF']);
                    exit;
                }
                $new_name = time() . '_' . uniqid() . '.' . $ext;
                $upload_path = '../uploads/' . $new_name;
                if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $upload_path)) {
                    $pdf = $new_name;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload PDF']);
                    exit;
                }
            }

            if ($id > 0) {
                // Fetch old files to delete if replaced
                $old_stmt = $conn->prepare("SELECT image, pdf_file FROM blogs WHERE id = ?");
                $old_stmt->bind_param("i", $id);
                $old_stmt->execute();
                $old = $old_stmt->get_result()->fetch_assoc();

                // Build update query dynamically
                $update_sql = "UPDATE blogs SET title=?, category=?, slug=?, content=?, video_url=?";
                $params = [$title, $category, $slug, $content, $video_url];
                $types = "sssss";

                if ($image) {
                    $update_sql .= ", image=?";
                    $params[] = $image;
                    $types .= "s";
                    if ($old['image'] && file_exists("../uploads/" . $old['image'])) {
                        unlink("../uploads/" . $old['image']);
                    }
                }
                if ($pdf) {
                    $update_sql .= ", pdf_file=?";
                    $params[] = $pdf;
                    $types .= "s";
                    if ($old['pdf_file'] && file_exists("../uploads/" . $old['pdf_file'])) {
                        unlink("../uploads/" . $old['pdf_file']);
                    }
                }
                $update_sql .= ", updated_at=NOW() WHERE id=?";
                $params[] = $id;
                $types .= "i";

                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Blog updated successfully']);
            } else {
                $stmt = $conn->prepare("INSERT INTO blogs (title, category, slug, content, image, video_url, pdf_file, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssssss", $title, $category, $slug, $content, $image, $video_url, $pdf);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Blog added successfully']);
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

$where = "1=1";
$params = [];
$types = "";

if ($search) {
    $where .= " AND (title LIKE ? OR category LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// Count total
$count_sql = "SELECT COUNT(*) as cnt FROM blogs WHERE $where";
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $limit);

// Fetch blogs
$sql = "SELECT * FROM blogs WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$blogs = $stmt->get_result();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Quill Rich Text Editor CSS -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
    /* Ensure the editor has proper height */
    #editor-container {
        height: 400px;
        margin-bottom: 15px;
        background: white;
    }
    /* Hide the original textarea */
    #content {
        display: none;
    }
    /* Toast styling */
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
    #blogModal.hidden { display: none; }
    .ql-editor {
        min-height: 300px;
    }
    .modal-content {
        max-height: 90vh;
        overflow-y: auto;
    }
</style>

<div class="bg-white rounded-lg shadow-lg p-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">StudyHub (Blogs)</h2>
        <button onclick="openBlogModal('add')" class="mt-3 md:mt-0 inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
            <i class="fas fa-plus-circle mr-2"></i> Add New Blog
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

    <!-- Blogs Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Video</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">PDF</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($blogs && $blogs->num_rows > 0): ?>
                    <?php while ($row = $blogs->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition" data-id="<?php echo $row['id']; ?>">
                        <td class="px-4 py-3 text-sm"><?php echo $row['id']; ?></td>
                        <td class="px-4 py-3 text-sm font-medium"><?php echo htmlspecialchars($row['title']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($row['category']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($row['slug']); ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php if ($row['image']): ?>
                                <a href="../uploads/<?php echo urlencode($row['image']); ?>" target="_blank" class="text-indigo-600 hover:underline">
                                    <img src="../uploads/<?php echo urlencode($row['image']); ?>" class="h-10 w-10 object-cover rounded" alt="thumbnail">
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php if (!empty($row['video_url'])): ?>
                                <i class="fas fa-check text-green-500"></i> Yes
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php if ($row['pdf_file']): ?>
                                <a href="../uploads/<?php echo urlencode($row['pdf_file']); ?>" target="_blank" class="text-indigo-600 hover:underline">View PDF</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                        <td class="px-4 py-3 text-sm space-x-2">
                            <button onclick="openBlogModal('edit', <?php echo $row['id']; ?>)" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteBlog(<?php echo $row['id']; ?>)" class="text-red-600 hover:text-red-800" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">No blogs found.</td>
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

<!-- Blog Modal -->
<div id="blogModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeBlogModal()"></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-4xl p-6 modal-content">
            <h3 class="text-lg font-bold mb-4" id="modalTitle">Add New Blog</h3>
            <form id="blogForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" id="blogId">
                <textarea name="content" id="content" style="display:none;"></textarea>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" name="title" id="title" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <input type="text" name="category" id="category" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Slug (leave empty to auto-generate)</label>
                    <input type="text" name="slug" id="slug" class="w-full px-3 py-2 border rounded-lg">
                </div>

                <!-- Quill Editor -->
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Content *</label>
                    <div id="editor-container"></div>
                </div>

                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Image (optional, max 5MB)</label>
                    <input type="file" name="image" id="image" accept="image/*" class="w-full px-3 py-2 border rounded-lg">
                    <div id="currentImage" class="mt-1 text-sm text-gray-500"></div>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Video URL (optional)</label>
                    <input type="url" name="video_url" id="video_url" class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">PDF File (optional, max 10MB)</label>
                    <input type="file" name="pdf_file" id="pdf_file" accept=".pdf" class="w-full px-3 py-2 border rounded-lg">
                    <div id="currentPdf" class="mt-1 text-sm text-gray-500"></div>
                </div>

                <div class="flex justify-end space-x-2 mt-4">
                    <button type="button" onclick="closeBlogModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quill JS -->
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
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

let quill = null;

// Initialize Quill with extended toolbar
function initQuill() {
    if (!quill) {
        quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link', 'image', 'video'],
                    ['clean']
                ]
            },
            placeholder: 'Write your blog content here...'
        });
    }
}

function openBlogModal(action, id = null) {
    const modal = document.getElementById('blogModal');
    const modalTitle = document.getElementById('modalTitle');
    const currentImageDiv = document.getElementById('currentImage');
    const currentPdfDiv = document.getElementById('currentPdf');
    const titleInput = document.getElementById('title');
    const categoryInput = document.getElementById('category');
    const slugInput = document.getElementById('slug');
    const videoUrlInput = document.getElementById('video_url');
    const blogIdInput = document.getElementById('blogId');

    // Reset form
    document.getElementById('blogForm').reset();
    blogIdInput.value = '';
    currentImageDiv.innerHTML = '';
    currentPdfDiv.innerHTML = '';

    // Initialize Quill if needed
    initQuill();

    if (action === 'add') {
        modalTitle.innerText = 'Add New Blog';
        quill.root.innerHTML = ''; // Clear content
    } else if (action === 'edit' && id) {
        modalTitle.innerText = 'Edit Blog';
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
                const blog = data.blog;
                blogIdInput.value = blog.id;
                titleInput.value = blog.title;
                categoryInput.value = blog.category;
                slugInput.value = blog.slug;
                quill.root.innerHTML = blog.content || '';
                videoUrlInput.value = blog.video_url || '';
                if (blog.image) {
                    currentImageDiv.innerHTML = `Current image: <a href="../uploads/${blog.image}" target="_blank" class="text-indigo-600">${blog.image}</a> (upload new to replace)`;
                }
                if (blog.pdf_file) {
                    currentPdfDiv.innerHTML = `Current PDF: <a href="../uploads/${blog.pdf_file}" target="_blank" class="text-indigo-600">${blog.pdf_file}</a> (upload new to replace)`;
                }
            } else {
                showToast(data.message, 'error');
                closeBlogModal();
            }
        });
    }
    modal.classList.remove('hidden');
}

function closeBlogModal() {
    document.getElementById('blogModal').classList.add('hidden');
}

// Form submit
document.getElementById('blogForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const contentInput = document.getElementById('content');
    contentInput.value = quill.root.innerHTML;

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
            closeBlogModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    });
});

function deleteBlog(id) {
    if (!confirm('Are you sure you want to delete this blog?')) return;
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
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) row.remove();
        } else {
            showToast(data.message, 'error');
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>