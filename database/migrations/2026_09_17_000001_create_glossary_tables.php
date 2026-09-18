<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glossary_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('canonical_name');
            $table->string('normalized_name');
            $table->json('aliases')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['novel_id', 'type', 'normalized_name']);
            $table->index(['novel_id', 'type', 'status']);
        });

        Schema::create('glossary_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('glossary_entities')->cascadeOnDelete();
            $table->string('fact_key', 100);
            $table->text('value');
            $table->json('value_data')->nullable();
            $table->decimal('confidence', 5, 4)->default(0);
            $table->string('status', 24)->default('pending_review');
            $table->unsignedBigInteger('first_seen_chapter_id')->nullable();
            $table->unsignedBigInteger('last_seen_chapter_id')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->foreign('first_seen_chapter_id')->references('id')->on('chapters')->nullOnDelete();
            $table->foreign('last_seen_chapter_id')->references('id')->on('chapters')->nullOnDelete();
            $table->unique(['entity_id', 'fact_key']);
            $table->index(['novel_id', 'status', 'last_seen_chapter_id']);
        });

        Schema::create('glossary_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fact_id')->constrained('glossary_facts')->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->text('quote');
            $table->text('context')->nullable();
            $table->decimal('confidence', 5, 4)->default(0);
            $table->timestamps();

            $table->unique(['fact_id', 'chapter_id', 'quote']);
            $table->index(['chapter_id', 'fact_id']);
        });

        Schema::create('glossary_extraction_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('model', 160);
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->json('metrics')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['novel_id', 'chapter_id', 'status']);
        });

        Schema::create('glossary_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fact_id')->constrained('glossary_facts')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 24);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['fact_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glossary_reviews');
        Schema::dropIfExists('glossary_extraction_runs');
        Schema::dropIfExists('glossary_evidence');
        Schema::dropIfExists('glossary_facts');
        Schema::dropIfExists('glossary_entities');
    }
};
