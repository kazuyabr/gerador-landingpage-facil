import { NextRequest, NextResponse } from 'next/server';
import { processHtml, fetchUrl } from '@/lib/cloner';
import { writeFile, mkdir, readdir, unlink, stat } from 'fs/promises';
import { join } from 'path';

const JOBS_DIR = '/tmp/jobs';

async function ensureJobsDir() {
  try {
    await mkdir(JOBS_DIR, { recursive: true });
  } catch {}
}

async function cleanExpiredJobs() {
  try {
    const files = await readdir(JOBS_DIR);
    for (const file of files) {
      if (!file.endsWith('.json')) continue;
      try {
        const content = await readFile(join(JOBS_DIR, file));
        const job = JSON.parse(content);
        if (new Date(job.expires_at) < new Date()) {
          await unlink(join(JOBS_DIR, file));
        }
      } catch {}
    }
  } catch {}
}

async function readFile(path: string): Promise<string> {
  const { readFile: rf } = await import('fs/promises');
  return rf(path, 'utf-8');
}

export async function POST(req: NextRequest) {
  try {
    await ensureJobsDir();
    await cleanExpiredJobs();

    const formData = await req.formData();
    const affiliateLink = (formData.get('affiliate_link') as string || '').trim();
    const sourceUrl = (formData.get('source_url') as string || '').trim();
    const sourceHtml = (formData.get('source_html') as string || '').trim();

    if (!affiliateLink) {
      return NextResponse.redirect(new URL('/?error=O+link+do+afiliado+e+obrigatorio.', req.url));
    }

    let url = affiliateLink;
    if (!url.match(/^https?:\/\//i)) {
      url = 'https://' + url;
    }
    if (!URL.canParse(url)) {
      return NextResponse.redirect(new URL('/?error=Link+do+afiliado+invalido.+Use+o+formato+https://...', req.url));
    }

    let html = '';
    let mode = 'paste';

    if (sourceHtml) {
      html = sourceHtml;
      mode = 'paste';
    } else if (sourceUrl) {
      mode = 'url';
      const result = await fetchUrl(sourceUrl);
      if (!result.success) {
        return NextResponse.redirect(new URL(`/?error=${encodeURIComponent('Nao foi possivel buscar a URL. Erro: ' + result.error + '. Tente colar o HTML manualmente.')}`, req.url));
      }
      html = result.html!;
    } else {
      return NextResponse.redirect(new URL('/?error=Forneça+uma+URL+ou+cole+o+HTML+da+landing+page.', req.url));
    }

    if (html.length < 200) {
      return NextResponse.redirect(new URL('/?error=O+HTML+parece+estar+vazio+ou+muito+pequeno.', req.url));
    }

    const result = processHtml(html, affiliateLink);

    const jobId = Array.from(crypto.getRandomValues(new Uint8Array(8))).map(b => b.toString(16).padStart(2, '0')).join('');
    const now = new Date();
    const expires = new Date(now.getTime() + 3600000);

    const jobData = {
      job_id: jobId,
      html: result.html,
      ctas: result.ctas,
      affiliate_link: url,
      source_domain: result.source_domain,
      mode,
      created_at: now.toISOString(),
      expires_at: expires.toISOString(),
    };

    await writeFile(join(JOBS_DIR, `${jobId}.json`), JSON.stringify(jobData, null, 2));

    return NextResponse.redirect(new URL(`/?job=${jobId}`, req.url));
  } catch (e: any) {
    return NextResponse.redirect(new URL(`/?error=${encodeURIComponent('Erro interno: ' + e.message)}`, req.url));
  }
}
