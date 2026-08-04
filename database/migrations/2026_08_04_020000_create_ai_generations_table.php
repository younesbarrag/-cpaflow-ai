<?php

use App\Enums\AiProcessStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('offer_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->json('hooks')->nullable();
            $table->json('captions')->nullable();

            $table->string('status', 20)
                ->default(AiProcessStatus::Pending->value)
                ->index();

            $table->char('input_hash', 64)->nullable();

            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();

            $table->text('error_message')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
