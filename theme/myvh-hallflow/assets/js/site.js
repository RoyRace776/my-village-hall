(() => {
  const toggle = document.querySelector('.site-menu-toggle');
  const nav = document.querySelector('.site-nav');

  if (!toggle || !nav) {
    return;
  }

  toggle.addEventListener('click', () => {
    const expanded = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    nav.classList.toggle('is-open');
  });
})();
