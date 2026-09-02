(function () {
  'use strict';

  const form = document.getElementById('cloneForm');
  if (!form) return;

  const sourceUrl = document.getElementById('source_url');
  const sourceHtml = document.getElementById('source_html');
  const affiliateLink = document.getElementById('affiliate_link');
  const submitBtn = document.getElementById('submitBtn');
  const loadingOverlay = document.getElementById('loadingOverlay');
  const loadingStep = document.getElementById('loadingStep');
  const loadingTime = document.getElementById('loadingTime');
  const progressFill = document.getElementById('progressFill');

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

  const steps = [
    { text: 'Enviando dados...', time: 0 },
    { text: 'Buscando HTML da página...', time: 3 },
    { text: 'Analisando estrutura...', time: 8 },
    { text: 'Detectando botões de checkout...', time: 12 },
    { text: 'Substituindo CTAs pelo seu link...', time: 16 },
    { text: 'Corrigindo lazy-load...', time: 20 },
    { text: 'Baixando fontes e ícones...', time: 24 },
    { text: 'Embutindo fontes como base64...', time: 28 },
    { text: 'Quase pronto...', time: 32 },
  ];

  let timerInterval = null;
  let startTime = 0;

  function showLoading() {
    if (!loadingOverlay) return;
    loadingOverlay.classList.add('active');
    startTime = Date.now();
    let currentStep = 0;

    loadingStep.textContent = steps[0].text;

    timerInterval = setInterval(function () {
      const elapsed = Math.floor((Date.now() - startTime) / 1000);
      loadingTime.textContent = elapsed + 's';

      while (currentStep < steps.length - 1 && elapsed >= steps[currentStep + 1].time) {
        currentStep++;
        loadingStep.textContent = steps[currentStep].text;
      }
    }, 200);
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
    showLoading();
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
