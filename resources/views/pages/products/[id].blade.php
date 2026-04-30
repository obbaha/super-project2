<?php
use App\Models\Product;
use App\Services\CartService;
use function Livewire\Volt\{state, computed, uses};
use Mary\Traits\Toast;

// استخدام الـ Toast للتنبيهات
uses([Toast::class]);

// 1. تعريف الحالة واستقبال المعرف من الرابط
state(['productId' => fn() => request()->route('id')]);

// 2. جلب المنتج كخاصية محسوبة (Computed)
$product = computed(function () {
    return Product::with(['variations.images', 'category']) // تغيير featuredImage إلى images
        ->where('is_available', true)
        ->findOrFail($this->productId);
});

// 3. دالة الإضافة للسلة
$addToCart = function (CartService $cartService, int $selectedVariationId, int $quantity) {
    $productData = $this->product;
    $variation = $productData->variations()->where('id', $selectedVariationId)->first();

    if (!$variation || !$variation->is_available) {
        $this->error('عذراً، هذا النوع غير متوفر حالياً.');
        return;
    }

    $cartService->add($selectedVariationId, $quantity);
    $this->dispatch('cart-updated');
    $this->success(
        title: 'تمت الإضافة بنجاح',
        description: 'تمت إضافة القطعة إلى حقيبتك الفاخرة',
        icon: 'o-shopping-bag',
        position: 'toast-bottom toast-start'
    );
};

?>

<x-layouts.app title="تفاصيل القطعة">
    @volt
    <div x-data="{
    quantity: 1,
    {{-- نأخذ أول تنوع متاح كافتراضي --}}
    selectedVariationId: {{ $this->product->variations->where('is_available', true)->first()->id ?? 0 }},

    {{-- 1. مؤشر الصورة النشطة (للتنقل داخل ألبوم الصور) --}}
    activeImageIndex: 0,

    basePrice: {{ $this->product->price }},

    {{-- 2. جلب مصفوفة الصور لكل تنوع بدلاً من صورة واحدة --}}
    variations: @js($this->product->variations->map(fn($v) => [
        'id' => $v->id,
        'name' => $v->attribute_name,
        'additional_price' => $v->additional_price,
        'images' => $v->images->map(fn($img) => Storage::url($img->path)), {{-- مصفوفة روابط --}}
        'isAvailable' => (bool)$v->is_available
    ])),

    get selectedVariation() {
        return this.variations.find(v => v.id == this.selectedVariationId)
    },

// داخل x-data أضف هذه الخاصية:
get allImages() {
    let images = [];
    this.variations.forEach(v => {
        v.images.forEach(img => {
            // نخزن الصورة مع معرف الموديل التابع لها
            images.push({ url: img, variationId: v.id });
        });
    });
    return images;
},

get thumbnailImages() {
    return this.variations.map(v => {
        return {
            url: (v.images && v.images.length > 0) ? v.images[0] : null,
            variationId: v.id
        };
    }).filter(img => img.url);
},




// تعديل currentImage لتعتمد على المصفوفة الشاملة
get currentImage() {
    return this.allImages[this.activeImageIndex]?.url || '';
},

    get currentPrice() {
        let addPrice = this.selectedVariation ? parseFloat(this.selectedVariation.additional_price) : 0;
        return (parseFloat(this.basePrice) + addPrice) * this.quantity;
    },

    get isAvailable() {
        return this.selectedVariation ? this.selectedVariation.isAvailable : false
    },

    {{-- 5. وظيفة لاختيار التنوع وتصفير عداد الصور --}}
selectVariation(id) {
    this.selectedVariationId = id;
    // ابحث عن أول فهرس لصورة تتبع لهذا الموديل في المصفوفة الشاملة
    this.activeImageIndex = this.allImages.findIndex(img => img.variationId == id);
    if (this.activeImageIndex === -1) this.activeImageIndex = 0;
    this.quantity = 1;
},

    offsetX: 0,
    isDragging: false,
    incrementQty() { this.quantity++ },
    decrementQty() { if(this.quantity > 1) this.quantity-- },
    fullscreenImage: false,
    // دوال التنقل بين الصور
    prevImage() {
        if(this.activeImageIndex > 0) this.activeImageIndex--;
    },
    nextImage() {
        if(this.activeImageIndex < this.allImages.length - 1) this.activeImageIndex++;
    },
    slideDirection: 'forward',

{{-- دالة المشاركة الذكية --}}
shareProduct() {
        if (navigator.share) {
            navigator.share({
                title: '{{ $this->product->name }}',
                text: 'اكتشفي هذه القطعة الفريدة من سوريا شوب',
                url: window.location.href
            }).catch(() => {}); {{-- لتجنب أخطاء الإلغاء --}}
        } else {
            navigator.clipboard.writeText(window.location.href);
            {{-- تنبيه ناعم يتناسب مع فخامة المتجر --}}
            $wire.success('تم نسخ الرابط بنجاح', '', 'toast-bottom toast-start');
        }
    }



}" class="min-h-screen pt-2 pb-60 lg:py-12 px-4 md:px-8 bg-main-gradient text-neutral overflow-x-hidden" dir="rtl">


        <div class="max-w-6xl mx-auto">
            {{-- زر العودة --}}
            <a href="/" class="inline-flex items-center text-primary hover:text-primary-dark mb-8 transition-all group font-bold">
                <x-icon name="o-arrow-right" class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" />
                <span>العودة للمتجر</span>
            </a>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">

{{-- الجانب الأيمن: معرض الصور المطور بنظام الألبوم --}}
<div class="space-y-6" data-aos="fade-right">
    {{-- الإطار الرئيسي للصورة --}}
    <div class="relative group">
        <div class="absolute inset-0 bg-white/20 backdrop-blur-md rounded-[2.5rem] border border-white/30 shadow-2xl -rotate-2 group-hover:rotate-0 transition-transform duration-500 pointer-events-none"></div>
{{-- إضافة أحداث اللمس للسحب يميناً ويساراً --}}
<div class="relative rounded-[2.5rem] overflow-hidden border border-white/50 shadow-xl aspect-square bg-white/10 touch-pan-y"
     @touchstart="touchStart = $event.touches[0].clientX; isDragging = true; offsetX = 0"
@touchmove="
    if(isDragging) {
        let rawOffset = $event.touches[0].clientX - touchStart;
        {{-- عند السحب لليمين (rawOffset > 0) في أول صورة، أو لليسار في آخر صورة --}}
        if (activeImageIndex === 0 && rawOffset < 0) {
            offsetX = rawOffset * 0.2;
        } else if (activeImageIndex === allImages.length - 1 && rawOffset > 0) {
            offsetX = rawOffset * 0.2;
        } else {
            offsetX = rawOffset;
        }
    }
"
{{-- التعديل في حدث @touchend --}}
@touchend="
    isDragging = false;
    if (Math.abs(offsetX) > 80) {
        // في RTL:
        // السحب لليمين (offsetX > 0) يعني الانتقال للصورة التالية (Next)
        // السحب لليسار (offsetX < 0) يعني الانتقال للصورة السابقة (Prev)
        if (offsetX > 0 && activeImageIndex < allImages.length - 1) {
            nextImage();
        } else if (offsetX < 0 && activeImageIndex > 0) {
            prevImage();
        }
    }
    offsetX = 0;
"
     x-data="{ touchStart: 0 }">

{{-- الحاوية المتحركة (Track) --}}
{{-- حذفنا dir=ltr وجعلنا الحسابات متوافقة مع اليمين --}}
<div class="flex h-full w-full transition-transform duration-500 ease-out"
     :style="{
    // نستخدم -100 مضروبة في الفهرس لعكس الاتجاه برمجياً بما يتناسب مع RTL
    transform: `translateX(calc(${(activeImageIndex * 100)}% + ${offsetX}px))`,
    transition: isDragging ? 'none' : 'transform 0.5s cubic-bezier(0.2, 1, 0.3, 1)'
}">
        <template x-for="(imgData, index) in allImages" :key="index">
            <div class="w-full h-full flex-shrink-0">
            <img :src="imgData.url"
                 @click="fullscreenImage = true"
                 class="w-full h-full object-cover"
                 alt="{{ $this->product->name }}">
</div>
    </template>
</div> {{-- إغلاق الـ Track المتحرك --}}


{{-- نقاط التنقل أسفل الصورة --}}
<div class="absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-2 z-10 pointer-events-none">
    <template x-for="(img, index) in allImages" :key="index">
        <div class="h-1.5 rounded-full transition-all duration-500"
             :class="activeImageIndex === index ? 'bg-primary w-6' : 'bg-white/40 w-1.5'"></div>
    </template>
</div>


            {{-- ملصق نفاذ الكمية يظهر فقط عند اختيار موديل غير متاح --}}
            <template x-if="!isAvailable">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center">
                    <span class="bg-white/90 text-neutral px-6 py-2 rounded-full font-black text-sm uppercase tracking-widest">نفذت الكمية</span>
                </div>
            </template>
        </div>
    </div>







{{-- الألبوم المصغر: يعرض الآن الصورة الأساسية لكل تنوع --}}
<div class="flex gap-4 overflow-x-auto overflow-y-hidden py-4 custom-scrollbar px-2 max-w-full">
<template x-for="(imgData, index) in thumbnailImages" :key="index">
    <button
        {{-- عند النقر: نختار التنوع ونحدث المعرض الرئيسي --}}
        @click="selectVariation(imgData.variationId)"
        class="relative flex-shrink-0 w-20 h-20 rounded-2xl overflow-hidden border-2 transition-all duration-300"
        :class="selectedVariationId == imgData.variationId
            ? 'border-primary ring-4 ring-primary/10 scale-110 shadow-lg'
            : 'border-white/40 hover:border-primary/50 opacity-70 hover:opacity-100'"
    >
        <img :src="imgData.url" class="w-full h-full object-cover" />

        <div x-show="selectedVariationId != imgData.variationId" class="absolute inset-0 bg-white/10"></div>
    </button>
</template>

</div>
</div>

{{-- السعر والموديلات للجوال فقط --}}
<div class="lg:hidden space-y-4 -mt-2">
    {{-- السعر --}}
    <div class="flex items-baseline gap-2 bg-white/40 backdrop-blur-md px-4 py-2 rounded-2xl border border-white/50 shadow-sm w-max">
        <span class="text-3xl font-black text-neutral" x-text="new Intl.NumberFormat().format(currentPrice)"></span>
        <span class="text-primary font-bold text-sm">ل.س</span>
    </div>


    {{-- اختيار الموديل --}}
    <div class="space-y-3">
        <h3 class="text-xs font-bold text-neutral/40 uppercase tracking-widest text-center">اختر الموديل</h3>
        <div class="grid grid-cols-2 gap-2">
            <template x-for="variation in variations" :key="variation.id">
                <button @click="selectVariation(variation.id)"
                    class="px-3 py-3 rounded-2xl border transition-all duration-300 font-bold text-xs flex flex-col items-center gap-1"
{{-- قللنا سماكة الحدود وجعلنا الخلفية في وضع عدم الاختيار أكثر بياضاً لزيادة الفخامة --}}
:class="[
    !variation.isAvailable ? 'opacity-40 cursor-not-allowed' : '',
    selectedVariationId == variation.id
        ? 'border-primary bg-primary text-white shadow-md shadow-primary/30 scale-[1.02]'
        : 'border-primary/10 bg-white text-neutral/70 hover:border-primary/30 shadow-sm'
]"
                    :disabled="!variation.isAvailable">
                    <span x-text="variation.name"></span>
                </button>
            </template>
        </div>
    </div>
</div>




                {{-- الجانب الأيسر: المعلومات والتحكم --}}
                <div class="space-y-8" data-aos="fade-left">
                    <div>
                        <span class="hidden lg:block text-primary text-xs uppercase tracking-[0.4em] font-bold mb-3">
                            {{ $this->product->category->name ?? 'مجموعة فاخرة' }}
                        </span>
<div class="hidden lg:flex items-start justify-between gap-4">
    <h1 class="text-4xl md:text-5xl font-black mb-4 leading-tight uppercase tracking-tighter">
        {{ $this->product->name }}
    </h1>

    {{-- زر المشاركة الزجاجي --}}
    <button @click="shareProduct"
            class="mt-2 p-4 rounded-2xl bg-white/30 backdrop-blur-md border border-white/40 text-primary hover:bg-primary hover:text-white transition-all duration-500 shadow-sm group">
        <x-icon name="o-share" class="w-6 h-6 group-hover:scale-110 transition-transform" />
    </button>
</div>
                        <p class="text-neutral/60 text-lg leading-relaxed max-w-xl">
                            {{ $this->product->description }}
                        </p>
                    </div>

                    {{-- السعر --}}
                    <div class="hidden lg:flex items-baseline gap-4 bg-white/30 backdrop-blur-md p-6 rounded-3xl border border-white/40 w-max">
                        <span class="text-4xl font-black text-neutral" x-text="new Intl.NumberFormat().format(currentPrice)"></span>
                        <span class="text-primary font-bold">ل.س</span>
                    </div>

                    {{-- خيارات المنتج (Variations) --}}
                    <div class="hidden lg:block space-y-4">
                        <h3 class="text-sm font-bold text-neutral/40 uppercase tracking-widest">اختر الموديل</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <template x-for="variation in variations" :key="variation.id">
                                <button
                                    @click="selectVariation(variation.id)"
                                    class="px-4 py-4 rounded-2xl border-2 transition-all duration-300 font-bold text-sm flex flex-col items-start gap-1"
                                    :class="[
                                        !variation.isAvailable ? 'opacity-40 cursor-not-allowed' : '',
                                        selectedVariationId == variation.id
                                            ? 'border-primary bg-primary text-white shadow-lg shadow-primary/20'
                                            : 'border-white/50 bg-white/30 backdrop-blur-sm text-neutral hover:border-primary/50'
                                    ]"
                                    :disabled="!variation.isAvailable"
                                >
                                    <span x-text="variation.name"></span>
                                    <template x-if="variation.additional_price > 0">
                                        <span class="text-[10px]" :class="selectedVariationId == variation.id ? 'text-white/80' : 'text-primary'">
                                            + <span x-text="new Intl.NumberFormat().format(variation.additional_price)"></span> ل.س
                                        </span>
                                    </template>
                                </button>
                            </template>
                        </div>
                    </div>



{{-- حاوية العداد والمشاركة فقط --}}
<div class="hidden lg:flex items-center gap-3 w-full lg:w-max mb-6">
    <div class="flex items-center bg-white border border-primary/10 rounded-2xl p-2 shadow-xl shadow-neutral-950/10">
        <button @click="decrementQty" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-white transition-colors text-primary font-bold text-xl">-</button>
        <span class="w-12 text-center text-xl font-black text-neutral" x-text="quantity"></span>
        <button @click="incrementQty" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-white transition-colors text-primary font-bold text-xl">+</button>
    </div>

    {{-- زر المشاركة للجوال --}}
    <button @click="shareProduct"
            class="lg:hidden flex-1 flex items-center justify-center gap-2 h-[58px] rounded-2xl bg-white border border-primary/10 text-primary shadow-xl shadow-neutral-950/10 active:scale-95 transition-transform">
        <x-icon name="o-share" class="w-5 h-5" />
        <span class="font-bold text-sm">مشاركة</span>
    </button>
</div>

{{-- زر الإضافة للسلة مستقلاً في الأسفل --}}
<div class="hidden lg:block relative group mt-2 ml-20 lg:ml-0">
    <x-button
        label="أضف للحقيبة الفاخرة"
        icon="o-shopping-bag"
        x-bind:disabled="!isAvailable"
        @click="$wire.addToCart(selectedVariationId, quantity)"
        spinner="addToCart"
        class="w-full h-20 rounded-[2rem] text-xl font-bold transition-all duration-500 border-none shadow-2xl btn-primary text-white"
        x-bind:class="!isAvailable && 'bg-neutral/10 text-neutral/30 cursor-not-allowed shadow-none'"
    />
</div>




                        {{-- حالة التوفر --}}
                        <template x-if="!isAvailable">
                            <div class="flex items-center justify-center gap-2 text-red-500 font-bold bg-red-50/50 backdrop-blur-sm p-4 rounded-2xl border border-red-100">
                                <x-icon name="o-exclamation-circle" class="w-5 h-5" />
                                <span>عذراً، هذا الموديل نفذ من المخزون</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>


        <style>
            .custom-scrollbar::-webkit-scrollbar { height: 4px; }
            .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(212, 165, 116, 0.3); border-radius: 10px; }
        </style>










                    {{-- التحكم بالكمية والطلب --}}

<div class="lg:hidden fixed bottom-0 left-0 w-full z-[200] bg-white rounded-t-[2rem] p-4 pb-safe shadow-[0_-10px_30px_rgba(0,0,0,0.08)] border-t border-neutral/5">


                    {{-- التحكم بالكمية والطلب --}}



<div class="lg:hidden fixed bottom-0 left-0 w-full z-[200] bg-white rounded-t-[2.5rem] p-6 pb-safe shadow-[0_-15px_40px_rgba(0,0,0,0.12)] border-t border-neutral/5">





{{-- حاوية العداد والمشاركة فقط --}}

<div class="flex items-center gap-3 w-full lg:w-max mb-6">

    <div class="flex items-center bg-white border border-primary/10 rounded-2xl p-2 shadow-xl shadow-neutral-950/10">

        <button @click="decrementQty" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white transition-colors text-primary font-bold text-lg">-</button>

        <span class="w-12 text-center text-xl font-black text-neutral" x-text="quantity"></span>

        <button @click="incrementQty" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-white transition-colors text-primary font-bold text-xl">+</button>

    </div>



    {{-- زر المشاركة للجوال --}}

    <button @click="shareProduct"

            class="lg:hidden flex-1 flex items-center justify-center gap-2 h-[58px] rounded-2xl bg-white border border-primary/10 text-primary shadow-xl shadow-neutral-950/10 active:scale-95 transition-transform">

        <x-icon name="o-share" class="w-5 h-5" />

        <span class="font-bold text-sm">مشاركة</span>

    </button>

</div>

{{-- زر الإضافة للسلة مستقلاً في الأسفل --}}
<div class="relative group mt-2 ml-20 lg:ml-0">
    <x-button
        label="أضف للحقيبة الفاخرة"
        icon="o-shopping-bag"
        x-bind:disabled="!isAvailable"
        @click="$wire.addToCart(selectedVariationId, quantity)"
        spinner="addToCart"
        class="w-full h-16 rounded-2xl text-lg font-bold transition-all duration-500 border-none shadow-xl btn-primary text-white"
        x-bind:class="!isAvailable && 'bg-neutral/10 text-neutral/30 cursor-not-allowed shadow-none'"
    />
</div>



</div>







{{-- مودال تكبير الصورة --}}
        <template x-teleport="body">
            <div x-show="fullscreenImage"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-[200] flex items-center justify-center bg-white/10 backdrop-blur-2xl p-4 md:p-12"
                 @keydown.escape.window="fullscreenImage = false">

                {{-- زر الإغلاق --}}
                <button @click="fullscreenImage = false"
                        class="absolute top-8 right-8 text-white/50 hover:text-white transition-colors z-[210]">
                    <x-icon name="o-x-mark" class="w-12 h-12" />
                </button>

                {{-- الصورة المكبرة --}}
                <img :src="currentImage"
                     @click.away="fullscreenImage = false"
                     class="max-w-full max-h-full object-contain rounded-3xl shadow-2xl shadow-primary/10"
                     alt="Full view">
            </div>
        </template>







    </div>
    @endvolt
</x-layouts.app>
