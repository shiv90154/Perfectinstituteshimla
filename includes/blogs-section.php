<?php
ob_start(); // 🔥 HEADER ISSUE FIX

require_once '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();

/**
 * DELETE POST ACTION
 * This must run BEFORE any HTML output
 */
if (isset($_GET['delete'])) {
    $post_id = (int) $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM blog_posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = "Post deleted successfully!";
    header("Location: manage-posts.php");
    exit;
}

// Fetch all posts
$query = "
    SELECT bp.*, 
           bc.name AS category_name, 
           u.full_name AS author_name
    FROM blog_posts bp
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id
    LEFT JOIN users u ON bp.author_id = u.id
    ORDER BY bp.created_at DESC
";

$result = $conn->query($query);
$posts  = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Posts - Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>

<body>
<div class="container-fluid">
    <div class="row">

        <?php include 'sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Posts</h1>
                <a href="add-post.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add New Post
                </a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Author</th>
                            <th>Date</th>
                            <th>Views</th>
                            <th>Status</th>
                            <th width="180">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td><?= $post['id']; ?></td>
                            <td><?= htmlspecialchars($post['title']); ?></td>
                            <td><?= htmlspecialchars($post['category_name']); ?></td>
                            <td><?= htmlspecialchars($post['author_name']); ?></td>
                            <td><?= formatDate($post['created_at'], 'M j, Y'); ?></td>
                            <td><?= (int) $post['views']; ?></td>
                            <td>
                                <span class="badge bg-<?= $post['status'] === 'published' ? 'success' : 'secondary'; ?>">
                                    <?= ucfirst($post['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="edit-post.php?id=<?= $post['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a href="manage-posts.php?delete=<?= $post['id']; ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Delete this post?');">
                                    <i class="bi bi-trash"></i>
                                </a>

                                <a href="../blog-details.php?slug=<?= $post['slug']; ?>"
                                   target="_blank"
                                   class="btn btn-sm btn-info">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>

                </table>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
