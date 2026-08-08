# xDeploy — Order Image Snapshot Package

This package updates the commercial Order flow so the operating system selected
from the live cloud catalog is persisted as an immutable purchase snapshot.

Included changes:

- Order migration updated in place (no follow-up migration).
- Dynamic supported-image listing based on:
  - CloudProviderInterface::listImages()
  - SupportedOperatingSystemPolicy
  - password-authentication capability
- Image/size/disk compatibility validation before Order creation.
- Order snapshot fields:
  - image_id
  - image_name
  - image_distribution
  - image_version
- Debug route for listing supported cloud images.
- Debug Order route accepts image_id.
- Billing tests updated for the new NOT NULL image snapshot fields.

## Install

Copy the package contents into the project root, preserving directories.

Because the original orders migration was edited in place and this project has
no production database yet:

    php artisan migrate:fresh

Then run:

    vendor\bin\pint app/Application/Cloud/Actions/ListSupportedCloudImagesAction.php
    vendor\bin\pint app/Application/Cloud/Actions/ResolveCloudImageForOrderAction.php
    vendor\bin\pint app/Application/Billing/Actions/CreateOrderAction.php
    vendor\bin\pint app/Models/Order.php
    vendor\bin\pint routes/testRoute.php
    vendor\bin\pint tests/Feature/Application/Billing/CreateOrderActionTest.php
    vendor\bin\pint tests/Feature/Application/Billing/CreatePaymentActionTest.php
    vendor\bin\pint tests/Feature/Application/Billing/VerifyPaymentActionTest.php

Run focused tests:

    php artisan test tests/Feature/Application/Billing/CreateOrderActionTest.php
    php artisan test tests/Feature/Application/Billing/CreatePaymentActionTest.php
    php artisan test tests/Feature/Application/Billing/VerifyPaymentActionTest.php

## Manual debug flow

1. List sellable images:

    http://localhost:8000/debug/cloud/images/eu-west1-a

2. Pick an image id returned by the API.

3. Create an Order:

    http://localhost:8000/debug/orders?region=eu-west1-a&size_id=eco-2-2-0&image_id=IMAGE_ID&disk_gib=30&period=2_days
