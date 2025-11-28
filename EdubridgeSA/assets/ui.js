// ui.js - global UI enhancements: scroll reveal and smooth transitions
document.addEventListener('DOMContentLoaded', () => {
  const candidates = document.querySelectorAll(
    '.section, .standard-section, .card, .standard-card, .feature-card, .service-box, .hero-content, .container'
  );

  candidates.forEach(el => {
    if (!el.classList.contains('reveal-on-scroll')) {
      el.classList.add('reveal-on-scroll');
    }
  });

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    },
    { root: null, rootMargin: '0px 0px -10% 0px', threshold: 0.1 }
  );

  document.querySelectorAll('.reveal-on-scroll').forEach(el => observer.observe(el));
});