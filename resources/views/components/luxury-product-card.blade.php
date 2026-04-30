@props([
    'product',
])

@php
    // جلب التنوع الأول المتاح كمرجع للصورة الأساسية والحالة
    $baseVariation = $product->variations->where('is_available', true)->first() ?? $product->variations->first();

    // استخدام السعر من الموديل مباشرة
    $displayPrice = $product->price;

    // جلب الصورة: نستخدم السمة المحسوبة في الموديل (getFeaturedImageAttribute)
    $displayImage = $product->featured_image;

    // حالة التوفر بناءً على حقل is_available في الموديل
    $isAvailable = $product->is_available;

    // وسم "جديد" إذا أضيف المنتج خلال آخر 7 أيام
    $isNew = $product->created_at->diffInDays(now()) < 7;
@endphp

<div class="relative group cursor-pointer" data-aos="fade-up">
    {{-- طبقة الخلفية الزجاجية (Glassmorphism) --}}
    <div class="absolute inset-0 bg-white/40 backdrop-blur-md rounded-3xl border border-primary/10 transition-all duration-500
                @if($isAvailable) group-hover:bg-white/60 group-hover:shadow-primary/20 group-hover:-translate-y-2 @endif">
    </div>

    <div class="relative overflow-hidden rounded-3xl flex flex-col h-full">

        {{-- قسم الصورة --}}
        <div class="relative h-48 md:h-72 overflow-hidden m-2 md:m-3 rounded-2xl bg-white/5">
            @if($displayImage)
                <img src="{{ Storage::url($displayImage->path) }}"
                     class="h-full w-full object-cover transition-all duration-700
                            @if(!$isAvailable) grayscale contrast-125 opacity-60 @else group-hover:scale-110 @endif"
                     alt="{{ $product->name }}">
            @else
                <div class="bg-primary/5 h-full w-full flex items-center justify-center">
                    <x-icon name="o-photo" class="w-12 h-12 text-primary/20" />
                </div>
            @endif

            {{-- ملصقات الحالة --}}
            <div class="absolute top-4 right-4 flex flex-col gap-2">
                @if(!$isAvailable)
                    <div class="bg-neutral/80 backdrop-blur-md text-white text-[9px] font-black px-3 py-1.5 rounded-full shadow-xl border border-white/20 uppercase tracking-widest">
                        غير متاح حالياً
                    </div>
                @elseif($isNew)
                    <div class="bg-primary text-white text-[10px] font-bold px-3 py-1 rounded-full shadow-lg">
                        جديد
                    </div>
                @endif
            </div>

            {{-- تأثير التظليل عند التحويم للمنتجات المتاحة فقط --}}
            @if($isAvailable)
                <div class="absolute inset-0 bg-gradient-to-t from-primary/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
            @endif
        </div>

        {{-- تفاصيل المنتج --}}
        <div class="p-3 md:p-6 pt-2 space-y-2 md:space-y-3 flex-grow @if(!$isAvailable) opacity-60 @endif">
            <div class="space-y-1">
                <h3 class="font-bold text-base md:text-xl text-neutral group-hover:text-primary transition-colors line-clamp-1 font-playfair uppercase tracking-tight">
                    {{ $product->name }}
                </h3>
                <p class="text-neutral/50 text-xs leading-relaxed line-clamp-2 font-light">
                    {{ $product->description }}
                </p>
            </div>

            <div class="flex items-center justify-between mt-4">
                <div class="flex flex-col">
                    <span class="text-[10px] text-primary uppercase tracking-widest font-medium opacity-70">السعر</span>
                    <span class="text-lg md:text-2xl font-black text-neutral">
                        {{ number_format($displayPrice, 0) }} <span class="text-xs font-normal">ل.س</span>
                    </span>
                </div>

                {{-- زر عرض التفاصيل --}}
                <div class="w-10 h-10 rounded-full border border-primary/20 flex items-center justify-center text-primary transition-all duration-300 shadow-sm
                            @if($isAvailable) group-hover:bg-primary group-hover:text-white @endif">
                    <x-icon name="o-chevron-left" class="w-5 h-5" />
                </div>
            </div>
        </div>

        {{-- الرابط الشفاف لتغطية البطاقة --}}
        <a href="/products/{{ $product->id }}"
           class="absolute inset-0 w-full h-full opacity-0 z-30 cursor-pointer"
           aria-label="عرض تفاصيل {{ $product->name }}">
        </a>
    </div>
</div>
