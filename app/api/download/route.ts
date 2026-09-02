import { NextRequest, NextResponse } from 'next/server';
import { readFile } from 'fs/promises';
import { join } from 'path';
import JSZip from 'jszip';

const JOBS_DIR = '/tmp/jobs';

async function getJob(jobId: string) {
  try {
    const content = await readFile(join(JOBS_DIR, `${jobId}.json`), 'utf-8');
    const job = JSON.parse(content);
    if (new Date(job.expires_at) < new Date()) return null;
    return job;
  } catch {
    return null;
  }
}

function buildReadme(metadata: any): string {
  const ctas = metadata.ctas || [];
  const link = metadata.affiliate_link || '(nao definido)';
  const created = metadata.created_at || new Date().toISOString();

  let ctaList = '';
  ctas.forEach((cta: any, i: number) => {
    ctaList += `  ${i + 1}. [${cta.type}] ${cta.text}\n`;
    ctaList += `     De: ${cta.old_href}\n`;
    ctaList += `     Para: ${cta.new_href}\n\n`;
  });

  return `========================================
CLONE DE LANDING PAGE - AFILIADO
========================================

Gerado em: ${created}
Link do afiliado aplicado: ${link}

CTAS DETECTADOS E SUBSTITUIDOS (${ctas.length}):
${ctaList}
COMO USAR:
----------
1. Faca upload de TODOS os arquivos para sua hospedagem
2. Mantenha a estrutura de pastas
3. Pode ser hospedagem comum (Hostinger, Locaweb, etc)
4. Os links de CTA ja estao apontando para seu link de afiliado

========================================`;
}

export async function GET(req: NextRequest) {
  const jobId = req.nextUrl.searchParams.get('job') || '';
  const type = req.nextUrl.searchParams.get('type') || 'html';

  if (!jobId || !/^[a-f0-9]{16}$/.test(jobId)) {
    return new NextResponse('Job ID invalido', { status: 400 });
  }

  const job = await getJob(jobId);
  if (!job) {
    return new NextResponse('Job nao encontrado ou expirado', { status: 404 });
  }

  const zip = new JSZip();
  const readme = buildReadme(job);

  switch (type) {
    case 'wix': {
      zip.file('landingpage/index.html', job.html);
      zip.file('LEIA-ME-WIX.txt', `========================================
COMO USAR NO WIX
========================================
1. Hospede o arquivo landingpage/index.html
2. No Wix, adicione um elemento HTML iframe
3. Aponte para a URL hospedada
========================================`);
      break;
    }
    case 'hostinger': {
      zip.file('public_html/index.html', job.html);
      zip.file('public_html/.htaccess', `# Basic cache and compression
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json
</IfModule>

<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 1 month"
  ExpiresByType application/javascript "access plus 1 month"
  ExpiresByType image/png "access plus 1 year"
  ExpiresByType image/jpg "access plus 1 year"
  ExpiresByType image/jpeg "access plus 1 year"
  ExpiresByType image/webp "access plus 1 year"
  ExpiresByType image/svg+xml "access plus 1 year"
</IfModule>

AddDefaultCharset UTF-8
Options -Indexes`);
      zip.file('INSTRUCOES-HOSTINGER.txt', `========================================
COMO SUBIR NO HOSTINGER
========================================
1. Acesse hpanel.hostinger.com
2. Va em "Gerenciador de Arquivos"
3. Navegue ate public_html
4. Faca upload dos arquivos da pasta public_html
========================================`);
      break;
    }
    case 'html':
    default: {
      zip.file('index.html', job.html);
      zip.file('LEIA-ME.txt', readme);
      break;
    }
  }

  const buffer = await zip.generateAsync({ type: 'uint8array' });
  const filename = type === 'html' ? `landingpage-${jobId}.zip` :
                   type === 'wix' ? `landingpage-wix-${jobId}.zip` :
                   `landingpage-hostinger-${jobId}.zip`;

  return new NextResponse(new Uint8Array(buffer), {
    headers: {
      'Content-Type': 'application/zip',
      'Content-Disposition': `attachment; filename="${filename}"`,
      'Cache-Control': 'no-cache, must-revalidate',
    },
  });
}
