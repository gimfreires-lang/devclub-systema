# Abre a previa do Cerveja-Clube no computador.
# Reaproveita o PHP e o banco (MariaDB) que ja estao em "PROJETO CERVEJA\.preview".
$ErrorActionPreference = 'Stop'

$pastaClube = Split-Path $PSScriptRoot -Parent
$desktop    = Split-Path $pastaClube -Parent
$runtime    = Join-Path $desktop 'PROJETO CERVEJA\.preview'
$phpExe     = Join-Path $runtime 'php\php.exe'
$phpIni     = Join-Path $runtime 'php.ini'
$dbExe      = Join-Path $runtime 'mariadb\mariadb-11.4.12-winx64\bin\mariadbd.exe'
$dbIni      = Join-Path $runtime 'data\my.ini'
$sistema    = Join-Path $pastaClube '01-sistema'
$porta      = 8001

if (-not (Test-Path $phpExe) -or -not (Test-Path $dbExe)) {
    Write-Host ''
    Write-Host 'Nao encontrei o PHP/banco da previa em:' -ForegroundColor Red
    Write-Host "  $runtime"
    Write-Host 'A pasta "PROJETO CERVEJA" precisa estar na mesma pasta que "Cerveja-Clube".'
    Read-Host 'Pressione Enter para fechar'
    exit 1
}

# Banco de dados (porta 3307), se ainda nao estiver ligado.
if (-not (Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue)) {
    Write-Host 'Ligando o banco de dados...'
    Start-Process -FilePath $dbExe -ArgumentList "--defaults-file=`"$dbIni`"", '--bind-address=127.0.0.1', '--port=3307', '--console' `
        -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtime 'db.stdout.log') -RedirectStandardError (Join-Path $runtime 'db.stderr.log')
    for ($i = 0; $i -lt 30; $i++) {
        Start-Sleep -Seconds 1
        if (Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue) { break }
    }
}

# Servidor do site (porta 8001), com o codigo da pasta Cerveja-Clube.
if (-not (Get-NetTCPConnection -LocalPort $porta -State Listen -ErrorAction SilentlyContinue)) {
    Write-Host 'Ligando o site...'
    $env:DB_HOST = '127.0.0.1;port=3307'
    $env:DB_NAME = 'cerveja_preview'
    $env:DB_USER = 'root'
    $env:DB_PASS = 'PreviewDb2026'
    Start-Process -FilePath $phpExe -ArgumentList '-c', "`"$phpIni`"", '-S', "127.0.0.1:$porta", '-t', "`"$sistema`"" `
        -WindowStyle Hidden -RedirectStandardOutput (Join-Path $PSScriptRoot 'web.stdout.log') -RedirectStandardError (Join-Path $PSScriptRoot 'web.stderr.log')
    Start-Sleep -Seconds 2
}

$url = "http://127.0.0.1:$porta/loja/"
Write-Host ''
Write-Host "Previa no ar: $url" -ForegroundColor Green
Write-Host "Painel do lojista: http://127.0.0.1:$porta/login.php"
Start-Process $url
