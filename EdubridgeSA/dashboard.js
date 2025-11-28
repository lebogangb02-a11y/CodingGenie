// Theme Toggle
document.addEventListener('DOMContentLoaded', function() {
    // Theme Toggle
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        const savedTheme = localStorage.getItem('dashboard-theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            themeToggle.innerHTML = '☀️ Light Mode';
        }

        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            themeToggle.innerHTML = isDark ? '☀️ Light Mode' : '🌙 Dark Mode';
            localStorage.setItem('dashboard-theme', isDark ? 'dark' : 'light');
        });
    }

    // Notification Updates
    function updateNotifications() {
        fetch('get-notifications.php')
            .then(response => response.json())
            .then(data => {
                const badge = document.querySelector('.notifications-badge');
                if (badge && data.unread > 0) {
                    badge.textContent = data.unread;
                    badge.style.display = 'block';
                } else if (badge) {
                    badge.style.display = 'none';
                }
            })
            .catch(console.error);
    }

    // Update notifications every 5 minutes
    setInterval(updateNotifications, 300000);

    // Add loading animation to cards
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
    });

    // Animate cards on load
    setTimeout(() => {
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }, 100);

    // Mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }
});