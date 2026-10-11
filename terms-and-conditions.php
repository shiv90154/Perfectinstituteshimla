
<?php
$meta_title = 'Terms & Conditions - Perfect Institute';
$meta_description = 'Read the Terms and Conditions governing admissions, courses, fees, and services offered by Perfect Institute.';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$current_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($meta_title) ?></title>
<meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
<meta name="robots" content="index, follow">

<meta property="og:type" content="website">
<meta property="og:url" content="<?= htmlspecialchars($current_url) ?>">
<meta property="og:title" content="<?= htmlspecialchars($meta_title) ?>">
<meta property="og:description" content="<?= htmlspecialchars($meta_description) ?>">

<style>
:root{
    --primary:#1a365d;
    --primary-light:#2d4f8b;
    --secondary:#f97316;
    --gray:#475569;
    --white:#fff;
    --off-white:#f8fafc;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Segoe UI',sans-serif;
    background:#fff;
    color:#1e293b;
    line-height:1.7;
}

.container{
    max-width:1200px;
    margin:auto;
    padding:0 20px;
}

.hero{
    background:linear-gradient(135deg,var(--primary),var(--primary-light));
    color:#fff;
    text-align:center;
    padding:80px 20px;
}

.hero h1{
    font-size:3rem;
    margin-bottom:15px;
}

.hero p{
    opacity:.9;
}

.content-section{
    padding:70px 0;
    background:var(--off-white);
}

.card{
    background:#fff;
    padding:40px;
    border-radius:12px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
}

.card h2{
    color:var(--primary);
    margin-top:30px;
    margin-bottom:15px;
}

.card h2:first-child{
    margin-top:0;
}

.card ul{
    padding-left:25px;
}

.card li{
    margin-bottom:10px;
}

.updated{
    display:inline-block;
    background:#eef2ff;
    color:var(--primary);
    padding:8px 15px;
    border-radius:6px;
    margin-bottom:25px;
}

@media(max-width:768px){
.hero h1{
font-size:2rem;
}

.card{
padding:25px;
}
}
</style>
</head>
<body>

<?php include __DIR__.'/includes/header.php'; ?>

<section class="hero">
    <div class="container">
        <h1>Terms & Conditions</h1>
        <p>Please read these terms carefully before using our services.</p>
    </div>
</section>

<section class="content-section">
<div class="container">

<div class="card">

<span class="updated">
Last Updated: June 2026
</span>

<h2>Acceptance of Terms</h2>

<p>
By accessing this website, submitting admission forms,
making payments, or enrolling in any course offered by
Perfect Institute, you agree to be bound by these Terms & Conditions.
</p>

<h2>Admission Policy</h2>

<ul>
<li>Admission is subject to seat availability.</li>
<li>Perfect Institute reserves the right to accept or reject any application.</li>
<li>All information submitted by students must be accurate and complete.</li>
<li>Providing false information may result in cancellation of admission.</li>
</ul>

<h2>Course Enrollment</h2>

<ul>
<li>Enrollment becomes valid only after successful payment and approval.</li>
<li>Students are responsible for selecting the correct course.</li>
<li>Course schedules may be revised when necessary.</li>
<li>The institute reserves the right to merge or reschedule batches.</li>
</ul>

<h2>Fees & Payments</h2>

<ul>
<li>All course fees must be paid through approved payment methods.</li>
<li>Online payments may be processed through Razorpay or other approved gateways.</li>
<li>Students must keep payment receipts for future reference.</li>
<li>Fees once paid are governed by the Refund Policy.</li>
</ul>

<h2>Student Responsibilities</h2>

<ul>
<li>Maintain respectful behavior towards staff and fellow students.</li>
<li>Follow institute rules and guidelines.</li>
<li>Attend classes regularly.</li>
<li>Protect login credentials and personal account information.</li>
</ul>

<h2>Study Materials</h2>

<p>
All notes, videos, presentations, assignments,
documents and educational resources provided by Perfect Institute
remain the intellectual property of the institute.
</p>

<p>
Students may not copy, reproduce, distribute, publish,
sell or share institute materials without written permission.
</p>

<h2>Certificates</h2>

<p>
Certificates may be issued only after successful completion
of course requirements and institute evaluation criteria.
</p>

<h2>Website Usage</h2>

<ul>
<li>Users must not attempt unauthorized access to any system.</li>
<li>Users must not upload malicious files or harmful content.</li>
<li>Any misuse of the website may result in permanent restriction.</li>
</ul>

<h2>Modification of Services</h2>

<p>
Perfect Institute reserves the right to modify, suspend,
replace or discontinue any course, service or feature without prior notice.
</p>

<h2>Limitation of Liability</h2>

<p>
Perfect Institute shall not be responsible for any indirect,
incidental, special or consequential damages resulting from
the use of our services, website or educational programs.
</p>

<h2>Governing Law</h2>

<p>
These Terms & Conditions shall be governed by and interpreted
in accordance with the laws of India.
</p>

<h2>Contact Information</h2>

<p>
<strong>Perfect Institute</strong><br>
Website: https://perfectinstitute.in<br>
Email: info@perfectinstitute.in
</p>

</div>

</div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>

</body>
</html>

