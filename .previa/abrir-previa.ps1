# Abre a previa do Cerveja-Clube no computador (Windows).
#
# Procura o PHP e o banco (MariaDB) da previa nesta ordem:
#   1. .previa\runtime (dentro deste projeto);
#   2. "PROJETO CERVEJA\.preview" ao lado da pasta do projeto (instalacao antiga).
# Se nao achar nenhum dos dois, baixa o PHP e o MariaDB uma unica vez para
# .previa\runtime, cria o banco "cerveja_preview" com o SysVeronica.sql e as
# carnes/bebidas do Clube, e define as senhas da previa. Nas proximas vezes
# so liga o banco e o site.
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$pastaClube = Split-Path $PSScriptRoot -Parent
$sistema    = Join-Path $pastaClube '01-sistema'
$bancoSql   = Join-Path $pastaClube '02-banco-de-dados'
$porta      = 8001
$portaBanco = 3307
$senhaBanco = 'PreviewDb2026'
$nomeBanco  = 'cerveja_preview'
$senhaPrevia = 'Previa@2026'

# Versoes usadas quando a previa precisa ser baixada.
$versaoPhp     = '8.3'
$phpReserva    = 'php-8.3.14-nts-Win32-vs16-x64.zip'
$versaoMariaDb = '11.4.4'

function Parar([string]$mensagem) {
    Write-Host ''
    Write-Host $mensagem -ForegroundColor Red
    Read-Host 'Pressione Enter para fechar'
    exit 1
}

function PortaOcupada([int]$numero) {
    return [bool](Get-NetTCPConnection -LocalPort $numero -State Listen -ErrorAction SilentlyContinue)
}

function Baixar([string[]]$enderecos, [string]$destino) {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    foreach ($endereco in $enderecos) {
        try {
            Write-Host "  baixando $endereco"
            Invoke-WebRequest -Uri $endereco -OutFile $destino -UseBasicParsing
            return
        } catch {
            Write-Host "  nao deu: $($_.Exception.Message)" -ForegroundColor Yellow
        }
    }
    Parar 'Nao consegui baixar os arquivos da previa. Confira a internet e tente de novo.'
}

# Roda um comando SQL no banco da previa (arquivo .sql via "source").
function RodarSql([string]$clienteExe, [string]$comando, [string]$banco) {
    $argumentos = @('-uroot', "-p$senhaBanco", '-h127.0.0.1', "-P$portaBanco", '--default-character-set=utf8mb4')
    if ($banco) { $argumentos += $banco }
    $argumentos += @('-e', $comando)
    & $clienteExe @argumentos
    if ($LASTEXITCODE -ne 0) { Parar "Erro ao preparar o banco da previa ($comando)." }
}

function EsperarBanco() {
    for ($i = 0; $i -lt 40; $i++) {
        if (PortaOcupada $portaBanco) { return }
        Start-Sleep -Seconds 1
    }
    Parar 'O banco de dados da previa nao ligou. Veja o arquivo db.stderr.log dentro de .previa\runtime.'
}

# ---------------------------------------------------------------------------
# 1. Achar (ou instalar) o PHP e o banco.
# ---------------------------------------------------------------------------
$runtimeLocal  = Join-Path $PSScriptRoot 'runtime'
$runtimeAntigo = Join-Path (Split-Path $pastaClube -Parent) 'PROJETO CERVEJA\.preview'

$runtime = $null
foreach ($candidato in @($runtimeLocal, $runtimeAntigo)) {
    if ((Test-Path (Join-Path $candidato 'php\php.exe')) -and (Get-ChildItem (Join-Path $candidato 'mariadb') -Recurse -Filter 'mariadbd.exe' -ErrorAction SilentlyContinue)) {
        $runtime = $candidato
        break
    }
}

if (-not $runtime) {
    Write-Host ''
    Write-Host 'Primeira vez: vou baixar o PHP e o banco da previa (uns 150 MB, so desta vez).' -ForegroundColor Cyan
    $runtime = $runtimeLocal
    New-Item -ItemType Directory -Force -Path $runtime | Out-Null
    $temp = Join-Path $runtime 'download'
    New-Item -ItemType Directory -Force -Path $temp | Out-Null

    # PHP: a versao 8.3 mais nova; se a lista do site falhar, uma versao fixa do arquivo.
    $enderecosPhp = @()
    try {
        $lista = Invoke-RestMethod -Uri 'https://windows.php.net/downloads/releases/releases.json' -UseBasicParsing
        $arquivoAtual = $lista.$versaoPhp.'nts-vs16-x64'.zip.path
        if ($arquivoAtual) { $enderecosPhp += "https://windows.php.net/downloads/releases/$arquivoAtual" }
    } catch { }
    $enderecosPhp += "https://windows.php.net/downloads/releases/archives/$phpReserva"
    $zipPhp = Join-Path $temp 'php.zip'
    Baixar $enderecosPhp $zipPhp
    Expand-Archive -Path $zipPhp -DestinationPath (Join-Path $runtime 'php') -Force

    $zipBanco = Join-Path $temp 'mariadb.zip'
    Baixar @(
        "https://archive.mariadb.org/mariadb-$versaoMariaDb/winx64-packages/mariadb-$versaoMariaDb-winx64.zip",
        "https://downloads.mariadb.org/rest-api/mariadb/$versaoMariaDb/mariadb-$versaoMariaDb-winx64.zip"
    ) $zipBanco
    Expand-Archive -Path $zipBanco -DestinationPath (Join-Path $runtime 'mariadb') -Force
    Remove-Item -Recurse -Force $temp
}

$phpExe = Join-Path $runtime 'php\php.exe'
$dbBin  = (Get-ChildItem (Join-Path $runtime 'mariadb') -Recurse -Filter 'mariadbd.exe' | Select-Object -First 1).DirectoryName
$dbExe  = Join-Path $dbBin 'mariadbd.exe'
$dbCli  = Join-Path $dbBin 'mariadb.exe'
$dbData = Join-Path $runtime 'data'
$dbIni  = Join-Path $dbData 'my.ini'
$phpIni = Join-Path $runtime 'php.ini'

# php.ini com as extensoes que o sistema usa (so cria se nao existir).
if (-not (Test-Path $phpIni)) {
    $pastaExt = (Join-Path $runtime 'php\ext') -replace '\\', '/'
    @(
        "extension_dir = `"$pastaExt`"",
        'extension=curl', 'extension=fileinfo', 'extension=gd', 'extension=mbstring',
        'extension=mysqli', 'extension=openssl', 'extension=pdo_mysql',
        'date.timezone = America/Sao_Paulo',
        'memory_limit = 256M', 'upload_max_filesize = 20M', 'post_max_size = 25M',
        'display_errors = Off', 'log_errors = On'
    ) | Set-Content -Path $phpIni -Encoding ASCII
}

# O PHP para Windows precisa do "Visual C++ Redistributable". Sem ele o php.exe nem abre.
& $phpExe -c $phpIni -r 'exit(extension_loaded(''pdo_mysql'') ? 0 : 3);' 2>$null
if ($LASTEXITCODE -ne 0) {
    Start-Process 'https://aka.ms/vs/17/release/vc_redist.x64.exe'
    Parar 'O PHP da previa nao abriu. Instale o "Visual C++ Redistributable" que o navegador acabou de baixar e rode a previa de novo.'
}

# ---------------------------------------------------------------------------
# 2. Banco de dados (porta 3307). Na primeira vez cria e importa o banco.
# ---------------------------------------------------------------------------
$bancoNovo = $false
if (-not (Test-Path (Join-Path $dbData 'mysql'))) {
    Write-Host 'Criando o banco de dados da previa...'
    & (Join-Path $dbBin 'mariadb-install-db.exe') "--datadir=$dbData" "--password=$senhaBanco" "--port=$portaBanco"
    if ($LASTEXITCODE -ne 0) { Parar 'Nao consegui criar o banco de dados da previa.' }
    $bancoNovo = $true
}

if (-not (PortaOcupada $portaBanco)) {
    Write-Host 'Ligando o banco de dados...'
    $argumentosBanco = @('--bind-address=127.0.0.1', "--port=$portaBanco", '--console')
    if (Test-Path $dbIni) { $argumentosBanco = @("--defaults-file=`"$dbIni`"") + $argumentosBanco }
    Start-Process -FilePath $dbExe -ArgumentList $argumentosBanco -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $runtime 'db.stdout.log') -RedirectStandardError (Join-Path $runtime 'db.stderr.log')
    EsperarBanco
}

if ($bancoNovo) {
    Write-Host 'Importando o banco do Clube da Cerveja...'
    RodarSql $dbCli "CREATE DATABASE IF NOT EXISTS $nomeBanco CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" $null
    foreach ($arquivo in @('SysVeronica.sql', 'carnes_e_contato_clube.sql', 'bebidas_clube.sql')) {
        $caminho = (Join-Path $bancoSql $arquivo) -replace '\\', '/'
        RodarSql $dbCli "source $caminho" $nomeBanco
    }
    # Senhas da previa (as mesmas do LEIA-ME). O hash sai do proprio PHP.
    $hash = (& $phpExe -c $phpIni -r "echo password_hash('$senhaPrevia', PASSWORD_DEFAULT);").Trim()
    RodarSql $dbCli "UPDATE usuarios SET senha_hash = '$hash' WHERE email = 'sistema@gmail.com'; UPDATE dev_usuarios SET senha_hash = '$hash', nome = 'Desenvolvedor', email = 'dev@previa.local' WHERE usuario = 'DevMaster';" $nomeBanco
}

# ---------------------------------------------------------------------------
# 3. Site (porta 8001), com o codigo da pasta 01-sistema.
# ---------------------------------------------------------------------------
if (-not (PortaOcupada $porta)) {
    Write-Host 'Ligando o site...'
    $env:DB_HOST = "127.0.0.1;port=$portaBanco"
    $env:DB_NAME = $nomeBanco
    $env:DB_USER = 'root'
    $env:DB_PASS = $senhaBanco
    Start-Process -FilePath $phpExe -ArgumentList '-c', "`"$phpIni`"", '-S', "127.0.0.1:$porta", '-t', "`"$sistema`"" `
        -WindowStyle Hidden -RedirectStandardOutput (Join-Path $PSScriptRoot 'web.stdout.log') -RedirectStandardError (Join-Path $PSScriptRoot 'web.stderr.log')
    for ($i = 0; $i -lt 15; $i++) {
        Start-Sleep -Seconds 1
        if (PortaOcupada $porta) { break }
    }
    if (-not (PortaOcupada $porta)) { Parar 'O site da previa nao ligou. Veja o arquivo web.stderr.log dentro de .previa.' }
}

$url = "http://127.0.0.1:$porta/loja/"
Write-Host ''
Write-Host "Previa no ar: $url" -ForegroundColor Green
Write-Host "Painel do lojista: http://127.0.0.1:$porta/login.php (sistema@gmail.com / $senhaPrevia)"
Write-Host "Painel do desenvolvedor: http://127.0.0.1:$porta/painel_dev/login.php (DevMaster / $senhaPrevia)"
Start-Process $url
