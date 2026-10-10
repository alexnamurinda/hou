(function () {
  'use strict';

  const panels = {
    login: document.getElementById('login-panel'),
    signup: document.getElementById('signup-panel'),
  };
  const welcomeHeadings = document.querySelectorAll('[data-welcome-heading]');
  const memberLayout = document.querySelector('.member-layout');
  const feedback = document.querySelector('[data-form-feedback]');
  const signupForm = document.querySelector('[data-signup-form]');
  const signupSteps = signupForm ? [...signupForm.querySelectorAll('[data-signup-step]')] : [];
  const signupStepLabel = document.querySelector('[data-signup-step-label]');
  const signupProgress = document.querySelector('[data-signup-progress]');
  const signupPassword = document.getElementById('signup-password');
  const signupPasswordConfirm = document.getElementById('signup-password-confirm');

  function setSignupStep(stepNumber) {
    signupSteps.forEach((step) => {
      const active = Number(step.dataset.signupStep) === stepNumber;
      step.hidden = !active;
      step.querySelectorAll('input').forEach((input) => {
        input.disabled = !active;
      });
    });

    if (signupStepLabel) signupStepLabel.textContent = `Step ${stepNumber} of 2`;
    if (signupProgress) signupProgress.style.width = stepNumber === 1 ? '50%' : '100%';
    if (feedback) {
      feedback.hidden = true;
      feedback.textContent = '';
    }
  }

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

    if (activeName === 'signup') setSignupStep(1);
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

  document.querySelector('[data-signup-next]')?.addEventListener('click', () => {
    const firstStep = signupSteps.find((step) => step.dataset.signupStep === '1');
    const invalidInput = firstStep && [...firstStep.querySelectorAll('input')].find((input) => !input.checkValidity());
    if (invalidInput) {
      invalidInput.reportValidity();
      invalidInput.focus();
      return;
    }

    setSignupStep(2);
    signupPassword?.focus();
  });

  document.querySelector('[data-signup-back]')?.addEventListener('click', () => {
    setSignupStep(1);
    document.getElementById('signup-email')?.focus();
  });

  document.querySelectorAll('[data-member-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!feedback) return;

      if (form === signupForm && signupPassword?.value !== signupPasswordConfirm?.value) {
        feedback.textContent = 'The passwords do not match. Please re-enter the same password.';
        feedback.hidden = false;
        signupPasswordConfirm?.focus();
        return;
      }

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
