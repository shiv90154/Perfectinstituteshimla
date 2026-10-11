<?php 
$title = "Page Not Found - Perfect English Institute";

?>

<style>
    /* 404 Page Styles */
    .error-page {
        min-height: 80vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        position: relative;
        overflow: hidden;
    }
    
    .error-container {
        text-align: center;
        max-width: 600px;
        padding: 40px;
        position: relative;
        z-index: 2;
    }
    
    .error-graphic {
        position: relative;
        margin-bottom: 40px;
    }
    
    .error-number {
        font-size: 12rem;
        font-weight: 900;
        background: linear-gradient(135deg, #0a192f 0%, #e63946 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        line-height: 1;
        margin-bottom: 20px;
        position: relative;
        display: inline-block;
    }
    
    .error-number::after {
        content: '';
        position: absolute;
        width: 100px;
        height: 100px;
        background: radial-gradient(circle, rgba(230, 57, 70, 0.1) 0%, transparent 70%);
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        border-radius: 50%;
        z-index: -1;
    }
    
    .error-icon {
        position: absolute;
        font-size: 4rem;
        color: rgba(230, 57, 70, 0.3);
        animation: float 3s ease-in-out infinite;
    }
    
    .error-icon:nth-child(1) {
        top: 20px;
        left: 10%;
        animation-delay: 0s;
    }
    
    .error-icon:nth-child(2) {
        top: 40px;
        right: 15%;
        animation-delay: 0.5s;
    }
    
    .error-icon:nth-child(3) {
        bottom: 30px;
        left: 20%;
        animation-delay: 1s;
    }
    
    .error-icon:nth-child(4) {
        bottom: 20px;
        right: 10%;
        animation-delay: 1.5s;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-15px); }
    }
    
    .error-title {
        font-size: 2.5rem;
        color: #0a192f;
        margin-bottom: 20px;
        font-weight: 700;
    }
    
    .error-message {
        color: #6c757d;
        font-size: 1.1rem;
        line-height: 1.6;
        margin-bottom: 30px;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .error-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
        margin-bottom: 40px;
    }
    
    .btn-home {
        background: #0a192f;
        color: white;
        padding: 12px 30px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
        border: 2px solid #0a192f;
    }
    
    .btn-home:hover {
        background: #172a45;
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(10, 25, 47, 0.1);
    }
    
    .btn-courses {
        background: #e63946;
        color: white;
        padding: 12px 30px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
        border: 2px solid #e63946;
    }
    
    .btn-courses:hover {
        background: #d32f2f;
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(230, 57, 70, 0.1);
    }
    
    .btn-contact {
        background: transparent;
        color: #0a192f;
        padding: 12px 30px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
        border: 2px solid #0a192f;
    }
    
    .btn-contact:hover {
        background: #0a192f;
        color: white;
        transform: translateY(-2px);
    }
    
    .search-container {
        max-width: 500px;
        margin: 0 auto 30px;
        position: relative;
    }
    
    .search-input {
        width: 100%;
        padding: 15px 20px;
        padding-right: 50px;
        border: 2px solid #e1e5eb;
        border-radius: 10px;
        font-size: 1rem;
        transition: all 0.3s;
    }
    
    .search-input:focus {
        outline: none;
        border-color: #0a192f;
        box-shadow: 0 0 0 3px rgba(10, 25, 47, 0.1);
    }
    
    .search-button {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: #0a192f;
        cursor: pointer;
        font-size: 1.2rem;
        padding: 5px;
    }
    
    .error-footer {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 30px;
        margin-top: 50px;
        padding-top: 40px;
        border-top: 1px solid #e9ecef;
    }
    
    .error-footer-item {
        text-align: center;
        padding: 20px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: transform 0.3s;
    }
    
    .error-footer-item:hover {
        transform: translateY(-5px);
    }
    
    .error-footer-item i {
        font-size: 2.5rem;
        color: #e63946;
        margin-bottom: 15px;
    }
    
    .error-footer-item h4 {
        color: #0a192f;
        margin-bottom: 10px;
        font-size: 1.2rem;
    }
    
    .error-footer-item p {
        color: #6c757d;
        font-size: 0.9rem;
        margin-bottom: 15px;
    }
    
    .error-footer-item a {
        color: #0a192f;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .error-footer-item a:hover {
        color: #e63946;
    }
    
    /* Decorative Elements */
    .error-decoration {
        position: absolute;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(230, 57, 70, 0.05) 0%, transparent 70%);
        border-radius: 50%;
        z-index: 1;
    }
    
    .error-decoration:nth-child(1) {
        top: -150px;
        left: -150px;
    }
    
    .error-decoration:nth-child(2) {
        bottom: -150px;
        right: -150px;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .error-number {
            font-size: 8rem;
        }
        
        .error-title {
            font-size: 2rem;
        }
        
        .error-actions {
            flex-direction: column;
            align-items: center;
        }
        
        .btn-home, .btn-courses, .btn-contact {
            width: 100%;
            max-width: 300px;
            justify-content: center;
        }
        
        .error-footer {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 480px) {
        .error-number {
            font-size: 6rem;
        }
        
        .error-title {
            font-size: 1.8rem;
        }
        
        .error-message {
            font-size: 1rem;
        }
    }
</style>

<div class="error-page">
    <!-- Decorative Background Elements -->
    <div class="error-decoration"></div>
    <div class="error-decoration"></div>
    
    <div class="error-container">
        <div class="error-graphic">
            <div class="error-number">404</div>
            
            <!-- Floating Icons -->
            <div class="error-icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="error-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="error-icon">
                <i class="fas fa-language"></i>
            </div>
            <div class="error-icon">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
        </div>
        
        <h1 class="error-title">Oops! Page Not Found</h1>
        
        <p class="error-message">
            It looks like the page you're looking for has wandered off into the world of grammar rules 
            and vocabulary lists. Don't worry, we'll help you find your way back!
        </p>
        
        <!-- Search Bar -->
        <div class="search-container">
            <input type="text" class="search-input" placeholder="Search for courses, pages, or information...">
            <button class="search-button">
                <i class="fas fa-search"></i>
            </button>
        </div>
        
        <!-- Action Buttons -->
        <div class="error-actions">
            <a href="/index" class="btn-home">
                <i class="fas fa-home"></i> Go to Homepage
            </a>
            <a href="/courses" class="btn-courses">
                <i class="fas fa-book-open"></i> Browse Courses
            </a>
            <a href="/contact" class="btn-contact">
                <i class="fas fa-headset"></i> Contact Support
            </a>
        </div>
        
        <!-- Helpful Links -->
        <div class="error-footer">
            <div class="error-footer-item">
                <i class="fas fa-compass"></i>
                <h4>Popular Pages</h4>
                <p>Find your way to our most visited pages</p>
                <a href="/courses">
                    Explore Courses <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
        
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.querySelector('.search-input');
    const searchButton = document.querySelector('.search-button');
    
    searchButton.addEventListener('click', function() {
        performSearch();
    });
    
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
    
    function performSearch() {
        const query = searchInput.value.trim();
        if (query) {
            // In a real application, you would redirect to search results page
            alert(`Searching for: ${query}\n\nIn a real application, this would redirect to search results.`);
            // window.location.href = `/search?q=${encodeURIComponent(query)}`;
        } else {
            searchInput.focus();
        }
    }
    
    // Add some fun animation to the 404 number
    const errorNumber = document.querySelector('.error-number');
    
    // Pulsing animation for the 404 number
    setInterval(() => {
        errorNumber.style.transform = 'scale(1.02)';
        setTimeout(() => {
            errorNumber.style.transform = 'scale(1)';
        }, 300);
    }, 2000);
    
    // Smooth scroll for contact button
    const contactBtn = document.querySelector('.btn-contact');
    contactBtn.addEventListener('click', function(e) {
        e.preventDefault();
        // In a real site, this would scroll to contact section
        alert('This would scroll to the contact section on the homepage.');
        // You can implement smooth scroll to contact section
        // window.location.href = '/index#contact';
    });
});
</script>

