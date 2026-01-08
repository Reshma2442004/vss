/**
 * Mobile-Specific JavaScript for VSS Hostel Management System
 * Handles touch interactions, mobile navigation, and responsive behaviors
 */

(function() {
    'use strict';

    // Mobile detection
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    const isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

    // Initialize mobile features when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initMobileFeatures();
        initTouchInteractions();
        initMobileNavigation();
        initMobileForms();
        initMobileModals();
        initMobileQRScanner();
        registerServiceWorker();
    });

    /**
     * Initialize mobile-specific features
     */
    function initMobileFeatures() {
        // Add mobile class to body
        if (isMobile) {
            document.body.classList.add('mobile-device');
        }
        if (isTouch) {
            document.body.classList.add('touch-device');
        }

        // Prevent zoom on input focus (iOS)
        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            const inputs = document.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.style.fontSize === '' || parseFloat(input.style.fontSize) < 16) {
                    input.style.fontSize = '16px';
                }
            });
        }

        // Handle orientation changes
        window.addEventListener('orientationchange', function() {
            setTimeout(function() {
                window.scrollTo(0, 0);
                adjustLayoutForOrientation();
            }, 100);
        });

        // Prevent pull-to-refresh on mobile
        document.body.addEventListener('touchstart', function(e) {
            if (e.touches.length === 1 && window.pageYOffset === 0) {
                e.preventDefault();
            }
        }, { passive: false });

        // Add viewport height CSS custom property
        updateViewportHeight();
        window.addEventListener('resize', updateViewportHeight);
    }

    /**
     * Initialize touch interactions
     */
    function initTouchInteractions() {
        // Add touch feedback to buttons and cards
        const touchElements = document.querySelectorAll('.btn, .dashboard-card, .stat-card, .modern-card');
        
        touchElements.forEach(element => {
            element.addEventListener('touchstart', function() {
                this.classList.add('touch-active');
            }, { passive: true });

            element.addEventListener('touchend', function() {
                setTimeout(() => {
                    this.classList.remove('touch-active');
                }, 150);
            }, { passive: true });

            element.addEventListener('touchcancel', function() {
                this.classList.remove('touch-active');
            }, { passive: true });
        });

        // Swipe gestures for cards
        let startX, startY, startTime;
        
        document.addEventListener('touchstart', function(e) {
            const touch = e.touches[0];
            startX = touch.clientX;
            startY = touch.clientY;
            startTime = Date.now();
        }, { passive: true });

        document.addEventListener('touchend', function(e) {
            if (!startX || !startY) return;

            const touch = e.changedTouches[0];
            const endX = touch.clientX;
            const endY = touch.clientY;
            const endTime = Date.now();

            const deltaX = endX - startX;
            const deltaY = endY - startY;
            const deltaTime = endTime - startTime;

            // Check for swipe gesture
            if (Math.abs(deltaX) > 50 && Math.abs(deltaY) < 100 && deltaTime < 300) {
                const direction = deltaX > 0 ? 'right' : 'left';
                handleSwipeGesture(direction, e.target);
            }

            startX = startY = null;
        }, { passive: true });
    }

    /**
     * Initialize mobile navigation
     */
    function initMobileNavigation() {
        // Create mobile menu toggle if navbar exists
        const navbar = document.querySelector('.navbar');
        if (navbar && !navbar.querySelector('.mobile-menu-toggle')) {
            const toggle = document.createElement('button');
            toggle.className = 'mobile-menu-toggle btn btn-outline-light d-md-none';
            toggle.innerHTML = '<i class="fas fa-bars"></i>';
            toggle.addEventListener('click', toggleMobileMenu);
            
            const navbarBrand = navbar.querySelector('.navbar-brand');
            if (navbarBrand) {
                navbarBrand.parentNode.insertBefore(toggle, navbarBrand.nextSibling);
            }
        }

        // Handle back button for mobile
        if (window.history && window.history.pushState) {
            window.addEventListener('popstate', function(e) {
                if (isMobile) {
                    // Handle mobile back navigation
                    const modal = document.querySelector('.modal.show');
                    if (modal) {
                        const modalInstance = bootstrap.Modal.getInstance(modal);
                        if (modalInstance) {
                            modalInstance.hide();
                            e.preventDefault();
                        }
                    }
                }
            });
        }
    }

    /**
     * Initialize mobile form enhancements
     */
    function initMobileForms() {
        // Auto-resize textareas
        const textareas = document.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });

        // Improve select dropdowns on mobile
        const selects = document.querySelectorAll('select');
        selects.forEach(select => {
            if (isMobile) {
                select.addEventListener('focus', function() {
                    this.size = Math.min(this.options.length, 5);
                });
                
                select.addEventListener('blur', function() {
                    this.size = 1;
                });
            }
        });

        // Handle form validation with mobile-friendly messages
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const invalidInputs = form.querySelectorAll(':invalid');
                if (invalidInputs.length > 0 && isMobile) {
                    e.preventDefault();
                    showMobileValidationMessage(invalidInputs[0]);
                }
            });
        });
    }

    /**
     * Initialize mobile modal enhancements
     */
    function initMobileModals() {
        // Enhance modals for mobile
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('show.bs.modal', function() {
                if (isMobile) {
                    document.body.style.overflow = 'hidden';
                    this.style.paddingRight = '0';
                }
            });

            modal.addEventListener('hidden.bs.modal', function() {
                if (isMobile) {
                    document.body.style.overflow = '';
                }
            });

            // Add swipe to close functionality
            let modalStartY;
            modal.addEventListener('touchstart', function(e) {
                modalStartY = e.touches[0].clientY;
            }, { passive: true });

            modal.addEventListener('touchmove', function(e) {
                if (!modalStartY) return;
                
                const currentY = e.touches[0].clientY;
                const deltaY = currentY - modalStartY;
                
                if (deltaY > 100) {
                    const modalInstance = bootstrap.Modal.getInstance(this);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    modalStartY = null;
                }
            }, { passive: true });
        });
    }

    /**
     * Initialize mobile QR scanner enhancements
     */
    function initMobileQRScanner() {
        const qrReader = document.getElementById('qr-reader');
        if (qrReader && isMobile) {
            // Add mobile-specific QR scanner styles
            qrReader.style.maxWidth = '100%';
            qrReader.style.height = 'auto';
            
            // Handle camera permissions
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                const startButton = document.getElementById('start-scan');
                if (startButton) {
                    startButton.addEventListener('click', function() {
                        requestCameraPermission();
                    });
                }
            }
        }
    }

    /**
     * Handle swipe gestures
     */
    function handleSwipeGesture(direction, target) {
        // Handle swipe on cards for quick actions
        const card = target.closest('.dashboard-card, .stat-card');
        if (card) {
            if (direction === 'left') {
                // Show quick actions menu
                showQuickActions(card);
            } else if (direction === 'right') {
                // Hide quick actions or go back
                hideQuickActions(card);
            }
        }
    }

    /**
     * Toggle mobile menu
     */
    function toggleMobileMenu() {
        const navbar = document.querySelector('.navbar');
        const navbarNav = navbar.querySelector('.navbar-nav');
        
        if (navbarNav) {
            navbarNav.classList.toggle('show');
            this.querySelector('i').classList.toggle('fa-bars');
            this.querySelector('i').classList.toggle('fa-times');
        }
    }

    /**
     * Show mobile validation message
     */
    function showMobileValidationMessage(input) {
        const message = input.validationMessage || 'Please fill out this field';
        
        // Create or update mobile validation tooltip
        let tooltip = document.getElementById('mobile-validation-tooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.id = 'mobile-validation-tooltip';
            tooltip.className = 'mobile-validation-tooltip';
            document.body.appendChild(tooltip);
        }
        
        tooltip.textContent = message;
        tooltip.style.display = 'block';
        
        // Position tooltip
        const rect = input.getBoundingClientRect();
        tooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
        tooltip.style.left = (rect.left + window.scrollX) + 'px';
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            tooltip.style.display = 'none';
        }, 3000);
        
        // Focus the invalid input
        input.focus();
    }

    /**
     * Request camera permission for QR scanner
     */
    function requestCameraPermission() {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function(stream) {
                    // Permission granted
                    stream.getTracks().forEach(track => track.stop());
                })
                .catch(function(error) {
                    console.error('Camera permission denied:', error);
                    showCameraPermissionError();
                });
        }
    }

    /**
     * Show camera permission error
     */
    function showCameraPermissionError() {
        const alert = document.createElement('div');
        alert.className = 'alert alert-warning mobile-alert';
        alert.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            Camera access is required for QR scanning. Please enable camera permissions in your browser settings.
        `;
        
        const container = document.querySelector('.container');
        if (container) {
            container.insertBefore(alert, container.firstChild);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    }

    /**
     * Show quick actions for cards
     */
    function showQuickActions(card) {
        // Remove existing quick actions
        hideQuickActions();
        
        const quickActions = document.createElement('div');
        quickActions.className = 'mobile-quick-actions';
        quickActions.innerHTML = `
            <button class="btn btn-sm btn-primary me-2" onclick="handleQuickAction('view', this)">
                <i class="fas fa-eye"></i>
            </button>
            <button class="btn btn-sm btn-success me-2" onclick="handleQuickAction('edit', this)">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-sm btn-danger" onclick="handleQuickAction('delete', this)">
                <i class="fas fa-trash"></i>
            </button>
        `;
        
        card.appendChild(quickActions);
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            hideQuickActions(card);
        }, 3000);
    }

    /**
     * Hide quick actions
     */
    function hideQuickActions(card) {
        const quickActions = card ? 
            card.querySelector('.mobile-quick-actions') : 
            document.querySelector('.mobile-quick-actions');
        
        if (quickActions) {
            quickActions.remove();
        }
    }

    /**
     * Update viewport height for mobile browsers
     */
    function updateViewportHeight() {
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
    }

    /**
     * Adjust layout for orientation changes
     */
    function adjustLayoutForOrientation() {
        const isLandscape = window.innerWidth > window.innerHeight;
        
        if (isMobile) {
            if (isLandscape) {
                document.body.classList.add('mobile-landscape');
                document.body.classList.remove('mobile-portrait');
            } else {
                document.body.classList.add('mobile-portrait');
                document.body.classList.remove('mobile-landscape');
            }
        }
    }

    /**
     * Global function to handle quick actions
     */
    window.handleQuickAction = function(action, button) {
        const card = button.closest('.dashboard-card, .stat-card');
        console.log(`Quick action: ${action} on card:`, card);
        
        // Implement specific actions based on card type and action
        switch(action) {
            case 'view':
                // Handle view action
                break;
            case 'edit':
                // Handle edit action
                break;
            case 'delete':
                // Handle delete action
                if (confirm('Are you sure you want to delete this item?')) {
                    // Implement delete logic
                }
                break;
        }
        
        hideQuickActions(card);
    };

    /**
     * Add mobile-specific CSS classes and styles
     */
    const mobileStyles = `
        .touch-active {
            transform: scale(0.98) !important;
            opacity: 0.8 !important;
            transition: all 0.1s ease !important;
        }
        
        .mobile-validation-tooltip {
            position: absolute;
            background: #dc3545;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            z-index: 10000;
            max-width: 250px;
            word-wrap: break-word;
            display: none;
        }
        
        .mobile-validation-tooltip::before {
            content: '';
            position: absolute;
            top: -5px;
            left: 10px;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-bottom: 5px solid #dc3545;
        }
        
        .mobile-quick-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.95);
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 100;
        }
        
        .mobile-alert {
            position: fixed;
            top: 20px;
            left: 20px;
            right: 20px;
            z-index: 10000;
            border-radius: 8px;
        }
        
        .mobile-device .modal-dialog {
            margin: 10px !important;
            max-width: calc(100% - 20px) !important;
        }
        
        .mobile-landscape .stats-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        
        .mobile-portrait .stats-grid {
            grid-template-columns: 1fr !important;
        }
        
        @media (max-width: 767px) {
            .mobile-menu-toggle {
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
            }
            
            .navbar-nav.show {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: rgba(0, 0, 0, 0.9);
                padding: 1rem;
                border-radius: 0 0 8px 8px;
            }
        }
    `;

    /**
     * Register service worker for PWA functionality
     */
    function registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/vss/sw.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                        
                        // Check for updates
                        registration.addEventListener('updatefound', function() {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', function() {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    showUpdateAvailable();
                                }
                            });
                        });
                    })
                    .catch(function(error) {
                        console.log('ServiceWorker registration failed: ', error);
                    });
            });
        }
    }

    /**
     * Show update available notification
     */
    function showUpdateAvailable() {
        const updateBanner = document.createElement('div');
        updateBanner.className = 'update-banner';
        updateBanner.innerHTML = `
            <div class="update-content">
                <span>🔄 New version available!</span>
                <button onclick="updateApp()" class="btn btn-sm btn-light">Update</button>
                <button onclick="dismissUpdate(this)" class="btn btn-sm btn-outline-light">Later</button>
            </div>
        `;
        document.body.appendChild(updateBanner);
    }

    /**
     * Update the app
     */
    window.updateApp = function() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistration().then(function(registration) {
                if (registration && registration.waiting) {
                    registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                    window.location.reload();
                }
            });
        }
    };

    /**
     * Dismiss update notification
     */
    window.dismissUpdate = function(button) {
        const banner = button.closest('.update-banner');
        if (banner) {
            banner.remove();
        }
    };

    // Inject mobile styles
    const styleSheet = document.createElement('style');
    styleSheet.textContent = mobileStyles + `
        .update-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            z-index: 10000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        
        .update-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .update-content button {
            margin-left: 0.5rem;
        }
        
        @media (max-width: 767px) {
            .update-content {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .update-content button {
                margin: 0 0.25rem;
            }
        }
    `;
    document.head.appendChild(styleSheet);

})();