import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'Gerador de Landing Page para Afiliados',
  description: 'Cole o HTML de uma landing page + seu link de afiliado e receba o clone pronto para hospedar.',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="pt-BR">
      <body>{children}</body>
    </html>
  );
}
