<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('awb_number')->nullable()->after('sameday_awb');
        });

        DB::table('orders')
            ->whereNull('awb_number')
            ->whereNotNull('sameday_awb')
            ->update(['awb_number' => DB::raw('sameday_awb')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('awb_number');
        });
    }
};
