<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Authentication\DTOs\RequestOtpData;
use App\Domain\User\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

final class RequestOtpRequest extends FormRequest
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
        ];
    }

    public function toDto(): RequestOtpData
    {
        return new RequestOtpData(
            phone: PhoneNumber::from(
                $this->string('phone')->toString(),
            ),
        );
    }
}
