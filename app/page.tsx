'use client';

import { useState, useEffect, useRef, Suspense } from 'react';
import { useSearchParams } from 'next/navigation';

interface CtaInfo {
  old_href: string;
  new_href: string;
  text: string;
  class: string;
  type: string;
}

interface PageData {
  data_b64: string;
  ctas: CtaInfo[];
  affiliate_link: string;
  created_at: string;
}

function HomeContent() {
  const searchParams = useSearchParams();
  const dataB64 = searchParams.get('data');
  const error = searchParams.get('error');

  const [pageData, setPageData] = useState<PageData | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadingStep, setLoadingStep] = useState('Preparando...');
  const [loadingTime, setLoadingTime] = useState(0);
  const timerRef = useRef<NodeJS.Timeout | null>(null);
  const startRef = useRef<number>(0);

  const steps = [
    { text: 'Enviando dados...', time: 0 },
    { text: 'Buscando HTML da pagina...', time: 3 },
    { text: 'Analisando estrutura...', time: 8 },
    { text: 'Detectando botoes de checkout...', time: 12 },
    { text: 'Substituindo CTAs pelo seu link...', time: 16 },
    { text: 'Corrigindo lazy-load...', time: 20 },
    { text: 'Quase pronto...', time: 24 },
  ];

  useEffect(() => {
    if (dataB64) {
      try {
        const decoded = JSON.parse(atob(dataB64));
        setPageData({
          data_b64: dataB64,
          ctas: decoded.metadata?.ctas || [],
          affiliate_link: decoded.metadata?.affiliate_link || '',
          created_at: decoded.metadata?.created_at || new Date().toISOString(),
        });
      } catch {
        // invalid data
      }
    }
  }, [dataB64]);

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLoading(true);
    startRef.current = Date.now();
    let stepIdx = 0;

    timerRef.current = setInterval(() => {
      const elapsed = Math.floor((Date.now() - startRef.current) / 1000);
      setLoadingTime(elapsed);
      while (stepIdx < steps.length - 1 && elapsed >= steps[stepIdx + 1].time) {
        stepIdx++;
        setLoadingStep(steps[stepIdx].text);
      }
    }, 200);

    try {
      const form = e.currentTarget;
      const formData = new FormData(form);
      const data: Record<string, string> = {};
      formData.forEach((value, key) => { data[key] = value as string; });

      const res = await fetch('/api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'process', ...data }),
      });
      const json = await res.json();

      if (json.error) {
        window.location.href = `/?error=${encodeURIComponent(json.error)}`;
        return;
      }

      window.location.href = `/?data=${json.data}`;
    } catch (err: any) {
      window.location.href = `/?error=${encodeURIComponent(err.message)}`;
    }
  };

  const handleDownload = async (type: string) => {
    if (!pageData) return;
    try {
      const res = await fetch('/api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'download', data: pageData.data_b64, type }),
      });
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `landingpage${type !== 'html' ? '-' + type : ''}.zip`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (err) {
      alert('Erro ao baixar');
    }
  };

  const handlePreview = async () => {
    if (!pageData) return;
    try {
      const res = await fetch('/api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'preview', data: pageData.data_b64 }),
      });
      const html = await res.text();
      const w = window.open('', '_blank');
      if (w) {
        w.document.write(html);
        w.document.close();
      }
    } catch (err) {
      alert('Erro ao gerar preview');
    }
  };

  useEffect(() => {
    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
    };
  }, []);

  return (
    <div className="app">
      <header className="app-header">
        <h1>Gerador de Landing Page para Afiliados</h1>
        <p className="subtitle">Cole o HTML de uma landing page + seu link de afiliado e receba o clone pronto para hospedar.</p>
      </header>

      <main className="app-main">
        {error && (
          <div className="alert alert-error">
            <strong>Erro:</strong> {error}
          </div>
        )}

        {pageData ? (
          <section className="result-card">
            <div className="result-header">
              <h2>Clone gerado com sucesso</h2>
              <span className="badge">{pageData.ctas.length} CTA(s) detectado(s)</span>
            </div>

            <div className="output-actions">
              <h3>Como voce quer exportar?</h3>
              <div className="buttons-grid">
                <button className="btn btn-primary" onClick={() => handleDownload('html')}>
                  Baixar ZIP (HTML)
                </button>
                <button className="btn btn-secondary" onClick={() => handleDownload('wix')}>
                  Pacote para Wix
                </button>
                <button className="btn btn-secondary" onClick={() => handleDownload('hostinger')}>
                  Pacote Hostinger
                </button>
                <button className="btn btn-ghost" onClick={handlePreview}>
                  Ver Preview
                </button>
              </div>
            </div>

            <div className="new-clone">
              <a href="/">Fazer outro clone</a>
            </div>
          </section>
        ) : (
          <form action="/api" method="POST" className="clone-form" id="cloneForm" onSubmit={handleSubmit}>
            <div className="form-group">
              <label htmlFor="affiliate_link">
                Link do Afiliado (CTA)
                <span className="required">*</span>
              </label>
              <input
                type="url"
                id="affiliate_link"
                name="affiliate_link"
                required
                placeholder="https://go.hotmart.com/SEU_ID?off=..."
              />
              <small>Este link substituira todos os botoes de checkout da landing page.</small>
            </div>

            <div className="form-group">
              <label htmlFor="source_url">URL da Landing Page (opcional)</label>
              <input
                type="url"
                id="source_url"
                name="source_url"
                placeholder="https://youtubesemaparecer.com.br/"
              />
              <small>Se o site for simples, o sistema tenta buscar sozinho. Se for Wix ou SPA, use o modo de colar HTML abaixo.</small>
            </div>

            <div className="form-divider"><span>OU</span></div>

            <div className="form-group">
              <label htmlFor="source_html">
                Cole o HTML da Landing Page
                <span className="required">*</span>
              </label>
              <textarea
                id="source_html"
                name="source_html"
                rows={12}
                placeholder='Abra o site no navegador, pressione Ctrl+U, copie tudo (Ctrl+A, Ctrl+C) e cole aqui.'
              />
              <small>
                Dica: No navegador, pressione <kbd>Ctrl</kbd>+<kbd>U</kbd> para ver o codigo-fonte, depois{' '}
                <kbd>Ctrl</kbd>+<kbd>A</kbd> e <kbd>Ctrl</kbd>+<kbd>C</kbd> para copiar tudo.
              </small>
            </div>

            <button type="submit" className="btn btn-primary btn-large" id="submitBtn">
              Clonar Landing Page
            </button>

            <p className="form-note">
              Processamento 100% local. Nenhum dado e armazenado em banco.
            </p>
          </form>
        )}
      </main>

      <footer className="app-footer">
        <p>Gerador de Landing Page para Afiliados — MVP gratuito</p>
      </footer>

      <div className={`loading-overlay ${loading ? 'active' : ''}`}>
        <div className="loading-box">
          <div className="loading-spinner"></div>
          <div className="loading-title">Processando sua landing page</div>
          <div className="loading-step">{loadingStep}</div>
          <div className="progress-bar-track">
            <div className="progress-bar-fill indeterminate"></div>
          </div>
          <div className="loading-time">{loadingTime}s</div>
        </div>
      </div>
    </div>
  );
}

export default function Home() {
  return (
    <Suspense fallback={<div>Carregando...</div>}>
      <HomeContent />
    </Suspense>
  );
}
