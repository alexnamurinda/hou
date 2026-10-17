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
  const feedback = document.querySelector('[data-payment-feedback]');
  const fixedAmounts = { membership: '600000', supervision: '100000' };
  let statusPollTimer;
  let feedbackTimeout;
  let historyRedirectTimer;
  let waitingForConfirmation = false;

  function setWaitingForConfirmation(isWaiting) {
    waitingForConfirmation = isWaiting;
    submitButton.disabled = isWaiting;
    cancelButton.disabled = isWaiting;
    submitButton.textContent = isWaiting ? 'Waiting for payment confirmation...' : 'Continue to payment';
  }

  function updateAmount() {
    const fixedAmount = fixedAmounts[typeSelect.value];
    amountInput.readOnly = Boolean(fixedAmount);
    amountInput.value = fixedAmount || '';
    amountHelp.textContent = fixedAmount
      ? 'This is the fixed amount for the selected payment type.'
      : 'Enter a whole UGX amount from 500 to 1,000,000,000.';
  }

  function hideFeedback() {
    window.clearTimeout(feedbackTimeout);
    feedback.hidden = true;
    feedback.replaceChildren();
    feedback.classList.remove('is-error', 'is-pending', 'is-successful');
    feedback.setAttribute('role', 'alert');
  }

  function showFeedback(message, status) {
    window.clearTimeout(feedbackTimeout);
    feedback.replaceChildren();
    feedback.classList.toggle('is-error', status === 'failed');
    feedback.classList.toggle('is-pending', status === 'pending' || status === 'loading');
    feedback.classList.toggle('is-successful', status === 'successful');
    feedback.setAttribute('role', status === 'failed' ? 'alert' : 'status');
    feedback.hidden = false;
    if (status === 'pending' || status === 'loading' || status === 'successful') {
      const indicator = document.createElement('span');
      indicator.className = `payment-status-indicator payment-status-indicator-${status}`;
      indicator.setAttribute('aria-hidden', 'true');
      feedback.append(indicator, document.createTextNode(' '));
    }
    feedback.append(document.createTextNode(message));
    if (status === 'failed' || status === 'successful') {
      feedbackTimeout = window.setTimeout(hideFeedback, 10000);
    }
  }

  function redirectToPaymentHistory(reference, delay = 1600) {
    window.clearTimeout(historyRedirectTimer);
    historyRedirectTimer = window.setTimeout(() => {
      window.location.assign(`member.php?section=payments&reference=${encodeURIComponent(reference)}`);
    }, delay);
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
          showFeedback('Payment successful. Refreshing your wallet and transaction history.', 'successful');
          redirectToPaymentHistory(reference);
          return;
        }
        if (result.status === 'failed') {
          setWaitingForConfirmation(false);
          showFeedback('Payment was not completed. Your wallet has not been updated.', 'failed');
          redirectToPaymentHistory(reference, 10000);
          return;
        }
        statusPollTimer = window.setTimeout(poll, attempts < 24 ? 5000 : 30000);
      } catch (error) {
        console.error('Could not refresh payment status.', error);
        statusPollTimer = window.setTimeout(poll, attempts < 24 ? 10000 : 30000);
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
    window.clearTimeout(historyRedirectTimer);
    hideFeedback();
    if (!form.reportValidity()) return;
    submitButton.disabled = true;
    submitButton.textContent = 'Connecting to IOTEC...';
    showFeedback('Sending your payment request. Please wait.', 'loading');
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

      const status = result.status || 'pending';
      if (status === 'successful') {
        showFeedback('Payment successful. Refreshing your wallet and transaction history.', 'successful');
        redirectToPaymentHistory(result.reference);
      } else if (status === 'failed') {
        setWaitingForConfirmation(false);
        showFeedback('Payment was not completed. Your wallet has not been updated.', 'failed');
        redirectToPaymentHistory(result.reference, 10000);
      } else {
        const message = 'Check your phone and approve the mobile money prompt. Keep this page open while we confirm your payment.';
        setWaitingForConfirmation(true);
        showFeedback(message, 'pending');
        feedback.dataset.paymentReference = result.reference;
        feedback.dataset.paymentStatus = 'pending';
        pollForStatus(result.reference);
      }
    } catch (error) {
      showFeedback(error instanceof Error ? error.message : 'Unable to start this payment. Please try again.', 'failed');
    } finally {
      if (!waitingForConfirmation) {
        setWaitingForConfirmation(false);
      }
    }
  });

  updateAmount();
  if (feedback.dataset.paymentStatus === 'pending' && feedback.dataset.paymentReference) {
    setWaitingForConfirmation(true);
    showFeedback('Check your phone and approve the mobile money prompt. HOU is waiting for IOTEC to confirm your payment.', 'pending');
    pollForStatus(feedback.dataset.paymentReference);
  }
})();
