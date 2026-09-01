# Teste E2E completo do Gerador de Landing Page
# Usa o HTML real de teste que está em jobs/test-input.html

$ErrorActionPreference = "Stop"
$baseUrl = "http://localhost:8765"

# Limpa cookies anteriores
$cookieFile = "cookies.txt"
if (Test-Path $cookieFile) { Remove-Item $cookieFile }

Write-Host "=== Teste E2E do Gerador de Landing Page ===" -ForegroundColor Cyan
Write-Host ""

# 1. GET inicial para criar sessão
Write-Host "1. GET index.php" -NoNewline
& curl.exe -s -c $cookieFile -b $cookieFile -o init.html -w "  Status: %{http_code}`n" "$baseUrl/index.php"

# 2. POST com HTML real
Write-Host "2. POST process.php com HTML realista" -NoNewline
& curl.exe -s -c $cookieFile -b $cookieFile -X POST `
    --data-urlencode "affiliate_link=https://go.hotmart.com/MEU_LINK_TESTE_E2E?off=e2e-final" `
    --data-urlencode "source_url=" `
    --data-urlencode "source_html@jobs/test-input.html" `
    -o post-response.html -w "  Status: %{http_code} | Redirect: %{redirect_url}`n" "$baseUrl/process.php"

# 3. GET index.php para ver resultado
Write-Host "3. GET index.php" -NoNewline
& curl.exe -s -c $cookieFile -b $cookieFile -o result.html -w "  Status: %{http_code}`n" "$baseUrl/index.php"

# Verifica se apareceu o resultado
if (Select-String -Path "result.html" -Pattern "Clone gerado com sucesso" -Quiet) {
    Write-Host "   ✅ Resultado aparece" -ForegroundColor Green

    $matches = [regex]::Match((Get-Content "result.html" -Raw), 'href="download\.php\?job=([a-f0-9]+)')
    if ($matches.Success) {
        $jobId = $matches.Groups[1].Value
        Write-Host "   Job ID: $jobId" -ForegroundColor Green

        # 4. Download ZIP HTML
        Write-Host ""
        Write-Host "4. Downloads:" -ForegroundColor Yellow
        & curl.exe -s -o "dl-html.zip" -w "   HTML ZIP:        Status=%{http_code} Size=%{size_download}b`n" "$baseUrl/download.php?job=$jobId&type=html"
        & curl.exe -s -o "dl-wix.zip" -w "   Wix ZIP:         Status=%{http_code} Size=%{size_download}b`n" "$baseUrl/download.php?job=$jobId&type=wix"
        & curl.exe -s -o "dl-host.zip" -w "   Hostinger ZIP:   Status=%{http_code} Size=%{size_download}b`n" "$baseUrl/download.php?job=$jobId&type=hostinger"

        # 5. Preview
        Write-Host ""
        Write-Host "5. Preview do clone:" -ForegroundColor Yellow
        & curl.exe -s -o "preview.html" -w "   Status=%{http_code} Size=%{size_download}b`n" "$baseUrl/preview.php?job=$jobId"

        # 6. Validações
        Write-Host ""
        Write-Host "6. Validações:" -ForegroundColor Yellow
        $previewContent = Get-Content "preview.html" -Raw

        $linkCount = ([regex]::Matches($previewContent, "MEU_LINK_TESTE_E2E")).Count
        Write-Host "   Link do afiliado no preview: $linkCount vez(es)" -ForegroundColor $(if ($linkCount -ge 4) { "Green" } else { "Red" })

        $hasDoctype = $previewContent.StartsWith("<!DOCTYPE")
        Write-Host "   Começa com <!DOCTYPE>: $hasDoctype" -ForegroundColor $(if ($hasDoctype) { "Green" } else { "Red" })

        $hasOriginal = ([regex]::Matches($previewContent, "data-original-href")).Count
        Write-Host "   data-original-href presente: $hasOriginal" -ForegroundColor $(if ($hasOriginal -ge 4) { "Green" } else { "Yellow" })

        # 7. Extrair e mostrar ZIP
        Write-Host ""
        Write-Host "7. Estrutura do ZIP HTML:" -ForegroundColor Yellow
        if (Test-Path "dl-html.zip") {
            Expand-Archive -Path "dl-html.zip" -DestinationPath "extract-html" -Force
            Get-ChildItem "extract-html" | ForEach-Object {
                Write-Host "   $($_.Name) ($($_.Length) bytes)"
            }
        }

        Write-Host ""
        Write-Host "8. Estrutura do ZIP Wix:" -ForegroundColor Yellow
        if (Test-Path "dl-wix.zip") {
            Expand-Archive -Path "dl-wix.zip" -DestinationPath "extract-wix" -Force
            Get-ChildItem "extract-wix" -Recurse | ForEach-Object {
                $relative = $_.FullName.Substring($_.FullName.IndexOf("extract-wix") + 11)
                Write-Host "   $relative ($($_.Length) bytes)"
            }
        }

        Write-Host ""
        Write-Host "9. Estrutura do ZIP Hostinger:" -ForegroundColor Yellow
        if (Test-Path "dl-host.zip") {
            Expand-Archive -Path "dl-host.zip" -DestinationPath "extract-host" -Force
            Get-ChildItem "extract-host" -Recurse | ForEach-Object {
                $relative = $_.FullName.Substring($_.FullName.IndexOf("extract-host") + 12)
                Write-Host "   $relative ($($_.Length) bytes)"
            }
        }
    }
} else {
    Write-Host "   ❌ Erro" -ForegroundColor Red
    $errorMatch = [regex]::Match((Get-Content "result.html" -Raw), 'Erro:</strong>\s*([^<]+)')
    if ($errorMatch.Success) {
        Write-Host "   Mensagem: $($errorMatch.Groups[1].Value.Trim())" -ForegroundColor Yellow
    } else {
        Write-Host "   Conteúdo:" -ForegroundColor Yellow
        Get-Content "result.html" | Select-Object -First 30
    }
}

# Limpa
$filesToRemove = @("cookies.txt", "init.html", "post-response.html", "result.html",
    "dl-html.zip", "dl-wix.zip", "dl-host.zip", "preview.html",
    "extract-html", "extract-wix", "extract-host")
foreach ($f in $filesToRemove) {
    if (Test-Path $f) { Remove-Item $f -Recurse -Force -ErrorAction SilentlyContinue }
}

Write-Host ""
Write-Host "=== FIM DO TESTE ===" -ForegroundColor Cyan
