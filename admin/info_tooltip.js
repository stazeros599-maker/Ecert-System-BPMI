(function () {
    const triggerSelector = '.page-info, .login-info';
    const tooltip = document.createElement('div');
    tooltip.id = 'adminInfoTooltip';
    tooltip.className = 'admin-info-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.setAttribute('aria-hidden', 'true');
    document.body.appendChild(tooltip);

    let activeTrigger = null;

    function findTrigger(target) {
        return target instanceof Element ? target.closest(triggerSelector) : null;
    }

    function positionTooltip() {
        if (!activeTrigger || !activeTrigger.isConnected) return;

        const triggerRect = activeTrigger.getBoundingClientRect();
        tooltip.style.left = '16px';
        tooltip.style.top = '16px';

        const tooltipRect = tooltip.getBoundingClientRect();
        const edgePadding = 16;
        const gap = 8;
        const maxLeft = Math.max(edgePadding, window.innerWidth - tooltipRect.width - edgePadding);
        const left = Math.min(maxLeft, Math.max(edgePadding, triggerRect.right - tooltipRect.width));
        let top = triggerRect.bottom + gap;

        if (top + tooltipRect.height > window.innerHeight - edgePadding) {
            top = triggerRect.top - tooltipRect.height - gap;
        }

        const maxTop = Math.max(edgePadding, window.innerHeight - tooltipRect.height - edgePadding);
        tooltip.style.left = left + 'px';
        tooltip.style.top = Math.min(maxTop, Math.max(edgePadding, top)) + 'px';
    }

    function showTooltip(trigger) {
        const message = trigger.getAttribute('data-tooltip');
        if (!message) return;

        activeTrigger = trigger;
        tooltip.textContent = message;
        tooltip.classList.toggle('login-tooltip', trigger.classList.contains('login-info'));
        tooltip.setAttribute('aria-hidden', 'false');
        tooltip.classList.add('is-visible');
        trigger.setAttribute('aria-describedby', tooltip.id);
        positionTooltip();
    }

    function hideTooltip(force) {
        if (!activeTrigger) return;
        if (!force && (activeTrigger.matches(':hover') || document.activeElement === activeTrigger)) return;

        activeTrigger.removeAttribute('aria-describedby');
        activeTrigger = null;
        tooltip.classList.remove('is-visible', 'login-tooltip');
        tooltip.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('pointerover', function (event) {
        const trigger = findTrigger(event.target);
        if (trigger && !trigger.contains(event.relatedTarget)) {
            showTooltip(trigger);
        }
    });

    document.addEventListener('pointerout', function (event) {
        const trigger = findTrigger(event.target);
        if (trigger && !trigger.contains(event.relatedTarget) && document.activeElement !== trigger) {
            hideTooltip(true);
        }
    });

    document.addEventListener('focusin', function (event) {
        const trigger = findTrigger(event.target);
        if (trigger) showTooltip(trigger);
    });

    document.addEventListener('focusout', function (event) {
        const trigger = findTrigger(event.target);
        if (trigger && !trigger.matches(':hover')) hideTooltip(true);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && activeTrigger) {
            hideTooltip(true);
        }
    });

    window.addEventListener('resize', positionTooltip);
    window.addEventListener('scroll', positionTooltip, true);
})();