(function () {
  'use strict';

  const form = document.querySelector('[data-onboarding-form]');
  if (!form) return;

  const steps = [...form.querySelectorAll('[data-onboarding-step]')];
  const stepperItems = [...document.querySelectorAll('[data-stepper-item]')];
  const backButton = form.querySelector('[data-onboarding-back]');
  const nextButton = form.querySelector('[data-onboarding-next]');
  const stepLabel = form.querySelector('[data-onboarding-step-label]');
  const feedback = form.querySelector('[data-onboarding-feedback]');
  let activeIndex = Math.min(Number(form.dataset.initialStep) || 0, steps.length - 1);

  function renderStep() {
    steps.forEach((step, index) => {
      step.hidden = index !== activeIndex;
    });
    stepperItems.forEach((item, index) => {
      item.classList.toggle('is-active', index === activeIndex);
      item.classList.toggle('is-complete', index < activeIndex);
    });
    backButton.hidden = activeIndex === 0;
    stepLabel.textContent = `Step ${activeIndex + 1} of ${steps.length}`;
    nextButton.textContent = activeIndex === steps.length - 1 ? 'Done' : 'Next →';
    feedback.hidden = true;
    feedback.textContent = '';
  }

  function showError(message) {
    feedback.textContent = message;
    feedback.hidden = false;
    feedback.focus();
  }

  async function saveCurrentStep() {
    const step = steps[activeIndex];
    const formData = new FormData(form);
    formData.set('step', step.dataset.onboardingStep);
    nextButton.disabled = true;
    backButton.disabled = true;

    try {
      const response = await fetch('api/profile/save-step.php', {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          Accept: 'application/json',
          'X-CSRF-Token': form.dataset.csrfToken,
        },
        body: formData,
      });
      let result;
      try {
        result = await response.json();
      } catch {
        throw new Error('The server returned an invalid response. Please refresh and try again.');
      }
      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Unable to save this step. Please try again.');
      }

      if (activeIndex === steps.length - 1) {
        window.location.assign('member.php?section=overview');
      } else {
        activeIndex += 1;
        renderStep();
      }
    } catch (error) {
      showError(error instanceof Error ? error.message : 'Unable to save this step. Please try again.');
    } finally {
      nextButton.disabled = false;
      backButton.disabled = false;
    }
  }

  nextButton.addEventListener('click', () => {
    const invalidInput = [...steps[activeIndex].querySelectorAll('input')]
      .find((input) => !input.checkValidity());
    if (invalidInput) {
      invalidInput.reportValidity();
      invalidInput.focus();
      return;
    }
    saveCurrentStep();
  });

  backButton.addEventListener('click', () => {
    if (activeIndex > 0) {
      activeIndex -= 1;
      renderStep();
    }
  });

  renderStep();
})();
