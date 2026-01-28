/**
 * RPS Arena - Main App JavaScript
 */

(function() {
    'use strict';
    
    // Close modals when clicking outside (skip game-critical modals)
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            // Don't dismiss game-over or match-found modals
            if (e.target.id === 'game-over-modal' || e.target.id === 'match-found-modal') return;
            e.target.classList.add('hidden');
        }
    });

    // Close modals with Escape key (skip game-critical modals)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal:not(.hidden)').forEach(modal => {
                if (modal.id === 'game-over-modal' || modal.id === 'match-found-modal') return;
                modal.classList.add('hidden');
            });
        }
    });
    
    // Add loading state to forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.originalText = submitBtn.textContent;
                submitBtn.textContent = 'Loading...';
            }
        });
    });
    
    // Utility: Format numbers
    window.formatNumber = function(num) {
        return new Intl.NumberFormat().format(num);
    };
    
    // Utility: Time ago
    window.timeAgo = function(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    };
    
})();
