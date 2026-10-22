<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Enums\BoostOrderStatus;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\BoostOrder;
use App\Services\Boosts\BoostOrders;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Promotion orders (B5, ADR-026, `boosts.manage`): find an order by its reference (as written on the
 * bank transfer), mark it paid once the money is seen (the boost starts), cancel an unpaid one or
 * refund a paid one (the boost ends). Cancel and refund need a reason, which the seller is emailed.
 * Staff never handle orders for their own ads. Every action is audited.
 */
#[Layout('layouts.admin')]
#[Title('Orders')]
class BoostOrdersAdmin extends Component
{
    use RequiresPermission, WithPagination;

    public const ACTIONS = ['paid', 'cancel', 'refund'];

    #[Url]
    public string $status = 'pending';

    #[Url]
    public string $q = '';

    public ?int $actingId = null;

    public string $action = '';

    public string $note = '';

    public function mount(): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        if ($this->status !== '' && BoostOrderStatus::tryFrom($this->status) === null) {
            $this->status = 'pending';
        }
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
        $this->stop();
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function start(int $id, string $action): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        if (! in_array($action, self::ACTIONS, true)) {
            return;
        }
        $this->actingId = $id;
        $this->action = $action;
        $this->note = '';
        $this->resetErrorBag();
    }

    public function stop(): void
    {
        $this->reset(['actingId', 'action', 'note']);
        $this->resetErrorBag();
    }

    public function confirm(BoostOrders $orders): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        $this->validate([
            'action' => ['required', Rule::in(self::ACTIONS)],
            'note' => [$this->action === 'paid' ? 'nullable' : 'required', 'string', 'max:500'],
        ], [], ['note' => $this->action === 'paid' ? __('admin.orders.payment_note') : __('admin.orders.reason')]);
        $order = BoostOrder::findOrFail($this->actingId);
        $note = trim($this->note) === '' ? null : trim($this->note);

        try {
            match ($this->action) {
                'paid' => $orders->markPaid(Auth::user(), $order, $note),
                'cancel' => $orders->cancel(Auth::user(), $order, (string) $note),
                'refund' => $orders->refund(Auth::user(), $order, (string) $note),
                default => throw new \InvalidArgumentException("Unknown order action [{$this->action}]."),
            };
        } catch (ValidationException $e) {
            $this->addError('note', collect($e->errors())->flatten()->first());

            return;
        }

        session()->flash('status', __('admin.orders.done.'.$this->action, ['reference' => $order->reference]));
        $this->stop();
    }

    public function render(): View
    {
        $q = strtoupper(trim($this->q));

        return view('livewire.admin.boost-orders', [
            'orders' => BoostOrder::query()
                ->with(['listing', 'user', 'country', 'currencyModel', 'handledBy'])
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->when($q !== '', fn ($query) => $query->where('reference', 'like', '%'.addcslashes($q, '%_\\').'%'))
                ->orderByRaw($this->status === 'pending' ? 'created_at asc' : 'created_at desc')
                ->orderBy('id')
                ->paginate(30),
        ]);
    }
}
