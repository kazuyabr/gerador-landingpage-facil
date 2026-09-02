import { NextRequest, NextResponse } from 'next/server';
import { readFile } from 'fs/promises';
import { join } from 'path';

const JOBS_DIR = '/tmp/jobs';

export async function GET(req: NextRequest) {
  const jobId = req.nextUrl.searchParams.get('job') || '';

  if (!jobId || !/^[a-f0-9]{16}$/.test(jobId)) {
    return new NextResponse('Job ID invalido', { status: 400 });
  }

  try {
    const content = await readFile(join(JOBS_DIR, `${jobId}.json`), 'utf-8');
    const job = JSON.parse(content);

    if (new Date(job.expires_at) < new Date()) {
      return new NextResponse('Job expirado', { status: 410 });
    }

    return new NextResponse(job.html, {
      headers: {
        'Content-Type': 'text/html; charset=UTF-8',
        'X-Frame-Options': 'SAMEORIGIN',
      },
    });
  } catch {
    return new NextResponse('Job nao encontrado ou expirado', { status: 404 });
  }
}
