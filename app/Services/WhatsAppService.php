<?php

namespace App\Services;

use App\Models\Order;

class WhatsAppService
{
    protected string $phoneNumber;

    public function __construct()
    {
        $rawNumber = config('services.whatsapp.phone_number', '963983743275');
        $this->phoneNumber = preg_replace('/[^0-9]/', '', $rawNumber);
    }

    public function generateOrderLink(Order $order): string
    {
        $message = $this->buildOrderMessage($order);
        return "https://wa.me/" . $this->phoneNumber . "?text=" . rawurlencode($message);
    }

    protected function buildOrderMessage(Order $order): string
    {
        // تحميل كافة العلاقات بما فيها فرع الشحن
        $order->load(['governorate', 'customer', 'items.variation.product', 'shippingBranch']);

        $lines = [];
        $lines[] = "📦 *طلب جديد رقم: #{$order->id}*";
        $lines[] = "━━━━━━━━━━━━━━";
        $lines[] = "👤 *الزبون:* " . ($order->customer->name ?? 'غير محدد');
        $lines[] = "📞 *الهاتف:* " . ($order->customer->phone ?? 'غير محدد');

        // --- قسم العنوان الذكي ---
// --- قسم العنوان الذكي ---
$governorateName = $order->governorate->name ?? 'غير محدد';
$lines[] = "📍 *المحافظة:* " . $governorateName;

if ($order->shippingBranch && $order->shippingBranch->id) {
    // استخدم branch_name بدلاً من name ✅
    $lines[] = "🚚 *طريقة الاستلام:* من فرع [ " . $order->shippingBranch->branch_name . " ]";
} else {
    // إذا كان عنواناً تفصيلياً
    $lines[] = "🏠 *العنوان التفصيلي:* " . ($order->detailed_address ?: 'لا يوجد عنوان محدد');
}
        // -----------------------

        $lines[] = "━━━━━━━━━━━━━━";
        $lines[] = "🛒 *المنتجات:*";

        foreach ($order->items as $item) {
            $productName = $item->variation->product->name ?? 'منتج';
            $variantName = $item->variation->attribute_name ?? '';
            $lines[] = "🔹 {$productName} ({$variantName}) x{$item->quantity}";
        }

        $lines[] = "━━━━━━━━━━━━━━";
        if ($order->discount_amount > 0) {
            $lines[] = "💰 *الخصم:* " . number_format($order->discount_amount) . " ل.س";
        }
        $lines[] = "🚚 *أجور الشحن:* " . number_format($order->shipping_cost) . " ل.س";
        $lines[] = "💵 *الإجمالي النهائي:* " . number_format($order->total_price) . " ل.س";
        $lines[] = "━━━━━━━━━━━━━━";
        $lines[] = "✅ تم تسجيل الطلب بنجاح.. شكراً لثقتكم! ✨";

        return implode("\n", $lines);
    }
}
