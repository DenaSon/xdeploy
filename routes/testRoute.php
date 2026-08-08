<?php

declare(strict_types=1);

use App\Application\Billing\Actions\CalculateCloudPurchasePriceAction;
use App\Application\Billing\Actions\CreateOrderAction;
use App\Application\Cloud\Actions\ListSupportedCloudImagesAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/*
|--------------------------------------------------------------------------
| Debug Pricing
|--------------------------------------------------------------------------
*/

Route::get(
    '/debug/pricing/{region}/{size}/{disk}/{period}',
    function (
        string $region,
        string $size,
        int $disk,
        string $period,
        CalculateCloudPurchasePriceAction $action,
    ) {
        abort_unless(
            app()->isLocal(),
            404,
        );

        return response()->json(
            $action->execute(
                region: $region,
                sizeId: $size,
                selectedDiskGiB: $disk,
                period: $period,
            )->toArray(),
        );
    },
);

/*
|--------------------------------------------------------------------------
| Debug Supported Cloud Images
|--------------------------------------------------------------------------
|
| Example:
|
| /debug/cloud/images/eu-west1-a
|
*/

Route::get(
    '/debug/cloud/images/{region}',
    function (
        string $region,
        ListSupportedCloudImagesAction $action,
    ) {
        abort_unless(
            app()->isLocal(),
            404,
        );

        $images = $action->execute(
            $region,
        );

        return response()->json(
            array_map(
                static fn ($image): array => [
                    'id' => $image->id,
                    'name' => $image->name,
                    'distribution' => $image->distribution,
                    'version' => $image->version,
                    'architecture' => $image->architecture,
                    'min_disk_gib' => $image->minDiskGiB,
                    'min_memory_mib' => $image->minMemoryMiB,
                    'supports_password' => $image->supportsPassword,
                    'supports_ssh_key' => $image->supportsSshKey,
                ],
                $images,
            ),
        );
    },
);

/*
|--------------------------------------------------------------------------
| Debug Create Order
|--------------------------------------------------------------------------
|
| Example:
|
| /debug/orders
| ?region=eu-west1-a
| &size_id=eco-2-2-0
| &image_id=IMAGE_UUID
| &disk_gib=30
| &period=2_days
|
*/

Route::match(
    [
        'get',
        'post',
    ],
    '/debug/orders',
    function (
        Request $request,
        CreateOrderAction $action,
    ) {
        abort_unless(
            app()->isLocal(),
            404,
        );

        $data = $request->validate([
            'region' => [
                'required',
                'string',
            ],

            'size_id' => [
                'required',
                'string',
            ],

            'image_id' => [
                'required',
                'string',
            ],

            'disk_gib' => [
                'required',
                'integer',
                'min:1',
            ],

            'period' => [
                'required',
                'string',

                Rule::in(
                    array_keys(
                        config(
                            'money.periods',
                            [],
                        ),
                    ),
                ),
            ],
        ]);

        $user = $request->user()
            ?? User::query()->firstOrFail();

        $order = $action->execute(
            user: $user,

            region: $data['region'],

            sizeId: $data['size_id'],

            imageId: $data['image_id'],

            selectedDiskGiB: (int) $data['disk_gib'],

            period: $data['period'],
        );

        return response()->json([
            'id' => $order->id,

            'user_id' => $order->user_id,

            'region_id' => $order->region_id,
            'size_id' => $order->size_id,

            'image' => [
                'id' => $order->image_id,
                'name' => $order->image_name,
                'distribution' => $order->image_distribution,
                'version' => $order->image_version,
            ],

            'default_disk_gib' => $order->default_disk_gib,
            'selected_disk_gib' => $order->selected_disk_gib,

            'period' => $order->period,
            'duration_hours' => $order->duration_hours,

            'provider_cost' => $order->provider_cost,
            'markup_percent' => $order->markup_percent,
            'final_amount' => $order->final_amount,
            'currency' => $order->currency,

            'status' => $order->status->value,

            'quote_expires_at' =>
                $order->quote_expires_at?->toIso8601String(),

            'paid_at' =>
                $order->paid_at?->toIso8601String(),
        ]);
    },
);
