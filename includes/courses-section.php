<?php
require_once '../includes/config.php';

// Search
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$level = $_GET['level'] ?? '';

$query = "SELECT * FROM courses WHERE 1=1";
$params = []; 
$types = "";

// Search filter
if (!empty($search)) {
    $query .= " AND (title LIKE ? OR description LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
    $types .= "ss";
}

// Category filter
if (!empty($category)) {
    $query .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

// Level filter
if (!empty($level)) {
    $query .= " AND level = ?";
    $params[] = $level;
    $types .= "s";
}

$query .= " ORDER BY id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
<title>Manage Courses</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
<style>
body { background:#f8f9fa; font-family:poppins; }
.card { border-radius:10px; }
.btn-primary { background:#e63946; border:none; }
.btn-primary:hover { background:#c71c28; }
</style>
</head>
<body>

<div class="container mt-4">
    <h2 class="mb-4">Courses Management</h2>

    <!-- Search Filters -->
    <form method="GET" class="row mb-4">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control"
                placeholder="Search course..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="col-md-3">
            <input type="text" name="category" class="form-control"
                placeholder="Category" value="<?= htmlspecialchars($category) ?>">
        </div>

        <div class="col-md-3">
            <input type="text" name="level" class="form-control"
                placeholder="Level" value="<?= htmlspecialchars($level) ?>">
        </div>

        <div class="col-md-2">
            <button class="btn btn-primary w-100">Apply</button>
        </div>
    </form>

    <!-- Add New Course -->
    <button onclick="openAddModal()" class="btn btn-primary mb-3">
        + Add New Course
    </button>

    <!-- Courses Table -->
    <div class="card p-3">
        <table class="table table-bordered">
            <thead style="background:#0a192f;color:#fff;">
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Level</th>
                    <th>Price</th>
                    <th>Thumbnail</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($courses) > 0): ?>
                    <?php foreach ($courses as $c): ?>
                        <tr>
                            <td><?= $c['id'] ?></td>
                            <td><?= $c['title'] ?></td>
                            <td><?= $c['category'] ?></td>
                            <td><?= $c['level'] ?></td>
                            <td>₹ <?= $c['discounted_price'] ?></td>
                            <td><img src="<?= $c['thumbnail_url'] ?>" width="60"></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editCourse(<?= $c['id'] ?>)">Edit</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteCourse(<?= $c['id'] ?>)">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center">No Courses Found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="courseModal" class="modal" style="display:none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content p-4">
            <h4 id="modalTitle">Add Course</h4>

            <form id="courseForm" onsubmit="saveCourse(event)">
                <input type="hidden" name="id" id="course_id">

                <label>Title</label>
                <input type="text" required name="title" id="title" class="form-control mb-2">

                <label>Description</label>
                <textarea required name="description" id="description" rows="3" class="form-control mb-2"></textarea>

                <label>Category</label>
                <input type="text" required name="category" id="category" class="form-control mb-2">

                <label>Level</label>
                <input type="text" required name="level" id="level" class="form-control mb-2">

                <label>Duration</label>
                <input type="text" required name="duration" id="duration" class="form-control mb-2">

                <label>Delivery Mode</label>
                <input type="text" required name="delivery_mode" id="delivery_mode" class="form-control mb-2">

                <label>Original Price</label>
                <input type="number" required name="original_price" id="original_price" class="form-control mb-2">

                <label>Discounted Price</label>
                <input type="number" required name="discounted_price" id="discounted_price" class="form-control mb-2">

                <label>Thumbnail URL</label>
                <input type="text" required name="thumbnail_url" id="thumbnail_url" class="form-control mb-3">

                <button class="btn btn-primary">Save Course</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('courseForm').reset();
    document.getElementById('course_id').value = "";
    document.getElementById('modalTitle').innerHTML = "Add New Course";
    document.getElementById('courseModal').style.display = "block";
}

function closeModal() {
    document.getElementById('courseModal').style.display = "none";
}

function editCourse(id) {
    fetch("get_course.php?id=" + id)
    .then(res => res.json())
    .then(data => {
        for (let key in data) {
            if (document.getElementById(key)) {
                document.getElementById(key).value = data[key];
            }
        }
        document.getElementById('modalTitle').innerHTML = "Edit Course";
        document.getElementById('courseModal').style.display = "block";
    });
}

function saveCourse(event) {
    event.preventDefault();
    let formData = new FormData(document.getElementById('courseForm'));

    fetch("save_course.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

function deleteCourse(id) {
    if (!confirm("Delete this course?")) return;

    fetch("delete_course.php?id=" + id)
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}
</script>

</body>
</html>
