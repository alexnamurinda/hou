(function () {
  'use strict';

  const openButton = document.querySelector('[data-payment-open]');
  const panel = document.querySelector('[data-payment-form-panel]');
  const form = document.querySelector('[data-payment-form]');
  if (!openButton || !panel || !form) return;

  const cancelButton = form.querySelector('[data-payment-cancel]');
  const submitButton = form.querySelector('[data-payment-submit]');
  const typeSelect = form.querySelector('[data-payment-type]');
  const amountInput = form.querySelector('[data-payment-amount]');
  const amountHelp = form.querySelector('[data-payment-amount-help]');
  const feedback = form.querySelector('[data-payment-feedback]');
  const fixedAmounts = { membership: '600000', supervision: '100000' };
  let statusPollTimer;

  function updateAmount() {
    const fixedAmount = fixedAmounts[typeSelect.value];
    amountInput.readOnly = Boolean(fixedAmount);
    amountInput.value = fixedAmount || '';
    amountHelp.textContent = fixedAmount
      ? 'This is the fixed amount for the selected payment type.'
      : 'Enter a whole UGX amount from 500 to 1,000,000,000.';
  }

  function showFeedback(message, isError) {
    feedback.textContent = message;
    feedback.classList.toggle('is-error', isError);
    feedback.setAttribute('role', isError ? 'alert' : 'status');
    feedback.hidden = false;
  }

  function hideFeedback() {
    feedback.hidden = true;
    feedback.textContent = '';
    feedback.classList.remove('is-error');
    feedback.setAttribute('role', 'alert');
  }

  function showReturnStatus(reference, status, message) {
    let notice = document.querySelector('[data-payment-return]');
    if (!notice) {
      notice = document.createElement('div');
      notice.className = 'payment-return-notice';
      notice.setAttribute('role', 'status');
      const wallet = document.querySelector('.payment-wallet-card');
      wallet.insertAdjacentElement('afterend', notice);
    }
    notice.dataset.paymentReturn = '';
    notice.dataset.paymentReference = reference;
    notice.dataset.paymentStatus = status;
    notice.textContent = message;
  }

  async function pollForStatus(reference) {
    let attempts = 0;
    window.clearTimeout(statusPollTimer);
    async function poll() {
      attempts += 1;
      try {
        const response = await fetch(`api/payments/status.php?reference=${encodeURIComponent(reference)}`, {
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { Accept: 'application/json' },
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.message || 'Unable to check payment status.');
        }
        if (result.status === 'successful') {
          window.location.assign(`member.php?section=payments&reference=${encodeURIComponent(reference)}`);
          return;
        }
        if (result.status === 'failed') {
          window.location.assign(`member.php?section=payments&reference=${encodeURIComponent(reference)}`);
          return;
        }
        if (attempts < 24) statusPollTimer = window.setTimeout(poll, 5000);
      } catch (error) {
        console.error('Could not refresh payment status.', error);
        if (attempts < 24) statusPollTimer = window.setTimeout(poll, 10000);
      }
    }
    poll();
  }

  typeSelect.addEventListener('change', updateAmount);
  openButton.addEventListener('click', () => {
    panel.hidden = false;
    openButton.setAttribute('aria-expanded', 'true');
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    typeSelect.focus({ preventScroll: true });
  });
  cancelButton.addEventListener('click', () => {
    panel.hidden = true;
    openButton.setAttribute('aria-expanded', 'false');
    hideFeedback();
    openButton.focus();
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    hideFeedback();
    if (!form.reportValidity()) return;
    submitButton.disabled = true;
    submitButton.textContent = 'Connecting to IOTEC...';
    try {
      const response = await fetch('api/payments/create.php', {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-Token': form.dataset.csrfToken,
        },
        body: JSON.stringify(Object.fromEntries(new FormData(form))),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Unable to start this payment. Please try again.');
      }

      if (result.checkout_url) {
        window.location.assign(result.checkout_url);
        return;
      }

      const message = `${result.message} Reference: ${result.reference}`;
      const status = result.status || 'pending';
      showFeedback(message, status === 'failed');
      showReturnStatus(result.reference, status, message);
      if (status === 'successful' || status === 'failed') {
        window.location.assign(`member.php?section=payments&reference=${encodeURIComponent(result.reference)}`);
      } else {
        pollForStatus(result.reference);
      }
    } catch (error) {
      showFeedback(error instanceof Error ? error.message : 'Unable to start this payment. Please try again.', true);
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = 'Continue to payment';
    }
  });

  updateAmount();
  const returnNotice = document.querySelector('[data-payment-return]');
  if (returnNotice && returnNotice.dataset.paymentStatus === 'pending') {
    pollForStatus(returnNotice.dataset.paymentReference);
  }
})();
