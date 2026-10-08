/* ==========================================================================
   WTSBill ERP - Comprehensive Vanilla JS Framework
   Handles Theme Switcher, Mobile Sidebar Drawer, Toast System, Modals,
   Table Search & Filtering, GST Calculation Engine, & CSV Exporters
   ========================================================================== */

(function () {
  'use strict';

  // --------------------------------------------------------------------------
  // 1. THEME MANAGER (LIGHT / DARK MODE PERSISTENCE)
  // --------------------------------------------------------------------------
  const ThemeManager = {
    STORAGE_KEY: 'wts_theme',

    init: function () {
      const savedTheme = localStorage.getItem(this.STORAGE_KEY);
      const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      const theme = savedTheme || (systemDark ? 'dark' : 'light');
      this.applyTheme(theme, false);
    },

    applyTheme: function (theme, showToastNotification = true) {
      document.documentElement.setAttribute('data-theme', theme);
      localStorage.setItem(this.STORAGE_KEY, theme);

      // Update toggle button icon if present
      const toggleBtns = document.querySelectorAll('.theme-toggle-btn');
      toggleBtns.forEach(btn => {
        const icon = btn.querySelector('i');
        if (icon) {
          if (theme === 'dark') {
            icon.className = 'fa-solid fa-sun';
            btn.setAttribute('title', 'Switch to Light Mode');
          } else {
            icon.className = 'fa-solid fa-moon';
            btn.setAttribute('title', 'Switch to Dark Mode');
          }
        }
      });

      if (showToastNotification && window.showToast) {
        const modeLabel = theme === 'dark' ? 'Dark Mode Activated' : 'Light Mode Activated';
        window.showToast(modeLabel, 'info', null, 2000);
      }
    },

    toggle: function () {
      const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      this.applyTheme(newTheme, true);
    }
  };

  window.toggleTheme = function () {
    ThemeManager.toggle();
  };

  // --------------------------------------------------------------------------
  // 2. RESPONSIVE SIDEBAR & MOBILE DRAWER
  // --------------------------------------------------------------------------
  window.toggleSidebar = function () {
    const sidebar = document.querySelector('.sidebar');
    let overlay = document.querySelector('.sidebar-mobile-overlay');

    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'sidebar-mobile-overlay';
      document.body.appendChild(overlay);

      overlay.addEventListener('click', function () {
        window.closeSidebar();
      });
    }

    if (sidebar) {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('active', sidebar.classList.contains('open'));
    }
  };

  window.closeSidebar = function () {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-mobile-overlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('active');
  };

  // Highlight active menu link automatically
  function setupActiveNav() {
    const currentPath = window.location.pathname.toLowerCase();
    const menuLinks = document.querySelectorAll('.sidebar-menu .menu-item');

    menuLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (href && (href === currentPath || (href !== '/' && currentPath.startsWith(href)))) {
        link.classList.add('active');
      }
    });
  }

  // --------------------------------------------------------------------------
  // 3. TOAST NOTIFICATION SYSTEM
  // --------------------------------------------------------------------------
  window.showToast = function (message, type = 'info', title = null, duration = 4000) {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    let iconClass = 'fa-circle-info';
    if (type === 'success') iconClass = 'fa-circle-check';
    else if (type === 'warning') iconClass = 'fa-triangle-exclamation';
    else if (type === 'danger') iconClass = 'fa-circle-xmark';

    let contentHtml = `<i class="fa-solid ${iconClass}"></i><div>`;
    if (title) {
      contentHtml += `<div style="font-weight: 800; font-size: 13px; margin-bottom: 2px;">${title}</div>`;
    }
    contentHtml += `<div>${message}</div></div>`;

    // Progress bar element
    contentHtml += `<div class="toast-progress" style="animation-duration: ${duration}ms;"></div>`;

    toast.innerHTML = contentHtml;
    container.appendChild(toast);

    // Auto dismiss
    const timer = setTimeout(() => {
      dismissToast(toast);
    }, duration);

    toast.addEventListener('click', function () {
      clearTimeout(timer);
      dismissToast(toast);
    });
  };

  function dismissToast(toast) {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(100%)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 300);
  }

  // --------------------------------------------------------------------------
  // 4. MODAL SYSTEM & BACKDROP HANDLER
  // --------------------------------------------------------------------------
  window.openModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';

      // Focus first input inside modal
      const firstInput = modal.querySelector('input, select, textarea');
      if (firstInput) setTimeout(() => firstInput.focus(), 100);
    }
  };

  window.closeModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('active');
      document.body.style.overflow = '';
    }
  };

  // Close overlays on click outside
  document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
      e.target.classList.remove('active');
      document.body.style.overflow = '';
    }
  });

  // --------------------------------------------------------------------------
  // 5. LIVE TABLE SEARCH & FILTER ENGINE
  // --------------------------------------------------------------------------
  window.initTableSearch = function (inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('keyup', function () {
      const query = input.value.toLowerCase().trim();
      const rows = table.querySelectorAll('tbody tr:not(.no-records-row)');
      let visibleCount = 0;

      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (!query || text.includes(query)) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      // Manage empty state row
      let noRow = table.querySelector('.no-records-row');
      if (visibleCount === 0) {
        if (!noRow) {
          const cols = table.querySelectorAll('thead th').length || 5;
          noRow = document.createElement('tr');
          noRow.className = 'no-records-row';
          noRow.innerHTML = `<td colspan="${cols}" class="text-center" style="padding: 32px; color: var(--text-muted); font-weight: 600;"><i class="fa-solid fa-folder-open" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>No matching records found</td>`;
          table.querySelector('tbody').appendChild(noRow);
        } else {
          noRow.style.display = '';
        }
      } else if (noRow) {
        noRow.style.display = 'none';
      }
    });
  };

  // --------------------------------------------------------------------------
  // 6. GST BILLING & CURRENCY CALCULATOR ENGINE
  // --------------------------------------------------------------------------
  window.formatINR = function (amount) {
    const val = parseFloat(amount) || 0;
    return '₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  window.calculateGST = function (subtotal, mode = 'intra', ratePct = 18) {
    const taxTotal = (parseFloat(subtotal) || 0) * (ratePct / 100);
    if (mode === 'intra') {
      return {
        cgst: taxTotal / 2,
        sgst: taxTotal / 2,
        igst: 0,
        totalTax: taxTotal,
        grandTotal: (parseFloat(subtotal) || 0) + taxTotal
      };
    } else {
      return {
        cgst: 0,
        sgst: 0,
        igst: taxTotal,
        totalTax: taxTotal,
        grandTotal: (parseFloat(subtotal) || 0) + taxTotal
      };
    }
  };

  // --------------------------------------------------------------------------
  // 7. EXPORT TABLE TO CSV HELPER
  // --------------------------------------------------------------------------
  window.exportTableToCSV = function (tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) {
      showToast('Table not found for export', 'warning');
      return;
    }

    const rows = table.querySelectorAll('tr');
    const csvLines = [];

    rows.forEach(row => {
      // Ignore hidden or empty state rows
      if (row.style.display === 'none' || row.classList.contains('no-records-row')) return;

      const cols = row.querySelectorAll('th, td');
      const rowData = [];
      cols.forEach(col => {
        // Clean text content
        let text = col.innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/"/g, '""').trim();
        rowData.push(`"${text}"`);
      });
      csvLines.push(rowData.join(','));
    });

    const csvContent = 'data:text/csv;charset=utf-8,' + csvLines.join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    showToast(`Exported table to ${filename}`, 'success');
  };

  // --------------------------------------------------------------------------
  // 8. GLOBAL EVENT LISTENERS & SHORTCUTS
  // --------------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', function () {
    ThemeManager.init();
    setupActiveNav();

    // ESC Key listener for modals & drawers
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => {
          m.classList.remove('active');
        });
        window.closeSidebar();
        document.body.style.overflow = '';
      }
    });

    // Global Nav Dropdown toggle
    window.toggleNavDropdown = function (id) {
      const el = document.getElementById(id);
      const isOpen = el && el.classList.contains('show');
      document.querySelectorAll('.nav-dropdown-menu').forEach(m => m.classList.remove('show'));
      if (!isOpen && el) {
        el.classList.add('show');
      }
    };

    // Click outside to close dropdowns
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.nav-pill-dropdown') && !e.target.closest('.create-action-dropdown')) {
        document.querySelectorAll('.nav-dropdown-menu').forEach(m => m.classList.remove('show'));
      }
    });

    // Global Ctrl + K search focus
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        const searchInput = document.getElementById('globalQuickSearch') || document.getElementById('salesSearchInput') || document.getElementById('inventorySearchInput');
        if (searchInput) {
          searchInput.focus();
          searchInput.select();
        }
      }
    });

    // Global Tab Switching Engine
    window.switchTab = function (tabContainerId, targetPaneId, evt) {
      evt = evt || window.event;
      if (evt && evt.preventDefault) evt.preventDefault();

      // Find tabs within the specified container or by class
      let container = document.getElementById(tabContainerId);
      const tabSelector = '.nav-tab, .subnav-tab, .tab-btn, .set-tab-btn';
      if (container) {
        container.querySelectorAll(tabSelector).forEach(t => t.classList.remove('active'));
      } else {
        document.querySelectorAll(tabSelector).forEach(t => t.classList.remove('active'));
      }

      // Mark the active tab
      let activeTabBtn = null;
      if (evt) {
        activeTabBtn = evt.currentTarget || (evt.target ? evt.target.closest(tabSelector) : null);
      }
      if (!activeTabBtn && targetPaneId) {
        activeTabBtn = document.querySelector(`[onclick*="${targetPaneId}"]`);
      }
      if (activeTabBtn) {
        activeTabBtn.classList.add('active');
      }

      // Hide all target pane siblings and show the active one
      const targetPane = document.getElementById(targetPaneId);
      if (targetPane) {
        const parent = targetPane.parentElement;
        if (parent) {
          parent.querySelectorAll('.tab-pane, .subnav-pane, .report-tab, .setting-tab').forEach(p => {
            p.classList.remove('active');
            p.style.display = 'none';
          });
        }
        targetPane.classList.add('active');
        targetPane.style.display = 'block';
      }
    };

    // --------------------------------------------------------------------------
    // 9. PERFORMANCE & INTERACTION HELPERS
    // --------------------------------------------------------------------------
    
    // Fast debouncer for search and filtering
    window.debounce = function (fn, waitMs = 150) {
      let timeout;
      return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(this, args), waitMs);
      };
    };

    // Button loading state manager (prevents double-clicks without freezing UI)
    window.setButtonLoading = function (btn, isLoading, loadingText = 'Processing...') {
      if (!btn) return;
      if (isLoading) {
        if (!btn.dataset.originalHtml) {
          btn.dataset.originalHtml = btn.innerHTML;
        }
        btn.disabled = true;
        btn.style.pointerEvents = 'none';
        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin" style="margin-right: 6px;"></i><span>${loadingText}</span>`;
      } else {
        if (btn.dataset.originalHtml) {
          btn.innerHTML = btn.dataset.originalHtml;
        }
        btn.disabled = false;
        btn.style.pointerEvents = '';
      }
    };

    // Auto-attach button loading to form submissions & clear instant cache
    document.addEventListener('submit', function (e) {
      const form = e.target;
      if (window.InstantNav) {
        window.InstantNav.clearCache();
      }
      const submitBtn = form.querySelector('button[type="submit"]:not([data-no-loading])');
      if (submitBtn && !form.dataset.submitting) {
        form.dataset.submitting = 'true';
        const customText = submitBtn.dataset.loadingText || (submitBtn.innerText.trim().startsWith('Save') ? 'Saving...' : 'Processing...');
        window.setButtonLoading(submitBtn, true, customText);
      }
    });

    // Auto-initialize tabs based on URL parameters (e.g. ?tab=...)
    window.initTabs = function () {
      const urlParams = new URLSearchParams(window.location.search);
      const tabParam = urlParams.get('tab');
      if (tabParam) {
        const matchingTab = document.querySelector(`[data-tab-target="${tabParam}"], [data-tab="${tabParam}"]`);
        if (matchingTab) {
          matchingTab.click();
        }
      }
    };

    window.initTabs();
  });

  // --------------------------------------------------------------------------
  // 10. INSTANT NAVIGATION & TAB SWITCHING ENGINE (TURBO ROUTER)
  // --------------------------------------------------------------------------
  const InstantNav = {
    cache: new Map(),
    progressBar: null,
    isNavigating: false,

    init: function () {
      this.createProgressBar();
      this.bindPrefetch();
      this.bindClicks();
      this.bindPopstate();
      // Pre-populate current page
      this.cache.set(window.location.href, { html: document.documentElement.outerHTML, timestamp: Date.now() });
    },

    createProgressBar: function () {
      let bar = document.getElementById('instant-progress-bar');
      if (!bar) {
        bar = document.createElement('div');
        bar.id = 'instant-progress-bar';
        document.body.appendChild(bar);
      }
      this.progressBar = bar;
    },

    startProgress: function () {
      if (this.progressBar) {
        this.progressBar.style.opacity = '1';
        this.progressBar.style.width = '45%';
      }
    },

    finishProgress: function () {
      if (this.progressBar) {
        this.progressBar.style.width = '100%';
        setTimeout(() => {
          if (this.progressBar) {
            this.progressBar.style.opacity = '0';
            setTimeout(() => {
              if (this.progressBar) this.progressBar.style.width = '0%';
            }, 150);
          }
        }, 100);
      }
    },

    isEligibleLink: function (anchor) {
      if (!anchor || anchor.tagName !== 'A') return false;
      const href = anchor.getAttribute('href');
      if (!href) return false;
      if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return false;
      if (anchor.target && anchor.target !== '_self') return false;
      if (anchor.hasAttribute('download')) return false;
      if (anchor.hasAttribute('data-no-instant') || anchor.hasAttribute('data-no-pjax')) return false;

      const lower = href.toLowerCase();
      if (lower.includes('logout') || lower.includes('switch-company') || lower.includes('switch-branch') || lower.includes('switch-fy') || lower.includes('print_')) {
        return false;
      }

      try {
        const targetUrl = new URL(anchor.href, window.location.href);
        return targetUrl.origin === window.location.origin;
      } catch (e) {
        return false;
      }
    },

    prefetch: async function (url) {
      if (this.cache.has(url)) return;
      try {
        const res = await fetch(url, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (res.ok) {
          const html = await res.text();
          this.cache.set(url, { html, timestamp: Date.now() });
        }
      } catch (e) {
        // Prefetch fails gracefully
      }
    },

    bindPrefetch: function () {
      const self = this;
      document.addEventListener('mouseover', function (e) {
        const a = e.target.closest('a');
        if (self.isEligibleLink(a)) {
          self.prefetch(a.href);
        }
      }, { passive: true });

      document.addEventListener('touchstart', function (e) {
        const a = e.target.closest('a');
        if (self.isEligibleLink(a)) {
          self.prefetch(a.href);
        }
      }, { passive: true });
    },

    bindClicks: function () {
      const self = this;
      document.addEventListener('click', function (e) {
        const a = e.target.closest('a');
        if (!self.isEligibleLink(a)) return;

        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0) return;

        e.preventDefault();
        self.navigateTo(a.href, true);
      });
    },

    bindPopstate: function () {
      const self = this;
      window.addEventListener('popstate', function () {
        self.navigateTo(window.location.href, false);
      });
    },

    navigateTo: async function (url, pushHistory = true) {
      if (this.isNavigating) return;
      this.isNavigating = true;
      this.startProgress();

      try {
        let html;
        if (this.cache.has(url)) {
          html = this.cache.get(url).html;
        } else {
          const res = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });
          if (!res.ok) {
            window.location.href = url;
            return;
          }
          html = await res.text();
          this.cache.set(url, { html, timestamp: Date.now() });
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        if (!doc.querySelector('.main-wrapper') && !doc.querySelector('.content-area')) {
          window.location.href = url;
          return;
        }

        // 1. Update Title
        if (doc.title) {
          document.title = doc.title;
        }

        // 2. Swap Content
        const currentWrapper = document.querySelector('.main-wrapper');
        const newWrapper = doc.querySelector('.main-wrapper');

        if (currentWrapper && newWrapper) {
          currentWrapper.replaceWith(newWrapper);
        } else {
          const currentContent = document.querySelector('.content-area');
          const newContent = doc.querySelector('.content-area');
          if (currentContent && newContent) {
            currentContent.replaceWith(newContent);
          }
        }

        // 3. Update History
        if (pushHistory) {
          window.history.pushState({ url: url }, doc.title || document.title, url);
        }

        // 4. Update Active Nav link in Sidebar
        const currentPath = window.location.pathname.toLowerCase();
        const menuLinks = document.querySelectorAll('.sidebar-menu .menu-item');
        menuLinks.forEach(link => {
          link.classList.remove('active');
          const href = link.getAttribute('href');
          if (href) {
            const linkPath = new URL(link.href, window.location.origin).pathname.toLowerCase();
            if (linkPath === currentPath) {
              link.classList.add('active');
            }
          }
        });

        // 5. Execute embedded script tags
        const scripts = (newWrapper || doc.body).querySelectorAll('script');
        scripts.forEach(script => {
          if (script.src) {
            if (!document.querySelector(`script[src="${script.src}"]`)) {
              const s = document.createElement('script');
              s.src = script.src;
              document.body.appendChild(s);
            }
          } else if (script.textContent.trim()) {
            try {
              const fn = new Function(script.textContent);
              fn();
            } catch (err) {
              // Ignore non-fatal script evaluation notices
            }
          }
        });

        // 6. Re-trigger init handlers
        window.dispatchEvent(new Event('DOMContentLoaded'));
        if (window.initTabs) window.initTabs();

        // 7. Scroll top & close mobile drawer
        window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
        window.closeSidebar();

      } catch (err) {
        window.location.href = url;
      } finally {
        this.finishProgress();
        this.isNavigating = false;
      }
    },

    clearCache: function () {
      this.cache.clear();
    }
  };

  window.InstantNav = InstantNav;

  // Initialize Instant Navigation on startup
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => InstantNav.init());
  } else {
    InstantNav.init();
  }
})();

