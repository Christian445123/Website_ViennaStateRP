// ============================================================
//  ViennaStateRP – main.js
// ============================================================

// --- Navbar scroll effect ---
(function () {
  const navbar = document.getElementById('navbar');
  if (!navbar) return;
  const onScroll = () => {
    navbar.classList.toggle('scrolled', window.scrollY > 40);
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
})();

// --- Maintenance banner: pushes navbar + page content below it ---
(function () {
  const banner = document.querySelector('.maintenance-banner');
  const navbar = document.getElementById('navbar');
  if (!banner) return;
  const h = banner.offsetHeight;
  if (navbar) navbar.style.top = h + 'px';
  document.body.style.marginTop = h + 'px';
})();

// --- Hamburger menu ---
(function () {
  const btn   = document.getElementById('hamburger');
  const links = document.getElementById('navLinks');
  if (!btn || !links) return;
  btn.addEventListener('click', () => {
    links.classList.toggle('open');
    btn.setAttribute('aria-expanded', links.classList.contains('open'));
  });
  // Close on link click
  links.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => links.classList.remove('open'));
  });
})();

// --- Smooth active-link highlight ---
(function () {
  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('.nav-links a[href^="#"]');
  if (!sections.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        navLinks.forEach(a => {
          a.classList.toggle('active', a.getAttribute('href') === '#' + entry.target.id);
        });
      }
    });
  }, { threshold: 0.4 });

  sections.forEach(s => observer.observe(s));
})();

// --- Scroll reveal ---
(function () {
  const els = document.querySelectorAll(
    '.about-card, .feature-item, .rule-card, .dash-card, .profile-header'
  );
  els.forEach(el => el.classList.add('reveal'));

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        setTimeout(() => entry.target.classList.add('visible'), i * 60);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  els.forEach(el => observer.observe(el));
})();

// --- Counter animation ---
(function () {
  const counters = document.querySelectorAll('.stat-num[data-target]');
  if (!counters.length) return;

  const animateCounter = (el) => {
    const target = parseInt(el.dataset.target, 10);
    const duration = 1600;
    const step = target / (duration / 16);
    let current = 0;
    const tick = () => {
      current = Math.min(current + step, target);
      el.textContent = Math.floor(current);
      if (current < target) requestAnimationFrame(tick);
      else el.textContent = target + '+';
    };
    requestAnimationFrame(tick);
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        animateCounter(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });

  counters.forEach(c => observer.observe(c));
})();

// --- Particle background (hero) ---
(function () {
  const container = document.getElementById('particles');
  if (!container) return;

  const COUNT = 35;
  for (let i = 0; i < COUNT; i++) {
    const p = document.createElement('span');
    const size  = Math.random() * 3 + 1;
    const x     = Math.random() * 100;
    const delay = Math.random() * 8;
    const dur   = Math.random() * 12 + 8;
    const gold  = Math.random() > 0.5;

    Object.assign(p.style, {
      position:        'absolute',
      left:            x + '%',
      bottom:          '-10px',
      width:           size + 'px',
      height:          size + 'px',
      borderRadius:    '50%',
      background:      gold ? 'rgba(232,184,109,0.6)' : 'rgba(88,101,242,0.5)',
      animation:       `particleRise ${dur}s ${delay}s linear infinite`,
      pointerEvents:   'none',
    });
    container.appendChild(p);
  }

  // Inject keyframes once
  if (!document.getElementById('particle-style')) {
    const style = document.createElement('style');
    style.id = 'particle-style';
    style.textContent = `
      @keyframes particleRise {
        0%   { transform: translateY(0) scale(1); opacity: 0; }
        10%  { opacity: 1; }
        90%  { opacity: 0.4; }
        100% { transform: translateY(-100vh) scale(0.3); opacity: 0; }
      }
    `;
    document.head.appendChild(style);
  }
})();
