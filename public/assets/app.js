(function () {
  'use strict';

  var form = document.getElementById('cloneForm');
  if (!form) return;

  var sourceUrl = document.getElementById('source_url');
  var sourceHtml = document.getElementById('source_html');
  var affiliateLink = document.getElementById('affiliate_link');
  var submitBtn = document.getElementById('submitBtn');
  var loadingOverlay = document.getElementById('loadingOverlay');
  var loadingStep = document.getElementById('loadingStep');
  var loadingTime = document.getElementById('loadingTime');

  function updateRequiredStates() {
    var hasUrl = sourceUrl.value.trim() !== '';
    var hasHtml = sourceHtml.value.trim() !== '';

    if (hasUrl) {
      sourceHtml.removeAttribute('required');
    } else {
      sourceHtml.setAttribute('required', 'required');
    }

    if (hasHtml) {
      sourceUrl.removeAttribute('required');
    }
  }

  if (sourceUrl && sourceHtml) {
    sourceUrl.addEventListener('input', updateRequiredStates);
    sourceHtml.addEventListener('input', updateRequiredStates);
    updateRequiredStates();
  }

  function showLoading() {
    if (loadingOverlay) {
      loadingOverlay.classList.add('active');
    }
    if (loadingStep) {
      loadingStep.textContent = 'Enviando dados...';
    }
    if (loadingTime) {
      loadingTime.textContent = '0s';
    }
  }

  function updateLoadingStep(step, time) {
    if (loadingStep) {
      loadingStep.textContent = step;
    }
    if (loadingTime && time !== undefined) {
      loadingTime.textContent = time + 's';
    }
  }

  function hideLoading() {
    if (loadingOverlay) {
      loadingOverlay.classList.remove('active');
    }
  }

  var loadingInterval = null;
  var startTime = 0;

  function startLoadingTimer() {
    startTime = Date.now();
    var steps = [
      { text: 'Enviando dados...', time: 0 },
      { text: 'Buscando HTML da pagina...', time: 2 },
      { text: 'Analisando e substituindo CTAs...', time: 5 },
      { text: 'Baixando recursos externos...', time: 8 },
      { text: 'Finalizando...', time: 12 }
    ];
    var stepIdx = 0;

    loadingInterval = setInterval(function () {
      var elapsed = Math.floor((Date.now() - startTime) / 1000);

      while (stepIdx < steps.length - 1 && elapsed >= steps[stepIdx + 1].time) {
        stepIdx++;
      }
      updateLoadingStep(steps[stepIdx].text, elapsed);
    }, 200);
  }

  function stopLoadingTimer() {
    if (loadingInterval) {
      clearInterval(loadingInterval);
      loadingInterval = null;
    }
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    var url = sourceUrl.value.trim();
    var html = sourceHtml.value.trim();

    if (!url && !html) {
      alert('Preencha a URL OU cole o HTML da landing page.');
      return;
    }

    if (!affiliateLink.value.trim()) {
      alert('O link do afiliado e obrigatorio.');
      affiliateLink.focus();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Processando...';
    showLoading();
    startLoadingTimer();

    var formData = new FormData(form);
    formData.append('ajax', '1');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'process.php', true);

    xhr.onload = function () {
      stopLoadingTimer();
      if (xhr.status === 200) {
        var response = xhr.responseText;
        if (response.indexOf('index.php?job=') !== -1) {
          window.location.href = response.trim();
        } else {
          hideLoading();
          submitBtn.disabled = false;
          submitBtn.textContent = 'Clonar Landing Page';
          if (response.indexOf('error=') !== -1) {
            var errorMsg = decodeURIComponent(response.split('error=')[1].split('&')[0]);
            alert('Erro: ' + errorMsg);
          }
        }
      } else {
        hideLoading();
        submitBtn.disabled = false;
        submitBtn.textContent = 'Clonar Landing Page';
        alert('Erro ao processar. Tente novamente.');
      }
    };

    xhr.onerror = function () {
      stopLoadingTimer();
      hideLoading();
      submitBtn.disabled = false;
      submitBtn.textContent = 'Clonar Landing Page';
      alert('Erro de conexao. Verifique sua internet.');
    };

    xhr.send(formData);
  });

  var htmlField = document.getElementById('source_html');
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

      var file = e.dataTransfer.files[0];
      if (!file) return;

      if (!file.name.endsWith('.html') && !file.name.endsWith('.htm')) {
        alert('Por favor, solte um arquivo .html ou .htm');
        return;
      }

      var reader = new FileReader();
      reader.onload = function (ev) {
        htmlField.value = ev.target.result;
        updateRequiredStates();
      };
      reader.readAsText(file);
    });
  }
})();
