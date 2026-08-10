# xDeploy — Cloud Purchase Catalog Cache Refactor

Reviewed against GitHub HEAD:

`c4a9a1c88e4895289ed359fb6ef65cc83db34912`
`Improve buy.blade.php - polish`

## Goal

Remove the multi-second ArvanCloud catalog latency from the VPS purchase page without weakening the authoritative commercial flow.

The important separation is:

- **Presentation catalog**: cached
  - regions
  - sizes
  - images
- **Authoritative price / order creation**: still direct provider-backed
- **Payment / provisioning**: unchanged

So the page becomes fast while `CreateOrderAction` and `CalculateCloudPurchasePriceAction` keep their current source-of-truth behavior.

## Architecture

New contract:

`App\Domain\Cloud\Contracts\CloudCatalogReaderInterface`

Implementation:

`App\Infrastructure\Cloud\Catalog\CachedCloudCatalogReader`

The cache reader wraps the existing `CloudProviderInterface` only for presentation reads. Cache policy is provider-neutral and the cache key includes the active provider name.

`ListSupportedCloudImagesAction` remains authoritative and provider-backed.

A new `FilterSupportedCloudImagesAction` centralizes the supported-image policy so the Buy page can filter cached raw image data without duplicating the policy.

## Cache policy

Default values:

- Regions:
  - fresh: 30 minutes
  - stale window: 6 hours
- Sizes:
  - fresh: 10 minutes
  - stale window: 1 hour
- Images:
  - fresh: 30 minutes
  - stale window: 6 hours

Laravel `Cache::flexible()` provides stale-while-revalidate behavior. A stale catalog can be returned immediately while Laravel refreshes it after the response.

The cache is presentation-only. A stale size list can affect what is shown in the selector, but the final order still recalculates the authoritative provider price.

## Apply

From PowerShell:

```powershell
cd <folder-containing-this-bundle>

.\apply.ps1 -ProjectRoot D:\xDeploy
```

The script checks the exact reviewed code contexts and stops if the local code has drifted too far.

## Verify

Run in this order:

```powershell
cd D:\xDeploy

php artisan optimize:clear

php artisan test --filter=CachedCloudCatalogReaderTest
php artisan test --filter=ListSupportedCloudImagesActionTest
php artisan test --filter=BuyTest

php artisan test

php artisan cloud:catalog:warm --force
```

The warm command is automatically discovered from `app/Console/Commands`.

## Expected Debugbar result

After warming the cache and opening `/panel/servers/buy`:

Expected catalog calls:

- `GET /regions` -> cache
- `GET /regions/{region}/sizes` for selector -> cache
- `GET /regions/{region}/images` -> cache

One provider `GET /sizes` may still occur for the live quote. This is intentional: the price shown by the quote path remains provider-backed.

That means the previous 5+ second `/regions` call and the cached catalog calls should disappear from the request path.

## Other cleanup included

- 14-day period becomes the real default in `Buy.php`.
- OS selection no longer recalculates price unless it increases the required disk.
- The outdated Mary UI `x-group` comment is removed.
- Buy tests now reflect:
  - cached presentation catalog,
  - authoritative live pricing,
  - 14-day default,
  - Toman display,
  - Persian region display name.
- The 10-plan limit remains intact.
