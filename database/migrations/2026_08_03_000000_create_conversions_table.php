<?php

use App\Enums\ConversionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('campaign_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('external_id', 255)->unique();
            $table->string('source', 255)->nullable();
            $table->decimal('revenue', 12, 2);
            $table->string('status', 20)
                ->default(ConversionStatus::Pending->value)
                ->index();
            $table->timestamp('converted_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversions');
    }
};
