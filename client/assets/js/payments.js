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

  function showStatus(element, message, status) {
    element.replaceChildren();
    element.classList.toggle('is-error', status === 'failed');
    element.classList.toggle('is-pending', status === 'pending' || status === 'loading');
    element.classList.toggle('is-successful', status === 'successful');
    element.setAttribute('role', status === 'failed' ? 'alert' : 'status');
    element.hidden = false;
    if (status === 'pending' || status === 'loading' || status === 'successful') {
      const indicator = document.createElement('span');
      indicator.className = `payment-status-indicator payment-status-indicator-${status}`;
      indicator.setAttribute('aria-hidden', 'true');
      element.append(indicator, document.createTextNode(' '));
    }
    element.append(document.createTextNode(message));
  }

  function hideFeedback() {
    feedback.hidden = true;
    feedback.replaceChildren();
    feedback.classList.remove('is-error');
    feedback.setAttribute('role', 'alert');
  }

  function showFeedback(message, status) {
    showStatus(feedback, message, status);
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
    showStatus(notice, message, status);
    return notice;
  }

  function redirectToPaymentHistory(reference, delay = 1600) {
    window.setTimeout(() => {
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
          const message = 'Payment successful! Your wallet and transaction history are updating.';
          showFeedback(message, 'successful');
          showReturnStatus(reference, 'successful', message);
          redirectToPaymentHistory(reference);
          return;
        }
        if (result.status === 'failed') {
          const message = 'Payment was not completed. Your wallet has not been updated.';
          showFeedback(message, 'failed');
          showReturnStatus(reference, 'failed', message);
          redirectToPaymentHistory(reference, 2200);
          return;
        }
        if (attempts === 24) {
          const message = 'Still waiting for IOTEC. Keep this page open; it will refresh your wallet and transaction history once confirmed.';
          showFeedback(message, 'pending');
          showReturnStatus(reference, 'pending', message);
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
        const message = 'Payment successful! Your wallet and transaction history are updating.';
        showFeedback(message, 'successful');
        showReturnStatus(result.reference, 'successful', message);
        redirectToPaymentHistory(result.reference);
      } else if (status === 'failed') {
        const message = 'Payment was not completed. Your wallet has not been updated.';
        showFeedback(message, 'failed');
        showReturnStatus(result.reference, 'failed', message);
        redirectToPaymentHistory(result.reference, 2200);
      } else {
        const message = 'Check your phone and approve the mobile money prompt. Keep this page open while we confirm your payment.';
        showFeedback(message, 'pending');
        showReturnStatus(result.reference, 'pending', message);
        pollForStatus(result.reference);
      }
    } catch (error) {
      showFeedback(error instanceof Error ? error.message : 'Unable to start this payment. Please try again.', 'failed');
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = 'Continue to payment';
    }
  });

  updateAmount();
  const returnNotice = document.querySelector('[data-payment-return]');
  if (returnNotice && returnNotice.dataset.paymentStatus === 'pending') {
    showStatus(
      returnNotice,
      'Check your phone and approve the mobile money prompt. HOU is waiting for IOTEC to confirm your payment.',
      'pending'
    );
    pollForStatus(returnNotice.dataset.paymentReference);
  }
})();
