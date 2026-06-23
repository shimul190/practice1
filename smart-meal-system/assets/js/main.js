/**
 * Smart Meal System — main.js
 * Lightweight helpers shared across pages.
 */

// Confirm before destructive actions (delete, cancel, reject)
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      const msg = el.getAttribute('data-confirm') || 'Are you sure?';
      if (!confirm(msg)) {
        e.preventDefault();
      }
    });
  });
});
