<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine if the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        // User can view if they own the business that placed the order
        return $user->business && $user->business->id === $order->business_id;
    }

    /**
     * Determine if the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        // User can update if they own the business and order is still pending
        return $user->business 
            && $user->business->id === $order->business_id
            && in_array($order->status, ['pending', 'processing']);
    }

    /**
     * Determine if the user can delete/cancel the order.
     */
    public function delete(User $user, Order $order): bool
    {
        // Can only cancel pending orders
        return $user->business 
            && $user->business->id === $order->business_id
            && $order->status === 'pending';
    }
}
