<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connect.php';

if (!isset($_SESSION['registration_id'])) {
    header("Location: registration-section.php");
    exit;
}

$registration_id = $_SESSION['registration_id'];

$db = (new Database())->getConnection();
$stmt = $db->prepare("SELECT * FROM registrations WHERE registration_id = ?");
$stmt->execute([$registration_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    echo "Invalid Registration.";
    exit;
}

/* OPTIONAL: agar chaho to last me destroy karna
unset($_SESSION['registration_id']);
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Registration Successful</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root{
--navy:#0a192f;
--red:#e63946;
--green:#28a745;
--bg:#f4f6f8;
}
body{
margin:0;
font-family:Segoe UI;
background:linear-gradient(135deg,#f8f9fa,#eef2f7);
}
.container{
max-width:900px;
margin:50px auto;
padding:30px;
text-align:center;
}
.card{
background:#fff;
padding:35px;
border-radius:16px;
box-shadow:0 15px 40px rgba(0,0,0,.1);
border-top:6px solid var(--green);
}
.icon{
font-size:70px;
color:var(--green);
margin-bottom:20px;
}
h1{color:var(--navy)}
.reg-id{
display:inline-block;
background:var(--navy);
color:#fff;
padding:14px 28px;
border-radius:10px;
font-size:20px;
margin:25px 0;
letter-spacing:1px;
}
.grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
gap:20px;
margin-top:30px;
text-align:left;
}
.item{
padding-bottom:12px;
border-bottom:1px dashed #ddd;
}
.label{font-weight:600;color:var(--navy)}
.value{color:#555}
.actions{
margin-top:35px;
display:flex;
gap:15px;
justify-content:center;
flex-wrap:wrap;
}
.btn{
padding:14px 26px;
border-radius:8px;
text-decoration:none;
font-weight:600;
display:inline-flex;
align-items:center;
gap:8px;
}
.btn-home{background:var(--navy);color:#fff}
.btn-pay{background:var(--red);color:#fff}
.btn-print{border:2px solid var(--navy);color:var(--navy)}
.btn:hover{opacity:.9}
@media(max-width:600px){
.container{margin:20px}
}
</style>
</head>

<body>
<?php include('../includes/header.php'); ?>

<div class="container">
<div class="card">

<div class="icon"><i class="fas fa-check-circle"></i></div>

<h1>Registration Successful</h1>
<p>Your admission request has been received successfully.</p>

<div class="reg-id">
Registration ID: <?= htmlspecialchars($data['registration_id']) ?>
</div>

<div class="grid">
<div class="item">
<div class="label">Full Name</div>
<div class="value"><?= htmlspecialchars($data['full_name']) ?></div>
</div>

<div class="item">
<div class="label">Course</div>
<div class="value"><?= htmlspecialchars($data['course_name']) ?></div>
</div>

<div class="item">
<div class="label">Email</div>
<div class="value"><?= htmlspecialchars($data['email']) ?></div>
</div>

<div class="item">
<div class="label">Contact</div>
<div class="value"><?= htmlspecialchars($data['contact']) ?></div>
</div>

<div class="item">
<div class="label">Gender</div>
<div class="value"><?= htmlspecialchars($data['gender']) ?></div>
</div>

<div class="item">
<div class="label">Registration Date</div>
<div class="value"><?= date('d M Y, h:i A', strtotime($data['created_at'])) ?></div>
</div>
</div>

<div class="actions">
<a href="/index.php" class="btn btn-home">
<i class="fas fa-home"></i> Home
</a>

<a href="javascript:window.print()" class="btn btn-print">
<i class="fas fa-print"></i> Print
</a>

<a href="/pages/payment.php?rid=<?= $data['registration_id'] ?>" class="btn btn-pay">
<i class="fas fa-credit-card"></i> Pay Now
</a>
</div>

</div>
</div>

<?php include('../includes/footer.php'); ?>
</body>
</html>
