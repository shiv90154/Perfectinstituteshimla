<?php
require_once __DIR__ . '/../includes/config.php';

// Fetch active courses for display (limit to 3 for featured section)
$stmt = $conn->query("SELECT * FROM courses WHERE is_deleted = 0 ORDER BY id LIMIT 3");
$featuredCourses = $stmt->fetch_all(MYSQLI_ASSOC);

// ========== META DATA FOR SEO & SOCIAL SHARING ==========
$meta_title       = 'Perfect Institute – Best English & Personality Development Courses';
$meta_description = 'Perfect Institute offers English speaking, grammar, personality development and kids courses. Online & offline classes. Enroll today!';
$meta_image       = 'https://perfectinstitute.in/assets/images/og-home.jpeg'; // Update with actual image URL

// Build current URL for canonical and OG
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$request_uri = $_SERVER['REQUEST_URI'];
$current_url = $protocol . '://' . $host . strtok($request_uri, '?');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <!-- Primary Meta Tags -->
    <title><?= htmlspecialchars($meta_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
    <meta name="robots" content="index, follow">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($current_url) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($meta_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta_description) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($meta_image) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Perfect Institute English Courses">
    <meta property="og:site_name" content="Perfect Institute of Languages">
    <meta property="og:locale" content="en_IN">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($meta_title) ?>">
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

    <!-- Global CSS (custom scrollbar, utilities, header offset) -->
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
            /* HEADER OFFSET – adjust 80px to match your header height */
            
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

    <!-- Original theme styles (kept for layout consistency) -->
    <style>
        /* ---------- RESET & VARIABLES ---------- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
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
        body {
            font-family: 'Poppins', sans-serif;
            color: #1e293b;
            background: var(--white);
            line-height: 1.6;
        }
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .section-title {
            text-align: center;
            margin-bottom: 48px;
        }
        .section-title h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 12px;
            position: relative;
            display: inline-block;
        }
        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: var(--secondary);
            border-radius: 2px;
        }
        .section-title p {
            color: var(--gray);
            max-width: 600px;
            margin: 20px auto 0;
            font-size: 1rem;
        }
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            font-size: 1rem;
            transition: background 0.2s;
            text-align: center;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-light);
        }
        .btn-secondary {
            background: var(--secondary);
            color: white;
        }
        .btn-secondary:hover {
            background: var(--secondary-light);
        }
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        .hero {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 80px 0 40px;
        }
        .hero-container {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 40px;
        }
        .hero-content {
            flex: 1 1 400px;
        }
        .hero-content h1 {
            font-size: clamp(1.8rem, 5vw, 2.4rem);
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.3;
        }
        .hero-content p {
            font-size: 1.1rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        .hero-btns {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        .hero-image {
            flex: 1 1 300px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 18px rgba(0,0,0,0.15);
        }
        .hero-image img {
            width: 100%;
            height: auto;
            display: block;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 50px;
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 35px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 700;
            margin-bottom: 4px;
        }
        .stat-text {
            font-size: 0.9rem;
            opacity: 0.85;
        }
        .about-section {
            background: var(--white);
            padding: 70px 0;
        }
        .about-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }
        .about-image img {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 8px 18px rgba(0,0,0,0.05);
        }
        .about-text h3 {
            font-size: clamp(1.5rem, 4vw, 1.9rem);
            color: var(--primary);
            margin-bottom: 20px;
        }
        .about-text p {
            color: var(--gray);
            margin-bottom: 20px;
        }
        .feature-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 25px;
        }
        .about-text .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .about-text .feature-item i {
            background: rgba(249,115,22,0.08);
            color: var(--secondary);
            padding: 12px;
            border-radius: 50%;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .about-text .feature-item h4 {
            font-size: 1rem;
            margin-bottom: 2px;
        }
        .about-text .feature-item p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--gray);
        }
        .courses-section {
            background: var(--off-white);
            padding: 50px 0;
        }
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        .course-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 12px rgba(0,0,0,0.04);
            position: relative;
            transition: box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }
        .course-card:hover {
            box-shadow: 0 12px 24px rgba(0,0,0,0.08);
        }
        .course-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            background: var(--secondary);
            color: white;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 2;
        }
        .course-img {
            width: 100%;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            background-color: #e2e8f0;
        }
        .course-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .course-content {
            padding: 20px 18px 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .course-content h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 6px;
            line-height: 1.4;
        }
        .course-content > p {
            color: var(--gray);
            font-size: 0.85rem;
            margin-bottom: 16px;
        }
        .course-features {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }
        .course-features .feature-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--gray);
        }
        .course-features .feature-item i {
            color: var(--primary);
            font-size: 0.9rem;
            width: 18px;
        }
        .course-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 16px 0 12px;
            border-top: 1px solid var(--light-gray);
            padding-top: 16px;
        }
        .course-price {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
        }
        .course-duration {
            font-size: 0.9rem;
            color: var(--gray);
        }
        .course-duration i {
            color: var(--secondary);
            margin-right: 4px;
        }
        .course-actions {
            display: flex;
            gap: 12px;
            margin-top: auto;
        }
        .btn-view, .btn-enroll {
            flex: 1;
            padding: 10px 0;
            border-radius: 6px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            border: 2px solid;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: background 0.2s, color 0.2s;
        }
        .btn-view {
            background: white;
            border-color: var(--primary);
            color: var(--primary);
        }
        .btn-view:hover {
            background: var(--primary);
            color: white;
        }
        .btn-enroll {
            background: var(--secondary);
            border-color: var(--secondary);
            color: white;
        }
        .btn-enroll:hover {
            background: var(--secondary-light);
            border-color: var(--secondary-light);
        }
        .view-all {
            text-align: center;
            margin-top: 45px;
        }
        .skill-panel {
            background: var(--primary);
            color: white;
            padding: 50px 40px;
            border-radius: 20px;
            margin: 60px auto;
        }
        .skill-title {
            font-size: clamp(1.8rem, 5vw, 2rem);
            font-weight: 700;
            color: var(--secondary);
            text-align: center;
            margin-bottom: 15px;
        }
        .skill-sub {
            text-align: center;
            opacity: 0.85;
            margin-bottom: 35px;
            font-size: 1.1rem;
        }
        .skills-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .skill-box {
            background: rgba(255,255,255,0.06);
            border-left: 5px solid var(--secondary);
            padding: 18px 20px;
            border-radius: 10px;
            transition: background 0.2s;
        }
        .skill-box:hover {
            background: rgba(255,255,255,0.12);
        }
        .skill-box h4 {
            font-size: 1.1rem;
            margin-bottom: 6px;
            color: white;
        }
        .skill-box p {
            margin: 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        .methodology-section {
            padding: 70px 0;
            background: white;
        }
        .method-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }
        .method-card {
            background: var(--off-white);
            padding: 28px 22px;
            border-radius: 16px;
            border-bottom: 4px solid var(--secondary);
            box-shadow: 0 5px 12px rgba(0,0,0,0.02);
        }
        .step-number {
            background: var(--primary);
            color: white;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 20px;
        }
        .method-card h4 {
            font-size: 1.2rem;
            color: var(--primary);
            margin-bottom: 12px;
        }
        .method-card p {
            color: var(--gray);
            font-size: 0.95rem;
        }
        .testimonials-section {
            padding: 70px 0;
            background: #f1f5f9;
        }
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }
        .testimonial-card {
            background: white;
            border-radius: 20px;
            padding: 28px 22px;
            box-shadow: 0 8px 18px rgba(0,0,0,0.04);
        }
        .testimonial-rating {
            color: #fbbf24;
            font-size: 1rem;
            margin-bottom: 14px;
        }
        .testimonial-rating i {
            margin-right: 2px;
        }
        .testimonial-text {
            font-size: 0.95rem;
            color: #334155;
            line-height: 1.6;
            margin-bottom: 22px;
        }
        .testimonial-user {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .testimonial-user img {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--light-gray);
        }
        .testimonial-user h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 4px;
        }
        .testimonial-user span {
            font-size: 0.8rem;
            color: #64748b;
        }
        .google-link {
            display: flex;
            justify-content: center;
        }
        .google-review-btn {
            background: #e63946;
            color: white;
            padding: 12px 28px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s;
        }
        .google-review-btn:hover {
            background: #c72a36;
        }
        @media (max-width: 992px) {
            .courses-grid { grid-template-columns: repeat(2, 1fr); }
            .skills-grid { grid-template-columns: repeat(2, 1fr); }
            .testimonials-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .hero-content h1 { font-size: 1.9rem; }
            .about-grid { grid-template-columns: 1fr; }
            .stats { grid-template-columns: repeat(2, 1fr); }
            .feature-list { grid-template-columns: 1fr; }
            .hero-container { flex-direction: column; }
            .hero-btns { justify-content: center; }
            .hero-content { text-align: center; }
            .skill-panel { padding: 40px 20px; }
            .skill-sub { font-size: 1rem; }
        }
        @media (max-width: 576px) {
            .container { padding: 0 16px; }
            .section-title h2 { font-size: 1.7rem; }
            .courses-grid { grid-template-columns: 1fr; }
            .skills-grid { grid-template-columns: 1fr; }
            .testimonials-grid { grid-template-columns: 1fr; }
            .stats { grid-template-columns: 1fr; }
            .hero-btns .btn { width: 100%; }
            .hero-btns { flex-direction: column; }
            .course-actions { flex-direction: column; }
            .btn-view, .btn-enroll { width: 100%; }
            .course-content h3 { font-size: 1.2rem; }
            .skill-panel { padding: 30px 16px; }
            .skill-title { font-size: 1.6rem; }
        }
        @media (orientation: landscape) and (max-height: 600px) {
            .hero { padding: 40px 0 20px; }
            .stats { margin-top: 30px; padding-top: 25px; }
            .stat-number { font-size: 1.8rem; }
            .stat-text { font-size: 0.8rem; }
            .about-section, .courses-section, .methodology-section, .testimonials-section { padding: 50px 0; }
            .skill-panel { padding: 30px 20px; margin: 40px auto; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

    <!-- HERO SECTION -->
    <section class="hero" id="home">
        <div class="container hero-container">
            <div class="hero-content">
                <h1>Learn English & Communication Skills with Perfect Institute</h1>
                <p>We helped thousands of people to make their life better through our expert-led learning programs.</p>
                <div class="hero-btns">
                    <a href="/admissions" class="btn btn-primary">Take Admission</a>
                    <a href="/courses" class="btn btn-secondary">Explore Courses</a>
                </div>
            </div>
            <div class="hero-image">
                <img src="/Archive/hero.webp" alt="Students learning together" loading="lazy">
            </div>
        </div>
        <div class="container stats">
            <div class="stat-item">
                <div class="stat-number">5000+</div>
                <div class="stat-text">Students Trained</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">2017</div>
                <div class="stat-text">Teaching Since</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">98%</div>
                <div class="stat-text">Success Rate</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">500+</div>
                <div class="stat-text">Completed Batches</div>
            </div>
        </div>
    </section>

    <!-- ABOUT SECTION -->
    <section class="about-section" id="about">
        <div class="container">
            <div class="section-title">
                <h2>About Perfect Institute</h2>
                <p>Your journey to excellence begins here</p>
            </div>
            <div class="about-grid">
                <div class="about-image">
                    <img src="/Archive/class.webp" alt="Our Institute" loading="lazy">
                </div>
                <div class="about-text">
                    <h3>Transforming Lives Through Quality Education</h3>
                    <p>Founded with a vision to enhance people's communication skills & English.</p>
                    <p>Our practical teaching methodology, experienced trainers, and personalized approach ensure that every learner achieves their maximum potential.</p>
                    <div class="feature-list">
                        <div class="feature-item">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <div>
                                <h4>Expert Trainers</h4>
                                <p>Guaranteed Learning</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-handshake"></i>
                            <div>
                                <h4>Personal Attention</h4>
                                <p>Proper Guidance</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-users"></i>
                            <div>
                                <h4>Small Batches</h4>
                                <p>Proper Practice Time</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-certificate"></i>
                            <div>
                                <h4>Practical Learning</h4>
                                <p>Confident Speaking</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- DYNAMIC COURSES SECTION -->
    <section class="courses-section" id="courses">
        <div class="container">
            <div class="section-title">
                <h2>Our Popular Courses</h2>
                <p>Comprehensive English training for all levels with personalized attention</p>
            </div>
            <div class="courses-grid">
                <?php foreach ($featuredCourses as $course): 
                    $badge = $course['type']; // e.g., "Online", "Offline", "Online/Offline"
                    $image = $course['image_path'] ? '/uploads/' . $course['image_path'] : '/images/placeholder.jpg';
                    $description = htmlspecialchars($course['description']);
                    $title = htmlspecialchars($course['title']);
                    $classDays = htmlspecialchars($course['class_days'] ?: 'Flexible');
                    $seats = htmlspecialchars($course['seats'] ?: 'Limited seats');
                    $duration = htmlspecialchars($course['duration'] ?: '—');
                    $price = $course['fee_display'] ?: '₹' . number_format($course['fee']);
                    $courseId = $course['id'];
                ?>
                <div class="course-card">
                    <div class="course-badge"><?= $badge ?></div>
                    <div class="course-img">
                        <img src="<?= $image ?>" alt="<?= $title ?>" loading="lazy">
                    </div>
                    <div class="course-content">
                        <h3><?= $title ?></h3>
                        <p><?= $description ?></p>
                        <div class="course-features">
                            <div class="feature-item"><i class="fas fa-calendar-alt"></i> <?= $classDays ?></div>
                            <div class="feature-item"><i class="fas fa-users"></i> <?= $seats ?></div>
                        </div>
                        <div class="course-meta">
                            <span class="course-price"><?= $price ?></span>
                            <span class="course-duration"><i class="far fa-clock"></i> <?= $duration ?></span>
                        </div>
                        <div class="course-actions">
                            <a href="/view_course?id=<?= $courseId ?>" class="btn-view"><i class="fas fa-eye"></i> View</a>
                            <a href="/admissions" class="btn-enroll"><i class="fas fa-check-circle"></i> Enroll</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="view-all">
                <a href="/courses" class="btn btn-outline">View All Courses</a>
            </div>
        </div>
    </section>

    <!-- SKILLS PANEL -->
    <div class="container">
        <div class="skill-panel">
            <h2 class="skill-title">Unlock Professional English Skills</h2>
            <p class="skill-sub">Perfect for Spoken English, Fluency, Grammar & Interview Preparation</p>
            <div class="skills-grid">
                <div class="skill-box"><h4>🔹 Spoken English</h4><p>Speak confidently in real-life situations</p></div>
                <div class="skill-box"><h4>🔹 Powerful Vocabulary</h4><p>Learn new words daily with examples</p></div>
                <div class="skill-box"><h4>🔹 Interview Training</h4><p>Mock interviews + proper feedback</p></div>
                <div class="skill-box"><h4>🔹 Pronunciation Improvement</h4><p>Improve clarity, tone & fluency</p></div>
                <div class="skill-box"><h4>🔹 Grammar Made Easy</h4><p>Simple rules with practical examples</p></div>
                <div class="skill-box"><h4>🔹 Personality Development</h4><p>Confidence + communication growth</p></div>
            </div>
        </div>
    </div>

    <!-- METHODOLOGY SECTION -->
    <section class="methodology-section" id="methodology">
        <div class="container">
            <div class="section-title">
                <h2>Our Teaching Methodology</h2>
                <p>A focused 3-step approach to ensure your success</p>
            </div>
            <div class="method-grid">
                <div class="method-card">
                    <div class="step-number">1</div>
                    <h4>Assessment & Concept Building</h4>
                    <p>We start by evaluating your current level and designing a personalized learning plan. Structured lessons then focus on grammar, vocabulary, and pronunciation using practical examples.</p>
                </div>
                <div class="method-card">
                    <div class="step-number">2</div>
                    <h4>Practical Application</h4>
                    <p>Students actively apply concepts through conversations, role-plays, discussions, and real-world communication scenarios to build fluency and clarity.</p>
                </div>
                <div class="method-card">
                    <div class="step-number">3</div>
                    <h4>Performance & Confidence Building</h4>
                    <p>Regular assessments, speaking activities, and mock sessions help track progress while strengthening confidence, communication skills, and real-life readiness.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS SECTION -->
    <section class="testimonials-section" id="testimonials">
        <div class="container">
            <div class="section-title">
                <h2>Learners Success Stories</h2>
                <p>Hear what our learners say about their learning experience</p>
            </div>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p class="testimonial-text">Joining this institute was one of the best decisions of my life. I gained confidence, improved personally and professionally, and successfully cleared my interview with the guidance and support. Thank you, Perfect Institute and Keshav Verma sir, for your constant encouragement and help.</p>
                    <div class="testimonial-user">
                        <img src="/images/t1.webp" alt="Rahul Sharma" loading="lazy">
                        <div><h4>Raksha Kanwar</h4><span>Cabin Crew, IndoGo</span></div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p class="testimonial-text">I lacked confidence and struggled to communicate. After joining, I improved my communication, gained confidence, and became comfortable with interviews, discussions, and public speaking. I'm grateful to Keshav Sir for helping me grow personally and professionally.</p>
                    <div class="testimonial-user">
                        <img src="/images/t2.webp" alt="Priya Patel" loading="lazy">
                        <div><h4>Simran Kashyap</h4><span>Working Professional</span></div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p class="testimonial-text">Joining Perfect Institute was a great decision as it helped me improve not only my English but also my overall personality. After completing Level B, I successfully cleared four interviews in Hospitality and Real Estate. Thank you for providing such a supportive learning environment.</p>
                    <div class="testimonial-user">
                        <img src="/images/t3.webp" alt="Amit Kumar" loading="lazy">
                        <div><h4>Bharat Bhushan</h4><span>Asst. Manager, ClubMahindra</span></div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p class="testimonial-text">I am a police officer. Perfect Institute helped me overcome my hesitation and improve my English communication, which is very important in my profession. With trainers simple and practical teaching method, I gained fluency and confidence, and I am truly grateful for their guidance and support.</p>
                    <div class="testimonial-user">
                        <img src="/images/t4.jpg" alt="Simran Kaur" loading="lazy">
                        <div><h4>Tamanna Thakur</h4><span>HP Police</span></div>
                    </div>
                </div>
            </div>
            <div class="google-link">
                <a href="https://www.google.com/search?sca_esv=4b6756fe69c99ed1&sxsrf=ANbL-n7hzAmopsnhApSgzyPLigCECAtaAg:1774757536805&si=AL3DRZEsmMGCryMMFSHJ3StBhOdZ2-6yYkXd_doETEE1OR-qOVIfJcZr8U7SBJ0mfHQwJVh_hYC_TF_gvz6cbHT3rN9cGOuWOo3CHHUSaZbgs3s0MiRLBFCyd822T2bL3sZKFOp6u4L3Btp-HvC5PLhRJsGnz4OMfc1xoramMfB9DfucmPTVisk%3D&q=Perfect+Institute+of+English+Language+Reviews&sa=X&ved=2ahUKEwi8tayEn8STAxW82TgGHah-NrAQ0bkNegQIMxAH&biw=1280&bih=631&dpr=2" target="_blank" class="google-review-btn">
                    <i class="fab fa-google"></i> Read Our Google Reviews
                </a>
            </div>
        </div>
    </section>

</body>
</html>
<?php include __DIR__ . '/../includes/footer.php'; ?>