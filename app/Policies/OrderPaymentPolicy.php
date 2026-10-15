<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;

class OrderPaymentPolicy
{
    public function create(User $user, Order $order): bool
    {
        return $user->checkPermissionTo('manage offline payments')
            && $order->status !== 'cancelled';
    }

    public function update(User $user, OrderPayment $payment): bool
    {
        return $user->checkPermissionTo('manage offline payments')
            && $payment->status === 'confirmed';
    }
}
