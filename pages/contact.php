<?php
// pages/contact.php
$pageTitle = 'Contact Us';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../mailer/mailer.php'; // adjust path if needed

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $name    = trim($_POST['fullName'] ?? '');
    $phone   = trim($_POST['phoneNumber'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Basic validation
    if (empty($name) || empty($phone) || empty($email) || empty($message)) {
        $error = 'All fields marked with * are required.';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Please enter a valid 10-digit phone number.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Insert into database
        $stmt = $conn->prepare("
            INSERT INTO contact_enquiries (full_name, phone, email, subject, message, created_at, status)
            VALUES (?, ?, ?, ?, ?, NOW(), 'pending')
        ");
        $stmt->bind_param("sssss", $name, $phone, $email, $subject, $message);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            // Prepare email content
            $mailSubject = "New Contact Form Message: " . ($subject ?: 'General Inquiry');
            $body = "
                <h2>New Contact Form Submission</h2>
                <p><strong>Name:</strong> {$name}</p>
                <p><strong>Phone:</strong> {$phone}</p>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Subject:</strong> " . ($subject ?: 'General') . "</p>
                <p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>
            ";
            $altBody = "Name: $name\nPhone: $phone\nEmail: $email\nSubject: " . ($subject ?: 'General') . "\nMessage: $message";

            $to = 'info@perfectinstitute.in';

            if (sendEmail($to, $mailSubject, $body, $altBody)) {
                $success = 'Thank you for contacting us! We will get back to you soon.';
            } else {
                // Email failed but DB insert succeeded – still show success, but log error
                error_log("Failed to send email for contact enquiry from $email");
                $success = 'Your message has been received. We will contact you soon.';
            }
        } else {
            $error = 'Database error. Please try again later.';
        }
    }
}

// ---------- META DATA FOR SOCIAL SHARING ----------
$meta = [
    'title'       => 'Contact Us - Perfect Institute of Languages',
    'description' => 'Get in touch with Perfect Institute for any inquiries about English courses, admissions or support. Call, WhatsApp or visit us.',
    'image'       => 'https://perfectinstitute.in/assets/images/og-contact.jpeg',
];
$current_url = 'https://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <!-- Primary Meta Tags -->
    <title><?= htmlspecialchars($meta['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta['description']) ?>">
    <meta name="robots" content="index, follow">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($current_url) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($meta['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta['description']) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($meta['image']) ?>">
    <meta property="og:site_name" content="Perfect Institute of Languages">
    <meta property="og:locale" content="en_IN">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($meta['title']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($meta['description']) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($meta['image']) ?>">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= htmlspecialchars($current_url) ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Global CSS (same as homepage) -->
    <style>
        /* =========================================
           GLOBAL RESET
        ========================================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }

        /* =========================================
           HTML + BODY
        ========================================= */
        html {
            scroll-behavior: smooth;
        }

        body {
            
            background-color: #ffffff;
            color: #1e293b;
            line-height: 1.6;
        }

        /* =========================================
           SCROLLBAR (DESKTOP ONLY)
        ========================================= */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #e63946;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #c92e3a;
        }

        /* =========================================
           UTILITY CLASSES
        ========================================= */
        .text-center {
            text-align: center;
        }

        .hidden {
            display: none !important;
        }

        /* =========================================
           MAIN CONTENT SAFETY
        ========================================= */
        main {
            display: block;
            width: 100%;
        }
    </style>

    <!-- Page-specific styles (updated to site theme) -->
    <style>
        :root {
            --primary: #1a365d;
            --primary-light: #2d4f8b;
            --secondary: #f97316;
            --secondary-light: #fb923c;
            --gray: #475569;
            --light-gray: #e2e8f0;
            --white: #ffffff;
            --off-white: #f9fafc;
            --border: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 700;
            position: relative;
            display: inline-block;
        }

        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--secondary);
            border-radius: 2px;
        }

        .contact-hero {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            padding: 80px 0;
            text-align: center;
        }

        .contact-hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .contact-hero p {
            font-size: 1.3rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.9;
        }

        .contact-section {
            padding: 80px 0;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
        }

        .contact-info-card,
        .contact-form-card {
            background: var(--white);
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .contact-info-card {
            border-top: 5px solid var(--secondary);
        }

        .contact-form-card {
            border: 2px solid var(--border);
        }

        .contact-info h3,
        .contact-form h3 {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 30px;
            font-weight: 700;
        }

        .contact-details {
            list-style: none;
        }

        .contact-details li {
            display: flex;
            margin-bottom: 30px;
            align-items: flex-start;
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 1.3rem;
            flex-shrink: 0;
            background: rgba(249, 115, 22, 0.1);
            color: var(--secondary);
        }

        .contact-text h4 {
            color: var(--primary);
            font-size: 1.2rem;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .contact-text p,
        .contact-text a {
            color: var(--gray);
            text-decoration: none;
            line-height: 1.6;
        }

        .contact-text a:hover {
            color: var(--secondary);
        }

        .business-hours {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid var(--border);
        }

        .business-hours h4 {
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .hours-list {
            list-style: none;
        }

        .hours-list li {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: var(--gray);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
        }

        .form-group label .required {
            color: var(--secondary);
        }

        .form-control {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid var(--border);
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 150px;
        }

        .btn {
            display: inline-block;
            padding: 14px 40px;
            background: var(--secondary);
            color: var(--white);
            border: none;
            border-radius: 5px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: var(--secondary-light);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(249, 115, 22, 0.2);
        }

        .map-section {
            padding: 0 0 80px;
        }

        .map-container {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            height: 500px;
        }

        .map-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .faq-section {
            padding: 80px 0;
            background: var(--off-white);
        }

        .faq-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }

        .faq-item {
            background: var(--white);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .faq-item h4 {
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .faq-item p {
            color: var(--gray);
            line-height: 1.7;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 992px) {
            .contact-grid,
            .faq-grid {
                grid-template-columns: 1fr;
            }

            .contact-hero h1 {
                font-size: 2.8rem;
            }

            .map-container {
                height: 400px;
            }
        }

        @media (max-width: 768px) {
            .contact-hero h1 {
                font-size: 2.2rem;
            }

            .contact-hero p {
                font-size: 1.1rem;
            }

            .section-title h2 {
                font-size: 2rem;
            }

            .contact-info-card,
            .contact-form-card {
                padding: 30px;
            }

            .map-container {
                height: 350px;
            }
        }

        @media (max-width: 576px) {
            .contact-icon {
                width: 45px;
                height: 45px;
                font-size: 1.2rem;
                margin-right: 15px;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<section class="contact-hero">
    <div class="container">
        <h1>Contact Us</h1>
        <p>We're here to help you succeed. Reach out to us for any queries or assistance.</p>
    </div>
</section>

<section class="contact-section">
    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="contact-grid">
            <div class="contact-info">
                <div class="contact-info-card">
                    <h3>Get in Touch</h3>
                    <ul class="contact-details">
                        <li>
                            <div class="contact-icon">
                                <i class="bi bi-geo-alt-fill"></i>
                            </div>
                            <div class="contact-text">
                                <h4>Address</h4>
                                <p>2nd Floor, Shushant Bhawan, near Co-operative Bank, Chotta Shimla, Shimla <br>Himachal Pradesh - 171002, India</p>
                            </div>
                        </li>
                        <li>
                            <div class="contact-icon">
                                <i class="bi bi-telephone-fill"></i>
                            </div>
                            <div class="contact-text">
                                <h4>Phone Number</h4>
                                <a href="tel:+919805962233">+91 98059 62233</a>
                            </div>
                        </li>
                        <li>
                            <div class="contact-icon">
                                <i class="bi bi-whatsapp"></i>
                            </div>
                            <div class="contact-text">
                                <h4>WhatsApp</h4>
                                <a href="https://wa.me/919805962233" target="_blank">+91 98059 62233</a><br>
                                <span>Available 9 AM - 7 PM</span>
                            </div>
                        </li>
                        <li>
                            <div class="contact-icon">
                                <i class="bi bi-envelope-fill"></i>
                            </div>
                            <div class="contact-text">
                                <h4>Email Address</h4>
                                <a href="mailto:info@perfectinstitute.in">info@perfectinstitute.in</a>
                            </div>
                        </li>
                    </ul>

                    <div class="business-hours">
                        <h4>Business Hours</h4>
                        <ul class="hours-list">
                            <li><span>Monday - Friday</span><span>9:00 AM - 7:00 PM</span></li>
                            <li><span>Saturday</span><span>9:00 AM - 5:00 PM</span></li>
                            <li><span>Sunday</span><span>Closed</span></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="contact-form">
                <div class="contact-form-card">
                    <h3>Send a Message</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="fullName">Full Name <span class="required">*</span></label>
                            <input type="text" id="fullName" name="fullName" class="form-control" placeholder="Enter your full name" required value="<?= htmlspecialchars($_POST['fullName'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="phoneNumber">Phone Number <span class="required">*</span></label>
                            <input type="tel" id="phoneNumber" name="phoneNumber" class="form-control" placeholder="10-digit mobile number" required value="<?= htmlspecialchars($_POST['phoneNumber'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email address" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <select id="subject" name="subject" class="form-control">
                                <option value="">Select a subject</option>
                                <option value="admission" <?= (isset($_POST['subject']) && $_POST['subject'] == 'admission') ? 'selected' : '' ?>>Admission Inquiry</option>
                                <option value="course" <?= (isset($_POST['subject']) && $_POST['subject'] == 'course') ? 'selected' : '' ?>>Course Information</option>
                                <option value="fee" <?= (isset($_POST['subject']) && $_POST['subject'] == 'fee') ? 'selected' : '' ?>>Fee Structure</option>
                                <option value="other" <?= (isset($_POST['subject']) && $_POST['subject'] == 'other') ? 'selected' : '' ?>>Other Query</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="message">Message <span class="required">*</span></label>
                            <textarea id="message" name="message" class="form-control" placeholder="Write your message here..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="map-section">
    <div class="container">
        <div class="section-title">
            <h2>Find Our Location</h2>
        </div>
        <div class="map-container">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3416.800521041997!2d77.1793512!3d31.087475299999998!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3905789e415686ef%3A0xaecd64227072d70!2sPerfect%20Institute%20of%20English%20Language!5e0!3m2!1sen!2sin!4v1769858038612!5m2!1sen!2sin"
                allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </div>
</section>

<section class="faq-section">
    <div class="container">
        <div class="section-title">
            <h2>Frequently Asked Questions</h2>
        </div>
        <div class="faq-grid">
            <div class="faq-item">
                <h4>What is Level A and Level B in English?</h4>
                <p>Level A is the basic level for beginners who learn simple words, sentences, and everyday communication. Learners can introduce themselves and understand simple English. Level B is the intermediate level for learners who already know basic English and want to speak more fluently, express ideas, and handle daily conversations confidently.</p>
            </div>
            <div class="faq-item">
                <h4>Which course should I prefer Level A Or B?</h4>
                <p>The right course depends on your current English level. If you are a beginner and find it difficult to speak or understand basic English, Level A is the best choice. If you already know and speak basic English and want to improve fluency, confidence, and communication skills, you should join Level B. You can also take a level assessment to choose the most suitable course.</p>
            </div>
            <div class="faq-item">
                <h4>Do you focus on grammar or spoken English?</h4>
                <p>We focus on both grammar and spoken English. Grammar is taught in a practical way through speaking activities and real-life examples, not in a traditional theoretical method. This helps students use grammar naturally while speaking with confidence.</p>
            </div>
            <div class="faq-item">
                <h4>Will speaking practice be included in every class?</h4>
                <p>Yes, speaking practice is included in both modes of classes – Offline and Online. Students participate in conversations, role plays, and practical speaking activities to build confidence, improve fluency, and use English in real-life situations.</p>
            </div>
        </div>
    </div>
</section>

</body>
</html>
<?php include __DIR__ . '/../includes/footer.php'; ?>