<?php
// about.php - About Us page
$meta_title       = 'About Us - Perfect Institute';
$meta_description = 'Learn about Perfect Institute of Languages – our mission, vision, experienced trainers, and the story behind our success in English language education.';
$meta_image       = 'https://perfectinstitute.in/assets/images/og-about.jpeg'; // Update with actual image URL

// For dynamic URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$current_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');

// If you use $pageTitle elsewhere, keep it:
$pageTitle = 'About Us';
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
    <meta property="og:site_name" content="Perfect Institute of Languages">
    <meta property="og:locale" content="en_IN">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($meta_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($meta_description) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($meta_image) ?>">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= htmlspecialchars($current_url) ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">

    <!-- Bootstrap Icons & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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

    <!-- Page-specific styles (updated to match site theme) -->
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-title h2 {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
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
        
        .section-title p {
            color: var(--gray);
            max-width: 700px;
            margin: 20px auto 0;
            font-size: clamp(0.9rem, 2vw, 1rem);
        }
        
        .about-hero {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            padding: 60px 0;
            text-align: center;
        }
        
        .about-hero h1 {
            font-size: clamp(2rem, 5vw, 3.5rem);
            margin-bottom: 20px;
            font-weight: 700;
            line-height: 1.2;
            padding: 0 15px;
        }
        
        .about-hero p {
            font-size: clamp(1rem, 2.5vw, 1.3rem);
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.9;
            padding: 0 15px;
        }
        
        .about-content {
            padding: 60px 0;
        }
        
        .about-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 40px;
            align-items: center;
        }
        
        @media (min-width: 768px) {
            .about-grid {
                grid-template-columns: 1fr 1fr;
                gap: 60px;
            }
        }
        
        .about-image {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 100%;
        }
        
        .about-image img {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.5s ease;
            min-height: 300px;
            object-fit: cover;
        }
        
        .about-image:hover img {
            transform: scale(1.05);
        }
        
        .about-text h3 {
            font-size: clamp(1.5rem, 3vw, 2.2rem);
            color: var(--primary);
            margin-bottom: 20px;
            font-weight: 700;
            line-height: 1.3;
        }
        
        .about-text p {
            margin-bottom: 15px;
            color: var(--gray);
            font-size: clamp(0.95rem, 2vw, 1.05rem);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 30px;
        }
        
        @media (min-width: 576px) {
            .stats {
                grid-template-columns: repeat(3, 1fr);
                gap: 30px;
                margin-top: 40px;
            }
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: var(--off-white);
            border-radius: 8px;
        }
        
        .stat-number {
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 5px;
        }
        
        .stat-text {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        @media (min-width: 576px) {
            .stat-text {
                font-size: 1rem;
            }
        }
        
        .mission-vision {
            padding: 60px 0;
            background: var(--off-white);
        }
        
        .mv-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        @media (min-width: 768px) {
            .mv-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 40px;
            }
        }
        
        .mv-card {
            background: var(--white);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .mv-card:hover {
            transform: translateY(-10px);
        }
        
        .mv-icon,
        .feature-icon {
            width: 70px;
            height: 70px;
            background: rgba(249, 115, 22, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: var(--secondary);
            font-size: 1.8rem;
        }
        
        .mv-card h3 {
            font-size: clamp(1.3rem, 2.5vw, 1.8rem);
            color: var(--primary);
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .mv-card p {
            color: var(--gray);
            line-height: 1.7;
            font-size: 0.95rem;
        }
        
        .why-choose {
            padding: 60px 0;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
        }
        
        @media (min-width: 576px) {
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }
        }
        
        @media (min-width: 992px) {
            .features-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 40px;
            }
        }
        
        .feature-card {
            text-align: center;
            padding: 30px 20px;
            border-radius: 10px;
            background: var(--white);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            height: 100%;
        }
        
        .feature-card:hover {
            border-color: var(--secondary);
            transform: translateY(-10px);
        }
        
        .feature-card h3 {
            font-size: clamp(1.2rem, 2vw, 1.5rem);
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .feature-card p {
            color: var(--gray);
            line-height: 1.7;
            font-size: 0.9rem;
        }
        
        .founder-section {
            padding: 60px 0;
            background: var(--off-white);
        }
        
        .founder-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
        }
        
        @media (min-width: 768px) {
            .founder-wrapper {
                flex-direction: row;
                align-items: flex-start;
                gap: 40px;
            }
        }
        
        .founder-image {
            flex-shrink: 0;
        }
        
        .founder-image img {
            width: 100%;
            max-width: 330px;
            height: 380px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .founder-content {
            flex: 1;
            text-align: center;
        }
        
        @media (min-width: 768px) {
            .founder-content {
                text-align: left;
            }
        }
        
        .founder-content h2 {
            font-size: clamp(1.5rem, 3vw, 2.2rem);
            font-weight: 700;
            color: var(--primary);
            line-height: 1.3;
        }
        
        .founder-underline {
            width: 70px;
            height: 4px;
            background: var(--secondary);
            margin: 10px auto 25px auto;
            border-radius: 2px;
        }
        
        @media (min-width: 768px) {
            .founder-underline {
                margin: 10px 0 25px 0;
            }
        }
        
        .founder-content p {
            font-size: clamp(0.95rem, 2vw, 1.05rem);
            color: var(--gray);
            margin-bottom: 15px;
            line-height: 1.7;
        }
        
        .founder-name {
            margin-top: 20px;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        /* Gallery section */
        .gallery-section {
            padding: 60px 0;
            background: var(--white);
        }
        
        .gallery-title {
            text-align: center;
            font-size: clamp(1.5rem, 3vw, 2.4rem);
            font-weight: 700;
            color: var(--primary);
        }
        
        .gallery-subtitle {
            text-align: center;
            margin-top: 10px;
            font-size: clamp(0.9rem, 2vw, 1rem);
            color: var(--gray);
            margin-bottom: 30px;
        }
        
        .gallery-grid {
            display: grid;
            gap: 20px;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }
        
        .gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            aspect-ratio: 16 / 9;
            width: 100%;
        }
        
        .gallery-item.tall {
            aspect-ratio: 16 / 9;
        }
        
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0;
            transition: opacity .4s ease-in-out, transform .3s ease-in-out;
            position: relative;
            z-index: 2;
        }
        
        .gallery-item img.loaded {
            opacity: 1;
        }
        
        .img-loader {
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, #f1f1f1 0%, #e2e2e2 50%, #f1f1f1 100%);
            background-size: 200% 200%;
            animation: shimmer 1.2s infinite linear;
            border-radius: 10px;
            z-index: 1;
        }
        
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        .gallery-item:hover img {
            transform: scale(1.07);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .about-hero,
            .about-content,
            .mission-vision,
            .why-choose,
            .founder-section,
            .gallery-section {
                padding: 50px 0;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .section-title {
                margin-bottom: 40px;
            }
            
            .mv-card,
            .feature-card {
                padding: 25px 20px;
            }
        }
        
        @media (max-width: 576px) {
            .about-hero,
            .about-content,
            .mission-vision,
            .why-choose,
            .founder-section,
            .gallery-section {
                padding: 40px 0;
            }
            
            .section-title {
                margin-bottom: 30px;
            }
            
            .about-hero h1 {
                font-size: 1.8rem;
            }
            
            .about-hero p {
                font-size: 1rem;
            }
            
            .gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                gap: 12px;
            }
            
            .founder-image img {
                max-width: 280px;
                height: 320px;
            }
            
            .mv-card,
            .feature-card {
                padding: 20px 15px;
            }
            
            .mv-icon,
            .feature-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
                margin-bottom: 20px;
            }
        }
        
        @media (max-width: 400px) {
            .gallery-grid {
                grid-template-columns: 1fr;
            }
            
            .about-hero h1 {
                font-size: 1.6rem;
            }
            
            .about-hero p {
                font-size: 0.9rem;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<section class="about-hero">
    <div class="container">
        <h1>About Perfect Institute of Languages</h1>
        <p>Building Confidence Through English Communication</p>
    </div>
</section>

<section class="founder-section">
    <div class="container">
        <div class="founder-wrapper">
            <div class="founder-image">
                <img src="/Archive/founder.jpg" alt="Keshav Verma, Founder" loading="lazy">
            </div>
            <div class="founder-content">
                <h2>Founder's Message</h2>
                <div class="founder-underline"></div>
                <p>Dear Learners and Visitors,</p>
                <p>When I started <strong>Perfect Institute</strong>, my vision was simple — to create a place where every learner receives the right guidance, right environment, and the right push to achieve greatness.</p>
                <p>Today, success requires more than just completing a syllabus. Proper Mentorship, discipline and clarity play an equally important role. With this belief, we have built a system where concepts are strengthened, our learners receive personal attention, and parents get transparent updates.</p>
                <p>For us, every learner is not just a number — but a dream and responsibility. When you join us, we take a commitment to shape not just your course, but your future.</p>
                <p><strong>Together, let's grow, let's achieve more.</strong></p>
                <h4 class="founder-name">– Keshav Verma, Perfect Institute</h4>
            </div>
        </div>
    </div>
</section>

<section class="mission-vision">
    <div class="container">
        <div class="section-title">
            <h2>Our Mission & Vision</h2>
            <p>Guiding principles that define our educational approach</p>
        </div>
        
        <div class="mv-grid">
            <div class="mv-card">
                <div class="mv-icon">
                    <i class="bi bi-bullseye"></i>
                </div>
                <h3>Our Mission</h3>
                <p>To provide high-quality English language education that empowers individuals to communicate effectively, think critically, and succeed in their personal and professional lives. We aim to create a supportive learning environment that fosters confidence, cultural awareness, and lifelong learning skills.</p>
            </div>
            
            <div class="mv-card">
                <div class="mv-icon">
                    <i class="bi bi-eye"></i>
                </div>
                <h3>Our Vision</h3>
                <p>To be the leading English language institute recognized for excellence in communication training, innovative teaching methodologies, and transformative student outcomes. We envision a world where language barriers are eliminated and individuals can confidently express themselves in English across all spheres of life.</p>
            </div>
        </div>
    </div>
</section>

<section class="why-choose">
    <div class="container">
        <div class="section-title">
            <h2>Why Choose Perfect Institute</h2>
            <p>Discover what makes us the preferred choice for English language learning</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <h3>Experienced Trainers</h3>
                <p>Our trainers have wide experience of teaching and are experts in English language instruction with international standards.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-mic-fill"></i>
                </div>
                <h3>Practical English Training</h3>
                <p>We focus on practical communication skills with real-world scenarios, role-plays, and interactive speaking activities.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-laptop-fill"></i>
                </div>
                <h3>Interactive Classes</h3>
                <p>Modern classrooms with digital learning tools, multimedia resources, and engaging teaching methods for effective learning.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <h3>Affordable Fees</h3>
                <p>Quality education at reasonable prices and huge discount on rejoining of any course for practice.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <h3>Personalized Attention</h3>
                <p>Small batch sizes with individual attention, regular progress tracking, and customized learning plans for each learner.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-award-fill"></i>
                </div>
                <h3>Included Study Material</h3>
                <p>Carefully designed study material to support classroom learning - helps students revise easily and continue learning even after class.</p>
            </div>
        </div>
    </div>
</section>

<!-- GALLERY SECTION – all images 16:9 landscape -->
<section class="gallery-section">
    <div class="container">
        <h2 class="gallery-title">Our Gallery</h2>
        <div class="founder-underline"></div>
        <p class="gallery-subtitle">A glimpse of our classrooms, events, achievements and student activities.</p>
        
        <div class="gallery-grid">
            <!-- All gallery items – update image paths as needed -->
            <div class="gallery-item">
                <div class="img-loader"></div>
                <img src="/Archive/g1.webp" alt="Gallery Image 1" loading="lazy">
            </div>
            <div class="gallery-item">
                <div class="img-loader"></div>
                <img src="/Archive/g2.webp" alt="Gallery Image 2" loading="lazy">
            </div>
            <div class="gallery-item">
                <div class="img-loader"></div>
                <img src="/Archive/g3.webp" alt="Gallery Image 3" loading="lazy">
            </div>
            <div class="gallery-item tall">
                <div class="img-loader"></div>
                <img src="/Archive/g4.webp" alt="Gallery Image 4" loading="lazy">
            </div>
            <div class="gallery-item">
                <div class="img-loader"></div>
                <img src="/Archive/g5.webp" alt="Gallery Image 5" loading="lazy">
            </div>
            <div class="gallery-item">
                <div class="img-loader"></div>
                <img src="/Archive/g6.webp" alt="Gallery Image 6" loading="lazy">
            </div>
            <div class="gallery-item tall">
                <div class="img-loader"></div>
                <img src="/Archive/g7.webp" alt="Gallery Image 7" loading="lazy">
            </div>
            <div class="gallery-item">
                <div class="img-loader"></div>
                <img src="/Archive/g8.webp" alt="Gallery Image 8" loading="lazy">
            </div>
            <div class="gallery-item">
                <div class="img-loader"></div>
                <img src="/Archive/g9.webp" alt="Gallery Image 9" loading="lazy">
            </div>
            <div class="gallery-item">
                <div class="img-loader"></div>
                <img src="/Archive/g10.webp" alt="Gallery Image 10" loading="lazy">
            </div>
            <div class="gallery-item">
                <div class="img-loader"></div>
                <img src="/Archive/g11.webp" alt="Gallery Image 11" loading="lazy">
            </div>
            <div class="gallery-item">
                <div class="img-loader"></div>
                <img src="/Archive/g12.webp" alt="Gallery Image 12" loading="lazy">
            </div>
        </div>
    </div>
</section>

<script>
// Gallery image loading handler
document.addEventListener('DOMContentLoaded', function() {
    const galleryImages = document.querySelectorAll('.gallery-item img');
    
    galleryImages.forEach(img => {
        if (img.complete) {
            img.classList.add('loaded');
            const loader = img.previousElementSibling;
            if (loader && loader.classList.contains('img-loader')) {
                loader.style.display = 'none';
            }
        } else {
            img.addEventListener('load', function() {
                this.classList.add('loaded');
                const loader = this.previousElementSibling;
                if (loader && loader.classList.contains('img-loader')) {
                    loader.style.display = 'none';
                }
            });
            
            img.addEventListener('error', function() {
                const loader = this.previousElementSibling;
                if (loader && loader.classList.contains('img-loader')) {
                    loader.style.display = 'none';
                }
                this.style.display = 'none';
            });
        }
    });
    
    // Smooth scroll to top on page load (optional)
    window.addEventListener('load', () => {
        setTimeout(() => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }, 100);
    });
});
</script>

</body>
</html>
<?php include __DIR__ . '/../includes/footer.php'; ?>