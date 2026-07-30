<?php
// index.php - Alpine Healthcare Landing Page
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alpine Healthcare - Quality Care, Alpine Standard</title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- ===== EXTERNAL CSS ===== -->
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <!-- Fallback inline styles if CSS fails to load -->
    <style>
        /* Fallback styles only - all main styles are in external CSS */
        body { margin: 0; padding: 0; }
        .hero-bg { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            z-index: 0; 
            background: #0a0a0a; 
        
        }
    </style>
</head>
<body>

<!-- ============================================
     BACKGROUND
     ============================================ -->
<div class="hero-bg"></div>

<!-- Floating Particles -->
<div class="particles" id="particles"></div>

<!-- ============================================
     NAVBAR
     ============================================ -->
<nav class="navbar" id="navbar">
    <a href="#" class="navbar-brand">
        <span class="logo-icon">🏔️</span>
        <span class="brand-text">
            <span class="name"><span>Alpine</span> Healthcare</span>
            <span class="tagline">Quality Care · Alpine Standard</span>
        </span>
        <span class="medical-cross">✚</span>
    </a>
    
    <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
    
    <div class="nav-links" id="navLinks">
        <a href="#features">Features</a>
        <a href="#about">About</a>
        <a href="#explore">Explore</a>
        <a href="#contact">Contact</a>
        <a href="login.php" class="nav-cta">
            <i class="fas fa-sign-in-alt"></i> Login
        </a>
    </div>
</nav>

<!-- ============================================
     HERO CONTENT
     ============================================ -->
<div class="content">
    <div class="hero">
        <div class="badge">
            <i class="fas fa-shield-alt"></i> Trusted Healthcare Platform
        </div>
        
        <h1>
            Quality Healthcare,<br>
            <span class="highlight">Alpine Standard</span>
        </h1>
        
        <p>
            Streamline your clinic operations with our comprehensive management system.
            From patient registration to billing, we've got you covered.
        </p>
        
        <div class="cta-group">
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> Get Started
            </a>
            <a href="#features" class="btn btn-secondary">
                <i class="fas fa-play-circle"></i> Explore Features
            </a>
        </div>
    </div>
    
    <!-- ============================================
         STATS
         ============================================ -->
    <div class="stats">
        <div class="stat-item">
            <span class="number" data-count="5000">0</span>
            <span class="label">Patients Served</span>
        </div>
        <div class="stat-item">
            <span class="number" data-count="2500">0</span>
            <span class="label">Consultations</span>
        </div>
        <div class="stat-item">
            <span class="number" data-count="98">0</span>
            <span class="label">Satisfaction Rate %</span>
        </div>
        <div class="stat-item">
            <span class="number" data-count="24">0</span>
            <span class="label">Staff Members</span>
        </div>
    </div>
    
    <!-- ============================================
         FEATURES SECTION
         ============================================ -->
    <section class="section" id="features">
        <div class="section-header">
            <h2>Our <span>Features</span></h2>
            <p>Everything you need to manage your clinic efficiently</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <span class="icon">📋</span>
                <h3>Patient Management</h3>
                <p>Register, search, and manage patient records with ease. Complete medical history at your fingertips.</p>
            </div>
            <div class="feature-card">
                <span class="icon">⏳</span>
                <h3>Queue Management</h3>
                <p>Automated queue system with real-time updates. Reduce waiting times and improve patient flow.</p>
            </div>
            <div class="feature-card">
                <span class="icon">💊</span>
                <h3>Prescription System</h3>
                <p>Digital prescriptions with tracking. Legible, secure, and integrated with patient records.</p>
            </div>
            <div class="feature-card">
                <span class="icon">💰</span>
                <h3>Automated Billing</h3>
                <p>Generate invoices instantly. Support for multiple payment methods including M-Pesa.</p>
            </div>
            <div class="feature-card">
                <span class="icon">📊</span>
                <h3>Reports & Analytics</h3>
                <p>Real-time insights into clinic performance. Data-driven decision making for better outcomes.</p>
            </div>
            <div class="feature-card">
                <span class="icon">🔒</span>
                <h3>Secure & Reliable</h3>
                <p>Role-based access control. Patient data protected with enterprise-grade security.</p>
            </div>
        </div>
    </section>
    
    <!-- ============================================
         ABOUT SECTION
         ============================================ -->
    <section class="section" id="about">
        <div class="section-header">
            <h2>About <span>Alpine Healthcare</span></h2>
            <p>Committed to providing quality healthcare management solutions</p>
        </div>
        
        <div class="about-grid">
            <div class="about-text">
                <h3>Your Trusted <span>Healthcare Partner</span></h3>
                <p>
                    Alpine Healthcare is a comprehensive clinic management system designed to 
                    streamline operations, improve patient care, and enhance the overall 
                    healthcare experience. Our platform integrates all aspects of clinic 
                    management into one seamless solution.
                </p>
                <p>
                    From patient registration and appointment scheduling to billing and 
                    reporting, we provide the tools you need to run your clinic efficiently 
                    and effectively.
                </p>
                
                <div class="about-stats">
                    <div class="about-stat">
                        <span class="number">4+</span>
                        <span class="label">Years Experience</span>
                    </div>
                    <div class="about-stat">
                        <span class="number">50+</span>
                        <span class="label">Clinic Partners</span>
                    </div>
                    <div class="about-stat">
                        <span class="number">100%</span>
                        <span class="label">Client Satisfaction</span>
                    </div>
                </div>
            </div>
            
            <div class="about-image">
                <div class="placeholder">
                    🏥
                    <span>Healthcare Excellence</span>
                </div>
            </div>
        </div>
    </section>
    
    <!-- ============================================
         EXPLORE SECTION
         ============================================ -->
    <section class="section" id="explore">
        <div class="explore-section">
            <h2>Explore <span>More</span></h2>
            <p>Discover all the ways Alpine Healthcare can transform your clinic</p>
            
            <div class="explore-grid">
                <div class="explore-item">
                    <span class="icon">👨‍⚕️</span>
                    <h4>Doctor Dashboard</h4>
                    <p>Manage consultations and patient records</p>
                </div>
                <div class="explore-item">
                    <span class="icon">📅</span>
                    <h4>Appointment Scheduling</h4>
                    <p>Book and manage appointments easily</p>
                </div>
                <div class="explore-item">
                    <span class="icon">💳</span>
                    <h4>Payment Processing</h4>
                    <p>Secure and fast payment handling</p>
                </div>
                <div class="explore-item">
                    <span class="icon">📈</span>
                    <h4>Performance Analytics</h4>
                    <p>Track clinic performance metrics</p>
                </div>
                <div class="explore-item">
                    <span class="icon">📱</span>
                    <h4>Mobile Ready</h4>
                    <p>Access from any device, anywhere</p>
                </div>
                <div class="explore-item">
                    <span class="icon">🛡️</span>
                    <h4>Data Security</h4>
                    <p>Enterprise-grade security protocols</p>
                </div>
            </div>
            
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-rocket"></i> Get Started Today
            </a>
        </div>
    </section>
    
    <!-- ============================================
         CONTACT SECTION
         ============================================ -->
    <section class="section" id="contact">
        <div class="section-header">
            <h2>Get In <span>Touch</span></h2>
            <p>Have questions? We're here to help</p>
        </div>
        
        <div style="text-align:center; padding: 20px 0;">
            <p style="color: rgba(255,255,255,0.5); margin-bottom: 20px;">
                <i class="fas fa-envelope" style="color: #2ecc71;"></i> 
                <a href="mailto:info@alpinehealthcare.com" style="color: rgba(255,255,255,0.7); text-decoration: none;">
                    info@alpinehealthcare.com
                </a>
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <i class="fas fa-phone" style="color: #2ecc71;"></i> 
                <a href="tel:+254700000000" style="color: rgba(255,255,255,0.7); text-decoration: none;">
                    +254 700 000000
                </a>
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <i class="fas fa-map-marker-alt" style="color: #2ecc71;"></i> 
                <span style="color: rgba(255,255,255,0.5);">Nairobi, Kenya</span>
            </p>
        </div>
    </section>
    
    <!-- ============================================
         FOOTER
         ============================================ -->
    <footer class="footer">
        <p>
            © <?php echo date('Y'); ?> <a href="#">Alpine Healthcare</a> — 
            Built with <span class="heart">❤️</span> for better healthcare
        </p>
        <p style="margin-top: 8px; font-size: 11px; color: rgba(255,255,255,0.15);">
            <a href="#features" style="color: rgba(255,255,255,0.2);">Features</a> &middot;
            <a href="#about" style="color: rgba(255,255,255,0.2);">About</a> &middot;
            <a href="#explore" style="color: rgba(255,255,255,0.2);">Explore</a> &middot;
            <a href="#contact" style="color: rgba(255,255,255,0.2);">Contact</a> &middot;
            <a href="login.php" style="color: rgba(255,255,255,0.2);">Login</a>
        </p>
    </footer>
</div>

<!-- ============================================
     JAVASCRIPT
     ============================================ -->
<script>
    // ============================================
    // 1. MOBILE MENU TOGGLE
    // ============================================
    const menuToggle = document.getElementById('menuToggle');
    const navLinks = document.getElementById('navLinks');
    
    menuToggle.addEventListener('click', function() {
        this.classList.toggle('active');
        navLinks.classList.toggle('open');
    });
    
    navLinks.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            menuToggle.classList.remove('active');
            navLinks.classList.remove('open');
        });
    });
    
    // ============================================
    // 2. NAVBAR SCROLL EFFECT
    // ============================================
    const navbar = document.getElementById('navbar');
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // ============================================
    // 3. SMOOTH SCROLL
    // ============================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
    
    // ============================================
    // 4. ANIMATED STAT COUNTER
    // ============================================
    const statNumbers = document.querySelectorAll('.stat-item .number');
    
    function animateCounter(element, target) {
        let current = 0;
        const increment = Math.ceil(target / 60);
        const duration = 2000;
        const stepTime = duration / 60;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = current.toLocaleString();
        }, stepTime);
    }
    
    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const target = parseInt(el.dataset.count);
                if (target && !el.dataset.animated) {
                    el.dataset.animated = 'true';
                    animateCounter(el, target);
                }
            }
        });
    }, { threshold: 0.5 });
    
    statNumbers.forEach(el => {
        statsObserver.observe(el);
    });
    
    // ============================================
    // 5. PARTICLES BACKGROUND
    // ============================================
    function createParticles() {
        const container = document.getElementById('particles');
        const count = 30;
        
        for (let i = 0; i < count; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.width = (Math.random() * 4 + 2) + 'px';
            particle.style.height = particle.style.width;
            particle.style.animationDuration = (Math.random() * 20 + 15) + 's';
            particle.style.animationDelay = (Math.random() * 15) + 's';
            particle.style.opacity = Math.random() * 0.5 + 0.2;
            container.appendChild(particle);
        }
    }
    createParticles();
    
    // ============================================
    // 6. KEYBOARD SHORTCUT: Ctrl+L to open login
    // ============================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'l' || e.key === 'L')) {
            e.preventDefault();
            window.location.href = 'login.php';
        }
    });
    
    // ============================================
    // 7. PAGE SHOW - Reset UI state
    // ============================================
    window.addEventListener('pageshow', function(event) {
        try {
            document.body.style.overflow = '';
            const menuToggleEl = document.getElementById('menuToggle');
            const navLinksEl = document.getElementById('navLinks');
            if (menuToggleEl) menuToggleEl.classList.remove('active');
            if (navLinksEl) navLinksEl.classList.remove('open');
        } catch (e) {
            // silently ignore
        }
    });
    
    // ============================================
    // 8. CONSOLE WELCOME
    // ============================================
    console.log('%c🏔️ Alpine Healthcare', 'font-size:24px; font-weight:bold; color:#2ecc71;');
    console.log('%cQuality Healthcare, Alpine Standard', 'font-size:14px; color:#888;');
    console.log('%c🔑 Press Ctrl+L to login', 'font-size:12px; color:#555;');
    console.log('%c📌 Features, About, and Explore sections are now live!', 'font-size:12px; color:#2ecc71;');
</script>

</body>
</html>