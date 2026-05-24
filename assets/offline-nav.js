/**
 * Offline fallback for Takeda header nav flyouts (Next.js hydration does not run locally).
 */
(function () {
  'use strict';

  const FLYOUT_SELECTOR = '[id^="nav-flyout"]';
  const TRIGGER_SELECTOR = 'button[aria-controls^="nav-flyout"]';

  function getFlyout(trigger) {
    const id = trigger.getAttribute('aria-controls');
    return id ? document.getElementById(id) : null;
  }

  function setFlyoutOpen(flyout, open) {
    if (!flyout) return;
    if (open) {
      flyout.classList.remove('hidden');
      flyout.removeAttribute('aria-hidden');
    } else {
      flyout.classList.add('hidden');
      flyout.setAttribute('aria-hidden', 'true');
    }
  }

  function setTriggerOpen(trigger, open) {
    trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    trigger.setAttribute('data-optly-state', open ? 'open' : 'closed');
  }

  function positionTopLevelFlyout(trigger, flyout) {
    if (!flyout.id || flyout.id.split('-').length > 3) return;
    const rect = trigger.getBoundingClientRect();
    flyout.style.left = Math.round(rect.left) + 'px';
  }

  function closeFlyoutTree(flyout) {
    if (!flyout) return;
    setFlyoutOpen(flyout, false);
    flyout.querySelectorAll(TRIGGER_SELECTOR).forEach(function (childTrigger) {
      setTriggerOpen(childTrigger, false);
      closeFlyoutTree(getFlyout(childTrigger));
    });
  }

  function closeAllFlyouts() {
    document.querySelectorAll(FLYOUT_SELECTOR).forEach(function (flyout) {
      setFlyoutOpen(flyout, false);
    });
    document.querySelectorAll(TRIGGER_SELECTOR).forEach(function (trigger) {
      setTriggerOpen(trigger, false);
    });
  }

  function closeSiblingFlyouts(trigger) {
    const hostLi = trigger.closest('li');
    if (!hostLi) return;
    hostLi.parentElement.querySelectorAll(':scope > li').forEach(function (li) {
      if (li === hostLi) return;
      li.querySelectorAll(TRIGGER_SELECTOR).forEach(function (siblingTrigger) {
        setTriggerOpen(siblingTrigger, false);
        closeFlyoutTree(getFlyout(siblingTrigger));
      });
    });
  }

  document.addEventListener(
    'click',
    function (event) {
      const trigger = event.target.closest(TRIGGER_SELECTOR);
      if (!trigger) return;

      event.preventDefault();
      event.stopPropagation();

      const flyout = getFlyout(trigger);
      if (!flyout) return;

      const isOpen = trigger.getAttribute('aria-expanded') === 'true';

      closeSiblingFlyouts(trigger);

      if (isOpen) {
        setTriggerOpen(trigger, false);
        closeFlyoutTree(flyout);
        return;
      }

      setTriggerOpen(trigger, true);
      setFlyoutOpen(flyout, true);
      positionTopLevelFlyout(trigger, flyout);
    },
    true
  );

  document.addEventListener('click', function (event) {
    if (
      event.target.closest(TRIGGER_SELECTOR) ||
      event.target.closest(FLYOUT_SELECTOR)
    ) {
      return;
    }
    closeAllFlyouts();
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeAllFlyouts();
  });
})();
