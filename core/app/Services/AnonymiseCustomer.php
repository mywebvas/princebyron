<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\DownloadEntitlement;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * AnonymiseCustomer — the supported way to honour "delete my account" (P1-5).
 *
 * WHY THIS EXISTS
 * ---------------
 * Sprint 2 made `User::delete()` throw when the customer has orders, and the
 * error message promised "deactivate or anonymise" as the alternative. That
 * promise has to exist, or the guard is just a wall: an owner faced with a
 * deletion request would have no lawful way to comply.
 *
 * The tension is real and it is the reason this is a service and not a cascade:
 *
 *   - The customer has a right to have their personal data removed.
 *   - The business has a duty to keep a record of the transaction: what was sold,
 *     for how much, when, and to which account. Tax authorities require it, and
 *     a chargeback dispute is unwinnable without it.
 *
 * Both can hold at once only if the identifying fields go and the transaction
 * stays. So this:
 *
 *   1. Replaces every direct identifier on the account (name, email, phone,
 *      password) with a non-identifying placeholder.
 *   2. Strips the postal address and customer name from the orders, keeping the
 *      money, the line items, the dates and the reference.
 *   3. Revokes download entitlements: the files belonged to that person and the
 *      account can no longer be authenticated as them.
 *   4. Deactivates the account so it cannot sign in.
 *   5. Records what happened, in the audit log, without the data that was
 *      removed. The log says "anonymised, request ref X" — it does not keep a
 *      copy of the thing we were asked to delete, which would defeat the point.
 *
 * What it does NOT do:
 *   - It does not delete the order rows (P1-5: they are financial records).
 *   - It does not touch the audit trail's actor_id, which is already a foreign
 *     key to a row that still exists (now anonymised).
 *   - It does not claim to be irreversible. It IS irreversible by design, and
 *     the admin action that calls it says so before the owner confirms.
 */
class AnonymiseCustomer
{
    public const REASON_DELETION_REQUEST = 'customer_request';
    public const REASON_INACTIVE = 'account_inactive';
    public const REASON_LEGAL = 'legal_requirement';

    /**
     * Anonymise a customer, keeping their financial history intact.
     *
     * @return array{orders:int, entitlements:int, reference:string}
     */
    public function handle(User $user, string $reason = self::REASON_DELETION_REQUEST, ?string $note = null): array
    {
        if ($user->isOwner()) {
            // An owner account is not a customer record; anonymising it would
            // destroy the audit trail's attribution for every admin action the
            // owner ever took. That is an ownership-transfer operation, not a
            // privacy operation.
            throw new \DomainException('Owner accounts cannot be anonymised: the audit trail would lose its attributions.');
        }

        $reference = 'ANON-'.strtoupper(bin2hex(random_bytes(4)));
        $ordersTouched = 0;
        $entitlementsRevoked = 0;

        DB::transaction(function () use ($user, &$ordersTouched, &$entitlementsRevoked) {
            // 1. Orders: keep the money and the line items, drop the identity.
            $ordersTouched = Order::where('user_id', $user->id)->update([
                'customer_name'  => 'Anonymised customer',
                'customer_email' => 'anonymised@invalid',
                'notes'          => null,
                // The address is the most sensitive field here — it is where a
                // person physically is. Aggregation only needs the country for
                // tax reporting, which is kept.
                'shipping_name'         => 'Anonymised customer',
                'shipping_address_line1' => null,
                'shipping_address_line2' => null,
                'shipping_city'         => null,
                'shipping_state'        => null,
                'shipping_postal_code'  => null,
            ]);

            // 2. Downloads belonged to that person.
            $entitlementsRevoked = DownloadEntitlement::where('user_id', $user->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // 3. The account itself. The email must remain unique, so the
            //    placeholder is derived from the id rather than a shared literal.
            $user->forceFill([
                'name'              => 'Anonymised customer',
                'email'             => 'anonymised+'.$user->id.'@invalid',
                'phone'             => null,
                'password'          => bin2hex(random_bytes(32)), // unusable, not a known hash
                'remember_token'    => null,
                'is_active'         => false,
                'email_verified_at' => null,
            ])->save();
        });

        // 4. Record it. Deliberately without the removed values: keeping a copy
        //    of the deleted data in the log would defeat the request.
        AuditEvent::create([
            'actor_id'       => auth()->id(),
            'action'         => 'customer.anonymised',
            'auditable_type' => User::class,
            'auditable_id'   => $user->id,
            'old_values'     => null,
            'new_values'     => [
                'reference'             => $reference,
                'reason'                => $reason,
                'note'                  => $note,
                'orders_retained'       => $ordersTouched,
                'entitlements_revoked'  => $entitlementsRevoked,
                'personal_data_removed' => ['name', 'email', 'phone', 'password', 'address'],
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return [
            'orders' => $ordersTouched,
            'entitlements' => $entitlementsRevoked,
            'reference' => $reference,
        ];
    }
}
