<?php

use App\Models\Category;
use App\Models\Product;
use function Livewire\Volt\{state, computed, component};
use Livewire\Attributes\Url;
use Filament\Forms\Components\Component;

// 1. تعريف الحالة (State)
state([
    'selectedCategoryId' => fn() => request('category'),
    'categories' => fn() => Category::all(),
    'search' => '',          // ← added
    'perPage' => 8,           // ← added (default for "load more")
    'showContact' => false, // ← أضف هذا السطر
]);

// 2. تعريف المنتجات كخاصية محسوبة (Computed) لتعمل التفاعلية
$products = computed(function() {
    return Product::query()
        ->with(['variations.featuredImage'])
        ->when($this->selectedCategoryId, fn($q) => $q->where('category_id', $this->selectedCategoryId))
        ->when($this->search, function($query) {                     // ← added search logic
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        })
        ->limit($this->perPage)                                      // ← respect pagination
        ->get();
});

// 3. التصنيفات يمكن تركها كمتغير عادي لأنها لا تتغير
$categories = Category::all();

// 4. دالة زيادة عدد المنتجات عند الضغط على "عرض المزيد"
$loadMore = function() {
    $this->perPage += 8; // نزيد 8 منتجات إضافية في كل مرة
};



// دالة لإعادة ضبط كافة الفلاتر (التصنيف والبحث)
$resetFilters = function() {
    $this->selectedCategoryId = null;
    $this->search = '';
    // يمكنك أيضاً إعادة الصفحة للأولى إذا كنت تستخدم pagination
    $this->perPage = 8;
};



?>



<x-layouts.app>
    @volt
    {{-- 1. عنصر الجذر: يحمل التدرج الأساسي والخلفية --}}
    <div class="min-h-screen bg-main-gradient text-neutral relative overflow-x-clip" dir="rtl">

        {{-- العناصر الضبابية العائمة لتعزيز العمق --}}
        <div class="absolute top-[-10%] left-[-10%] w-[45%] h-[45%] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-[20%] right-[-5%] w-[35%] h-[35%] bg-primary/10 rounded-full blur-[100px] pointer-events-none"></div>

        {{-- اسم المتجر (غير متحرك) --}}
        <div class="relative w-full h-auto md:h-0 pt-8 pb-4 md:py-0">
            <div class="static md:absolute md:top-8 md:right-8 z-10 flex justify-center md:justify-end" data-aos="fade-left">
                <div class="flex items-center gap-4">
                    <div class="flex flex-col items-end">
                        <span class="text-primary text-3xl font-black tracking-tighter leading-none select-none">
                            SYRIA SHOP<span class="opacity-40 text-sm ml-1">0</span>
                        </span>
                        <div class="flex items-center gap-2 mt-1">
                            <div class="h-[1px] w-8 bg-primary/30"></div>
                            <span class="text-[10px] text-neutral/40 uppercase tracking-[0.4em] select-none">Luxury Concept</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 md:p-8">
            {{-- 3. شريط البحث: نهج الزجاج (Glassmorphism) --}}
            <div class="sticky top-2 z-[40] max-w-md mx-auto mb-8 px-4" data-aos="fade-up">
                <div class="relative group">
                    <x-input
                        wire:model.live.debounce.300ms="search"
                        placeholder="ابحث عن قطعة فريدة..."
                        icon="o-magnifying-glass"
                        class="bg-white/40 backdrop-blur-md border-primary/20 focus:border-primary text-neutral placeholder:text-neutral/40 rounded-2xl h-14 transition-all"
                        clearable
                    />
                </div>
            </div>

<livewire:slider-section />


{{-- حاوية الشريط الموحد --}}
<div class="max-w-fit mx-auto mb-16 px-2 py-2 bg-white/20 backdrop-blur-xl rounded-full border border-white/30 shadow-xl flex items-center gap-1 overflow-x-auto no-scrollbar" data-aos="fade-up">

    {{-- زر "الكل" كجزء من الشريط --}}
    <button wire:click="resetFilters"
        class="whitespace-nowrap px-8 py-3 rounded-full text-[10px] font-black tracking-[0.2em] uppercase transition-all duration-500
        @if(!$selectedCategoryId)
            bg-primary text-white shadow-md shadow-primary/30
        @else
            text-neutral/40 hover:text-primary hover:bg-white/20
        @endif">
        الكل
    </button>

    @foreach($categories as $cat)
        <button wire:click="$set('selectedCategoryId', {{ $cat->id }})"
            class="whitespace-nowrap px-8 py-3 rounded-full text-[10px] font-black tracking-[0.2em] uppercase transition-all duration-500
            @if($selectedCategoryId == $cat->id)
                bg-primary text-white shadow-md shadow-primary/30
            @else
                text-neutral/40 hover:text-primary hover:bg-white/20
            @endif">
            {{ $cat->name }}
        </button>
    @endforeach
</div>

            {{-- 5. شبكة المنتجات --}}
            <div class="max-w-7xl mx-auto" id="products" wire:loading.class="opacity-50 pointer-events-none">
                @if($this->products->isNotEmpty())
<div class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-10">
                            @foreach($this->products as $product)
                            @php
                                $baseVariation = $product->variations->where('is_available', true)->first() ?? $product->variations->first();
                                $displayPrice = $product->price;
                                $displayImage = $baseVariation?->featuredImage;
                                $isNew = $product->created_at->diffInDays(now()) < 7;
                                $inStock = $baseVariation ? ($baseVariation->stock_quantity > 0) : false;
                            @endphp

                            {{-- تأكد أن مكون x-luxury-product-card يعتمد داخلياً على نهج الزجاج ليتناسب مع الصفحة --}}
                            <x-luxury-product-card
                                :product="$product"
                                :displayPrice="$displayPrice"
                                :displayImage="$displayImage"
                                :isNew="$isNew"
                                :inStock="$inStock"
                                :fastShipping="true"
                            />
                        @endforeach
                    </div>

                    {{-- زر تحميل المزيد: نهج Minimalist --}}
                    <div class="mt-20 text-center mb-20" data-aos="fade-up">
                        <button wire:click="loadMore"
                                class="group px-12 py-4 bg-white/20 backdrop-blur-md text-primary border border-primary/20 rounded-full hover:bg-primary hover:text-white transition-all duration-500 font-bold tracking-tighter shadow-sm">
                            اكتشفي المزيد من القطع
                            <x-icon name="o-chevron-down" class="w-4 h-4 mr-2 inline group-hover:translate-y-1 transition-transform" />
                        </button>
                    </div>
                @else
                    {{-- حالة عدم وجود نتائج --}}
                    <div class="text-center py-32 bg-white/20 backdrop-blur-xl border border-dashed border-primary/20 rounded-[3rem]" data-aos="fade">
                        <x-icon name="o-sparkles" class="w-16 h-16 mx-auto mb-6 text-primary/40" />
                        <p class="text-2xl font-light text-neutral mb-2">نبحث عن سحرك الخاص...</p>
                        <p class="text-neutral/40 mb-10">جربي كلمات بحث مختلفة لاكتشاف مجموعتنا</p>
                        <button wire:click="resetFilters" class="px-8 py-3 bg-primary text-white rounded-full font-bold">إعادة تعيين</button>
                    </div>
                @endif
            </div>
        </div>

        {{-- 6. الفاصل والفوتر --}}
        <div class="max-w-4xl mx-auto px-6 mt-32" data-aos="fade-up">
            <div class="relative flex items-center justify-center">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full h-[1.5px] bg-gradient-to-r from-transparent via-primary/60 to-transparent shadow-[0_1px_3px_rgba(212,165,116,0.2)]"></div>
                </div>
            </div>
        </div>

        <footer class="relative pt-32 pb-16 text-neutral">
            <div class="max-w-7xl mx-auto px-6 relative z-10 text-center"   >
                {{-- محتوى الفوتر كما هو مع تعديل اللمسات الزجاجية في أيقونة الإنستقرام --}}
                <div class="flex flex-col items-center">
                    <div class="mb-12 flex justify-center group" data-aos="zoom-in">
                        <a href="https://www.instagram.com/syria_shop0/?hl=ar" target="_blank" class="relative">
                            <div class="w-24 h-24 rounded-full bg-white/10 backdrop-blur-2xl border border-primary/20 flex items-center justify-center transition-all duration-700 group-hover:border-primary/50 group-hover:scale-110">
                                <i class="fab fa-instagram text-5xl transition-transform group-hover:rotate-12" style="background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                            </div>
                        </a>
                    </div>





                    <h3 class="text-5xl font-black text-neutral mb-4 tracking-tighter uppercase font-playfair">SYRIA SHOP0</h3>
                    <div class="flex items-center gap-4 mb-12">
                        <div class="h-[1px] w-12 bg-primary/20"></div>
                        <p class="text-primary text-xs uppercase tracking-[0.8em] font-semibold">Luxury Concept</p>
                        <div class="h-[1px] w-12 bg-primary/20"></div>
                    </div>


<div class="mb-12" data-aos="zoom-in" data-aos-delay="100">
<a href="https://wa.me/963930761582"
   target="_blank"
   class="inline-flex items-center gap-3 text-[14px] md:text-[16px] text-primary font-bold uppercase tracking-[0.2em] px-10 py-4 border border-primary/30 rounded-full hover:bg-primary hover:text-white transition-all duration-500 transform hover:scale-105 shadow-sm">

    <!-- أيقونة واتساب -->
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" class="flex-shrink-0">
        <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.14-.1.085.1c1.158.677 2.482 1.035 3.826 1.035h.003c4.367 0 7.926-3.558 7.93-7.93a7.882 7.882 0 0 0-2.388-5.709zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/>
    </svg>

    <span>تواصل معنا عبر واتساب</span>
</a>
</div>




                    {{-- باقي حقوق الملكية --}}
                    <div class="pt-12 border-t border-primary/5 w-full max-w-2xl text-[10px] text-neutral/30 uppercase tracking-[0.4em]">
                        &copy; 2026 Syria Shop. Crafted for Elegance.
                    </div>
                </div>
            </div>
        </footer>
    </div>
    @endvolt
</x-layouts.app>
