$ErrorActionPreference = 'Stop'

$legacyAction = 'app/Application/Applications/Actions/GetApplicationOverviewAction.php'

Write-Host 'Checking legacy Application Catalog/Overview references...' -ForegroundColor Cyan

# Only application-overview references are legacy here.
# Generic ->overview() calls are valid elsewhere in xDeploy (for example Server overview).
$matches = rg -n `
    'GetApplicationOverviewAction|\$applicationManager->overview\(' `
    app resources tests `
    -g '!app/Application/Applications/Actions/GetApplicationOverviewAction.php' `
    2>$null

if ($LASTEXITCODE -eq 0 -and $matches) {
    Write-Host $matches
    Write-Host ''
    Write-Host 'Legacy Application Overview references still exist. Cleanup stopped before deleting anything.' -ForegroundColor Yellow
    exit 1
}

if (Test-Path $legacyAction) {
    Remove-Item $legacyAction -Force
    Write-Host "Removed: $legacyAction" -ForegroundColor Green
}

Write-Host ''
Write-Host 'Checking optional dead-code candidates...' -ForegroundColor Cyan
$optionalMatches = rg -n 'ApplicationService|inspectAll\(' app tests 2>$null

if ($LASTEXITCODE -eq 0 -and $optionalMatches) {
    Write-Host $optionalMatches
    Write-Host 'ApplicationService/inspectAll is only reported for review; this script does not delete it automatically.' -ForegroundColor DarkGray
}

Write-Host ''
Write-Host 'Refreshing autoload/cache...' -ForegroundColor Cyan
composer dump-autoload
php artisan optimize:clear

Write-Host ''
Write-Host 'Running focused catalog test...' -ForegroundColor Cyan
php artisan test tests/Feature/Livewire/Applications/ApplicationCatalogIndexTest.php

Write-Host ''
Write-Host 'Running full regression suite...' -ForegroundColor Cyan
php artisan test

Write-Host ''
Write-Host 'Cleanup completed.' -ForegroundColor Green
