
<?php
$meta_title = 'Cancellation Policy - Perfect Institute';
$meta_description = 'Read the Cancellation Policy of Perfect Institute regarding admissions, courses, batches and services.';

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

.notice{
    background:#fff7ed;
    border-left:4px solid #f97316;
    padding:15px;
    margin:20px 0;
    border-radius:6px;
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
        <h1>Cancellation Policy</h1>
        <p>Guidelines regarding admission, batch and course cancellations.</p>
    </div>
</section>

<section class="content-section">
<div class="container">

<div class="card">

<span class="updated">
Last Updated: June 2026
</span>

<h2>Cancellation Policy Overview</h2>

<p>
This Cancellation Policy explains the process and conditions
under which admissions, course enrollments, batches, workshops
and educational services may be cancelled.
</p>

<div class="notice">
<strong>Note:</strong>
Any refund arising from a cancellation request will be governed by our Refund Policy.
</div>

<h2>Student-Initiated Cancellation</h2>

<p>
Students may request cancellation of their admission or enrollment
by contacting Perfect Institute through official communication channels.
</p>

<ul>
<li>Cancellation requests must be submitted in writing.</li>
<li>The request should include student details and registration information.</li>
<li>The institute may require identity verification before processing the request.</li>
</ul>

<h2>Course Cancellation by Student</h2>

<ul>
<li>A student may request withdrawal before the course begins.</li>
<li>Cancellation requests after course commencement may not qualify for refunds.</li>
<li>Course access, study material delivery or attendance may affect refund eligibility.</li>
</ul>

<h2>Batch Rescheduling</h2>

<p>
Perfect Institute may reschedule batches, class timings,
faculty assignments or course schedules when necessary.
</p>

<ul>
<li>Students will be informed through available communication channels.</li>
<li>Rescheduling does not automatically qualify for refunds.</li>
</ul>

<h2>Institute-Initiated Cancellation</h2>

<p>
Perfect Institute reserves the right to cancel or postpone any course,
batch, workshop or training program due to operational requirements.
</p>

Possible reasons may include:

<ul>
<li>Insufficient enrollments.</li>
<li>Faculty unavailability.</li>
<li>Technical issues.</li>
<li>Natural disasters or force majeure events.</li>
<li>Government restrictions or legal requirements.</li>
</ul>

<h2>Online Classes and Digital Services</h2>

<p>
For online programs, temporary service interruptions caused by internet,
power, software or third-party platform issues shall not automatically
constitute grounds for cancellation or refund.
</p>

<h2>Refund Eligibility</h2>

<p>
Any refund associated with a cancellation request shall be determined
strictly in accordance with the Refund Policy published on our website.
</p>

<h2>Right to Refuse Service</h2>

<p>
Perfect Institute reserves the right to refuse, suspend or terminate
services in cases involving misconduct, policy violations,
fraudulent activities or misuse of institute resources.
</p>

<h2>Policy Updates</h2>

<p>
Perfect Institute may update this Cancellation Policy from time to time.
Any revised version will be published on this page.
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
