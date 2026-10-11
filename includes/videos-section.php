<?php
require_once "../includes/config.php";

$conn = getDBConnection();

$res = $conn->query("SELECT * FROM youtube_videos ORDER BY display_order ASC, created_at DESC");
?>

<div class="content">

    <div class="d-flex justify-content-between mb-3">
        <h3>YouTube Videos</h3>
        <a href="add-video.php" class="btn btn-danger">+ Add Video</a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success"><?php echo ucfirst($_GET['msg']); ?> successfully!</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Thumbnail</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Order</th>
                        <th width="150">Actions</th>
                    </tr>
                </thead>
                <tbody>

                <?php 
                $i=1;
                while($v = $res->fetch_assoc()):
                    preg_match('/embed\/([a-zA-Z0-9_-]{11})/', $v['youtube_url'], $m);
                    $vidID = $m[1] ?? "";
                ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td>
                            <?php if ($vidID): ?>
                                <img src="https://img.youtube.com/vi/<?php echo $vidID; ?>/hqdefault.jpg" width="120">
                            <?php endif; ?>
                        </td>
                        <td><?php echo $v['title']; ?></td>
                        <td><?php echo ucfirst($v['category']); ?></td>
                        <td><?php echo $v['status']; ?></td>
                        <td><?php echo $v['display_order']; ?></td>
                        <td>
                            <a href="edit-video.php?id=<?php echo $v['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                            <a href="delete-video.php?id=<?php echo $v['id']; ?>"
                               onclick="return confirm('Delete this video?')"
                               class="btn btn-danger btn-sm">Delete</a>
                        </td>
                    </tr>

                <?php endwhile; ?>

                </tbody>
            </table>
        </div>
    </div>

</div>

