<?php
// includes/footer.php
// If this file is included from a page that already has config, we don't need to include again.
if (!isset($conn)) {
    require_once __DIR__ . '/config.php';
}

// Fetch active courses once and cache them in a static variable
// This prevents re‑running the query if the footer is included multiple times
// and also avoids the result pointer being exhausted.
static $cachedCourses = null;

if ($cachedCourses === null) {
    $cachedCourses = [];
    if ($conn && $conn->ping()) { // check connection is still alive
        $result = $conn->query("SELECT id, title, type FROM courses WHERE is_deleted = 0 ORDER BY id");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $cachedCourses[] = $row;
            }
        }
    }
}
$courses = $cachedCourses;
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<footer class="pi-footer">
    <div class="pi-footer-container">
        <div class="pi-footer-grid">

            <!-- ABOUT -->
            <div class="pi-footer-col">
                <div class="pi-footer-logo">PERFECT INSTITUTE</div>
                <p class="pi-footer-about">
                    Perfect Institute is committed to quality education, practical learning,
                    and career-focused training designed to help students achieve real success.
                </p>
                <div class="pi-footer-social">
                    <a href="https://www.facebook.com/perfectlanguages" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/perfect.institute.english" target="_blank" rel="noopener noreferrer"><i class="fab fa-instagram"></i></a>
                    <a href="https://youtube.com/@perfectinstituteenglish" target="_blank" rel="noopener noreferrer"><i class="fab fa-youtube"></i></a>
                    <a href="https://www.g.page/perfectinstitute" target="_blank" rel="noopener noreferrer"><i class="fas fa-map-marker-alt"></i></a>
                </div>
            </div>

            <!-- QUICK LINKS -->
            <div class="pi-footer-col">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="/">Home</a></li>
                    <li><a href="/courses">Courses</a></li>
                    <li><a href="/admissions">Admission</a></li>
                    <li><a href="/blog">StudyHub</a></li>
                    <li><a href="/youtube">Videos</a></li>
                    <li><a href="/contact">Contact</a></li>
                    <li><a href="/about">About</a></li>
                </ul>
            </div>

            <!-- COURSES (DYNAMIC) -->
            <div class="pi-footer-col">
                <h3>Our Courses</h3>
                <ul>
                    <?php foreach ($courses as $course): 
                        $display = htmlspecialchars($course['title'] . ' (' . ucfirst($course['type']) . ')');
                    ?>
                        <li><a href="/view_course?id=<?= urlencode($course['id']) ?>"><?= $display ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- CONTACT -->
            <div class="pi-footer-col">
                <h3>Contact Us</h3>
                <ul class="pi-contact">
                    <li>
                        <i class="fas fa-map-marker-alt"></i>
                        <span>2nd Floor, Shushant Bhawan,<br>Chhota Shimla, HP 171002</span>
                    </li>
                    <li>
                        <i class="fas fa-phone"></i>
                        <span><a href="tel:+919805962233">+91 98059 62233</a></span>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <span><a href="mailto:info@perfectinstitute.in">info@perfectinstitute.in</a></span>
                    </li>
                </ul>
            </div>

        </div> <!-- /pi-footer-grid -->

        <!-- ===== NEW LEGAL SECTION ===== -->
        <div class="pi-legal-section">
            <div class="pi-legal-links">
                <a href="/privacy-policy">Privacy Policy</a>
                 
    <a href="/terms-and-conditions">Terms & Conditions</a>
                <a href="/terms-of-service">Terms of Service</a>
                <a href="/disclaimer">Disclaimer</a>
                <a href="/sitemap">Sitemap</a>
          
                 <a href="/refund-policy">Refund Policy</a>
    <a href="/cancellation-policy">Cancellation Policy</a>
            </div>
        </div>

    </div> <!-- /pi-footer-container -->

    <!-- BOTTOM STRIP – अब सिर्फ कॉपीराइट (सेंटर) -->
    <div class="pi-footer-bottom">
        <div class="pi-footer-bottom-container">
            <div class="pi-footer-copy">
                &copy; <?= date('Y') ?> Perfect Institute. All Rights Reserved.
            </div>
        </div>
    </div>
</footer>

<style>
/* ===== FOOTER BASE ===== */
.pi-footer {
    background: linear-gradient(135deg, #0a192f, #0f2747);
    color: #fff;
    padding-top: 45px;
    border-top: 1px solid rgba(255,255,255,0.05);
}

.pi-footer-container {
    max-width: 1200px;
    margin: auto;
    padding: 0 20px;
}

.pi-footer-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 35px;
}

.pi-footer-logo {
    font-size: 1.45rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
}

.pi-footer-col h3 {
    font-size: 1.05rem;
    margin-bottom: 14px;
    position: relative;
}

.pi-footer-col h3::after {
    content: '';
    width: 28px;
    height: 2px;
    background: #e63946;
    position: absolute;
    bottom: -6px;
    left: 0;
}

.pi-footer-about {
    color: #b0b7c3;
    font-size: 0.9rem;
    line-height: 1.6;
}

.pi-footer-col ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.pi-footer-col ul li {
    margin-bottom: 8px;
}

.pi-footer-col a {
    color: #b0b7c3;
    text-decoration: none;
    font-size: 0.9rem;
    transition: 0.25s ease;
}

.pi-footer-col a:hover {
    color: #e63946;
    padding-left: 3px;
}

.pi-contact li {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    font-size: 0.9rem;
    color: #b0b7c3;
}

.pi-contact i {
    color: #e63946;
    margin-top: 3px;
}

.pi-footer-social {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}

.pi-footer-social a {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    transition: 0.3s ease;
    font-size: 0.9rem;
}

.pi-footer-social a:hover {
    background: #e63946;
    transform: translateY(-2px);
}

/* ===== LEGAL SECTION (नया) ===== */
.pi-legal-section {
    margin-top: 30px;
    padding: 18px 0 10px 0;
    border-top: 1px solid rgba(255,255,255,0.08);
    text-align: center;
}

.pi-legal-links {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 18px 28px;
}

.pi-legal-links a {
    color: #b0b7c3;
    font-size: 0.85rem;
    text-decoration: none;
    transition: 0.2s;
    letter-spacing: 0.3px;
}

.pi-legal-links a:hover {
    color: #e63946;
    text-decoration: underline;
}

/* ===== BOTTOM STRIP (सिर्फ कॉपीराइट, सेंटर) ===== */
.pi-footer-bottom {
    margin-top: 10px;
    background: rgba(0,0,0,0.25);
    padding: 14px 0;
    border-top: 1px solid rgba(255,255,255,0.05);
}

.pi-footer-bottom-container {
    max-width: 1200px;
    margin: auto;
    padding: 0 20px;
    text-align: center; /* center the copyright */
}

.pi-footer-copy {
    color: #b0b7c3;
    font-size: 0.85rem;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 992px) {
    .pi-footer-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 25px;
    }
}

@media (max-width: 576px) {
    .pi-footer {
        padding-top: 35px;
    }
    .pi-footer-grid {
        grid-template-columns: 1fr;
        gap: 22px;
    }
    .pi-footer-col h3 {
        font-size: 1rem;
    }
    .pi-footer-about {
        font-size: 0.88rem;
    }
    .pi-legal-links {
        gap: 12px 18px;
    }
    .pi-legal-links a {
        font-size: 0.8rem;
    }
    .pi-footer-bottom-container {
        text-align: center;
    }
}
</style>