<?php

require_once __DIR__ . '/../lib/Config.php';
require_once __DIR__ . '/../lib/AssetProcessor.php';

$jobId = $_GET['job'] ?? '';

if ($jobId === '' || !preg_match('/^[a-f0-9]{16}$/', $jobId)) {
    http_response_code(400);
    die('Job ID invalido');
}

$jobFile = Config::getJobsDir() . '/' . $jobId . '.json';
if (!file_exists($jobFile)) {
    http_response_code(404);
    die('Job nao encontrado ou expirado. Gere um novo clone.');
}

$jobData = json_decode(file_get_contents($jobFile), true);
if (!$jobData || !isset($jobData['html'])) {
    http_response_code(500);
    die('Dados do job corrompidos');
}

if (isset($jobData['expires_at']) && strtotime($jobData['expires_at']) < time()) {
    @unlink($jobFile);
    http_response_code(410);
    die('Job expirado');
}

$html = $jobData['html'];

$sourceDomain = '';
if (preg_match('#https?://([a-zA-Z0-9.-]+)#', $html, $m)) {
    $sourceDomain = $m[1];
}

$html = preg_replace('#<link[^>]+href=["\'][^"\']*font-awesome[^"\']*["\'][^>]*/?>#i', '', $html);
$html = preg_replace('#<link[^>]+href=["\'][^"\']*fontawesome[^"\']*["\'][^>]*/?>#i', '', $html);
$html = preg_replace('#<link[^>]+href=["\'][^"\']*\/all\.min\.css[^"\']*["\'][^>]*/?>#i', '', $html);

$fontAwesomeCDN = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">';
$googleFontsCDN = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';

$html = preg_replace('/<\/head>/i', "{$fontAwesomeCDN}\n{$googleFontsCDN}\n</head>", $html, 1);

$proxyScript = '';
if (!empty($sourceDomain)) {
    $proxyScript = <<<HTML
<script>
(function() {
  var proxyBase = '/proxy.php?url=';
  var sourceDomain = '{$sourceDomain}';

  var blockedDomains = [
    'google-analytics.com', 'googletagmanager.com', 'google.com', 'googleapis.com',
    'facebook.net', 'facebook.com', 'doubleclick.net',
    'cdnjs.cloudflare.com',
    'taboola.com', 'outbrain.com', 'hotjar.com',
    'cloudflareinsights.com', 'youtube.com',
    'googlesyndication.com', 'googleadservices.com'
  ];

  function shouldProxy(url) {
    try {
      var parsed = new URL(url, window.location.href);
      var host = parsed.hostname;
      if (host === sourceDomain || host.endsWith('.' + sourceDomain)) {
        return true;
      }
      for (var i = 0; i < blockedDomains.length; i++) {
        if (host === blockedDomains[i] || host.endsWith('.' + blockedDomains[i])) {
          return false;
        }
      }
      return false;
    } catch (e) {
      return false;
    }
  }

  function proxyUrl(url) {
    if (!url) return url;
    if (url.indexOf('data:') === 0) return url;
    if (url.indexOf(proxyBase) === 0) return url;
    if (url.indexOf('//') === 0) url = 'https:' + url;
    if (url.indexOf('http') !== 0) return url;
    if (!shouldProxy(url)) return url;
    return proxyBase + encodeURIComponent(url);
  }

  var origFetch = window.fetch;
  window.fetch = function() {
    var args = Array.prototype.slice.call(arguments);
    if (args[0] && typeof args[0] === 'string') {
      args[0] = proxyUrl(args[0]);
    }
    return origFetch.apply(this, args);
  };

  var origOpen = XMLHttpRequest.prototype.open;
  XMLHttpRequest.prototype.open = function() {
    var args = Array.prototype.slice.call(arguments);
    if (args[1] && typeof args[1] === 'string') {
      args[1] = proxyUrl(args[1]);
    }
    return origOpen.apply(this, args);
  };

  var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      mutation.addedNodes.forEach(function(node) {
        if (node.nodeType !== 1) return;
        if (node.tagName === 'LINK' && node.rel && node.rel.indexOf('stylesheet') !== -1) {
          var href = node.getAttribute('href');
          if (href && href.indexOf(proxyBase) === -1) {
            node.setAttribute('href', proxyUrl(href));
          }
        }
        if (node.tagName === 'SCRIPT' && node.src) {
          if (node.src.indexOf(proxyBase) === -1) {
            var proxied = proxyUrl(node.src);
            if (proxied !== node.src) node.src = proxied;
          }
        }
        if (node.tagName === 'IMG' && node.src) {
          if (node.src.indexOf(proxyBase) === -1) {
            var proxied = proxyUrl(node.src);
            if (proxied !== node.src) node.src = proxied;
          }
        }
      });
    });
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });
})();
</script>
HTML;
}

$html = preg_replace('/<head([^>]*)>/i', "<head\$1>\n{$proxyScript}", $html, 1);

header('Content-Type: text/html; charset=UTF-8');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');

echo $html;
exit;
