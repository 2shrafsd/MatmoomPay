<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Support\GatewayAccount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for gateway API requests. Authentication and tenant binding happen in AuthenticateApiKey;
 * every lookup a request performs is scoped to account().
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function account(): GatewayAccount
    {
        return app(GatewayAccount::class);
    }

    /**
     * @return list<string>
     */
    protected function urlRules(): array
    {
        return ['required', 'string', 'max:2000', $this->account()->livemode ? 'url:https' : 'url:http,https'];
    }
}
