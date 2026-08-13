<#
    installer\build.ps1
    Stages the portable stack + application, then compiles the installer.

    Usage:
        powershell -ExecutionPolicy Bypass -File build.ps1
        powershell -ExecutionPolicy Bypass -File build.ps1 -StageOnly

    Result: installer\dist\EmployeeInformationSystem_Setup.exe
#>

[CmdletBinding()]
param(
    [string] $ApacheSource,
    [string] $PhpSource,
    [string] $DbSource,
    [switch] $StageOnly
)

$ErrorActionPreference = 'Stop'
$root      = Split-Path -Parent $MyInvocation.MyCommand.Path
$appRoot   = Split-Path -Parent $root
$buildDir  = Join-Path $root 'build'
$distDir   = Join-Path $root 'dist'

function Say([string]$msg, [string]$color = 'Cyan') { Write-Host "  $msg" -ForegroundColor $color }
function Fail([string]$msg) { Write-Host "  ERROR: $msg" -ForegroundColor Red; exit 1 }

Write-Host "`n=== Employee Information System - installer build ===`n" -ForegroundColor Yellow

# ---------------------------------------------------------------- 1. sources
function Find-First([string[]]$patterns, [string]$mustContain) {
    foreach ($p in $patterns) {
        foreach ($d in (Get-Item $p -ErrorAction SilentlyContinue)) {
            if (Test-Path (Join-Path $d.FullName $mustContain)) { return $d.FullName }
        }
    }
    return $null
}

if (-not $ApacheSource) {
    $ApacheSource = Find-First @('C:\laragon\bin\apache\*') 'bin\httpd.exe'
}
if (-not $PhpSource) {
    $PhpSource = Find-First @('C:\laragon\bin\php\*') 'php8apache2_4.dll'
}
if (-not $DbSource) {
    $DbSource = Find-First @('C:\laragon\bin\mariadb\*', 'C:\laragon\bin\mysql\*') 'bin\mysqld.exe'
}

if (-not $ApacheSource) { Fail 'Apache not found. Pass -ApacheSource "C:\path\to\httpd-x.y".' }
if (-not $PhpSource)    { Fail 'PHP (thread-safe, with php8apache2_4.dll) not found. Pass -PhpSource.' }
if (-not $DbSource)     { Fail 'MariaDB/MySQL not found. Pass -DbSource.' }

Say "Apache : $ApacheSource"
Say "PHP    : $PhpSource"
Say "DB     : $DbSource"

# ------------------------------------------------------------ 2. clean stage
if (Test-Path $buildDir) { Remove-Item $buildDir -Recurse -Force }
New-Item -ItemType Directory -Force -Path $buildDir, $distDir | Out-Null

# ------------------------------------------------------- 3. copy application
Write-Host "`n[1/5] Staging the application..." -ForegroundColor Yellow
$wwwDir = Join-Path $buildDir 'www'
$exclude = @(
    'installer', '.git', '.claude', 'node_modules',
    'PRE-DOWNGRADE-BACKUP.sql', 'BEFORE-CLEANUP-BACKUP.sql',
    'Start-EIS.bat', 'Stop-EIS.bat'
)
# google_oauth_client.json IS shipped: it identifies the application to Google
# so the Backup page can offer "Connect Google Drive". google_token.json is a
# specific person's authorisation and must never leave this machine.
robocopy $appRoot $wwwDir /E /NFL /NDL /NJH /NJS /NP /XD $exclude `
    /XF 'local.php' 'google_token.json' 'google_credentials.json' | Out-Null

# Ship a clean slate: no local data, no personal authorisation, empty uploads
Get-ChildItem (Join-Path $wwwDir 'uploads') -Recurse -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -ne '.gitkeep' } | Remove-Item -Force
Get-ChildItem (Join-Path $wwwDir 'backups') -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Extension -eq '.sql' } | Remove-Item -Force
$allowedConfig = @('app.php', 'db.php', '.htaccess', 'google_oauth_client.json')
Get-ChildItem (Join-Path $wwwDir 'config') -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -notin $allowedConfig } | Remove-Item -Force
Get-ChildItem $wwwDir -Filter '*BACKUP*.sql' -File -ErrorAction SilentlyContinue | Remove-Item -Force
Say ("application: {0} MB" -f [math]::Round((Get-ChildItem $wwwDir -Recurse -File | Measure-Object Length -Sum).Sum / 1MB, 1))

# --------------------------------------------------------- 4. copy the stack
Write-Host "`n[2/5] Staging Apache, PHP and the database..." -ForegroundColor Yellow
$stackDir = Join-Path $buildDir 'stack'

# Apache: skip manuals, cgi examples and existing configs/logs
robocopy $ApacheSource (Join-Path $stackDir 'apache') /E /NFL /NDL /NJH /NJS /NP `
    /XD 'manual' 'cgi-bin' 'htdocs' 'logs' 'conf' | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $stackDir 'apache\logs'), (Join-Path $stackDir 'apache\conf') | Out-Null
# mime.types is required by httpd.conf
Copy-Item (Join-Path $ApacheSource 'conf\mime.types') (Join-Path $stackDir 'apache\conf\mime.types') -Force

# PHP: skip the shipped inis and any dev folders
robocopy $PhpSource (Join-Path $stackDir 'php') /E /NFL /NDL /NJH /NJS /NP `
    /XF 'php.ini' 'php.ini-development' 'php.ini-production' | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $stackDir 'php\logs') | Out-Null

# Root certificates, so outgoing HTTPS (the Google Drive backup) can verify
# Google's certificate. Without this the bundled PHP fails with cURL error 60.
$sslDir = Join-Path $stackDir 'php\extras\ssl'
New-Item -ItemType Directory -Force -Path $sslDir | Out-Null
Copy-Item (Join-Path $root 'certs\cacert.pem') (Join-Path $sslDir 'cacert.pem') -Force

# Database: binaries + share (error messages / init scripts), never the data dir
robocopy $DbSource (Join-Path $stackDir 'mariadb') /E /NFL /NDL /NJH /NJS /NP `
    /XD 'data' 'test' 'sql-bench' 'mysql-test' 'docs' 'include' 'lib\plugin\debug' | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $stackDir 'mariadb\data'), (Join-Path $stackDir 'tmp') | Out-Null

foreach ($part in 'apache', 'php', 'mariadb') {
    $mb = [math]::Round((Get-ChildItem (Join-Path $stackDir $part) -Recurse -File -ErrorAction SilentlyContinue | Measure-Object Length -Sum).Sum / 1MB, 1)
    Say ("{0,-8}: {1} MB" -f $part, $mb)
}

# ------------------------------------------------------------- 5. scripts
Write-Host "`n[3/5] Staging control scripts..." -ForegroundColor Yellow
$scriptsDir = Join-Path $buildDir 'scripts'
New-Item -ItemType Directory -Force -Path $scriptsDir | Out-Null
Copy-Item (Join-Path $root 'scripts\*') $scriptsDir -Force
Say ("{0} scripts" -f (Get-ChildItem $scriptsDir -File).Count)

# ------------------------------------------- 6. generate the extension block
Write-Host "`n[4/5] Building php.ini extension list..." -ForegroundColor Yellow
$stagedExt = Join-Path $stackDir 'php\ext'
$wanted    = 'curl','fileinfo','gd','mbstring','openssl','pdo_mysql','mysqli','zip','intl','exif','sodium'
$lines     = @()
foreach ($e in $wanted) {
    if (Test-Path (Join-Path $stagedExt "php_$e.dll")) { $lines += "extension=$e" }
}
if (Test-Path (Join-Path $stagedExt 'php_opcache.dll')) { $lines += 'zend_extension=opcache' }

$iniTemplate = Get-Content (Join-Path $root 'templates\php.ini') -Raw
$iniStaged   = $iniTemplate -replace '; \{#EXTENSIONS#\}', ($lines -join "`r`n")
$tmpIni      = Join-Path $buildDir 'php-test.ini'
# The test ini needs real paths, so resolve the placeholder to the build dir
($iniStaged -replace '@@APPDIR@@', ($buildDir -replace '\\','/')) | Set-Content $tmpIni -Encoding ascii
Set-Content (Join-Path $root 'templates\php.ini.generated') $iniStaged -Encoding ascii
Say ("{0} extensions enabled: {1}" -f $lines.Count, (($lines | ForEach-Object { $_ -replace '.*=','' }) -join ', '))

# --------------------------------------------------------------- 7. sanity
Write-Host "`n[5/6] Verifying the staged files..." -ForegroundColor Yellow
$required = @(
    'www\index.php', 'www\config\app.php', 'www\employee_information_system.sql',
    'www\migrations\upgrade.sql',
    'www\vendor\autoload.php', 'stack\apache\bin\httpd.exe',
    'stack\php\php8apache2_4.dll', 'stack\php\php.exe',
    'stack\mariadb\bin\mysqld.exe', 'stack\mariadb\bin\mysql.exe', 'stack\mariadb\bin\mysqldump.exe',
    'scripts\eis-service.bat', 'scripts\eis-launch.vbs', 'scripts\eis-dbsetup.bat'
)
$missing = $required | Where-Object { -not (Test-Path (Join-Path $buildDir $_)) }
if ($missing) { $missing | ForEach-Object { Write-Host "  MISSING: $_" -ForegroundColor Red }; Fail 'staging incomplete' }
Say 'all required files present' 'Green'

# No personal authorisation or local data may reach the distributable installer
$allowedShipped = @('composer.json', 'composer.lock', 'google_oauth_client.json')
$secrets = Get-ChildItem $wwwDir -Recurse -File -Include '*.json', 'local.php' -ErrorAction SilentlyContinue |
    Where-Object { $_.FullName -notlike '*\vendor\*' -and $_.Name -notin $allowedShipped }
if ($secrets) {
    $secrets | ForEach-Object { Write-Host "  LEAK: $($_.FullName)" -ForegroundColor Red }
    Fail 'private files would be shipped in the installer'
}
if (Test-Path (Join-Path $wwwDir 'config\google_token.json')) { Fail 'a personal Google token would be shipped' }
Say 'no personal tokens or local data in the package' 'Green'

# The extensions the application genuinely needs, however they are provided
$stagedPhp = Join-Path $stackDir 'php\php.exe'
$loaded    = & $stagedPhp -n -c $tmpIni -m 2>$null
foreach ($need in 'gd','zip','curl','mbstring','openssl','pdo_mysql','fileinfo') {
    if ($loaded -notcontains $need) { Fail "the bundled PHP cannot provide the '$need' extension" }
}
Say 'bundled PHP provides every required extension' 'Green'

# The application's own libraries must load under the bundled PHP
$autoloadProbe = Join-Path $buildDir 'probe.php'
@'
require __DIR__ . "/www/vendor/autoload.php";
$c = ["PhpOffice\PhpSpreadsheet\Spreadsheet","Smalot\PdfParser\Parser","Dompdf\Dompdf","Google\Service\Drive"];
foreach ($c as $k) { if (!class_exists($k)) { echo "MISSING $k\n"; exit(1); } }
echo "libraries OK\n";
'@ | Set-Content $autoloadProbe -Encoding ascii
$probe = & $stagedPhp -n -c $tmpIni $autoloadProbe 2>&1
Remove-Item $autoloadProbe -Force
if ($LASTEXITCODE -ne 0) { Fail "library check failed: $probe" }
Say 'PhpSpreadsheet, PdfParser, Dompdf and Google Drive all load' 'Green'
Remove-Item $tmpIni -Force

$total = [math]::Round((Get-ChildItem $buildDir -Recurse -File | Measure-Object Length -Sum).Sum / 1MB, 0)
Say "staged total: $total MB (compressed installer will be much smaller)"

if ($StageOnly) { Write-Host "`nStage only - stopping here.`n" -ForegroundColor Yellow; exit 0 }

# -------------------------------------------------------------- 7. compile
Write-Host "`n[5/5] Compiling the installer..." -ForegroundColor Yellow
$iscc = @(
    "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
    "$env:ProgramFiles\Inno Setup 6\ISCC.exe",
    "${env:ProgramFiles(x86)}\Inno Setup 5\ISCC.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $iscc) {
    Write-Host ''
    Write-Host '  Inno Setup is not installed - the files are staged but not compiled.' -ForegroundColor Yellow
    Write-Host '  Install it from https://jrsoftware.org/isdl.php then run this script again,' -ForegroundColor Yellow
    Write-Host '  or open EmployeeInformationSystem.iss in the Inno Setup IDE and press F9.' -ForegroundColor Yellow
    exit 2
}

& $iscc (Join-Path $root 'EmployeeInformationSystem.iss')
if ($LASTEXITCODE -ne 0) { Fail "ISCC failed with exit code $LASTEXITCODE" }

$setup = Join-Path $distDir 'EmployeeInformationSystem_Setup.exe'
if (Test-Path $setup) {
    $mb = [math]::Round((Get-Item $setup).Length / 1MB, 1)
    Write-Host "`n  DONE: $setup  ($mb MB)`n" -ForegroundColor Green
} else {
    Fail 'ISCC reported success but the installer was not produced.'
}
