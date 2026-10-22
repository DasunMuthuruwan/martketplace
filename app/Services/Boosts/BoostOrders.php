<?php

namespace App\Services\Boosts;

use App\Enums\BoostOrderStatus;
use App\Enums\BoostType;
use App\Models\BoostOrder;
use App\Models\BoostPrice;
use App\Models\Listing;
use App\Models\ListingBoost;
use App\Models\User;
use App\Notifications\BoostOrderClosed;
use App\Notifications\BoostOrderPaid;
use App\Notifications\BoostOrderPlaced;
use App\Notifications\TopAdEndingSoon;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Boost orders paid by bank transfer (ADR-026, D3, B5). The seller orders for their own live ad at
 * the server's price (never one from the browser), pays by transfer with the order reference, and
 * staff confirm the payment, which starts the boost. Staff can cancel an unpaid order or refund a
 * paid one (the boost ends). Unpaid orders lapse after `boosts.payment_days`. Staff never handle
 * orders for their own ads. Every staff action is audited; the seller is emailed at each step.
 */
class BoostOrders
{
    public const MAX_PENDING = 3;

    /** Letters and digits that cannot be confused when typed into a bank form (no 0/O, 1/I/L). */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly BoostCatalogue $catalogue,
        private readonly BoostManager $boosts,
        private readonly AuditLogger $audit,
    ) {}

    /** @throws ValidationException */
    public function place(User $seller, Listing $listing, int $priceId): BoostOrder
    {
        Gate::forUser($seller)->authorize('update', $listing);
        if (! Listing::query()->public()->whereKey($listing->id)->exists()) {
            throw ValidationException::withMessages(['price' => __('boosts.not_live')]);
        }
        $country = $listing->country;
        if (! $this->catalogue->isOpen($country)) {
            throw ValidationException::withMessages(['price' => __('boosts.order.closed')]);
        }
        $price = $this->catalogue->offers($country)->first(fn (BoostPrice $p) => $p->id === $priceId);
        if ($price === null) {
            throw ValidationException::withMessages(['price' => __('boosts.order.unavailable')]);
        }
        $product = $price->product;
        if ($product->type === BoostType::Top && $listing->expires_at !== null
            && now()->addDays((int) config('marketplace.boosts.payment_days'))->addDays((int) $product->duration_days)->isAfter($listing->expires_at)) {
            throw ValidationException::withMessages(['price' => __('boosts.order.renew_first')]);
        }

        return DB::transaction(function () use ($seller, $listing, $price, $product): BoostOrder {
            User::whereKey($seller->id)->lockForUpdate()->first();
            $pending = BoostOrder::where('user_id', $seller->id)->where('status', BoostOrderStatus::Pending->value)->count();
            if ($pending >= self::MAX_PENDING) {
                throw ValidationException::withMessages(['price' => __('boosts.order.too_many_pending', ['max' => self::MAX_PENDING])]);
            }

            $order = new BoostOrder;
            $order->forceFill([
                'reference' => $this->newReference(),
                'user_id' => $seller->id,
                'listing_id' => $listing->id,
                'boost_product_id' => $product->id,
                'product_type' => $product->type,
                'duration_days' => $product->duration_days,
                'country_id' => $listing->country_id,
                'currency' => $price->currency,
                'amount' => $price->amount,
                'status' => BoostOrderStatus::Pending,
                'expires_at' => now()->addDays((int) config('marketplace.boosts.payment_days')),
            ])->save();
            $seller->notify(new BoostOrderPlaced($order));

            return $order;
        });
    }

    /** The seller withdraws an unpaid order. */
    public function cancelBySeller(User $seller, BoostOrder $order): void
    {
        if ($order->user_id !== $seller->id || ! $order->status->isOpen()) {
            throw ValidationException::withMessages(['order' => __('boosts.order.not_pending')]);
        }
        $order->forceFill(['status' => BoostOrderStatus::Cancelled, 'closed_at' => now()])->save();
    }

    /**
     * Staff saw the transfer: the boost starts now. A boost that cannot start (the ad is no longer
     * live, or a Top ad would outlast it) leaves the order pending with the reason.
     *
     * @throws ValidationException
     */
    public function markPaid(User $staff, BoostOrder $order, ?string $note = null): ListingBoost
    {
        return DB::transaction(function () use ($staff, $order, $note): ListingBoost {
            $order = BoostOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertHandledBy($staff, $order, BoostOrderStatus::Pending);
            $listing = $order->listing;
            if ($listing === null || $listing->trashed()) {
                throw ValidationException::withMessages(['order' => __('boosts.not_live')]);
            }

            $boost = $order->product_type === BoostType::Bump
                ? $this->boosts->bump($listing, null, $order->id)
                : $this->boosts->top($listing, (int) $order->duration_days, null, $order->id);

            $order->forceFill(['status' => BoostOrderStatus::Paid, 'paid_at' => now(), 'handled_by' => $staff->id, 'note' => $note])->save();
            $this->audit->record($staff, 'boost_order.paid', $listing, $note, ['order' => $order->reference, 'amount' => $order->amount, 'currency' => $order->currency]);
            $order->user?->notify(new BoostOrderPaid($order, $boost));

            return $boost;
        });
    }

    public function cancel(User $staff, BoostOrder $order, string $reason): void
    {
        $this->close($staff, $order, BoostOrderStatus::Pending, BoostOrderStatus::Cancelled, $reason);
    }

    /** Refund a paid order (D4: the marketplace failed to deliver, or a published exception): the boost ends. */
    public function refund(User $staff, BoostOrder $order, string $reason): void
    {
        DB::transaction(function () use ($staff, $order, $reason): void {
            $this->close($staff, $order, BoostOrderStatus::Paid, BoostOrderStatus::Refunded, $reason);
            if ($order->boost !== null) {
                $this->boosts->end($order->boost, $staff, $reason);
            }
        });
    }

    /** Hourly: unpaid orders past their payment window lapse. */
    public function expireOverdue(): int
    {
        $count = 0;
        BoostOrder::where('status', BoostOrderStatus::Pending->value)->where('expires_at', '<', now())
            ->with('user')->each(function (BoostOrder $order) use (&$count): void {
                $order->forceFill(['status' => BoostOrderStatus::Expired, 'closed_at' => now()])->save();
                $order->user?->notify(new BoostOrderClosed($order));
                $count++;
            });

        return $count;
    }

    /** Hourly: "your Top ad ends tomorrow" for paid Top ads, once each. */
    public function sendEndingReminders(): int
    {
        $count = 0;
        ListingBoost::query()->running()
            ->where('type', BoostType::Top->value)
            ->whereNotNull('order_id')
            ->whereNull('ending_reminder_sent_at')
            ->where('ends_at', '<=', now()->addDay())
            ->with('listing.user')
            ->each(function (ListingBoost $boost) use (&$count): void {
                $boost->forceFill(['ending_reminder_sent_at' => now()])->save();
                $boost->listing?->user?->notify(new TopAdEndingSoon($boost));
                $count++;
            });

        return $count;
    }

    private function close(User $staff, BoostOrder $order, BoostOrderStatus $from, BoostOrderStatus $to, string $reason): void
    {
        DB::transaction(function () use ($staff, $order, $from, $to, $reason): void {
            $locked = BoostOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertHandledBy($staff, $locked, $from);
            $locked->forceFill(['status' => $to, 'closed_at' => now(), 'handled_by' => $staff->id, 'note' => $reason])->save();
            $order->setRawAttributes($locked->getAttributes(), true);
            $this->audit->record($staff, 'boost_order.'.$to->value, $locked->listing, $reason, ['order' => $locked->reference]);
            $locked->user?->notify(new BoostOrderClosed($locked));
        });
    }

    /** @throws ValidationException */
    private function assertHandledBy(User $staff, BoostOrder $order, BoostOrderStatus $expected): void
    {
        if ($order->user_id === $staff->id) {
            throw ValidationException::withMessages(['order' => __('boosts.order.own_order')]);
        }
        if ($order->status !== $expected) {
            throw ValidationException::withMessages(['order' => __('boosts.order.wrong_state', ['status' => __('boosts.order.status.'.$order->status->value)])]);
        }
    }

    private function newReference(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
            }
            $reference = 'B'.$code;
        } while (BoostOrder::where('reference', $reference)->exists());

        return Str::upper($reference);
    }
}
