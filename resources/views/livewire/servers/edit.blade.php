<div class="flex min-h-screen items-center justify-center bg-base-200/30 px-4 py-10">

    <div class="w-full max-w-2xl">

        <x-card class="border border-base-300 bg-base-100 p-8 shadow-2xl md:p-10">

            {{-- Header --}}
            <div class="space-y-2">

                <div>
                    <h1 class="text-3xl font-bold">
                        ویرایش سرور
                    </h1>
                </div>

            </div>

            {{-- Information Card --}}
            <div class="alert mt-8 border border-base-300 bg-base-200/50">

                <x-icon
                    name="o-pencil-square"
                    class="size-6 text-warning"
                />

                <div class="text-sm">

                    <p class="font-medium">
                        ویرایش اطلاعات اتصال
                    </p>

                    <p class="mt-1 text-base-content/70">
                        اطلاعات اتصال SSH سرور را در صورت نیاز بروزرسانی کنید. این تغییرات برای تمامی عملیات آینده xDeploy روی این سرور اعمال خواهند شد.
                    </p>

                </div>

            </div>

            <x-hr />

            {{-- Form --}}
            <div class="mt-8">

                <x-servers.form
                    submit="update"
                    button="ذخیره تغییرات"
                />

            </div>

        </x-card>

    </div>

</div>
