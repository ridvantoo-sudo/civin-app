<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('coins_balance')->default(0);
            $table->unsignedBigInteger('diamonds_balance')->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32);
            $table->bigInteger('amount');
            $table->string('currency', 16);
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['currency', 'created_at']);
        });

        Schema::create('recharge_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('package_name', 120);
            $table->unsignedBigInteger('coins');
            $table->unsignedBigInteger('price');
            $table->string('currency', 8);
            $table->string('status', 24)->index();
            $table->string('payment_provider', 64);
            $table->string('transaction_id', 191);
            $table->timestamp('created_at');
            $table->unique(['payment_provider', 'transaction_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('withdraw_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('diamonds');
            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->index();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        if (Schema::hasColumn('users', 'coin_balance')) {
            $users = DB::table('users')->select(['id', 'coin_balance', 'earning_balance'])->get();
            $now = now();

            foreach ($users as $user) {
                DB::table('wallets')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'coins_balance' => (int) $user->coin_balance,
                    'diamonds_balance' => (int) $user->earning_balance,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn(['coin_balance', 'earning_balance']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'coin_balance')) {
                $table->unsignedBigInteger('coin_balance')->default(0)->after('status');
                $table->unsignedBigInteger('earning_balance')->default(0)->after('coin_balance');
            }
        });

        $wallets = DB::table('wallets')->select(['user_id', 'coins_balance', 'diamonds_balance'])->get();
        foreach ($wallets as $wallet) {
            DB::table('users')->where('id', $wallet->user_id)->update([
                'coin_balance' => $wallet->coins_balance,
                'earning_balance' => $wallet->diamonds_balance,
            ]);
        }

        Schema::dropIfExists('withdraw_requests');
        Schema::dropIfExists('recharge_orders');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
