// VIB - Fiche local : la page est rendue par le serveur (local.php).
// Seul le changement de la grande photo au clic sur une vignette se fait ici.
document.addEventListener('DOMContentLoaded', () => {
    const thumbsEl = document.getElementById('local-thumbs');
    const main = document.getElementById('local-main-img');
    if (!thumbsEl || !main) return;
    thumbsEl.querySelectorAll('.local-detail__thumb').forEach(thumb => {
        thumb.addEventListener('click', () => {
            main.src = thumb.dataset.src;
            thumbsEl.querySelectorAll('.local-detail__thumb').forEach(t => t.classList.toggle('is-active', t === thumb));
        });
    });
});
