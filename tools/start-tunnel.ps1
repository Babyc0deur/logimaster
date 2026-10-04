# Ouvre un tunnel HTTPS public vers l'application locale (localhost.run, via le ssh de Windows : rien à installer),
# active le « mode tunnel » (seuls l'accueil, /m et /api/mobile sont joignables de l'extérieur : l'administration reste
# locale) et met à jour l'adresse du QR code dans .env.
#
#   powershell -ExecutionPolicy Bypass -File tools\start-tunnel.ps1            # ouvre le tunnel et rend la main
#   powershell -ExecutionPolicy Bypass -File tools\start-tunnel.ps1 -Watch     # reste ouvert et le rouvre s'il tombe
#
# Les adresses gratuites de localhost.run expirent de temps en temps (message « no tunnel ») et changent à chaque ouverture :
# avec -Watch, le script détecte la coupure, rouvre un tunnel et met le QR code à jour. Pour une adresse qui ne change
# jamais : un compte localhost.run avec votre clé SSH, ou un hébergement avec un nom de domaine HTTPS.
param([int]$Port = 8085, [switch]$Watch)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$envFile = Join-Path $root '.env'
$log = Join-Path $env:TEMP 'lm_tunnel.log'

function Set-EnvValue([string]$Key, [string]$Value) {
    $lines = Get-Content $envFile
    if ($lines -match "^$Key=") { $lines = $lines -replace "^$Key=.*", "$Key=$Value" } else { $lines += "$Key=$Value" }
    Set-Content $envFile $lines
}

function Stop-Tunnel {
    Get-CimInstance Win32_Process -Filter "Name='ssh.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -like '*localhost.run*' } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
}

function Open-Tunnel {
    Stop-Tunnel
    Remove-Item $log, "$log.err" -ErrorAction SilentlyContinue
    # 127.0.0.1 (et non « localhost ») : le serveur de développement n'écoute qu'en IPv4
    Start-Process ssh -ArgumentList '-T', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'ServerAliveInterval=30', '-o', 'ExitOnForwardFailure=yes', '-R', "80:127.0.0.1:$Port", 'nokey@localhost.run' `
        -RedirectStandardOutput $log -RedirectStandardError "$log.err" -WindowStyle Hidden

    $url = $null
    for ($i = 0; $i -lt 30 -and -not $url; $i++) {
        Start-Sleep -Seconds 1
        if (Test-Path $log) { $m = Select-String -Path $log -Pattern 'https://\S+\.lhr\.life' | Select-Object -First 1; if ($m) { $url = $m.Matches.Value } }
    }
    if (-not $url) { throw "Tunnel non établi. Détails : $log.err" }

    Set-EnvValue 'LOGIMASTER_MOBILE_URL' "$url/m"
    php (Join-Path $root 'artisan') config:clear | Out-Null
    Write-Host ("[{0:HH:mm:ss}] Application mobile : {1}/m   (affiche d'installation : {1}/m/installer)" -f (Get-Date), $url)

    return $url
}

function Test-Tunnel([string]$Url) {
    try { return ((curl.exe -s -o NUL -w '%{http_code}' --max-time 20 "$Url/m") -eq '200') } catch { return $false }
}

# 1) le mode tunnel d'abord : on n'expose rien avant d'avoir verrouillé l'administration
Set-EnvValue 'LOGIMASTER_TUNNEL_MODE' 'true'
php (Join-Path $root 'artisan') config:clear | Out-Null

$url = Open-Tunnel
Write-Host "L'administration n'est pas joignable depuis cette adresse (mode tunnel)."

if ($Watch) {
    Write-Host 'Surveillance active : Ctrl+C pour arrêter.'
    while ($true) {
        Start-Sleep -Seconds 30
        if (-not (Test-Tunnel $url)) {
            Write-Host ("[{0:HH:mm:ss}] Tunnel coupé, nouvelle ouverture…" -f (Get-Date)) -ForegroundColor Yellow
            try { $url = Open-Tunnel } catch { Write-Host $_.Exception.Message -ForegroundColor Red }
        }
    }
}
