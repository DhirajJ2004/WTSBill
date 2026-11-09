/**
 * WTSBill ERP — Premium Green Login Client Logic
 * Handles interactive password visibility toggle, input validation feedback,
 * and submit button state management.
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // 1. Password Visibility Toggle (Reuse or define window.togglePasswordVisibility)
    if (typeof window.togglePasswordVisibility !== 'function') {
        window.togglePasswordVisibility = function (e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.getElementById('btn-toggle-password');
            if (!passwordInput) return;

            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');

            if (toggleBtn) {
                const eyeOpen = toggleBtn.querySelector('.icon-eye-open');
                const eyeClosed = toggleBtn.querySelector('.icon-eye-closed');

                if (eyeOpen && eyeClosed) {
                    eyeOpen.style.display = isPassword ? 'none' : 'block';
                    eyeClosed.style.display = isPassword ? 'block' : 'none';
                }
                toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                toggleBtn.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
            }
            try { passwordInput.focus(); } catch (err) {}
        };
    }

    const toggleBtn = document.getElementById('btn-toggle-password');
    if (toggleBtn && !toggleBtn.hasAttribute('onclick')) {
        toggleBtn.addEventListener('click', window.togglePasswordVisibility);
    }

    // 2. Form Submission & Double-Submit Prevention
    const loginForm = document.getElementById('login-form');
    const submitBtn = document.getElementById('btn-login-submit');

    if (loginForm && submitBtn) {
        loginForm.addEventListener('submit', function () {
            const emailInput = document.getElementById('email');
            const emailVal = emailInput ? emailInput.value.trim() : '';
            const passVal = passwordInput ? passwordInput.value : '';

            if (!emailVal || !passVal) {
                return; // Let native browser HTML5 validation fire
            }

            // Provide visual feedback with the palette colors
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.9';
            submitBtn.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 0.8s linear infinite;">
                    <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                    <path d="M12 2a10 10 0 0 1 10 10"></path>
                </svg>
                <span>Signing In...</span>
            `;
        });
    }

    // 3. Auto-focus management
    const emailInput = document.getElementById('email');
    if (emailInput && !emailInput.value) {
        emailInput.focus();
    } else if (passwordInput) {
        passwordInput.focus();
    }
});
