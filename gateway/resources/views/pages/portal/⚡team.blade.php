<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Team\InviteTeamMember;
use App\Actions\Team\UpdateTeamMember;
use App\Enums\MerchantRole;
use App\Exceptions\GatewayException;
use App\Livewire\PortalComponent;
use App\Models\MerchantInvitation;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Team')] class extends PortalComponent {
    public string $email = '';

    public string $role = 'viewer';

    public function invite(InviteTeamMember $inviteTeamMember): void
    {
        $this->authorize('manageTeam', $this->merchant());

        $this->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::in(array_map(fn (MerchantRole $role) => $role->value, MerchantRole::assignable()))],
        ]);

        try {
            $inviteTeamMember->handle($this->merchant(), $this->email, MerchantRole::from($this->role), Auth::user());
        } catch (GatewayException $exception) {
            $this->addError('email', $exception->getMessage());

            return;
        }

        $this->reset('email', 'role');
        Flux::modal('invite')->close();
        Flux::toast(variant: 'success', text: __('Invitation sent.'));
    }

    public function changeRole(int $userId, string $role, UpdateTeamMember $updateTeamMember): void
    {
        $this->authorize('manageTeam', $this->merchant());

        $member = $this->member($userId);
        $newRole = MerchantRole::tryFrom($role);

        if ($newRole === null || ! in_array($newRole, MerchantRole::assignable(), true)) {
            return;
        }

        try {
            $updateTeamMember->changeRole($this->merchant(), $member, $newRole, Auth::user());
            Flux::toast(variant: 'success', text: __('Role updated.'));
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function remove(int $userId, UpdateTeamMember $updateTeamMember): void
    {
        $this->authorize('manageTeam', $this->merchant());

        try {
            $updateTeamMember->remove($this->merchant(), $this->member($userId), Auth::user());
            Flux::toast(variant: 'success', text: __('Team member removed.'));
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function revokeInvitation(int $invitationId): void
    {
        $this->authorize('manageTeam', $this->merchant());

        $invitation = MerchantInvitation::query()->where('merchant_id', $this->merchant()->id)->whereKey($invitationId)->firstOrFail();
        $invitation->delete();

        app(RecordAuditLog::class)->handle(auth()->user(), 'team.invitation_withdrawn', $this->merchant(), null, ['email' => $invitation->email]);
    }

    private function member(int $userId): User
    {
        return $this->merchant()->users()->whereKey($userId)->firstOrFail();
    }

    #[Computed]
    public function members()
    {
        return $this->merchant()->users()->orderBy('name')->get();
    }

    #[Computed]
    public function invitations()
    {
        return $this->merchant()->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())->latest('id')->get();
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->role()->canManageTeam();
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Team')" :subheading="__('Give colleagues access with the right role. Only owners and administrators can change the team.')">
        @if ($this->canManage)
            <x-slot:actions>
                <flux:modal.trigger name="invite">
                    <flux:button variant="primary" icon="user-plus">{{ __('Invite someone') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
        @endif
    </x-portal.page-header>

    <section class="surface divide-y divide-zinc-200">
        @foreach ($this->members as $member)
            @php($memberRole = MerchantRole::from($member->pivot->role))
            <div class="flex flex-wrap items-center justify-between gap-3 p-4" wire:key="member-{{ $member->id }}">
                <div class="flex min-w-0 items-center gap-3">
                    <flux:avatar :name="$member->name" :initials="$member->initials()" />
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $member->name }} @if ($member->id === auth()->id()) <span class="text-zinc-500">({{ __('you') }})</span> @endif</p>
                        <p class="ltr-nums truncate text-start text-sm text-zinc-500">{{ $member->email }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if ($this->canManage && $memberRole !== MerchantRole::Owner && $member->id !== auth()->id())
                        <flux:select size="sm" class="w-40" wire:change="changeRole({{ $member->id }}, $event.target.value)" :aria-label="__('Role')">
                            @foreach (MerchantRole::assignable() as $option)
                                <flux:select.option value="{{ $option->value }}" :selected="$option === $memberRole">{{ $option->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:button size="sm" variant="ghost" icon="user-minus" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Remove :name from the team?', ['name' => $member->name])) }}, danger: true, run: () => $wire.remove({{ $member->id }}) })" :aria-label="__('Remove :name from the team', ['name' => $member->name])" />
                    @else
                        <flux:badge size="sm">{{ $memberRole->label() }}</flux:badge>
                    @endif
                </div>
            </div>
        @endforeach
    </section>

    @if ($this->invitations->isNotEmpty())
        <section class="surface mt-6 p-4">
            <flux:heading>{{ __('Pending invitations') }}</flux:heading>
            <ul class="mt-3 divide-y divide-zinc-200 text-sm">
                @foreach ($this->invitations as $invitation)
                    <li class="flex items-center justify-between gap-3 py-2.5" wire:key="inv-{{ $invitation->id }}">
                        <span><span class="ltr-nums">{{ $invitation->email }}</span> · {{ $invitation->role->label() }}</span>
                        @if ($this->canManage)
                            <flux:button size="sm" variant="ghost" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Withdraw the invitation for :email? The link in their email stops working.', ['email' => $invitation->email])) }}, danger: true, run: () => $wire.revokeInvitation({{ $invitation->id }}) })">{{ __('Withdraw') }}</flux:button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="surface mt-6 p-5">
        <flux:heading>{{ __('What each role can do') }}</flux:heading>
        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="font-medium">{{ __('Administrator') }}</dt><dd class="text-zinc-500">{{ __('Everything the owner can do, including the team and bank details.') }}</dd></div>
            <div><dt class="font-medium">{{ __('Developer') }}</dt><dd class="text-zinc-500">{{ __('API keys, webhooks and read access to payments.') }}</dd></div>
            <div><dt class="font-medium">{{ __('Finance') }}</dt><dd class="text-zinc-500">{{ __('Refunds, payment links and settlements.') }}</dd></div>
            <div><dt class="font-medium">{{ __('Viewer') }}</dt><dd class="text-zinc-500">{{ __('Read-only access to payments and reports.') }}</dd></div>
        </dl>
    </section>

    @if ($this->canManage)
        <flux:modal name="invite" class="w-full max-w-md">
            <form wire:submit="invite" class="space-y-5">
                <flux:heading size="lg">{{ __('Invite a team member') }}</flux:heading>
                <flux:input wire:model="email" type="email" :label="__('Email address')" dir="ltr" required />
                <flux:select wire:model="role" :label="__('Role')">
                    @foreach (MerchantRole::assignable() as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Send invitation') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
