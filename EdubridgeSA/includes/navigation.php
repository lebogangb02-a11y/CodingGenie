<?php
// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_page_name = pathinfo($current_page, PATHINFO_FILENAME);

// Function to check if current page matches
function isActivePage($page) {
    global $current_page_name;
    return $current_page_name === $page ? 'active' : '';
}
?>

<!-- Enhanced Navigation Styles -->
<style>
/* Navigation Variables */
:root {
    --nav-primary: #1a5fb4;
    --nav-primary-dark: #0f4a8f;
    --nav-secondary: #2e7d32;
    --nav-accent: #ff9800;
    --nav-light: #f5f7fa;
    --nav-dark: #1a237e;
    --nav-white: #ffffff;
    --nav-text: #333333;
    --nav-text-light: #666666;
    --nav-shadow: 0 2px 15px rgba(0,0,0,0.1);
    --nav-transition: all 0.3s ease;
    --nav-border-radius: 8px;
}

/* Header Styles */
.edu-header {
    background: var(--nav-white);
    box-shadow: var(--nav-shadow);
    position: sticky;
    top: 0;
    z-index: 1000;
    transition: var(--nav-transition);
}

.edu-header.scrolled {
    padding: 5px 0;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.header-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

/* Logo Styles */
.logo {
    display: flex;
    align-items: center;
    text-decoration: none;
    color: var(--nav-dark);
    font-weight: 700;
    font-size: 1.5rem;
    transition: var(--nav-transition);
    padding: 10px 0;
}

.logo:hover {
    transform: translateY(-2px);
}

.logo img {
    height: 50px;
    width: auto;
    margin-right: 12px;
    transition: var(--nav-transition);
}

.logo span {
    background: linear-gradient(135deg, var(--nav-primary), var(--nav-dark));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Navigation Links */
.nav-links {
    display: flex;
    align-items: center;
    gap: 8px;
    transition: var(--nav-transition);
}

.nav-links a {
    text-decoration: none;
    color: var(--nav-text);
    font-weight: 500;
    padding: 12px 16px;
    border-radius: var(--nav-border-radius);
    transition: var(--nav-transition);
    position: relative;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
}

.nav-links a:not(.btn-primary):not(.btn-outline-light):hover {
    color: var(--nav-primary);
    background: rgba(26, 95, 180, 0.05);
    transform: translateY(-1px);
}

.nav-links a.active {
    color: var(--nav-primary);
    background: rgba(26, 95, 180, 0.1);
    font-weight: 600;
}

.nav-links a.active:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 30px;
    height: 3px;
    background: var(--nav-primary);
    border-radius: 2px;
}

/* Button Styles in Navigation */
.nav-links .btn-primary {
    background: linear-gradient(135deg, var(--nav-accent), #e68900);
    color: var(--nav-white);
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    box-shadow: 0 4px 15px rgba(255, 152, 0, 0.3);
    transition: var(--nav-transition);
}

.nav-links .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 152, 0, 0.4);
    background: linear-gradient(135deg, #e68900, #cc7a00);
}

.nav-links .btn-primary-large {
    padding: 12px 24px;
    font-size: 0.95rem;
}

.nav-links .btn-outline-light {
    border: 2px solid var(--nav-primary);
    color: var(--nav-primary);
    background: transparent;
    padding: 8px 18px;
    border-radius: 25px;
    font-weight: 500;
    transition: var(--nav-transition);
}

.nav-links .btn-outline-light:hover {
    background: var(--nav-primary);
    color: var(--nav-white);
    transform: translateY(-1px);
}

/* User-specific navigation styles */
.nav-links a[href*="dashboard"] {
    background: rgba(46, 125, 50, 0.1);
    color: var(--nav-secondary);
    font-weight: 600;
}

.nav-links a[href*="dashboard"]:hover {
    background: rgba(46, 125, 50, 0.2);
    color: var(--nav-secondary);
}

.nav-links a[href*="logout"] {
    color: #dc3545;
    border-color: #dc3545;
}

.nav-links a[href*="logout"]:hover {
    background: #dc3545;
    color: var(--nav-white);
}

/* Mobile Menu Button */
.hamburger {
    display: none;
    background: none;
    border: none;
    color: var(--nav-primary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 10px;
    border-radius: var(--nav-border-radius);
    transition: var(--nav-transition);
    z-index: 1001;
}

.hamburger:hover {
    background: rgba(26, 95, 180, 0.1);
    transform: scale(1.1);
}

/* Mobile Styles */
@media (max-width: 1024px) {
    .hamburger {
        display: block;
    }
    
    .nav-links {
        position: fixed;
        top: 0;
        right: 0; /* anchor panel to right, then slide with transform */
        width: 300px;
        height: 100vh;
        background: var(--nav-white);
        flex-direction: column;
        align-items: flex-start;
        padding: 80px 30px 30px;
        box-shadow: -5px 0 25px rgba(0,0,0,0.1);
        transition: transform 350ms cubic-bezier(.4,0,.2,1);
        gap: 0;
        overflow-y: auto;
        transform: translateX(100%);
        will-change: transform;
        overscroll-behavior: contain;
        z-index: 1001;
    }
    
    .nav-links.mobile-active,
    .nav-links.active {
        transform: translateX(0);
    }
    
    .nav-links a {
        width: 100%;
        padding: 15px 20px;
        margin: 2px 0;
        border-radius: var(--nav-border-radius);
        justify-content: flex-start;
    }
    
    .nav-links a.active:after {
        left: 20px;
        transform: none;
        width: 4px;
        height: 100%;
        top: 0;
        bottom: 0;
    }

    /* Reduce aggressive active background on mobile */
    .nav-links a.active {
        background: transparent;
    }
    
    .nav-links .btn-primary,
    .nav-links .btn-outline-light {
        width: 100%;
        justify-content: center;
        margin: 10px 0;
    }
    
    .logo img {
        height: 40px;
    }
    
    .logo span {
        font-size: 1.3rem;
    }
}

@media (max-width: 768px) {
    .header-container {
        padding: 0 15px;
    }
    
    .nav-links {
        width: 280px;
    }
    
    .logo span {
        font-size: 1.2rem;
    }
}

@media (max-width: 480px) {
    .nav-links {
        width: 100%;
        padding: 70px 20px 20px;
    }
    
    .logo img {
        height: 35px;
    }
    
    .logo span {
        font-size: 1.1rem;
    }
}

/* Animation for mobile menu icon */
.hamburger i {
    transition: var(--nav-transition);
}

.hamburger:hover i {
    transform: rotate(90deg);
}

/* Backdrop for mobile menu */
.mobile-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.mobile-backdrop.active {
    display: block;
    opacity: 1;
}

/* Scroll progress indicator */
.scroll-progress {
    position: fixed;
    top: 0;
    left: 0;
    width: 0%;
    height: 3px;
    background: linear-gradient(90deg, var(--nav-primary), var(--nav-accent));
    z-index: 1001;
    transition: width 0.3s ease;
}

/* Notification badge */
.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--nav-accent);
    color: var(--nav-white);
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

/* Dropdown styles (for future enhancements) */
.nav-dropdown {
    position: relative;
}

.nav-dropdown-content {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: var(--nav-white);
    min-width: 200px;
    box-shadow: var(--nav-shadow);
    border-radius: var(--nav-border-radius);
    padding: 10px 0;
    z-index: 1000;
}

.nav-dropdown:hover .nav-dropdown-content {
    display: block;
}

.nav-dropdown-content a {
    padding: 12px 20px;
    display: block;
    color: var(--nav-text);
    text-decoration: none;
    transition: var(--nav-transition);
}

.nav-dropdown-content a:hover {
    background: rgba(26, 95, 180, 0.05);
    color: var(--nav-primary);
}
</style>

<!-- Navigation Structure -->
<header class="edu-header">
    <div class="scroll-progress" id="scrollProgress"></div>
    <div class="header-container">
        <a href="index.php" class="logo">
            <img src="images/logo.png.jpg" alt="EduBridgeSA Logo" onerror="this.src='https://placehold.co/100x60/1a5fb4/ffffff?text=EB'">
            <span>EduBridgeSA</span>
        </a>
        
        <button class="hamburger" id="mobile-menu" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="nav-links" id="nav-links">
            <a href="index.php" class="<?php echo isActivePage('index'); ?>">
                <i class="fas fa-home"></i>Home
            </a>
            <a href="about.php" class="<?php echo isActivePage('about'); ?>">
                <i class="fas fa-info-circle"></i>About
            </a>
            <a href="news.php" class="<?php echo isActivePage('news'); ?>">
                <i class="fas fa-newspaper"></i>News & Announcements
            </a>
            <a href="resources.php" class="<?php echo isActivePage('resources'); ?>">
                <i class="fas fa-book"></i>Resources
            </a>
            
            <!-- Apply Now Button -->
            <a href="application-access.php" class="btn-primary hero-btn btn-primary-large">
                <i class="fas fa-paper-plane"></i>Apply Now
            </a>

            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Logged in user navigation -->
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student'): ?>
                    <a href="student-dashboard.php" class="<?php echo isActivePage('student-dashboard'); ?>">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                        <?php if (isset($_SESSION['notification_count']) && $_SESSION['notification_count'] > 0): ?>
                            <span class="notification-badge"><?php echo $_SESSION['notification_count']; ?></span>
                        <?php endif; ?>
                    </a>
                <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
                    <a href="admin/dashboard.php" class="<?php echo isActivePage('dashboard'); ?>">
                        <i class="fas fa-cog"></i>Admin Dashboard
                    </a>
                <?php endif; ?>
                <a href="auth.php?action=logout" class="btn-outline-light">
                    <i class="fas fa-sign-out-alt"></i>Logout
                </a>
            <?php else: ?>
                <!-- Guest navigation -->
                <a href="student-login.php" class="btn-outline-light">
                    <i class="fas fa-sign-in-alt"></i>Login
                </a>
                <a href="create-profile.php" class="btn-primary">
                    <i class="fas fa-user-plus"></i>Sign Up
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Mobile backdrop -->
    <div class="mobile-backdrop" id="mobileBackdrop"></div>
</header>

<!-- Enhanced JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenu = document.getElementById('mobile-menu');
    const navLinks = document.getElementById('nav-links');
    const mobileBackdrop = document.getElementById('mobileBackdrop');
    const scrollProgress = document.getElementById('scrollProgress');
    const header = document.querySelector('.edu-header');
    
    // Mobile menu functionality
    if (mobileMenu && navLinks) {
        mobileMenu.addEventListener('click', function(e) {
            e.stopPropagation();
            navLinks.classList.toggle('mobile-active');
            mobileBackdrop.classList.toggle('active');
            
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            }
            
            // Toggle body scroll
            document.body.style.overflow = navLinks.classList.contains('mobile-active') ? 'hidden' : '';
            // Update accessibility state
            const isOpen = navLinks.classList.contains('mobile-active');
            mobileMenu.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            // Focus the first link when opening
            if (isOpen) {
                const firstLink = navLinks.querySelector('a');
                if (firstLink) firstLink.focus();
            }
        });
        
        // Close menu when clicking on backdrop
        mobileBackdrop.addEventListener('click', function() {
            navLinks.classList.remove('mobile-active');
            mobileBackdrop.classList.remove('active');
            document.body.style.overflow = '';
            
            const icon = mobileMenu.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
            mobileMenu.setAttribute('aria-expanded', 'false');
        });
        
        // Close menu when clicking on a link (mobile)
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) {
                    navLinks.classList.remove('mobile-active');
                    mobileBackdrop.classList.remove('active');
                    document.body.style.overflow = '';
                    
                    const icon = mobileMenu.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                    mobileMenu.setAttribute('aria-expanded', 'false');
                }
            });
        });
    }
    
    // Scroll progress indicator
    window.addEventListener('scroll', function() {
        const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
        const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const scrolled = (winScroll / height) * 100;
        
        if (scrollProgress) {
            scrollProgress.style.width = scrolled + '%';
        }
        
        // Header scroll effect
        if (header) {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            navLinks.classList.remove('mobile-active');
            mobileBackdrop.classList.remove('active');
            document.body.style.overflow = '';
            
            const icon = mobileMenu.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
            mobileMenu.setAttribute('aria-expanded', 'false');
        }
    });
    
    // Add smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Keyboard navigation support
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && navLinks.classList.contains('mobile-active')) {
            navLinks.classList.remove('mobile-active');
            mobileBackdrop.classList.remove('active');
            document.body.style.overflow = '';
            
            const icon = mobileMenu.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
            mobileMenu.setAttribute('aria-expanded', 'false');
        }
    });
});
</script>
<script src="assets/ui.js"></script>