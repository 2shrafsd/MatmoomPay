<?php

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('actions never touch the http layer')
    ->expect('App\Actions')
    ->not->toUse(['Illuminate\Http\Request', 'Illuminate\Http\JsonResponse']);

arch('money is never handled as a float in the payment domain')
    ->expect('App\Actions')
    ->not->toUse(['floatval', 'round']);

arch('every application file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('environment variables are only read in config files')
    ->expect('env')
    ->not->toBeUsed();

arch('actions are small final classes')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal();

arch('models never call out over HTTP')
    ->expect('App\Models')
    ->not->toUse('Illuminate\Support\Facades\Http');
