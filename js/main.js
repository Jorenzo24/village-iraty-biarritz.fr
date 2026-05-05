document.addEventListener('DOMContentLoaded', () => {
    const yearEl = document.getElementById('year');
    if (yearEl) yearEl.textContent = new Date().getFullYear();

    const burger = document.getElementById('burger');
    const mobileNav = document.getElementById('mobile-nav');
    if (burger && mobileNav) {
        burger.addEventListener('click', () => {
            const isOpen = mobileNav.classList.toggle('is-open');
            burger.classList.toggle('is-open', isOpen);
            burger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('is-active'));
            tab.classList.add('is-active');
        });
    });
});
