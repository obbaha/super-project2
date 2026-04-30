<?php
use App\Models\Slider;
use function Livewire\Volt\{state};

state(['slides' => fn() => Slider::where('is_active', true)->orderBy('order')->get()]);
?>

<div x-data="{
        active: 0,
        loop() {
            setInterval(() => { this.active = (this.active + 1) % {{ $slides->count() }} }, 5000)
        }
     }"
     x-init="loop()"
    class="relative w-full max-w-[95%] mx-auto overflow-hidden rounded-[2.5rem] shadow-2xl h-[350px] md:h-[600px] group mb-12 md:mb-20"
{{-- إضافة AOS: ظهور للأعلى، ببطء، ومع تأخير بسيط --}}
data-aos="fade-up"
data-aos-duration="1500"
data-aos-delay="200"
data-aos-easing="ease-out-back">

    @foreach($slides as $index => $slide)
        <div x-show="active == {{ $index }}"

{{-- زيادة المدة إلى 1100 لزيادة الثقل والهدوء --}}
     x-transition:enter="transition transform duration-[1100ms]"
     x-transition:enter-start="-translate-x-full"
     x-transition:enter-end="translate-x-0"

     x-transition:leave="transition transform duration-[1100ms]"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="translate-x-full"

     {{-- منحنى أكثر توازناً وهدوءاً في الانطلاق --}}
     style="transition-timing-function: cubic-bezier(0.45, 0, 0.55, 1);"



             class="absolute inset-0">

            {{-- الصورة --}}
            <img src="{{ Storage::url($slide->image->path) }}" class="object-cover w-full h-full">

            {{-- الطبقة المظلمة والمحتوى --}}
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent flex items-end justify-start p-8 md:p-20 text-right">
                <div class="text-neutral-light">
                    @if($slide->title)
                        <h2 class="text-3xl md:text-5xl font-black mb-4">{{ $slide->title }}</h2>
                    @endif
                    @if($slide->link)
                        <a href="{{ $slide->link }}" class="inline-block bg-primary text-white px-8 py-3 rounded-full font-bold hover:bg-white hover:text-primary transition-colors">
                            اكتشف الآن
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    {{-- نقاط التنقل (Indicators) --}}
    <div class="absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-2">
        @foreach($slides as $index => $slide)
            <button @click="active = {{ $index }}"
                    class="w-3 h-3 rounded-full transition-all"
                    :class="active == {{ $index }} ? 'bg-primary w-8' : 'bg-white/50'"></button>
        @endforeach
    </div>
</div>
