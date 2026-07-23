<#
  refresh-promo.ps1
  Publishes the promo status images into the secret gallery and commits them.

    1. Wipes  promo-82f02098/img/  (keeps .gitkeep)
    2. Moves  pcos-status-*.png  from your Downloads folder into it
    3. Commits (dated message) and pushes to GitHub
    4. Prints the manual next steps

  Deploy for this project is MANUAL (cPanel Git "Update from Remote" — there is
  no CI/FTP/automated deploy and no SSH), so this script does NOT deploy or hit
  the notifier itself. It stops after pushing and tells you what to click.

  Run from anywhere:   powershell -File tools\refresh-promo.ps1
#>

$ErrorActionPreference = 'Stop'

$repo      = Split-Path -Parent $PSScriptRoot                 # tools\ -> repo root
$imgDir    = Join-Path $repo 'promo-82f02098\img'
$downloads = Join-Path $env:USERPROFILE 'Downloads'
$galleryUrl = 'https://shop.pconestop.co.za/promo-82f02098/'
$notifyUrl  = 'https://shop.pconestop.co.za/promo-82f02098/notify.php?key=2ce5b7f83a48a976'

if (-not (Test-Path $imgDir)) { throw "Gallery img folder not found: $imgDir" }

# 1. Wipe existing PNGs (keep .gitkeep and anything non-PNG)
Get-ChildItem -Path $imgDir -Filter '*.png' -File -ErrorAction SilentlyContinue | Remove-Item -Force
Write-Host "Cleared old PNGs from promo-82f02098/img/" -ForegroundColor DarkGray

# 2. Move the new cards in from Downloads
$pngs = Get-ChildItem -Path $downloads -Filter 'pcos-status-*.png' -File -ErrorAction SilentlyContinue
if (-not $pngs -or $pngs.Count -eq 0) {
    Write-Host "No pcos-status-*.png files found in $downloads." -ForegroundColor Yellow
    Write-Host "Generate them first with the Status Maker, then re-run this script." -ForegroundColor Yellow
    exit 1
}
foreach ($f in $pngs) { Move-Item -Path $f.FullName -Destination (Join-Path $imgDir $f.Name) -Force }
Write-Host "Moved $($pngs.Count) card image(s) into promo-82f02098/img/" -ForegroundColor Green

# 2b. Move the frozen captions snapshot in beside the PNGs (overwrites last pack's).
$capSrc = Join-Path $downloads 'pcos-captions.json'
$capDst = Join-Path $repo 'promo-82f02098\captions.json'
if (Test-Path $capSrc) {
    Move-Item -Path $capSrc -Destination $capDst -Force
    Write-Host "Moved captions snapshot into promo-82f02098/captions.json" -ForegroundColor Green
} else {
    Write-Host "WARNING: pcos-captions.json not found in $downloads." -ForegroundColor Red
    Write-Host "         Use the maker's 'Download all' (it writes the captions file)." -ForegroundColor Red
    Write-Host "         Without it the gallery will show the empty state." -ForegroundColor Red
}

# 3. Commit (images + captions together, so the gallery and pack always match)
Set-Location $repo
git add promo-82f02098/img promo-82f02098/captions.json
$staged = git status --porcelain -- promo-82f02098/img promo-82f02098/captions.json
if (-not $staged) {
    Write-Host "No changes to commit (identical pack already published)." -ForegroundColor Yellow
    exit 0
}
$stamp = Get-Date -Format 'yyyy-MM-dd HH:mm'
git commit -m "Refresh promo pack ($stamp) - $($pngs.Count) cards"

# 4. Try to push, then report the exact remaining manual steps.
$pushOk = $false
try { git push origin main; if ($LASTEXITCODE -eq 0) { $pushOk = $true } } catch {}
$ahead = (git rev-list --count origin/main..HEAD 2>$null)
if (-not $ahead) { $ahead = '?' }

Write-Host ""
Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host " REMAINING STEPS (deploy is MANUAL for this project):" -ForegroundColor Cyan
if ($pushOk -and $ahead -eq '0') {
    Write-Host "   1) Push ......... DONE (pushed to origin/main)" -ForegroundColor Green
} else {
    Write-Host "   1) Push ......... TODO -> run:  git push origin main" -ForegroundColor Yellow
    Write-Host "                     (local is ahead of origin by $ahead commit(s))"
}
Write-Host "   2) Deploy ....... cPanel -> Git Version Control -> Update from Remote"
Write-Host "   3) Notify team .. open:  $notifyUrl"
Write-Host "   4) Post from .... $galleryUrl"
Write-Host "==================================================================" -ForegroundColor Cyan
