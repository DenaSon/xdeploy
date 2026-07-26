<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Authentication\DTOs\VerifyOtpData;
use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
            ],

            'code' => [
                'required',
                'string',
            ],
        ];
    }

    public function toDto(): VerifyOtpData
    {
        return new VerifyOtpData(
            phone: PhoneNumber::from(
                $this->string('phone')->toString(),
            ),

            code: OtpCode::from(
                $this->string('code')->toString(),
            ),
        );
    }
}
