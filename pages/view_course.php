<?php
// pages/view_course.php - Dynamic course detail page
require_once __DIR__ . '/../includes/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$result = $conn->query("SELECT * FROM courses WHERE id = $id AND (is_deleted = 0 OR is_deleted IS NULL)");
$course = $result->fetch_assoc();
if (!$course) {
    header("Location: ?page=courses");
    exit;
}

// ========== META DATA FOR SEO & SOCIAL SHARING ==========
$meta_title       = htmlspecialchars($course['title']) . ' - Perfect Institute';
$meta_description = substr(strip_tags($course['full_description'] ?: $course['description']), 0, 160) . '…';
$meta_description = !empty($meta_description) ? $meta_description : 'Join ' . htmlspecialchars($course['title']) . ' at Perfect Institute. Enhance your English skills with expert trainers.';

// Build full image URL (if image exists, otherwise use default)
$image_path = !empty($course['image_path']) ? '/uploads/' . $course['image_path'] : '/images/placeholder.jpg';
$meta_image = 'https://' . $_SERVER['HTTP_HOST'] . $image_path; // absolute URL for social sharing

// Build current URL for canonical and OG
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$request_uri = $_SERVER['REQUEST_URI'];
$current_url = $protocol . '://' . $host . $request_uri;

// Extract course details for display
$highlights = explode("\n", trim($course['highlights'] ?? ''));
$syllabus = explode("\n", trim($course['syllabus'] ?? ''));
$fee_display = $course['fee_display'] ?: '₹' . number_format($course['fee']);
$image = $course['image_path'] ? '/uploads/' . $course['image_path'] : '/images/placeholder.jpg';
include __DIR__ . '/../includes/header.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <!-- Primary Meta Tags -->
    <title><?= $meta_title ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
    <meta name="robots" content="index, follow">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($current_url) ?>">
    <meta property="og:title" content="<?= $meta_title ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta_description) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($meta_image) ?>">
    <meta property="og:site_name" content="Perfect Institute of Languages">
    <meta property="og:locale" content="en_IN">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $meta_title ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($meta_description) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($meta_image) ?>">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= htmlspecialchars($current_url) ?>">

    <!-- Favicon (adjust path if needed) -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--off-white);
            color: #1e293b;
            line-height: 1.6;
            font-family: 'Poppins', sans-serif;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Page header (replaces original header, since we use header.php) */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            padding: 40px 0;
            margin-bottom: 30px;
        }

        .page-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header-content h1 {
            font-size: clamp(1.8rem, 4vw, 2.2rem);
            margin-bottom: 5px;
        }

        .page-header-content p {
            opacity: 0.9;
            font-size: 1rem;
        }

        .back-btn {
            background-color: var(--secondary);
            color: var(--white);
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .back-btn:hover {
            background-color: var(--secondary-light);
        }

        .course-detail-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin: 30px 0 60px;
        }

        .course-image-large {
            width: 100%;
            aspect-ratio: 16 / 10;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            background-color: var(--white);
        }

        .course-image-large img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .course-type-badge {
            display: inline-block;
            background-color: var(--secondary);
            color: var(--white);
            padding: 6px 20px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .highlight-list {
            list-style: none;
            margin-top: 10px;
        }

        .highlight-list li {
            padding: 10px 0;
            border-bottom: 1px dashed var(--light-gray);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .highlight-list li:before {
            content: "✓";
            color: var(--secondary);
            font-weight: bold;
        }

        .course-details {
            background-color: var(--white);
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
        }

        .detail-section {
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--light-gray);
        }

        .detail-section:last-of-type {
            border-bottom: none;
        }

        .detail-section h3 {
            color: var(--primary);
            margin-bottom: 18px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-section h3 i {
            color: var(--secondary);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .detail-item {
            background-color: var(--off-white);
            padding: 16px 18px;
            border-radius: 12px;
            border-left: 5px solid var(--primary);
        }

        .detail-label {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 6px;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .syllabus-list {
            list-style: none;
        }

        .syllabus-list li {
            padding: 12px 0;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .syllabus-list li:before {
            content: "•";
            color: var(--secondary);
            font-size: 1.8rem;
            line-height: 0;
        }

        .fee-badge {
            background-color: var(--primary);
            color: var(--white);
            padding: 18px 20px;
            border-radius: 12px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 25px 0 15px;
        }

        .enroll-btn {
            background-color: var(--secondary);
            color: var(--white);
            border: none;
            padding: 16px 30px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            margin-top: 15px;
            transition: all 0.25s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 8px 18px rgba(249, 115, 22, 0.25);
        }

        .enroll-btn:hover {
            background-color: var(--secondary-light);
            transform: translateY(-3px);
        }

        @media (max-width: 768px) {
            .course-detail-container {
                grid-template-columns: 1fr;
            }
            .detail-grid {
                grid-template-columns: 1fr;
            }
            .page-header-content {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="page-header">
    <div class="container">
        <div class="page-header-content">
            <div>
                <h1><?= htmlspecialchars($course['title']) ?></h1>
                <p><?= htmlspecialchars($course['description']) ?></p>
            </div>
            <a href="/courses" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Courses
            </a>
        </div>
    </div>
</div>

<div class="container">
    <div class="course-detail-container">
        <!-- LEFT COLUMN: Image & Highlights -->
        <div>
            <div class="course-image-large">
                <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($course['title']) ?>">
            </div>
            <div class="detail-section">
                <span class="course-type-badge"><?= htmlspecialchars($course['type']) ?></span>
                <h3><i class="fas fa-star"></i> Course Highlights</h3>
                <ul class="highlight-list">
                    <?php foreach ($highlights as $hl): if (trim($hl)): ?>
                        <li><?= htmlspecialchars(trim($hl)) ?></li>
                    <?php endif; endforeach; ?>
                </ul>
            </div>
        </div>
        <!-- RIGHT COLUMN: Details -->
        <div class="course-details">
            <div class="detail-section">
                <h3><i class="fas fa-info-circle"></i> Course Details</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Batch Time</div>
                        <div><?= htmlspecialchars($course['batch_time'] ?: 'Flexible') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Duration</div>
                        <div><?= htmlspecialchars($course['duration'] ?: '—') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Class Days</div>
                        <div><?= htmlspecialchars($course['class_days'] ?: '—') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Batch Size</div>
                        <div><?= htmlspecialchars($course['seats'] ?: '—') ?></div>
                    </div>
                </div>
            </div>
            <div class="detail-section">
                <h3><i class="fas fa-align-left"></i> Full Description</h3>
                <p><?= nl2br(htmlspecialchars($course['full_description'])) ?></p>
            </div>
            <div class="detail-section">
                <h3><i class="fas fa-book"></i> Course Syllabus</h3>
                <ul class="syllabus-list">
                    <?php foreach ($syllabus as $item): if (trim($item)): ?>
                        <li><?= htmlspecialchars(trim($item)) ?></li>
                    <?php endif; endforeach; ?>
                </ul>
            </div>
            <div class="detail-section">
                <h3><i class="fas fa-clipboard-check"></i> Requirements</h3>
                <p><?= nl2br(htmlspecialchars($course['requirements'] ?: 'None')) ?></p>
            </div>
            <div class="fee-badge">
                Course Fee: <?= htmlspecialchars($fee_display) ?>
            </div>
            <a href="/admissions" class="enroll-btn">
                <i class="fas fa-check-circle"></i> Enroll Now
            </a>
        </div>
    </div>
</div>

</body>
</html>
<?php
// ===== INCLUDE FOOTER =====
include __DIR__ . '/../includes/footer.php';
?>