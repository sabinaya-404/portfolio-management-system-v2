const toggleBtn = document.querySelector('.password-toggle-button');
const passwordInput = document.querySelector('#password');
if (toggleBtn && passwordInput) {
    toggleBtn.addEventListener('click', function () {
        const isPassword = passwordInput.getAttribute('type') === 'password';
        passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
        toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        toggleBtn.classList.toggle('active');
    });
}

const form = document.querySelector('form');
if (form) {
    form.addEventListener('submit', function () {
        const btn = form.querySelector('button[type="submit"]');
        if (btn) {
            btn.classList.add('btn-loading');
            btn.disabled = true;
        }
    });
}