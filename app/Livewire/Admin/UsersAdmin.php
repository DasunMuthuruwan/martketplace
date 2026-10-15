<?php

namespace App\Livewire\Admin;

use App\Actions\Staff\ManageAccount;
use App\Actions\Staff\SetLegalHold;
use App\Authorization\Permissions;
use App\Enums\UserStatus;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * User manager (ADM-04): search by name, email or phone; view an account (email and phone only
 * for staff allowed to see them, UserPolicy::viewContactDetails); suspend (optionally until a
 * date), ban, reinstate, verify email, delete, all through ManageAccount (policy, hierarchy,
 * audit, emails). Ban and delete also need a recently confirmed password.
 */
#[Layout('layouts.admin')]
#[Title('Users')]
class UsersAdmin extends Component
{
    use RequiresPermission, WithPagination;

    public const PER_PAGE = 25;

    #[Url]
    public string $q = '';

    #[Url]
    public string $status = '';

    #[Locked]
    public ?int $selectedId = null;

    public string $reason = '';

    public string $until = '';

    public function mount(): void
    {
        $this->requirePermission(Permissions::USERS_VIEW);
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['q', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function select(int $id): void
    {
        $this->requirePermission(Permissions::USERS_VIEW);
        abort_unless(User::whereKey($id)->exists(), 404);

        $this->selectedId = $id;
        $this->reset('reason', 'until');
        $this->resetErrorBag();
    }

    /** Back to the list (phones). */
    public function close(): void
    {
        $this->reset('selectedId', 'reason', 'until');
        $this->resetErrorBag();
    }

    public function suspend(ManageAccount $accounts): void
    {
        $until = $this->until === '' ? null : Carbon::parse($this->until)->endOfDay();
        $this->act(fn (User $user) => $accounts->suspend(Auth::user(), $user, $this->reason, $until), 'staff.accounts.suspended');
    }

    public function ban(ManageAccount $accounts): void
    {
        if (! $this->hasRecentPassword()) {
            return;
        }
        $this->act(fn (User $user) => $accounts->ban(Auth::user(), $user, $this->reason), 'staff.accounts.banned');
    }

    public function reinstate(ManageAccount $accounts): void
    {
        $this->act(fn (User $user) => $accounts->reinstate(Auth::user(), $user, $this->reason ?: null), 'staff.accounts.reinstated');
    }

    public function verifyEmail(ManageAccount $accounts): void
    {
        $this->act(fn (User $user) => $accounts->verifyEmail(Auth::user(), $user), 'staff.accounts.verified');
    }

    public function delete(ManageAccount $accounts): void
    {
        if (! $this->hasRecentPassword()) {
            return;
        }
        $this->act(fn (User $user) => $accounts->delete(Auth::user(), $user, $this->reason), 'staff.accounts.deleted');
        $this->selectedId = null;
    }

    /** Retention §3: keep the account and its listings out of retention deletion (Owner, reason required). */
    public function toggleLegalHold(SetLegalHold $hold): void
    {
        $this->requirePermission(Permissions::USERS_DELETE);
        if (! $this->hasRecentPassword()) {
            return;
        }
        $this->validate(['reason' => ['required', 'string', 'max:500']]);

        $user = User::withTrashed()->findOrFail($this->selectedId);
        $hold->handle(Auth::user(), $user, ! $user->legal_hold, $this->reason);
        session()->flash('status', __($user->legal_hold ? 'staff.accounts.hold_set' : 'staff.accounts.hold_lifted'));
        $this->reset('reason');
    }

    public function render(): View
    {
        $term = trim($this->q);
        $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
        // Digits without leading zeros, so a number typed in national format (with a trunk "0")
        // still finds the stored E.164 form.
        $digits = ltrim((string) preg_replace('/\D/', '', $term), '0');
        $selected = $this->selectedId === null ? null : User::withTrashed()->find($this->selectedId);

        return view('livewire.admin.users', [
            'users' => User::query()
                ->when($term !== '', fn ($q) => $q->where(function ($q) use ($like, $digits): void {
                    $q->where('name', 'like', $like)->orWhere('email', 'like', $like);
                    if (strlen($digits) >= 4) {
                        $q->orWhere('phone', 'like', '%'.$digits.'%');
                    }
                }))
                ->when(in_array($this->status, array_column(UserStatus::cases(), 'value'), true), fn ($q) => $q->where('status', $this->status))
                ->orderByDesc('id')
                ->paginate(self::PER_PAGE),
            'selected' => $selected,
            'details' => $selected === null ? null : $this->details($selected),
            'statuses' => UserStatus::cases(),
        ]);
    }

    /** @param  callable(User): mixed  $action */
    private function act(callable $action, string $message): void
    {
        $this->requirePermission(Permissions::USERS_VIEW);
        $user = $this->selectedId === null ? null : User::find($this->selectedId);
        abort_if($user === null, 404);

        try {
            $action($user);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        } catch (AuthorizationException) {
            $this->addError('status', __('staff.accounts.not_allowed'));

            return;
        }

        session()->flash('status', __($message, ['name' => $user->name]));
        $this->reset('reason', 'until');
    }

    /** @return array<string, mixed> */
    private function details(User $user): array
    {
        $actor = Auth::user();

        return [
            'contact' => Gate::forUser($actor)->allows('viewContactDetails', $user),
            'listings' => Listing::withTrashed()->where('user_id', $user->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'reports' => Report::where('reportable_type', 'user')->where('reportable_id', $user->id)->count(),
            'role' => $user->staffRole()?->value,
            'can' => [
                'suspend' => Gate::forUser($actor)->allows('suspend', $user),
                'ban' => Gate::forUser($actor)->allows('ban', $user),
                'reinstate' => $user->status !== UserStatus::Active && Gate::forUser($actor)->allows('reinstate', $user),
                'verify' => ! $user->hasVerifiedEmail() && Gate::forUser($actor)->allows('verifyEmail', $user),
                'delete' => ! $user->trashed() && Gate::forUser($actor)->allows('delete', $user),
            ],
            'legalHold' => $actor->can(Permissions::USERS_DELETE),
        ];
    }
}
