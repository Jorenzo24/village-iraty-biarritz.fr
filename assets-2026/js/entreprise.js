// VIB - Fiche acteur : la page est rendue par le serveur (entreprise.php).
// Seul le badge « Ouvert / Fermé » est posé ici, car il dépend de l'heure du visiteur.
document.addEventListener('DOMContentLoaded', () => {
    const statusEl = document.getElementById('entity-status');
    if (statusEl && statusEl.dataset.hours && window.OpeningStatus) {
        window.OpeningStatus.applyBadge(statusEl, statusEl.dataset.hours);
    }
});
