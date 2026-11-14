<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_period_closures', function (Blueprint $table) {
            $table->bigInteger('transfers_in')->default(0)->after('net_movement');
            $table->bigInteger('transfers_out')->default(0)->after('transfers_in');
            $table->bigInteger('net_transfer_movement')->default(0)->after('transfers_out');
            $table->integer('transfer_count')->default(0)->after('reversal_count');
        });
    }

    public function down(): void
    {
        Schema::table('financial_period_closures', function (Blueprint $table) {
            $table->dropColumn([
                'transfers_in',
                'transfers_out',
                'net_transfer_movement',
                'transfer_count',
            ]);
        });
    }
};
