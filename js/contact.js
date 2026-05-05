// VIB — Soumission AJAX du formulaire de contact
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('contact-form');
    const feedback = document.getElementById('form-feedback');
    const submit = document.getElementById('form-submit');
    const sujetInput = document.getElementById('sujet-input');

    // Pré-remplir le champ "sujet" depuis l'URL ?sujet=...
    const params = new URLSearchParams(window.location.search);
    const sujet = params.get('sujet');
    if (sujet && sujetInput) {
        sujetInput.value = sujet;
    }

    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        feedback.className = 'form__feedback';
        feedback.textContent = '';

        const data = new FormData(form);
        const originalText = submit.textContent;
        submit.disabled = true;
        submit.textContent = 'Envoi en cours…';

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: data,
                headers: { Accept: 'application/json' },
            });
            const json = await res.json().catch(() => ({}));

            if (res.ok && json.ok) {
                feedback.className = 'form__feedback is-success';
                feedback.textContent = 'Merci ! Votre message a bien été envoyé. Nous vous répondrons rapidement.';
                form.reset();
            } else {
                feedback.className = 'form__feedback is-error';
                feedback.textContent = json.error || 'Une erreur est survenue. Merci de réessayer.';
            }
        } catch (err) {
            feedback.className = 'form__feedback is-error';
            feedback.textContent = 'Impossible d\'envoyer le message. Vérifiez votre connexion.';
        } finally {
            submit.disabled = false;
            submit.textContent = originalText;
        }
    });
});
