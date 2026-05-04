<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
/**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // إضافة حقل السعر القديم بعد حقل السعر الأساسي
            $table->decimal('old_price', 10, 2)->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // حذف الحقل عند التراجع عن الهجرة
            $table->dropColumn('old_price');
        });
    }
};
