<?php
namespace App\Livewire;

use App\Models\Customer;
use App\Models\District;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingBranch;
use App\Models\ProductVariation;
use App\Models\Coupon;
use App\Services\CartService;
use App\Services\CartCalculationService;
use app\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Mary\Traits\Toast;

class CartWizard extends Component
{
    use Toast;

    public bool $showModal = false;
    public int $step = 1; // Control wizard steps

    // Customer data
    public string $name = '';
    public string $phone = '';

    // Shipping data
    public ?int $governorate_id = null;
    public ?int $district_id = null;
    public ?int $shipping_branch_id = null;
    public string $detailed_address = '';

    // Coupon data
    public string $coupon_code = '';
    public string $coupon_error = '';
    public float $discount_amount = 0;

    // Final calculations
    public float $shipping_cost = 0;
    public float $total_amount = 0; // NOTE: This is still used for UI but will be recalculated in transaction

    protected CartService $cartService;
    protected CartCalculationService $calculationService;

    // Enhanced cart summary with processed image URLs
    public array $cartSummary = [
        'count' => 0,
        'total' => 0,
        'items' => [],
    ];

    public function boot(CartService $cartService, CartCalculationService $calculationService)
    {
        $this->cartService = $cartService;
        $this->calculationService = $calculationService;
        $this->updateCartSummary();
    }

    // Listen for cart updates from other components
    protected $listeners = [
        'cartUpdated' => 'handleCartUpdate',
        'openCart' => 'open'
    ];

    public function handleCartUpdate()
    {
        $this->updateCartSummary();
        $this->dispatch('$refresh');
    }

    public function open()
    {
        $this->updateCartSummary();
        $this->showModal = true;
    }

    // Quantity controls
    public function increment(int $id): void
    {
        $this->cartService->add($id, 1);
        $this->updateCartSummary();
        $this->dispatch('cartUpdated');
    }

    public function decrement(int $id): void
    {
        $this->cartService->decrease($id);
        $this->updateCartSummary();
        $this->dispatch('cartUpdated');
    }

    public function remove(int $id): void
    {
        $this->cartService->remove($id);
        $this->updateCartSummary();
        $this->dispatch('cartUpdated');
        $this->success('تم حذف المنتج');
    }

    // Update district/branch when governorate changes
    public function updatedGovernorateId(): void
    {
        $this->district_id = null;
        $this->shipping_branch_id = null;
        $this->shipping_cost = 0;
        $this->calculateFinalTotals();
    }

    // Coupon handling
public function applyCoupon(): void
{
    $this->coupon_error = '';

    // 1. التحقق من إدخال الكود
    if (empty($this->coupon_code)) {
        $this->coupon_error = 'الرجاء إدخال كود الكوبون';
        return;
    }

    // 2. التحقق من إدخال رقم الهاتف أولاً (لأننا سنفحص التاريخ بناءً عليه)
    if (empty($this->phone)) {
        $this->coupon_error = 'الرجاء إدخال رقم هاتفك في الخطوة السابقة أولاً للتحقق من صلاحية الكوبون لك';
        // نصيحة: يفضل جعل إدخال الهاتف قبل إدخال الكوبون برمجياً
        return;
    }

    // 3. التحقق من سجل الطلبات السابقة لهذا الهاتف مع هذا الكوبون
    $alreadyUsed = Order::whereHas('customer', function ($query) {
        $query->where('phone', $this->phone);
    })
    ->where('coupon_code', $this->coupon_code)
    ->where('status', '!=', 'cancelled') // نتجاهل الطلبات الملغاة لنعطي الزبون فرصة أخرى
    ->exists();

    if ($alreadyUsed) {
        $this->coupon_error = 'عذراً، لقد قمت باستخدام هذا الكوبون مسبقاً في طلب آخر.';
        return;
    }

    // 4. التحقق العام من صحة الكوبون (الصلاحية التاريخية والعدد الكلي)
    if (!$this->calculationService->isValidCoupon($this->coupon_code)) {
        $this->coupon_error = 'كود الكوبون غير صحيح أو منتهي الصلاحية أو تم استخدامه بالكامل';
        return;
    }

    $this->calculateFinalTotals();
    $this->success('تم تطبيق الكوبون بنجاح');
}

    public function removeCoupon(): void
    {
        $this->coupon_code = '';
        $this->coupon_error = '';
        $this->discount_amount = 0;
        $this->calculateFinalTotals();
        $this->success('تم إزالة الكوبون');
    }

    // Navigate wizard steps
    public function nextStep(): void
    {
        if ($this->validateStep()) {
            $this->step++;

            if ($this->step === 4) {
                $this->calculateFinalTotals();
            }
        }
    }

    public function prevStep(): void
    {
        $this->step--;
    }

    public function validateStep(): bool
    {
        switch ($this->step) {
            case 1:
                if ($this->cartService->count() === 0) {
                    $this->error('السلة فارغة!');
                    return false;
                }
                break;

            case 2:
                $this->validate([
                    'name' => 'required|string|min:3',
                    'phone' => 'required|numeric|digits_between:8,14',
                ]);
                break;

            case 3:
                $rules = ['governorate_id' => 'required'];

                if ($this->governorate_id == 1) { // Damascus
                    $rules['district_id'] = 'required';
                    $rules['detailed_address'] = 'required|string|min:5';
                } else { // Other governorates
                    $rules['shipping_branch_id'] = 'required';
                }

                $this->validate($rules);
                break;
        }

        return true;
    }

    public function calculateFinalTotals(): void
    {
        // Calculate shipping cost based on location
        if ($this->governorate_id == 1 && $this->district_id) {
            $district = District::find($this->district_id);
            $this->shipping_cost = $district ? $district->shipping_cost : 0;
        } elseif ($this->shipping_branch_id) {
            $branch = ShippingBranch::find($this->shipping_branch_id);
            $this->shipping_cost = $branch ? $branch->shipping_cost : 0;
        }

        // Calculate discount using service
        $this->discount_amount = $this->calculationService->calculateDiscount($this->coupon_code);

        // Calculate final total using service
        $this->total_amount = $this->calculationService->calculateTotal($this->shipping_cost, $this->coupon_code);
    }

    public function placeOrder()
    {
        try {
            // Perform order creation in a single transaction
            $order = DB::transaction(function () {
                // 1. Handle customer (find or create)
                $customer = Customer::firstOrCreate(
                    ['phone' => $this->phone],
                    ['name' => $this->name]
                );

                // 2. Calculate verified total price inside transaction using the service
                $verifiedSubtotal = $this->calculationService->calculateSubtotal();

                // Calculate shipping cost again inside transaction for consistency
                $verifiedShippingCost = 0;
                if ($this->governorate_id == 1 && $this->district_id) {
                    $district = District::find($this->district_id);
                    $verifiedShippingCost = $district ? $district->shipping_cost : 0;
                } elseif ($this->shipping_branch_id) {
                    $branch = ShippingBranch::find($this->shipping_branch_id);
                    $verifiedShippingCost = $branch ? $branch->shipping_cost : 0;
                }

                // Re-validate coupon inside transaction and calculate discount
                $verifiedDiscount = 0;
                $finalCouponCode = null;

                if (!empty($this->coupon_code)) {
                    $validCoupon = $this->calculationService->isValidCoupon($this->coupon_code);
                    if (!$validCoupon) {
                        throw new \Exception("الكوبون غير متاح الآن. يرجى محاولة الطلب من جديد.");
                    }

                    // Get verified discount amount
                    $verifiedDiscount = $this->calculationService->calculateDiscount($this->coupon_code);
                    $finalCouponCode = $this->coupon_code;
                }

                // Calculate final total inside transaction using service
                $verifiedTotalAmount = $this->calculationService->calculateTotal($verifiedShippingCost, $this->coupon_code);

                // 3. Create order with verified total from database
                $order = Order::create([
                    'customer_id' => $customer->id,
                    'governorate_id' => $this->governorate_id,
                    'district_id' => $this->district_id,
                    'shipping_branch_id' => $this->shipping_branch_id,
                    'detailed_address' => $this->detailed_address,
                    'shipping_cost' => $verifiedShippingCost, // Use verified shipping cost
                    'total_price' => $verifiedTotalAmount, // Use verified total calculated from DB prices
                    'status' => 'pending',
                    'inventory_updated' => false, // Set to false as we're not handling stock
                    'coupon_code' => $finalCouponCode, // Persist coupon code in order
                    'discount_amount' => $verifiedDiscount, // Persist discount amount in order
                ]);

                // 4. Add order items with verified prices and availability checks
                $verifiedCartItems = $this->cartService->getItemsWithVerifiedPrices();
                foreach ($verifiedCartItems as $item) {
                    // Re-fetch variation to ensure availability is current
                    $variation = ProductVariation::with(['product'])
                        ->where('id', $item->variation_id)
                        ->first();

                    if (!$variation) {
                        throw new \Exception("Product variation not found: {$item->variation_id}");
                    }

                    // Availability check using is_available (critical security check)
                    if (!$variation->is_available) {
                        throw new \Exception("One or more items in your cart are currently unavailable.");
                    }

                    $verifiedUnitPrice = $variation->product->price + $variation->additional_price;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_variation_id' => $item->variation_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $verifiedUnitPrice, // Use verified price from DB
                        'subtotal' => $verifiedUnitPrice * $item->quantity, // Use verified calculation
                    ]);
                }

                // 5. Atomically increment coupon usage if applicable (atomic update)
                if (!empty($finalCouponCode)) {
                    $affectedRows = Coupon::whereRaw('LOWER(code) = LOWER(?)', [$finalCouponCode])
                        ->where('is_active', true)
                        ->where('expiry_date', '>=', now())
                        ->whereColumn('used_count', '<', 'usage_limit')
                        ->increment('used_count');

                    if ($affectedRows === 0) {
                        throw new \Exception("الكوبون غير متاح الآن. يرجى محاولة الطلب من جديد.");
                    }
                }

                return $order;
            });

            // 6. Clear cart and reset form after successful order
            $this->cartService->clear();
            $this->showModal = false;
            $this->step = 1;
            $this->reset(['name', 'phone', 'governorate_id', 'district_id', 'shipping_branch_id', 'detailed_address', 'coupon_code', 'coupon_error', 'discount_amount']);
            $this->updateCartSummary();

            $this->dispatch('cartUpdated');

// 2. توليد رابط الواتساب باستخدام الخدمة التي أنشأناها
        $whatsappService = app(\App\Services\WhatsAppService::class);
        $whatsappUrl = $whatsappService->generateOrderLink($order);

            $this->success('تم استلام طلبك بنجاح! رقم الطلب: #' . $order->id);


            return redirect()->away($whatsappUrl);
        } catch (\Exception $e) {
            $this->error('حدث خطأ أثناء إتمام الطلب: ' . $e->getMessage());
        }
    }


    private function updateCartSummary(): void
    {
        $items = $this->cartService->getItems();

        // Process image URLs for components
        $processedItems = $items->map(function ($item) {

        $productId = \App\Models\ProductVariation::where('id', $item->variation_id)->value('product_id');
            return [
                'variation_id' => $item->variation_id,
                'product_id' => $productId,
                'name' => $item->name,
                'image' => $item->image ? Storage::url($item->image) : null,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ];
        })->toArray();

        $this->cartSummary = [
            'count' => $this->cartService->count(),
            'total' => $this->cartService->total(),
            'items' => $processedItems,
        ];
    }

    public function render()
    {
        return view('livewire.cart-wizard', [
            'governorates' => Governorate::all()->map(function($governorate) {
                return [
                    'id' => $governorate->id,
                    'name' => $governorate->name
                ];
            }),
            'districts' => $this->governorate_id
                ? District::where('governorate_id', $this->governorate_id)->get()->map(function($district) {
                    return [
                        'id' => $district->id,
                        'name' => $district->name
                    ];
                })
                : collect([]),
            'branches' => $this->governorate_id
                ? ShippingBranch::where('governorate_id', $this->governorate_id)->get()->map(function($branch) {
                    return [
                        'id' => $branch->id,
                        'branch_name' => $branch->branch_name
                    ];
                })
                : collect([]),
        ]);
    }







public function shareCart()
{
    $items = $this->cartSummary['items'] ?? [];

    if (empty($items)) {
        $this->error('السلة فارغة، لا يوجد ما يمكن مشاركته.');
        return;
    }

    $message = "وقعت عيناي على هذه القطع في *Shaghaf Gifts2* ✨\n\n";

    foreach ($items as $item) {
        // تأكد أن الراوت يستقبل الـ variation_id فعلاً
        $url = url("/products/" . ($item['product_id'] ?? $item['variation_id']));
        $message .= "• *" . $item['name'] . "*\n";
        $message .= "🔗 " . $url . "\n\n";
    }

    $message .= "ما رأيك؟ هل أطلبها؟ 😍";

    $encodedMessage = rawurlencode($message);
    $whatsappUrl = "https://wa.me/?text=" . $encodedMessage;

    $this->dispatch('open-link', url: $whatsappUrl);
}






}
