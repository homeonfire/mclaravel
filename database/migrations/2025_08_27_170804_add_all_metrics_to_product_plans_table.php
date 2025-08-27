<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('product_plans', function (Blueprint $table) {
            $table->integer('plan_openCardCount')->nullable()->after('period_start_date');
            $table->integer('plan_addToCartCount')->nullable()->after('plan_openCardCount');
            $table->integer('plan_buyoutsSumRub')->nullable()->after('plan_ordersSumRub');
            $table->integer('plan_cancelCount')->nullable()->after('plan_buyoutsSumRub');
            $table->integer('plan_cancelSumRub')->nullable()->after('plan_cancelCount');
            $table->integer('plan_avgPriceRub')->nullable()->after('plan_cancelSumRub');
        });
    }
    public function down(): void {
        Schema::table('product_plans', function (Blueprint $table) {
            $table->dropColumn(['plan_openCardCount', 'plan_addToCartCount', 'plan_buyoutsSumRub', 'plan_cancelCount', 'plan_cancelSumRub', 'plan_avgPriceRub']);
        });
    }
};
