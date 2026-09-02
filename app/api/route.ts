import { NextRequest, NextResponse } from 'next/server';
import { processHtml, fetchUrl } from '@/lib-next/cloner';
import JSZip from 'jszip';

function buildReadme(metadata: any): string {
  const ctas = metadata.ctas || [];
  const link = metadata.affiliate_link || '(nao definido)';
  const created = metadata.created_at || new Date().toISOString();
  let ctaList = '';
  ctas.forEach((cta: any, i: number) => {
    ctaList += `  ${i + 1}. [${cta.type}] ${cta.text}\n     De: ${cta.old_href}\n     Para: ${cta.new_href}\n\n`;
  });
  return `========================================\nCLONE DE LANDING PAGE - AFILIADO\n========================================\n\nGerado em: ${created}\nLink do afiliado aplicado: ${link}\n\nCTAS DETECTADOS E SUBSTITUIDOS (${ctas.length}):\n${ctaList}========================================`;
}

export async function POST(req: NextRequest) {
  try {
    const formData = await req.formData();
    const affiliateLink = (formData.get('affiliate_link') as string || '').trim();
    const sourceUrl = (formData.get('source_url') as string || '').trim();
    const sourceHtml = (formData.get('source_html') as string || '').trim();

    if (!affiliateLink) {
      return NextResponse.redirect(new URL('/?error=Link+obrigatorio', req.url));
    }

    let url = affiliateLink;
    if (!url.match(/^https?:\/\//i)) url = 'https://' + url;

    let html = '';
    if (sourceHtml) {
      html = sourceHtml;
    } else if (sourceUrl) {
      const result = await fetchUrl(sourceUrl);
      if (!result.success) {
        return NextResponse.redirect(new URL(`/?error=${encodeURIComponent(result.error || 'Fetch failed')}`, req.url));
      }
      html = result.html!;
    } else {
      return NextResponse.redirect(new URL('/?error=Forneca+URL+ou+HTML', req.url));
    }

    if (html.length < 200) {
      return NextResponse.redirect(new URL('/?error=HTML+muito+pequeno', req.url));
    }

    const processed = processHtml(html, url);
    const metadata = {
      ctas: processed.ctas,
      affiliate_link: url,
      source_domain: processed.source_domain,
      created_at: new Date().toISOString(),
    };

    const b64 = Buffer.from(JSON.stringify({ html: processed.html, metadata })).toString('base64');
    return NextResponse.redirect(new URL(`/?data=${b64}`, req.url));
  } catch (e: any) {
    return NextResponse.redirect(new URL(`/?error=${encodeURIComponent(e.message)}`, req.url));
  }
}

export async function GET(req: NextRequest) {
  const action = req.nextUrl.searchParams.get('action');
  const dataB64 = req.nextUrl.searchParams.get('data') || '';

  if (action === 'preview' && dataB64) {
    try {
      const data = JSON.parse(Buffer.from(dataB64, 'base64').toString());
      return new NextResponse(data.html, {
        headers: { 'Content-Type': 'text/html; charset=UTF-8' },
      });
    } catch {
      return new NextResponse('Dados invalidos', { status: 400 });
    }
  }

  if (action === 'download' && dataB64) {
    try {
      const data = JSON.parse(Buffer.from(dataB64, 'base64').toString());
      const type = req.nextUrl.searchParams.get('type') || 'html';
      const zip = new JSZip();

      if (type === 'wix') {
        zip.file('landingpage/index.html', data.html);
        zip.file('LEIA-ME-WIX.txt', 'Hospede index.html e faca embed via iframe no Wix.');
      } else if (type === 'hostinger') {
        zip.file('public_html/index.html', data.html);
        zip.file('INSTRUCOES-HOSTINGER.txt', 'Upload da pasta public_html no Hostinger.');
      } else {
        zip.file('index.html', data.html);
        zip.file('LEIA-ME.txt', buildReadme(data.metadata));
      }

      const buffer = await zip.generateAsync({ type: 'uint8array' });
      const filename = `landingpage${type !== 'html' ? '-' + type : ''}.zip`;
      return new NextResponse(new Uint8Array(buffer), {
        headers: {
          'Content-Type': 'application/zip',
          'Content-Disposition': `attachment; filename="${filename}"`,
        },
      });
    } catch {
      return new NextResponse('Dados invalidos', { status: 400 });
    }
  }

  return new NextResponse('Not found', { status: 404 });
}
