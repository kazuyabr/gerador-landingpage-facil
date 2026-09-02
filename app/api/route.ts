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
    const body = await req.json();
    const action = body.action || 'process';

    if (action === 'process') {
      const affiliateLink = (body.affiliate_link || '').trim();
      const sourceUrl = (body.source_url || '').trim();
      const sourceHtml = (body.source_html || '').trim();

      if (!affiliateLink) {
        return NextResponse.json({ error: 'Link obrigatorio' }, { status: 400 });
      }

      let url = affiliateLink;
      if (!url.match(/^https?:\/\//i)) url = 'https://' + url;

      let html = '';
      if (sourceHtml) {
        html = sourceHtml;
      } else if (sourceUrl) {
        const result = await fetchUrl(sourceUrl);
        if (!result.success) {
          return NextResponse.json({ error: result.error || 'Fetch failed' }, { status: 400 });
        }
        html = result.html!;
      } else {
        return NextResponse.json({ error: 'Forneca URL ou HTML' }, { status: 400 });
      }

      if (html.length < 200) {
        return NextResponse.json({ error: 'HTML muito pequeno' }, { status: 400 });
      }

      const processed = processHtml(html, url);
      const metadata = {
        ctas: processed.ctas,
        affiliate_link: url,
        source_domain: processed.source_domain,
        created_at: new Date().toISOString(),
      };

      const b64 = Buffer.from(JSON.stringify({ html: processed.html, metadata })).toString('base64');
      return NextResponse.json({ data: b64 });
    }

    if (action === 'download' || action === 'preview') {
      const dataB64 = body.data || '';
      if (!dataB64) {
        return NextResponse.json({ error: 'Dados obrigatorios' }, { status: 400 });
      }

      const data = JSON.parse(Buffer.from(dataB64, 'base64').toString());

      if (action === 'preview') {
        return new NextResponse(data.html, {
          headers: { 'Content-Type': 'text/html; charset=UTF-8' },
        });
      }

      const type = body.type || 'html';
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
    }

    return NextResponse.json({ error: 'Acao invalida' }, { status: 400 });
  } catch (e: any) {
    return NextResponse.json({ error: e.message }, { status: 500 });
  }
}
