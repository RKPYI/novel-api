<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glossary_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extraction_run_id')->constrained('glossary_extraction_runs')->cascadeOnDelete();
            $table->string('entity_type', 40);
            $table->string('entity_name');
            $table->string('normalized_name');
            $table->string('fact_key', 100);
            $table->text('value');
            $table->text('quote');
            $table->char('quote_hash', 64);
            $table->text('context')->nullable();
            $table->decimal('confidence', 5, 4)->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['novel_id', 'normalized_name', 'fact_key']);
            $table->unique(
                ['extraction_run_id', 'entity_type', 'normalized_name', 'fact_key', 'quote_hash'],
                'glossary_observation_identity_unique'
            );
        });

        Schema::create('glossary_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('observation_id')->nullable()->constrained('glossary_observations')->nullOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained('glossary_entities')->nullOnDelete();
            $table->foreignId('fact_id')->nullable()->constrained('glossary_facts')->nullOnDelete();
            $table->string('operation', 32);
            $table->string('status', 24)->default('pending');
            $table->decimal('confidence', 5, 4)->default(0);
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['novel_id', 'status', 'operation']);
        });

        Schema::create('glossary_change_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained('glossary_proposals')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 24);
            $table->string('change_type', 40);
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['novel_id', 'subject_type', 'subject_id']);
        });

        Schema::create('glossary_entity_redirects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_entity_id')->constrained('glossary_entities')->cascadeOnDelete();
            $table->foreignId('target_entity_id')->constrained('glossary_entities')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['source_entity_id', 'target_entity_id'], 'glossary_redirect_pair_unique');
        });

        Schema::table('glossary_entities', function (Blueprint $table): void {
            $table->string('authority', 16)->default('ai')->after('status');
        });

        Schema::table('glossary_facts', function (Blueprint $table): void {
            $table->string('authority', 16)->default('ai')->after('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glossary_entity_redirects');
        Schema::dropIfExists('glossary_change_history');
        Schema::dropIfExists('glossary_proposals');
        Schema::dropIfExists('glossary_observations');
        Schema::table('glossary_facts', function (Blueprint $table): void {
            $table->dropColumn('authority');
        });
        Schema::table('glossary_entities', function (Blueprint $table): void {
            $table->dropColumn('authority');
        });
    }
};
