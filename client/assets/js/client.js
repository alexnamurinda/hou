(function () {
  'use strict';

  const panels = {
    login: document.getElementById('login-panel'),
    signup: document.getElementById('signup-panel'),
  };
  const welcomeHeadings = document.querySelectorAll('[data-welcome-heading]');
  const memberLayout = document.querySelector('.member-layout');
  const feedback = document.querySelector('[data-form-feedback]');

  function showPanel(name) {
    const activeName = Object.prototype.hasOwnProperty.call(panels, name) ? name : 'login';

    Object.keys(panels).forEach((panelName) => {
      const active = panelName === activeName;
      panels[panelName].hidden = !active;
    });
    welcomeHeadings.forEach((heading) => {
      heading.hidden = heading.dataset.welcomeHeading !== activeName;
    });
    memberLayout.dataset.authView = activeName;

    if (feedback) {
      feedback.hidden = true;
      feedback.textContent = '';
    }
  }

  function syncPanelToHash() {
    showPanel(window.location.hash.slice(1));
  }

  window.addEventListener('hashchange', syncPanelToHash);
  syncPanelToHash();

  document.querySelectorAll('[data-member-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!feedback) return;
      feedback.textContent = 'HOU member accounts are not available online yet. Please contact the Union using the link above.';
      feedback.hidden = false;
    });
  });

  async function removePortalCaching() {
    if ('caches' in window) {
      const cacheNames = await caches.keys();
      const portalCaches = cacheNames.filter((name) =>
        name.startsWith('hou-members-') || name.startsWith('matlex-')
      );
      await Promise.all(portalCaches.map((name) => caches.delete(name)));
    }

    if ('serviceWorker' in navigator) {
      const registrations = await navigator.serviceWorker.getRegistrations();
      const appPath = new URL('./', window.location.href).pathname;
      const portalRegistrations = registrations.filter((registration) =>
        new URL(registration.scope).pathname === appPath
      );
      await Promise.all(portalRegistrations.map((registration) => registration.unregister()));
    }
  }

  removePortalCaching().catch((error) => {
    console.error('Failed to remove cached HOU portal content.', error);
  });
})();
