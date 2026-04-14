<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->whereNull('awb_number')
            ->whereNotNull('sameday_awb')
            ->update(['awb_number' => DB::raw('sameday_awb')]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('sameday_awb');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('sameday_awb')->nullable()->after('stripe_session_id');
        });

        DB::table('orders')
            ->whereNull('sameday_awb')
            ->whereNotNull('awb_number')
            ->update(['sameday_awb' => DB::raw('awb_number')]);
    }
};
