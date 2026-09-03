<?php
require_once __DIR__ . '/../lib/Config.php';
require_once Config::getLibDir() . '/Cloner.php';
require_once Config::getLibDir() . '/ZipBuilder.php';

$error = $_GET['error'] ?? null;
$jobId = $_GET['job'] ?? null;
$result = null;

if ($jobId && preg_match('/^[a-f0-9]{16}$/', $jobId)) {
    $jobsDir = Config::getJobsDir();
    $jobFile = $jobsDir . '/' . $jobId . '.json';

    if (file_exists($jobFile)) {
        $jobData = json_decode(file_get_contents($jobFile), true);
        if ($jobData && isset($jobData['html'])) {
            $result = [
                'job_id' => $jobData['job_id'],
                'ctas' => $jobData['ctas'] ?? [],
                'affiliate_link' => $jobData['affiliate_link'] ?? '',
                'created_at' => $jobData['created_at'] ?? '',
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gerador de Landing Page para Afiliados</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="app">
    <header class="app-header">
      <h1>Gerador de Landing Page para Afiliados</h1>
      <p class="subtitle">Cole o HTML de uma landing page + seu link de afiliado e receba o clone pronto para hospedar.</p>
    </header>

    <main class="app-main">
      <?php if ($error): ?>
        <div class="alert alert-error">
          <strong>Erro:</strong> <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <?php if ($result): ?>
        <section class="result-card">
          <div class="result-header">
            <h2>Clone gerado com sucesso</h2>
            <span class="badge"><?= count($result['ctas']) ?> CTA(s) detectado(s)</span>
          </div>

          <?php if (!empty($result['ctas'])): ?>
            <details class="ctas-list" open>
              <summary>CTAs que foram substituidos</summary>
              <ul>
                <?php foreach ($result['ctas'] as $i => $cta): ?>
                  <li>
                    <strong><?= ($i + 1) . '. ' . htmlspecialchars($cta['text']) ?></strong>
                    <span class="cta-type">[<?= htmlspecialchars($cta['type']) ?>]</span>
                    <br>
                    <small>De: <code><?= htmlspecialchars(mb_strimwidth($cta['old_href'], 0, 60, '...')) ?></code></small>
                    <br>
                    <small>Para: <code><?= htmlspecialchars(mb_strimwidth($cta['new_href'], 0, 60, '...')) ?></code></small>
                  </li>
                <?php endforeach; ?>
              </ul>
            </details>
          <?php else: ?>
            <p class="warning">Nenhum CTA de checkout foi detectado. Verifique se a pagina tem botoes com links de checkout (Hotmart, Kiwify, etc).</p>
          <?php endif; ?>

          <div class="output-actions">
            <h3>Como voce quer exportar?</h3>
            <div class="buttons-grid">
              <a class="btn btn-primary" href="download.php?job=<?= urlencode($result['job_id']) ?>&type=html">
                Baixar ZIP (HTML)
              </a>
              <a class="btn btn-secondary" href="download.php?job=<?= urlencode($result['job_id']) ?>&type=wix">
                Pacote para Wix
              </a>
              <a class="btn btn-secondary" href="download.php?job=<?= urlencode($result['job_id']) ?>&type=hostinger">
                Pacote Hostinger
              </a>
              <a class="btn btn-ghost" href="preview.php?job=<?= urlencode($result['job_id']) ?>" target="_blank">
                Ver Preview
              </a>
            </div>
          </div>

          <div class="new-clone">
            <a href="index.php">Fazer outro clone</a>
          </div>
        </section>
      <?php else: ?>
        <form action="process.php" method="POST" class="clone-form" id="cloneForm">
          <div class="form-group">
            <label for="affiliate_link">
              Link do Afiliado (CTA)
              <span class="required">*</span>
            </label>
            <input
              type="url"
              id="affiliate_link"
              name="affiliate_link"
              required
              placeholder="https://go.hotmart.com/SEU_ID?off=..."
              value="<?= htmlspecialchars($_GET['affiliate'] ?? '') ?>"
            >
            <small>Este link substituira todos os botoes de checkout da landing page.</small>
          </div>

          <div class="form-group">
            <label for="source_url">
              URL da Landing Page (opcional)
            </label>
            <input
              type="url"
              id="source_url"
              name="source_url"
              placeholder="https://youtubesemaparecer.com.br/"
            >
            <small>Se o site for simples, o sistema tenta buscar sozinho. Se for Wix ou SPA, use o modo de colar HTML abaixo.</small>
          </div>

          <div class="form-divider">
            <span>OU</span>
          </div>

          <div class="form-group">
            <label for="source_html">
              Cole o HTML da Landing Page
              <span class="required">*</span>
            </label>
            <textarea
              id="source_html"
              name="source_html"
              rows="12"
              placeholder='Abra o site no navegador, pressione Ctrl+U, copie tudo (Ctrl+A, Ctrl+C) e cole aqui.'></textarea>
            <small>
              Dica: No navegador, pressione <kbd>Ctrl</kbd>+<kbd>U</kbd> para ver o codigo-fonte, depois
              <kbd>Ctrl</kbd>+<kbd>A</kbd> e <kbd>Ctrl</kbd>+<kbd>C</kbd> para copiar tudo.
            </small>
          </div>

          <button type="submit" class="btn btn-primary btn-large" id="submitBtn">
            Clonar Landing Page
          </button>

          <p class="form-note">
            Processamento 100% local. Nenhum dado e armazenado em banco. Os arquivos temporarios sao apagados em 1h.
          </p>
        </form>
      <?php endif; ?>
    </main>

    <footer class="app-footer">
      <p>Gerador de Landing Page para Afiliados — MVP gratuito</p>
    </footer>
  </div>

  <div class="loading-overlay" id="loadingOverlay">
    <div class="loading-box">
      <div class="loading-spinner"></div>
      <div class="loading-title">Processando sua landing page</div>
      <div class="loading-step" id="loadingStep">Preparando...</div>
      <div class="progress-bar-track">
        <div class="progress-bar-fill indeterminate" id="progressFill"></div>
      </div>
      <div class="loading-time" id="loadingTime"></div>
    </div>
  </div>

  <script src="assets/app.js"></script>
</body>
</html>
