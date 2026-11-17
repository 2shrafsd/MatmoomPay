<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\GatewayException;
use App\Http\Requests\Api\ListRequest;
use App\Support\GatewayAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class ApiController
{
    /**
     * Resolved lazily: the controller is built before the API key middleware binds the account.
     */
    protected function account(): GatewayAccount
    {
        return app(GatewayAccount::class);
    }

    /**
     * Finds a record owned by the authenticated merchant in the current mode, or fails with 4003.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  list<string>  $with
     * @return TModel
     */
    protected function findOwned(string $model, string $value, string $column = 'public_id', array $with = []): Model
    {
        /** @var TModel|null $record */
        $record = $model::query()
            ->where('merchant_id', $this->account()->merchant->id)
            ->where('livemode', $this->account()->livemode)
            ->where($column, $value)
            ->with($with)
            ->latest('id')
            ->first();

        return $record ?? throw GatewayException::notFound();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Collection<int, TModel>
     */
    protected function page(Builder $query, ListRequest $request): Collection
    {
        return $query->latest('id')->skip($request->skip())->take($request->limit())->get();
    }
}
