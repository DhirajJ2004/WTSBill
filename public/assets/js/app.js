/**
 * WTSBill ERP — Enterprise Application Client Script
 * Native JavaScript handling sidebar interactions, dropdowns, modal windows,
 * dynamic toasts, CSRF-protected AJAX requests, form safeguards, and tables.
 */

(function () {
    'use strict';

    // Top-level Enterprise Namespace
    const WTSBill = window.WTSBill || {};

    // 1. Toast Notification Manager
    const Toast = {
        container: null,

        init: function () {
            this.container = document.getElementById('toast-container');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toast-container';
                this.container.className = 'toast-container';
                document.body.appendChild(this.container);
            }
        },

        show: function (message, type = 'info', duration = 4000) {
            this.init();

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.setAttribute('role', 'alert');

            const text = document.createElement('div');
            text.className = 'toast-message';
            text.textContent = message;

            const closeBtn = document.createElement('button');
            closeBtn.className = 'toast-close';
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.innerHTML = '&times;';
            closeBtn.onclick = () => this.dismiss(toast);

            toast.appendChild(text);
            toast.appendChild(closeBtn);
            this.container.appendChild(toast);

            if (duration > 0) {
                setTimeout(() => this.dismiss(toast), duration);
            }
        },

        dismiss: function (toast) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 250);
        }
    };

    // 2. Modal Dialog Manager
    const Modal = {
        open: function (modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';

            // Auto-focus first input or button
            const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusable) focusable.focus();
        },

        close: function (modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }
    };

    // 3. CSRF-Protected Fetch Helper
    const Http = {
        fetchWithCsrf: function (url, options = {}) {
            options.headers = options.headers || {};
            
            // Extract CSRF token from meta tag
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) {
                options.headers['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content');
            }

            options.headers['X-Requested-With'] = 'XMLHttpRequest';
            if (!options.headers['Accept']) {
                options.headers['Accept'] = 'application/json';
            }

            return fetch(url, options);
        }
    };

    // 4. Sidebar Manager
    const Sidebar = {
        toggle: function (e) {
            if (typeof window.toggleSidebar === 'function') {
                window.toggleSidebar(e);
                return;
            }
            const sidebar = document.getElementById('erp-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            if (window.innerWidth <= 768) {
                if (sidebar) sidebar.classList.toggle('open');
                if (overlay) overlay.classList.toggle('active');
            } else {
                document.body.classList.toggle('sidebar-collapsed');
            }
        },
        close: function () {
            if (typeof window.closeSidebar === 'function') {
                window.closeSidebar();
                return;
            }
            const sidebar = document.getElementById('erp-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            if (sidebar) sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('active');
            document.body.classList.remove('sidebar-collapsed');
        },
        toggleNavGroup: function (groupId, forceState) {
            if (typeof window.toggleNavGroup === 'function') {
                window.toggleNavGroup(groupId, forceState);
            }
        }
    };

    // 5. Dropdown Manager
    const Dropdown = {
        toggle: function (menuId) {
            const menu = document.getElementById(menuId);
            if (menu) {
                menu.classList.toggle('show');
            }
        },
        closeAll: function () {
            document.querySelectorAll('.dropdown-menu.show').forEach(function (menu) {
                menu.classList.remove('show');
            });
        }
    };

    // 6. Form Safeguards & Input Helpers
    const Forms = {
        init: function () {
            // Prevent double submission
            document.querySelectorAll('form[data-disable-on-submit]').forEach(function (form) {
                form.addEventListener('submit', function () {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Processing...';
                    }
                });
            });

            // Auto uppercase GSTIN / PAN inputs
            document.querySelectorAll('input[data-uppercase]').forEach(function (input) {
                input.addEventListener('input', function () {
                    this.value = this.value.toUpperCase();
                });
            });
        }
    };

    // 7. Client Table Search Filter Helper
    const Tables = {
        filter: function (inputId, tableId) {
            const input = document.getElementById(inputId);
            const table = document.getElementById(tableId);
            if (!input || !table) return;

            input.addEventListener('keyup', function () {
                const term = this.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(function (row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });
        }
    };

    // 8. Native Lightweight Bar Chart Helper
    const Charts = {
        renderBars: function (containerId, data) {
            const container = document.getElementById(containerId);
            if (!container || !Array.isArray(data)) return;

            container.innerHTML = '';
            container.className = 'chart-container';

            const maxVal = Math.max(...data.map(d => d.value), 1);

            data.forEach(function (item) {
                const bar = document.createElement('div');
                bar.className = 'chart-bar';
                const pct = Math.round((item.value / maxVal) * 100);
                bar.style.height = `${pct}%`;
                bar.title = `${item.label}: ${item.value}`;
                container.appendChild(bar);
            });
        }
    };

    // Global Event Listeners & Shell Setup
    document.addEventListener('DOMContentLoaded', function () {
        // A. Sidebar Toggle (Mobile & Desktop)
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const overlay = document.getElementById('sidebar-overlay');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                Sidebar.toggle();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function () {
                Sidebar.close();
            });
        }

        // B. User Dropdown Menu
        const userBtn = document.getElementById('user-menu-btn');
        const userDropdown = document.getElementById('user-menu-dropdown');

        if (userBtn && userDropdown) {
            userBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });

            document.addEventListener('click', function (e) {
                if (!userDropdown.contains(e.target) && !userBtn.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            });
        }

        // C. Modal Backdrop & Dismiss Triggers
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop) {
                    backdrop.classList.remove('open');
                    document.body.style.overflow = '';
                }
            });
        });

        document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const targetId = btn.getAttribute('data-modal-close');
                if (targetId) {
                    Modal.close(targetId);
                } else {
                    const closestModal = btn.closest('.modal-backdrop');
                    if (closestModal) {
                        closestModal.classList.remove('open');
                        document.body.style.overflow = '';
                    }
                }
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-backdrop.open').forEach(function (modal) {
                    modal.classList.remove('open');
                });
                Dropdown.closeAll();
                document.body.style.overflow = '';
            }
        });

        const branchBtn = document.getElementById('branch-switch-btn');
        if (branchBtn) {
            branchBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                Dropdown.toggle('branch-switch-menu');
            });
        }

        // Initialize form helpers
        Forms.init();
    });

    // 9. Branch Switching Manager
    const Branch = {
        switch: function (branchId, branchName) {
            const btn = document.getElementById('branch-switch-btn');
            const nameEl = document.getElementById('header-branch-name');
            const menu = document.getElementById('branch-switch-menu');

            if (btn) {
                btn.classList.add('loading');
                btn.setAttribute('disabled', 'disabled');
            }

            Http.fetchWithCsrf('/branches/switch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ branch_id: branchId })
            })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, status: res.status, data: data };
                });
            })
            .then(function (result) {
                if (btn) {
                    btn.classList.remove('loading');
                    btn.removeAttribute('disabled');
                }

                if (result.ok && result.data.success) {
                    if (nameEl) {
                        nameEl.textContent = branchName || result.data.branch.name;
                    }
                    if (menu) {
                        menu.classList.remove('show');
                    }
                    Toast.show(result.data.message || 'Branch switched successfully.', 'success');
                    setTimeout(function () {
                        window.location.reload();
                    }, 400);
                } else {
                    Toast.show(result.data.message || 'Failed to switch branch.', 'danger');
                }
            })
            .catch(function (err) {
                if (btn) {
                    btn.classList.remove('loading');
                    btn.removeAttribute('disabled');
                }
                Toast.show('Network error while switching branch: ' + err.message, 'danger');
            });
        }
    };

    // Theme Controller (Light / Dark with local storage)
    const Theme = {
        init: function () {
            if (typeof window.updateThemeUI === 'function') {
                window.updateThemeUI();
                return;
            }
            const saved = localStorage.getItem('wtsbill_theme') || 'light';
            this.apply(saved);
        },
        apply: function (theme) {
            if (typeof window.updateThemeUI === 'function') {
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('wtsbill_theme', theme);
                window.updateThemeUI(theme);
                return;
            }
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('wtsbill_theme', theme);
            const sun = document.getElementById('theme-icon-sun');
            const moon = document.getElementById('theme-icon-moon');
            const label = document.getElementById('theme-toggle-label');
            if (sun && moon) {
                if (theme === 'dark') {
                    sun.style.display = 'inline-block';
                    moon.style.display = 'none';
                    if (label) label.textContent = 'Dark';
                } else {
                    sun.style.display = 'none';
                    moon.style.display = 'inline-block';
                    if (label) label.textContent = 'Light';
                }
            }
        },
        toggle: function (e) {
            if (typeof window.toggleTheme === 'function') {
                window.toggleTheme(e);
                return;
            }
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            this.apply(next);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        Theme.init();
    });

    // Expose APIs
    WTSBill.Toast = Toast;
    WTSBill.Modal = Modal;
    WTSBill.Http = Http;
    WTSBill.Sidebar = Sidebar;
    WTSBill.Dropdown = Dropdown;
    WTSBill.Forms = Forms;
    WTSBill.Tables = Tables;
    WTSBill.Charts = Charts;
    WTSBill.Branch = Branch;
    WTSBill.Theme = Theme;

    window.WTSBill = WTSBill;
    window.WTSTheme = Theme;
    window.Toast = Toast;
    window.Modal = Modal;
    window.fetchWithCsrf = Http.fetchWithCsrf;

})();

