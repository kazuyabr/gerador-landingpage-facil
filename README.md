# Gerador de Landing Page para Afiliados

Ferramenta PHP que clona landing pages e substitui CTAs por links de afiliado. Recebe uma URL ou HTML colado, processa localmente e gera um pacote pronto para hospedar.

## Como funciona

1. Cole a URL **ou** o HTML da landing page
2. Cole o link de afiliado
3. Clique em **Clonar Landing Page**
4. Escolha como exportar:
   - **ZIP (HTML)** — pacote único pronto pra subir
   - **Pacote Wix** — landingpage + arquivo de embed via iframe
   - **Pacote Hostinger** — estrutura `public_html/` + instruções
   - **Preview** — abre a versão clonada em nova aba

## Recursos

- ✅ Detecta CTAs em links externos (Hotmart, Kiwify, Eduzz, Braip, etc)
- ✅ Detecta botões com classes `elementor-button`, `cta`, `btn-primary`, etc
- ✅ Mantém âncoras internas (`#preco`) intactas
- ✅ Substitui APENAS o href, preservando todo o HTML/CSS/JS
- ✅ Funciona com sites Wix, WordPress, Elementor, estáticos
- ✅ Processamento 100% local — nenhum dado armazenado em banco
- ✅ Jobs expiram em 1 hora (limpeza automática)
- ✅ Download em ZIP, embed Wix, pacote Hostinger
- ✅ Suporte a drag-and-drop de arquivo HTML

## Rodar com Docker

```bash
docker-compose up --build
```

Acesse: `http://localhost:8080`

## Rodar com PHP local (8.2+)

```bash
php -S localhost:8080 -t public public/router.php
```

Acesse: `http://localhost:8080`

## Deploy na Vercel

```bash
vercel deploy
```

Configuração em `vercel.json` já está pronta.

## Deploy em Hospedagem Comum (Hostinger, Locaweb, cPanel)

Faça upload da pasta `public/` para o `public_html/` do seu servidor. Os jobs expiram em 1h e são apagados automaticamente.

## Estrutura de arquivos

```
gerador-landingpage-facil/
├── public/              # Pasta pública (document root)
│   ├── index.php       # UI principal
│   ├── process.php     # Processa POST
│   ├── download.php    # Força download ZIP
│   ├── preview.php     # Preview do clone
│   ├── router.php      # Router do PHP built-in server
│   └── assets/         # CSS e JS
├── lib/                # Classes PHP
│   ├── Cloner.php     # Detecta e substitui CTAs
│   └── ZipBuilder.php # Empacota ZIP (HTML, Wix, Hostinger)
├── jobs/               # Jobs temporários (auto-expira em 1h)
├── Dockerfile
├── docker-compose.yml
└── vercel.json
```

## Casos de uso

### Caso 1: Site simples (WordPress, Elementor, estático)
Cole a URL, o sistema busca o HTML e processa.

### Caso 2: Wix, SPAs complexas
Abra o site no navegador, `Ctrl+U` (ver código-fonte), `Ctrl+A` + `Ctrl+C`, cole no campo HTML.

### Caso 3: Já tem o HTML salvo em arquivo
Arraste o arquivo `.html` para o campo de texto.

## Limitações

- SPAs com JavaScript pesado podem ter partes renderizadas dinamicamente que não aparecem no HTML estático
- Imagens continuam sendo carregadas dos servidores originais (não é feito download)
- Para o Wix embed funcionar, você precisa hospedar a landingpage em algum lugar (Vercel, Netlify, etc) e atualizar a URL no arquivo `wix-embed.html`

## Licença

MVP gratuito. Use à vontade.
