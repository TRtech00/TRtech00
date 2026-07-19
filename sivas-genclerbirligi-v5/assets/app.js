(() => {
  'use strict';

  const header = document.getElementById('siteHeader');
  const onScroll = () => header?.classList.toggle('scrolled', window.scrollY > 24);
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  const mobileToggle = document.querySelector('.mobile-toggle');
  const mainNav = document.querySelector('.main-nav');
  mobileToggle?.addEventListener('click', () => mainNav?.classList.toggle('open'));
  mainNav?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => mainNav.classList.remove('open')));

  const modal = document.getElementById('joinModal');
  const openModal = () => {
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  };
  const closeModal = () => {
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  };
  document.querySelectorAll('.join-open').forEach(button => button.addEventListener('click', openModal));
  document.querySelector('.join-close')?.addEventListener('click', closeModal);
  modal?.addEventListener('click', event => {
    if (event.target === modal) closeModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeModal();
  });

  const revealObserver = 'IntersectionObserver' in window
    ? new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            revealObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -40px' })
    : null;

  document.querySelectorAll('.reveal').forEach((element, index) => {
    element.style.transitionDelay = `${Math.min(index % 5, 4) * 65}ms`;
    if (revealObserver) revealObserver.observe(element);
    else element.classList.add('visible');
  });

  document.querySelectorAll('.player-slider[data-autoplay="true"]').forEach(slider => {
    let timer = null;
    const start = () => {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      timer = window.setInterval(() => {
        const card = slider.querySelector('.player-card');
        if (!card) return;
        const amount = card.getBoundingClientRect().width + 24;
        const atEnd = slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 20;
        slider.scrollTo({ left: atEnd ? 0 : slider.scrollLeft + amount, behavior: 'smooth' });
      }, 4200);
    };
    const stop = () => {
      if (timer) window.clearInterval(timer);
      timer = null;
    };
    slider.addEventListener('mouseenter', stop);
    slider.addEventListener('mouseleave', start);
    slider.addEventListener('touchstart', stop, { passive: true });
    start();
  });

  document.querySelectorAll('.masonry-gallery figure').forEach(figure => {
    figure.addEventListener('click', () => {
      const image = figure.querySelector('img');
      if (!image) return;
      const overlay = document.createElement('div');
      overlay.style.cssText = 'position:fixed;inset:0;z-index:120;background:rgba(0,0,0,.9);display:grid;place-items:center;padding:25px;cursor:zoom-out;backdrop-filter:blur(8px)';
      const clone = image.cloneNode();
      clone.style.cssText = 'max-width:min(1200px,95vw);max-height:90vh;object-fit:contain;border-radius:18px;box-shadow:0 30px 100px rgba(0,0,0,.5)';
      overlay.appendChild(clone);
      overlay.addEventListener('click', () => overlay.remove());
      document.body.appendChild(overlay);
    });
  });
})();
