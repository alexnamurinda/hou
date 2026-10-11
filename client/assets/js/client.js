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
  const signupOtp = document.getElementById('signup-otp');
  const loginForm = document.querySelector('[data-login-form]');

  function setSignupStep(stepNumber) {
    signupSteps.forEach((step) => {
      const active = Number(step.dataset.signupStep) === stepNumber;
      step.hidden = !active;
      step.querySelectorAll('input').forEach((input) => {
        input.disabled = !active;
      });
    });

    if (signupStepLabel) signupStepLabel.textContent = `Step ${stepNumber} of 3`;
    if (signupProgress) signupProgress.style.width = `${Math.round((stepNumber / 3) * 100)}%`;
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

  async function initializeMemberServices() {
    try {
      const response = await fetch('api/auth/csrf.php', {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
      });
      const result = await response.json();
      if (!response.ok || !result.success || !result.csrf_token) {
        throw new Error(result.message || 'Could not connect to HOU member services.');
      }
    } catch (error) {
      if (feedback) {
        feedback.textContent = error instanceof Error
          ? error.message
          : 'Could not connect to HOU member services. Please try again.';
        feedback.hidden = false;
      }
      console.error('Failed to initialize HOU member services.', error);
    }
  }

  initializeMemberServices();

  async function postMemberRequest(endpoint, payload) {
    const csrfResponse = await fetch('api/auth/csrf.php', {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    });
    const csrfData = await csrfResponse.json();
    if (!csrfResponse.ok || !csrfData.success || !csrfData.csrf_token) {
      throw new Error(csrfData.message || 'Could not start a secure session. Please refresh and try again.');
    }

    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfData.csrf_token,
      },
      body: JSON.stringify(payload),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Unable to complete your request. Please try again.');
    }
    return result;
  }

  async function requestSignupCode(button = document.querySelector('[data-signup-next]')) {
    if (!feedback || !button) return;
    button.disabled = true;
    feedback.hidden = true;

    try {
      const result = await postMemberRequest('api/auth/send-signup-otp.php', {
        name: document.getElementById('signup-name').value,
        phone: document.getElementById('signup-phone').value,
        email: document.getElementById('signup-email').value,
      });
      document.querySelector('[data-signup-email]').textContent = document.getElementById('signup-email').value;
      setSignupStep(2);
      feedback.textContent = result.message;
      feedback.hidden = false;
      signupOtp?.focus();
    } catch (error) {
      feedback.textContent = error instanceof Error
        ? error.message
        : 'Unable to send a verification code. Please try again.';
      feedback.hidden = false;
    } finally {
      button.disabled = false;
    }
  }

  document.querySelector('[data-signup-next]')?.addEventListener('click', () => {
    const firstStep = signupSteps.find((step) => step.dataset.signupStep === '1');
    const invalidInput = firstStep && [...firstStep.querySelectorAll('input')].find((input) => !input.checkValidity());
    if (invalidInput) {
      invalidInput.reportValidity();
      invalidInput.focus();
      return;
    }

    requestSignupCode();
  });

  document.querySelector('[data-signup-back]')?.addEventListener('click', () => {
    setSignupStep(1);
    document.getElementById('signup-name')?.focus();
  });

  document.querySelector('[data-signup-verify]')?.addEventListener('click', async () => {
    if (!feedback || !signupOtp) return;
    if (!signupOtp.checkValidity()) {
      signupOtp.reportValidity();
      signupOtp.focus();
      return;
    }

    const button = document.querySelector('[data-signup-verify]');
    button.disabled = true;
    feedback.hidden = true;
    try {
      const result = await postMemberRequest('api/auth/verify-signup-otp.php', { otp: signupOtp.value });
      setSignupStep(3);
      feedback.textContent = result.message;
      feedback.hidden = false;
      signupPassword?.focus();
    } catch (error) {
      feedback.textContent = error instanceof Error
        ? error.message
        : 'Unable to verify the code. Please try again.';
      feedback.hidden = false;
    } finally {
      button.disabled = false;
    }
  });

  document.querySelector('[data-signup-resend]')?.addEventListener('click', (event) => {
    requestSignupCode(event.currentTarget);
  });

  document.querySelector('[data-signup-password-back]')?.addEventListener('click', () => {
    setSignupStep(1);
    document.getElementById('signup-name')?.focus();
  });

  async function submitAuthForm(form, endpoint, payload, onSuccess = () => {
    window.location.assign('member.php');
  }) {
    if (!feedback) return;
    const submitButton = form.querySelector('[type="submit"]');
    feedback.hidden = true;
    if (submitButton) submitButton.disabled = true;

    try {
      const result = await postMemberRequest(endpoint, payload());
      onSuccess(result);
    } catch (error) {
      feedback.textContent = error instanceof Error
        ? error.message
        : 'Unable to connect to HOU member services. Please try again.';
      feedback.hidden = false;
      if (!(error instanceof Error && error.message)) {
        console.error('HOU account request failed.', error);
      }
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
      }
    }
  }

  loginForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    submitAuthForm(loginForm, 'api/auth/login.php', () => ({
      phone: document.getElementById('login-identifier').value,
      password: document.getElementById('login-password').value,
    }));
  });

  signupForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const activeStep = signupForm.querySelector('[data-signup-step]:not([hidden])');
    if (activeStep?.dataset.signupStep !== '3') return;

    if (signupPassword?.value !== signupPasswordConfirm?.value) {
      if (feedback) {
        feedback.textContent = 'The passwords do not match. Please re-enter the same password.';
        feedback.hidden = false;
      }
      signupPasswordConfirm?.focus();
      return;
    }

    submitAuthForm(
      signupForm,
      'api/auth/register.php',
      () => ({
        password: signupPassword.value,
        password_confirmation: signupPasswordConfirm.value,
      }),
      (result) => {
        showPanel('login');
        window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#login`);
        document.getElementById('login-identifier').value = document.getElementById('signup-phone').value;
        feedback.textContent = result.message || 'Your account has been created. Please log in now.';
        feedback.hidden = false;
        document.getElementById('login-identifier').focus();
      }
    );
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
