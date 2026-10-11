<?php
require_once '../includes/config.php';

// ----------- CREATE ----------- //
if (isset($_POST['add_student'])) {
    $name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);

    $sql = "INSERT INTO students (full_name, email, phone, address, created_at)
            VALUES ('$name', '$email', '$phone', '$address', NOW())";

    if ($conn->query($sql)) {
        $msg = "Student added successfully!";
    } else {
        $msg = "Error: " . $conn->error;
    }
}

// ----------- UPDATE ----------- //
if (isset($_POST['edit_student'])) {
    $id = intval($_POST['id']);
    $name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);

    $sql = "UPDATE students 
            SET full_name='$name', email='$email', phone='$phone', address='$address'
            WHERE id=$id";

    if ($conn->query($sql)) {
        $msg = "Student updated successfully!";
    }
}

// ----------- DELETE ----------- //
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM students WHERE id=$id");
    header("Location: students.php?msg=deleted");
    exit;
}

// ----------- READ ----------- //
$students = $conn->query("SELECT * FROM students ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Management</title>

    <style>
        .table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
        }
        .table th {
            padding: 15px;
            background: #f1f3f5;
            text-align: left;
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .btn {
            padding: 8px 14px;
            border-radius: 5px;
            cursor: pointer;
            border: none;
        }
        .btn-primary { background: #0a192f; color: #fff; }
        .btn-edit { background: #007bff; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        .modal {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.4);
            display: none; justify-content: center; align-items: center;
        }
        .modal-content {
            width: 400px; background: white;
            padding: 25px; border-radius: 10px;
        }
        input {
            width: 100%; padding: 10px;
            margin-bottom: 12px; border-radius: 6px;
            border: 1px solid #ccc;
        }
    </style>

</head>
<body>

<h2 style="margin-bottom:20px;">Student Management</h2>

<button class="btn btn-primary" onclick="openAddModal()">+ Add Student</button>

<br><br>

<table class="table">
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Address</th>
        <th>Actions</th>
    </tr>

    <?php while ($st = $students->fetch_assoc()) { ?>
    <tr>
        <td><?= $st['id'] ?></td>
        <td><?= $st['full_name'] ?></td>
        <td><?= $st['email'] ?></td>
        <td><?= $st['phone'] ?></td>
        <td><?= $st['address'] ?></td>
        <td>
            <button class="btn btn-edit" 
                onclick="openEditModal(
                '<?= $st['id'] ?>',
                '<?= $st['full_name'] ?>',
                '<?= $st['email'] ?>',
                '<?= $st['phone'] ?>',
                '<?= $st['address'] ?>'
                )">Edit</button>



        </td>
    </tr>
    <?php } ?>
</table>

<!-- ADD MODAL -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <h3>Add Student</h3>

        <form method="POST">
            <input type="text" name="full_name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="phone" placeholder="Phone" required>
            <input type="text" name="address" placeholder="Address" required>

            <button name="add_student" class="btn btn-primary">Save</button>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <h3>Edit Student</h3>

        <form method="POST">
            <input type="hidden" name="id" id="edit_id">

            <input type="text" name="full_name" id="edit_name" required>
            <input type="email" name="email" id="edit_email" required>
            <input type="text" name="phone" id="edit_phone" required>
            <input type="text" name="address" id="edit_address" required>

            <button name="edit_student" class="btn btn-primary">Update</button>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById("addModal").style.display = "flex";
}
function openEditModal(id, name, email, phone, address) {
    document.getElementById("editModal").style.display = "flex";
    document.getElementById("edit_id").value = id;
    document.getElementById("edit_name").value = name;
    document.getElementById("edit_email").value = email;
    document.getElementById("edit_phone").value = phone;
    document.getElementById("edit_address").value = address;
}
window.onclick = function(e) {
    if (e.target.classList.contains("modal")) e.target.style.display = "none";
}
</script>

</body>
</html>
