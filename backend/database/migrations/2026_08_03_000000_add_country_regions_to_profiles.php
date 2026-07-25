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
        Schema::create('country_regions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('country_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['country_id', 'code']);
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->foreignUuid('country_region_id')
                ->nullable()
                ->after('country_id')
                ->constrained('country_regions')
                ->nullOnDelete();
        });

        $now = now();
        $iraq = DB::table('countries')->where('alpha2', 'IQ')->first();
        $iraqId = $iraq?->id ?? (string) Str::uuid();

        if ($iraq === null) {
            DB::table('countries')->insert([
                'id' => $iraqId,
                'alpha2' => 'IQ',
                'alpha3' => 'IRQ',
                'name' => 'Iraq',
                'phone_code' => '+964',
                'flag_emoji' => '🇮🇶',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $regionId = (string) Str::uuid();
        DB::table('country_regions')->insert([
            'id' => $regionId,
            'country_id' => $iraqId,
            // Internal hierarchical code: Kurdistan Region has no dedicated ISO 3166-2 code.
            'code' => 'IQ-KRI',
            'name' => 'Kurdistan Region of Iraq',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $legacy = DB::table('countries')->where('alpha2', 'KU')->first();
        if ($legacy !== null) {
            DB::table('profiles')
                ->where('country_id', $legacy->id)
                ->update([
                    'country_id' => $iraqId,
                    'country_region_id' => $regionId,
                ]);
            DB::table('countries')->where('id', $legacy->id)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('country_region_id');
        });

        Schema::dropIfExists('country_regions');
    }
};
