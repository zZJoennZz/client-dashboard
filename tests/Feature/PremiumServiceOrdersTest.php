<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PremiumServiceOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_and_items_can_be_created_without_an_online_payment_provider(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $service = Service::factory()->create(['price' => '125.00']);
        $order = Order::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $item = OrderItem::createFromService($order, $service, 2);

        $this->assertSame($business->id, $order->fresh()->business->id);
        $this->assertSame($user->id, $order->fresh()->user->id);
        $this->assertSame('250.00', $order->fresh()->total);
        $this->assertSame('250.00', $item->line_total);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_order_item_preserves_the_service_price_snapshot(): void
    {
        $order = Order::factory()->create();
        $service = Service::factory()->create(['price' => '75.50']);

        $item = OrderItem::createFromService($order, $service, 3);
        $service->update(['price' => '99.99']);

        $this->assertSame('75.50', $item->fresh()->unit_price);
        $this->assertSame('226.50', $item->fresh()->line_total);
        $this->assertSame('226.50', $order->fresh()->total);
    }

    public function test_manual_payments_update_partial_and_paid_summary_states(): void
    {
        $admin = $this->paymentManager();
        $order = $this->orderWithTotal('100.00');

        OrderPayment::recordManual($order, $admin, [
            'amount' => '35.00',
            'method' => 'cash',
            'reference' => 'CASH-1',
        ]);

        $this->assertSame('partially_paid', $order->fresh()->payment_status);

        OrderPayment::recordManual($order, $admin, [
            'amount' => '65.00',
            'method' => 'check',
            'reference' => 'CHECK-2',
        ]);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('submitted', $order->fresh()->status);
    }

    public function test_user_without_permission_cannot_update_offline_payment(): void
    {
        $admin = $this->paymentManager();
        $unauthorizedUser = User::factory()->create();
        $order = $this->orderWithTotal('100.00');
        $payment = OrderPayment::recordManual($order, $admin, [
            'amount' => '25.00',
            'method' => 'cash',
        ]);

        $this->expectException(AuthorizationException::class);

        Gate::forUser($unauthorizedUser)->authorize('update', $payment);
    }

    public function test_order_item_quantity_and_payment_amount_must_be_valid(): void
    {
        $order = Order::factory()->create();
        $service = Service::factory()->create();

        try {
            OrderItem::createFromService($order, $service, 0);
            $this->fail('A zero quantity should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $admin = $this->paymentManager();
        $order = $this->orderWithTotal('100.00');

        try {
            OrderPayment::recordManual($order, $admin, [
                'amount' => '0.00',
                'method' => 'cash',
            ]);
            $this->fail('A zero payment should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        try {
            OrderPayment::recordManual($order, $admin, [
                'amount' => '-1.00',
                'method' => 'cash',
            ]);
            $this->fail('A negative payment should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
    }

    public function test_order_cannot_be_overpaid_and_payment_status_is_separate(): void
    {
        $admin = $this->paymentManager();
        $order = $this->orderWithTotal('50.00', 'in_progress');

        OrderPayment::recordManual($order, $admin, [
            'amount' => '50.00',
            'method' => 'bank_transfer',
        ]);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('in_progress', $order->fresh()->status);

        $this->expectException(ValidationException::class);

        OrderPayment::recordManual($order, $admin, [
            'amount' => '0.01',
            'method' => 'cash',
        ]);
    }

    public function test_reversing_payment_preserves_entry_and_recalculates_order_summary(): void
    {
        $admin = $this->paymentManager();
        $order = $this->orderWithTotal('80.00');
        $payment = OrderPayment::recordManual($order, $admin, [
            'amount' => '80.00',
            'method' => 'check',
        ]);

        $payment->reverse($admin, 'Check was voided');

        $this->assertSame('reversed', $payment->fresh()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertNotNull($payment->fresh()->reversed_at);
    }

    private function paymentManager(): User
    {
        $user = User::factory()->create();
        $permission = Permission::findOrCreate('manage offline payments', 'web');
        $user->givePermissionTo($permission);
        $this->assertTrue($user->checkPermissionTo('manage offline payments'));

        return $user;
    }

    private function orderWithTotal(string $total, string $status = 'submitted'): Order
    {
        return Order::factory()->create([
            'business_id' => Business::factory(),
            'status' => $status,
            'subtotal' => $total,
            'total' => $total,
        ]);
    }
}
