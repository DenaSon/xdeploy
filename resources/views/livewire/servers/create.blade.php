<div class="flex min-h-screen items-center justify-center bg-base-200/30 px-4 py-10">

    <div class="w-full max-w-2xl">

        <x-card class="border border-base-300 bg-base-100 p-8 shadow-2xl md:p-10">

            {{-- Header --}}
            <div class="space-y-2">

                <div>
                    <h1 class="text-3xl font-bold">
                        افزودن سرور
                    </h1>

                </div>

            </div>


            {{-- Information Card --}}
            <div class="alert mt-8 border border-base-300 bg-base-200/50">

                <x-icon
                    name="o-information-circle"
                    class="size-6 text-info"
                />

                <div class="text-sm">

                    <p class="font-medium">
                        اطلاعات موردنیاز
                    </p>

                    <p class="mt-1 text-base-content/70">
                        برای برقراری ارتباط با سرور، اطلاعات اتصال SSH را وارد کنید. پس از ثبت سرور، امکان مدیریت آن از طریق xDeploy در دسترس خواهد بود.
                    </p>

                </div>

            </div>
            <x-hr/>
            {{-- Form --}}
            <div class="mt-8">

                <x-servers.form
                    submit="save"
                    button="افزودن"
                />

            </div>

        </x-card>

    </div>

</div>
