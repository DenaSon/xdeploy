<?php

declare(strict_types=1);

namespace App\Livewire\Applications\Marzban;

use App\Application\Applications\Marzban\DTOs\CreateMarzbanAdminData;
use App\Application\Applications\Marzban\MarzbanManager;
use App\Domain\Application\Marzban\Exceptions\MarzbanAdminAlreadyConfiguredException;
use App\Domain\Application\Marzban\Exceptions\MarzbanAdminProvisioningException;
use App\Models\Server;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Mary\Traits\Toast;
use Throwable;

final class SetupAdmin extends Component
{
    use Toast;

    #[Locked]
    public int $serverId;

    #[Reactive]
    public string $setupState;

    public string $username = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(
        int $serverId,
        string $setupState,
    ): void {
        $this->serverId = $serverId;
        $this->setupState = $setupState;
    }

    public function createAdmin(
        MarzbanManager $manager,
    ): void {
        try {
            $validated = $this->validate();

            $server = Server::query()->findOrFail(
                $this->serverId,
            );

            $snapshot = $manager->createAdmin(
                server: $server,
                data: new CreateMarzbanAdminData(
                    username: $validated['username'],
                    password: $validated['password'],
                ),
            );

            $this->username = '';
            $this->resetValidation();

            /*
             * Parent updates its management state.
             * setupState will then flow back to this component
             * through the reactive property.
             */
            $this->dispatch(
                "marzban-management-updated.{$this->serverId}",
                management: $snapshot->toArray(),
            );

            $this->success(
                'مدیر Marzban با موفقیت ساخته شد.',
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (MarzbanAdminAlreadyConfiguredException) {
            $this->username = '';
            $this->resetValidation();

            /*
             * Parent marks the setup as complete.
             * Do not mutate the reactive property here.
             */
            $this->dispatch(
                "marzban-setup-completed.{$this->serverId}",
            );

            $this->warning(
                'مدیر Marzban قبلاً ساخته شده است.',
            );
        } catch (InvalidArgumentException) {
            $this->error(
                'نام کاربری یا رمز عبور واردشده معتبر نیست.',
            );
        } catch (MarzbanAdminProvisioningException) {
            $this->error(
                'ساخت مدیر Marzban انجام نشد. وضعیت برنامه و اتصال سرور را بررسی کنید.',
            );
        } catch (Throwable) {
            $this->error(
                'خطای غیرمنتظره‌ای هنگام ساخت مدیر Marzban رخ داد.',
            );
        } finally {
            $this->reset(
                'password',
                'passwordConfirmation',
            );
        }
    }

    public function render(): View
    {
        return view(
            'livewire.applications.marzban.setup-admin',
        );
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:32',
                'regex:/\A[a-z0-9_]+\z/',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'not_regex:/[\x00-\x1F\x7F]/',
                'same:passwordConfirmation',
            ],
            'passwordConfirmation' => [
                'required',
                'string',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'username.regex' => 'نام کاربری فقط می‌تواند شامل حروف انگلیسی کوچک، عدد و زیرخط باشد.',
            'password.same' => 'رمز عبور و تکرار آن یکسان نیستند.',
            'password.not_regex' => 'رمز عبور شامل نویسه غیرمجاز است.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'username' => 'نام کاربری',
            'password' => 'رمز عبور',
            'passwordConfirmation' => 'تکرار رمز عبور',
        ];
    }
}
