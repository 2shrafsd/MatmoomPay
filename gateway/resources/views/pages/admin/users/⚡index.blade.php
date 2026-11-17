<?php

use App\Actions\Users\ManageUserAccount;
use App\Enums\MerchantRole;
use App\Exceptions\GatewayException;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin'), Title('Users')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $filter = '';

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    #[Locked]
    public ?int $banningId = null;

    public string $banReason = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'filter'], true)) {
            $this->resetPage();
        }
    }

    public function edit(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->resetValidation();

        Flux::modal('edit-user')->show();
    }

    public function saveUser(ManageUserAccount $manageUserAccount): void
    {
        $user = User::query()->findOrFail($this->editingId);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $manageUserAccount->update($user, $this->name, $this->email, Auth::user());

        $this->reset('editingId', 'name', 'email');
        Flux::modal('edit-user')->close();
        Flux::toast(variant: 'success', text: __('User details saved.'));
    }

    public function askBan(int $userId): void
    {
        $this->banningId = User::query()->findOrFail($userId)->id;
        $this->banReason = '';
        $this->resetValidation();

        Flux::modal('ban-user')->show();
    }

    public function ban(ManageUserAccount $manageUserAccount): void
    {
        $this->validate(['banReason' => ['required', 'string', 'max:500']], ['banReason.required' => __('Write why this account is banned. It is kept in the audit log.')]);

        $this->run(fn (ManageUserAccount $actions, User $user) => $actions->ban($user, $this->banReason, Auth::user()), $this->banningId, __('Account banned. The person is signed out on their next click.'));

        $this->reset('banningId', 'banReason');
        Flux::modal('ban-user')->close();
    }

    public function unban(int $userId): void
    {
        $this->run(fn (ManageUserAccount $actions, User $user) => $actions->unban($user, Auth::user()), $userId, __('Ban lifted. The person can sign in again.'));
    }

    public function setAdmin(int $userId, bool $isAdmin): void
    {
        $this->run(fn (ManageUserAccount $actions, User $user) => $actions->setPlatformAdmin($user, $isAdmin, Auth::user()), $userId, $isAdmin ? __('Admin rights granted.') : __('Admin rights removed.'));
    }

    public function resetTwoFactor(int $userId): void
    {
        $this->run(fn (ManageUserAccount $actions, User $user) => $actions->resetTwoFactor($user, Auth::user()), $userId, __('Two-factor authentication was reset.'));
    }

    public function verifyEmail(int $userId): void
    {
        $this->run(fn (ManageUserAccount $actions, User $user) => $actions->markEmailVerified($user, Auth::user()), $userId, __('Email marked as verified.'));
    }

    public function delete(int $userId): void
    {
        $this->run(fn (ManageUserAccount $actions, User $user) => $actions->delete($user, Auth::user()), $userId, __('Account deleted.'));
    }

    /**
     * Runs one owner action and turns a refused action into a readable toast.
     */
    private function run(Closure $action, ?int $userId, string $success): void
    {
        $user = User::query()->findOrFail($userId);

        try {
            $action(app(ManageUserAccount::class), $user);
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->users, $this->counts);
        Flux::toast(variant: 'success', text: $success);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return [
            '' => User::query()->count(),
            'admins' => User::query()->where('is_platform_admin', true)->count(),
            'banned' => User::query()->whereNotNull('banned_at')->count(),
            'unverified' => User::query()->whereNull('email_verified_at')->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $search = trim(mb_substr($this->search, 0, 64));
        $like = '%'.addcslashes($search, '%_\\').'%';

        return User::query()
            ->with(['merchants' => fn ($query) => $query->withTrashed()->select('merchants.id', 'merchants.public_id', 'merchants.name')])
            ->when($this->filter === 'admins', fn (Builder $query) => $query->where('is_platform_admin', true))
            ->when($this->filter === 'banned', fn (Builder $query) => $query->whereNotNull('banned_at'))
            ->when($this->filter === 'unverified', fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)))
            ->latest('id')
            ->paginate(25);
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Users')" :subheading="__('Everyone with an account: merchants, their teams and platform admins. Edit, ban or remove any account.')" />

    <nav class="mb-4 flex gap-2 overflow-x-auto pb-1" aria-label="{{ __('Filter users') }}">
        @foreach (['' => __('All'), 'admins' => __('Platform admins'), 'banned' => __('Banned'), 'unverified' => __('Unverified email')] as $value => $label)
            <button type="button" wire:click="$set('filter', '{{ $value }}')" wire:key="user-filter-{{ $value ?: 'all' }}"
                @class(['inline-flex min-h-9 shrink-0 items-center gap-2 rounded-full px-4 text-sm font-medium ring-1 transition', 'bg-ink-800 text-white ring-ink-800' => $filter === $value, 'bg-white text-zinc-600 ring-zinc-200 hover:text-zinc-900' => $filter !== $value])
                aria-pressed="{{ $filter === $value ? 'true' : 'false' }}" data-test="user-filter-{{ $value ?: 'all' }}">
                {{ $label }}
                <span @class(['ltr-nums rounded-full px-1.5 text-xs', 'bg-white/15' => $filter === $value, 'bg-zinc-100' => $filter !== $value])>{{ $this->counts[$value] }}</span>
            </button>
        @endforeach
    </nav>

    <div class="surface mb-4 p-4">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Name or email')" />
    </div>

    <section class="surface transition-opacity" wire:loading.class.delay="opacity-60" wire:loading.attr.delay="aria-busy">
        @if ($this->users->isEmpty())
            <x-portal.empty icon="users" :heading="__('No users found')" />
        @else
            <div class="data-table">
                <flux:table :paginate="$this->users">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Person') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Businesses') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Joined') }}</flux:table.column>
                        <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->users as $person)
                            <flux:table.row :key="'user-'.$person->id">
                                <flux:table.cell variant="strong">
                                    <span class="flex items-center gap-3">
                                        <flux:avatar size="sm" :name="$person->name" :initials="$person->initials()" />
                                        <span class="min-w-0">
                                            <span class="block truncate">{{ $person->name }} @if ($person->is(auth()->user()))<span class="text-xs font-normal text-zinc-500">({{ __('you') }})</span>@endif</span>
                                            <span class="ltr-nums block truncate text-start text-xs font-normal text-zinc-500">{{ $person->email }}</span>
                                        </span>
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="flex flex-wrap gap-1">
                                        @if ($person->isBanned())
                                            <flux:badge size="sm" color="red" icon="no-symbol" :title="$person->ban_reason">{{ __('Banned') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                        @endif
                                        @if ($person->isPlatformAdmin())
                                            <flux:badge size="sm" color="lime" icon="shield-check">{{ __('Platform admin') }}</flux:badge>
                                        @endif
                                        @if (! $person->hasVerifiedEmail())
                                            <flux:badge size="sm" color="amber">{{ __('Unverified email') }}</flux:badge>
                                        @endif
                                        @if ($person->two_factor_confirmed_at)
                                            <flux:badge size="sm" color="zinc">2FA</flux:badge>
                                        @endif
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="max-md:hidden">
                                    @forelse ($person->merchants as $business)
                                        <a href="{{ route('admin.merchants.show', $business->public_id) }}" wire:navigate class="block truncate text-sm hover:underline">{{ $business->name }} <span class="text-xs text-zinc-500">· {{ MerchantRole::tryFrom($business->pivot->role)?->label() }}</span></a>
                                    @empty
                                        <span class="text-sm text-zinc-400">—</span>
                                    @endforelse
                                </flux:table.cell>
                                <flux:table.cell class="max-sm:hidden">{{ $person->created_at->locale(app()->getLocale())->translatedFormat('j M Y') }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $person->name])" data-test="user-actions-{{ $person->id }}" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="edit({{ $person->id }})">{{ __('Edit') }}</flux:menu.item>
                                            @if (! $person->hasVerifiedEmail())
                                                <flux:menu.item icon="envelope-open" wire:click="verifyEmail({{ $person->id }})">{{ __('Mark email as verified') }}</flux:menu.item>
                                            @endif
                                            @if ($person->two_factor_confirmed_at)
                                                <flux:menu.item icon="device-phone-mobile" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Reset two-factor authentication for :name? They will set it up again after signing in.', ['name' => $person->name])) }}, danger: true, run: () => $wire.resetTwoFactor({{ $person->id }}) })">{{ __('Reset two-factor') }}</flux:menu.item>
                                            @endif
                                            @unless ($person->is(auth()->user()))
                                                <flux:menu.separator />
                                                @if ($person->isPlatformAdmin())
                                                    <flux:menu.item icon="shield-exclamation" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Remove admin rights from :name?', ['name' => $person->name])) }}, danger: true, run: () => $wire.setAdmin({{ $person->id }}, false) })">{{ __('Remove admin rights') }}</flux:menu.item>
                                                @elseif (! $person->isBanned())
                                                    <flux:menu.item icon="shield-check" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Give :name full control of the platform?', ['name' => $person->name])) }}, danger: true, run: () => $wire.setAdmin({{ $person->id }}, true) })">{{ __('Make platform admin') }}</flux:menu.item>
                                                @endif
                                                @if ($person->isBanned())
                                                    <flux:menu.item icon="lock-open" wire:click="unban({{ $person->id }})" data-test="unban-user-{{ $person->id }}">{{ __('Lift ban') }}</flux:menu.item>
                                                @elseif (! $person->isPlatformAdmin())
                                                    <flux:menu.item icon="no-symbol" variant="danger" wire:click="askBan({{ $person->id }})" data-test="ban-user-{{ $person->id }}">{{ __('Ban') }}</flux:menu.item>
                                                @endif
                                                <flux:menu.item icon="trash" variant="danger" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Delete the account of :name? This cannot be undone.', ['name' => $person->name])) }}, danger: true, run: () => $wire.delete({{ $person->id }}) })">{{ __('Delete account') }}</flux:menu.item>
                                            @endunless
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>

    <flux:modal name="edit-user" class="w-full max-w-md">
        <form wire:submit="saveUser" class="space-y-5">
            <flux:heading size="lg">{{ __('Edit user') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="email" type="email" :label="__('Email')" dir="ltr" required />
            <flux:text class="text-sm">{{ __('Changing the email asks the person to verify the new address.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-user">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="ban-user" class="w-full max-w-md">
        <form wire:submit="ban" class="space-y-5">
            <flux:heading size="lg">{{ __('Ban this account') }}</flux:heading>
            <flux:text>{{ __('The person is signed out and cannot sign in again until you lift the ban. Their businesses keep working for the rest of the team.') }}</flux:text>
            <flux:textarea wire:model="banReason" :label="__('Reason')" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger" data-test="confirm-ban-user">{{ __('Ban account') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
