import { NextRequest, NextResponse } from 'next/server';
import { processHtml, fetchUrl } from '@/lib-next/cloner';
import { writeFile, readFile, mkdir } from 'fs/promises';
import { join } from 'path';
import JSZip from 'jszip';

const JOBS_DIR = '/tmp/jobs';

async function ensureDir() {
  try { await mkdir(JOBS_DIR, { recursive: true }); } catch {}
}

async function getJob(jobId: string) {
  try {
    const content = await readFile(join(JOBS_DIR, `${jobId}.json`), 'utf-8');
    const job = JSON.parse(content);
    if (new Date(job.expires_at) < new Date()) return null;
    return job;
  } catch { return null; }
}

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

export async function GET(req: NextRequest) {
  const action = req.nextUrl.searchParams.get('action');
  const jobId = req.nextUrl.searchParams.get('job') || '';
  const type = req.nextUrl.searchParams.get('type') || 'html';

  // Preview
  if (action === 'preview') {
    if (!jobId || !/^[a-f0-9]{16}$/.test(jobId)) {
      return new NextResponse('Job ID invalido', { status: 400 });
    }
    const job = await getJob(jobId);
    if (!job) return new NextResponse('Job nao encontrado', { status: 404 });
    return new NextResponse(job.html, {
      headers: { 'Content-Type': 'text/html; charset=UTF-8' },
    });
  }

  // Download
  if (action === 'download') {
    if (!jobId || !/^[a-f0-9]{16}$/.test(jobId)) {
      return new NextResponse('Job ID invalido', { status: 400 });
    }
    const job = await getJob(jobId);
    if (!job) return new NextResponse('Job nao encontrado', { status: 404 });

    const zip = new JSZip();
    if (type === 'wix') {
      zip.file('landingpage/index.html', job.html);
      zip.file('LEIA-ME-WIX.txt', 'Hospede index.html e faca embed via iframe no Wix.');
    } else if (type === 'hostinger') {
      zip.file('public_html/index.html', job.html);
      zip.file('INSTRUCOES-HOSTINGER.txt', 'Upload da pasta public_html no Hostinger.');
    } else {
      zip.file('index.html', job.html);
      zip.file('LEIA-ME.txt', buildReadme(job));
    }

    const buffer = await zip.generateAsync({ type: 'uint8array' });
    const filename = `landingpage${type !== 'html' ? '-' + type : ''}-${jobId}.zip`;
    return new NextResponse(new Uint8Array(buffer), {
      headers: {
        'Content-Type': 'application/zip',
        'Content-Disposition': `attachment; filename="${filename}"`,
      },
    });
  }

  return new NextResponse('Not found', { status: 404 });
}

export async function POST(req: NextRequest) {
  try {
    await ensureDir();
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

    const result = processHtml(html, url);
    const jobId = Array.from(crypto.getRandomValues(new Uint8Array(8))).map(b => b.toString(16).padStart(2, '0')).join('');
    const now = new Date();

    await writeFile(join(JOBS_DIR, `${jobId}.json`), JSON.stringify({
      job_id: jobId, html: result.html, ctas: result.ctas,
      affiliate_link: url, source_domain: result.source_domain,
      created_at: now.toISOString(), expires_at: new Date(now.getTime() + 3600000).toISOString(),
    }, null, 2));

    return NextResponse.redirect(new URL(`/?job=${jobId}`, req.url));
  } catch (e: any) {
    return NextResponse.redirect(new URL(`/?error=${encodeURIComponent(e.message)}`, req.url));
  }
}
