(function () {
  'use strict';

  const form = document.getElementById('cloneForm');
  if (!form) return;

  const sourceUrl = document.getElementById('source_url');
  const sourceHtml = document.getElementById('source_html');
  const affiliateLink = document.getElementById('affiliate_link');
  const submitBtn = document.getElementById('submitBtn');

  function updateRequiredStates() {
    const hasUrl = sourceUrl.value.trim() !== '';
    const hasHtml = sourceHtml.value.trim() !== '';

    if (hasUrl) {
      sourceHtml.removeAttribute('required');
    } else {
      sourceHtml.setAttribute('required', 'required');
    }

    if (hasHtml) {
      sourceUrl.removeAttribute('required');
    } else {
      sourceUrl.removeAttribute('required');
    }
  }

  if (sourceUrl && sourceHtml) {
    sourceUrl.addEventListener('input', updateRequiredStates);
    sourceHtml.addEventListener('input', updateRequiredStates);
    updateRequiredStates();
  }

  form.addEventListener('submit', function (e) {
    const url = sourceUrl.value.trim();
    const html = sourceHtml.value.trim();

    if (!url && !html) {
      e.preventDefault();
      alert('Preencha a URL OU cole o HTML da landing page.');
      return;
    }

    if (!affiliateLink.value.trim()) {
      e.preventDefault();
      alert('O link do afiliado é obrigatório.');
      affiliateLink.focus();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = '⏳ Processando...';
  });

  const htmlField = document.getElementById('source_html');
  if (htmlField) {
    htmlField.addEventListener('dragover', function (e) {
      e.preventDefault();
      htmlField.style.borderColor = 'var(--color-primary)';
    });

    htmlField.addEventListener('dragleave', function () {
      htmlField.style.borderColor = '';
    });

    htmlField.addEventListener('drop', function (e) {
      e.preventDefault();
      htmlField.style.borderColor = '';

      const file = e.dataTransfer.files[0];
      if (!file) return;

      if (!file.name.endsWith('.html') && !file.name.endsWith('.htm')) {
        alert('Por favor, solte um arquivo .html ou .htm');
        return;
      }

      const reader = new FileReader();
      reader.onload = function (ev) {
        htmlField.value = ev.target.result;
        updateRequiredStates();
      };
      reader.readAsText(file);
    });
  }
})();
