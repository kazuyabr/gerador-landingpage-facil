import * as cheerio from 'cheerio';

const CHECKOUT_DOMAINS = [
  'hotmart', 'kiwify', 'eduzz', 'braip', 'monetizze', 'cartpanda',
  'payt.com.br', 'ticto', 'nutror', 'perfectpay', 'lastlink',
  'mindminers', 'vendd', 'klickmembers', 'membertoo',
];

const CTA_CLASSES = [
  'elementor-button', 'cta', 'btn-primary', 'btn-cta',
  'buy-button', 'checkout-btn',
];

const CTA_KEYWORDS = [
  'comprar', 'quero', 'adquirir', 'garantir', 'entrar',
  'iniciar', 'começar', 'acesso', 'aproveitar', 'resgatar',
  'buy', 'get', 'start', 'join', 'access', 'claim',
];

export interface CtaInfo {
  old_href: string;
  new_href: string;
  text: string;
  class: string;
  type: string;
}

export interface ProcessResult {
  html: string;
  ctas: CtaInfo[];
  source_domain: string;
}

function isCheckoutLink(href: string): boolean {
  const lower = href.toLowerCase();
  return CHECKOUT_DOMAINS.some(d => lower.includes(d));
}

function looksLikeCta($el: any): boolean {
  const cls = ($el.attr('class') || '').toLowerCase();
  if (CTA_CLASSES.some(c => cls.includes(c))) return true;
  const text = $el.text().trim().toLowerCase();
  return CTA_KEYWORDS.some(kw => text.includes(kw));
}

export function processHtml(html: string, affiliateLink: string): ProcessResult {
  const $ = cheerio.load(html, { xmlMode: false });
  const ctas: CtaInfo[] = [];

  $('a[href]').each((_, el) => {
    const $a = $(el);
    const href = $a.attr('href') || '';
    const text = $a.text().replace(/\s+/g, ' ').trim();

    if (!href || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

    const isExternal = isCheckoutLink(href);
    const isCta = looksLikeCta($a);
    const isAnchor = href.startsWith('#');

    if (!isExternal && !isCta) return;
    if (isAnchor && !isCta) return;

    ctas.push({
      old_href: href,
      new_href: affiliateLink,
      text: text || '(sem texto)',
      class: $a.attr('class') || '',
      type: isExternal ? 'checkout' : 'button',
    });

    $a.attr('href', affiliateLink);
    $a.attr('data-cloned-cta', 'true');
    $a.attr('data-original-href', href);
  });

  $('button').each((_, el) => {
    const $btn = $(el);
    if (!looksLikeCta($btn)) return;
    const text = $btn.text().replace(/\s+/g, ' ').trim();
    ctas.push({
      old_href: '(button)',
      new_href: affiliateLink,
      text: text || '(sem texto)',
      class: $btn.attr('class') || '',
      type: 'button-no-href',
    });
    $btn.attr('onclick', `window.location.href='${affiliateLink.replace(/'/g, "\\'")}'`);
    $btn.attr('data-cloned-cta', 'true');
  });

  // Fix lazy-load CSS
  $('style').each((_, el) => {
    const content = $(el).html() || '';
    if (/e-con\.e-parent.*background-image\s*:\s*none/is.test(content)) {
      $(el).remove();
    }
  });

  $('head').append(`<style data-cloned-fix="true">
    .e-con.e-parent, .e-con.e-parent * { background-image: revert !important; }
    .e-con { background-image: revert !important; }
    .e-con * { background-image: revert !important; }
  </style>`);

  $('img[loading="lazy"]').attr('loading', 'eager');

  $('img[data-src]').each((_, el) => {
    const $img = $(el);
    const src = $img.attr('data-src');
    if (src) $img.attr('src', src);
    $img.removeAttr('data-src');
    $img.removeAttr('data-srcset');
    $img.removeAttr('data-sizes');
    $img.removeAttr('loading');
  });

  $('[style]').each((_, el) => {
    const style = $(el).attr('style') || '';
    const cleaned = style.replace(/background-image\s*:\s*none\s*(!important)?\s*;?\s*/gi, '');
    if (cleaned.trim() === '') {
      $(el).removeAttr('style');
    } else {
      $(el).attr('style', cleaned.trim());
    }
  });

  $('script').each((_, el) => {
    const src = $(el).attr('src') || '';
    const content = $(el).html() || '';
    if (src.includes('lazyload') || src.includes('lazy-load') ||
        content.includes('lazyloadRunObserver') || content.includes('e-lazyloaded')) {
      $(el).remove();
    }
  });

  let processedHtml = $.html();
  processedHtml = processedHtml.replace(/<\?xml[^>]*\?>\s*/i, '');
  processedHtml = processedHtml.replace(/<!doctype[^>]*>/i, '<!DOCTYPE html>');
  processedHtml = processedHtml.replace(/[\uFEFF]/g, '');

  const domainMatch = html.match(/https?:\/\/([a-zA-Z0-9.-]+)/);

  return {
    html: processedHtml,
    ctas,
    source_domain: domainMatch?.[1] || '',
  };
}

export async function fetchUrl(url: string): Promise<{ success: boolean; html?: string; error?: string }> {
  try {
    const res = await fetch(url, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language': 'pt-BR,pt;q=0.9,en;q=0.8',
      },
      redirect: 'follow',
      signal: AbortSignal.timeout(60000),
    });

    if (!res.ok) {
      return { success: false, error: `HTTP ${res.status}` };
    }

    const html = await res.text();
    return { success: true, html };
  } catch (e: any) {
    return { success: false, error: e.message || 'Fetch failed' };
  }
}
