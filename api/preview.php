<?php

require_once __DIR__ . '/../lib/Config.php';

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

$proxyScript = '';
if (!empty($sourceDomain)) {
    $proxyScript = <<<HTML
<script>
(function() {
  var proxyBase = '/proxy.php?url=';
  var sourceDomain = '{$sourceDomain}';

  function proxyUrl(url) {
    if (!url || url.indexOf('data:') === 0 || url.indexOf('/') === 0 || url.indexOf(proxyBase) === 0) {
      return url;
    }
    if (url.indexOf('//') === 0) {
      url = 'https:' + url;
    }
    if (url.indexOf('http') !== 0) {
      return url;
    }
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

  var style = document.createElement('style');
  style.textContent = '@font-face { font-family: proxied; }';
  document.head.appendChild(style);

  var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      mutation.addedNodes.forEach(function(node) {
        if (node.nodeType === 1) {
          if (node.tagName === 'LINK' && node.rel && node.rel.indexOf('stylesheet') !== -1) {
            var href = node.getAttribute('href');
            if (href && href.indexOf(proxyBase) === -1) {
              node.setAttribute('href', proxyUrl(href));
            }
          }
          if (node.tagName === 'SCRIPT' && node.src) {
            if (node.src.indexOf(proxyBase) === -1) {
              node.src = proxyUrl(node.src);
            }
          }
          if (node.tagName === 'IMG' && node.src) {
            if (node.src.indexOf(proxyBase) === -1) {
              node.src = proxyUrl(node.src);
            }
          }
          if (node.tagName === 'SOURCE' && node.src) {
            if (node.src.indexOf(proxyBase) === -1) {
              node.src = proxyUrl(node.src);
            }
          }
        }
      });
    });
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });

  var origCreateElement = document.createElement.bind(document);
  document.createElement = function(tag) {
    var el = origCreateElement(tag);
    if (tag.toLowerCase() === 'style') {
      var origText = Object.getOwnPropertyDescriptor(CSSStyleDeclaration.prototype, 'textContent');
      if (origText && origText.set) {
        Object.defineProperty(el.style, 'textContent', {
          set: function(value) {
            var regex = /url\\s*\\(\\s*['"]?([^'")]+)['"]?\\s*\\)/gi;
            var proxied = value.replace(regex, function(match, url) {
              return 'url(' + proxyUrl(url) + ')';
            });
            origText.set.call(el.style, proxied);
          },
          get: function() {
            return origText.get.call(el.style);
          }
        });
      }
    }
    return el;
  };
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
