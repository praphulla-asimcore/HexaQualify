<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** HexaQualify data model (spec §5). */
return new class extends Migration
{
    public function up(): void
    {
        // Reusable section blocks, e.g. the common "Company and integrity" baseline (§4.1).
        Schema::create('section_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->json('questions_json'); // vendor-safe questions
            $table->json('guide_json');     // { <question_id>: rule }
            $table->timestampsTz();
        });

        Schema::create('questionnaires', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('questionnaire_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('questionnaire_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->decimal('pass_mark', 5, 2);
            $table->json('structure_json'); // vendor-safe: no scoring fields
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['questionnaire_id', 'version']);
        });

        // Hidden scoring guide, kept apart from the vendor-safe structure. Never joined from vendor endpoints.
        Schema::create('scoring_guides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('questionnaire_version_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('guide_json');
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('questionnaire_version_id')->constrained();
            $table->string('vendor_company');
            $table->string('contact_name');
            $table->string('contact_email');
            $table->char('token_hash', 64)->unique();
            $table->string('status', 16)->default('draft')->index();
            $table->timestampTz('expires_at');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('reminded_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable(); // internal only
            $table->boolean('notify_result')->default(false);
            $table->timestampsTz();
            $table->index(['contact_email', 'questionnaire_version_id']);
        });

        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invitation_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64);
            $table->timestampTz('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invitation_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('vendor_json')->nullable();
            $table->json('answers_json')->nullable();
            $table->timestampTz('declaration_at')->nullable();
            $table->timestampTz('submitted_at')->nullable()->index();
            $table->string('submitted_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampsTz();
        });

        Schema::create('response_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('response_id')->constrained()->cascadeOnDelete();
            $table->string('question_id', 32);
            $table->string('storage_key');
            $table->string('filename');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->string('av_status', 16)->default('pending');
            $table->timestampsTz();
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('response_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('guide_id')->nullable()->constrained('scoring_guides');
            $table->decimal('score', 8, 2);
            $table->decimal('max', 8, 2);
            $table->decimal('pct', 5, 1);
            $table->json('knockouts_json');
            $table->json('pending_json');
            $table->json('manual_json')->nullable();
            $table->string('result', 16);
            $table->json('ai_suggestions_json')->nullable();
            $table->timestampTz('computed_at');
            $table->string('computed_by')->nullable(); // user id or "system"
            $table->index(['response_id', 'computed_at']);
        });

        Schema::create('decisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('response_id')->constrained()->cascadeOnDelete();
            $table->string('final_result', 16);
            $table->boolean('override')->default(false);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('no_conflict_confirmed')->default(false);
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at');
            $table->index(['response_id', 'decided_at']);
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('actor_type', 16); // user|vendor|system
            $table->string('actor_id')->nullable();
            $table->string('action', 64)->index();
            $table->string('entity', 64);
            $table->string('entity_id')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
            $table->index(['entity', 'entity_id']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            // The audit log is append-only at the database level, not just in the application.
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION audit_log_append_only() RETURNS trigger AS $$
                BEGIN
                  RAISE EXCEPTION 'audit_log is append-only';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER audit_log_no_update BEFORE UPDATE OR DELETE ON audit_log
                  FOR EACH ROW EXECUTE FUNCTION audit_log_append_only();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_log_no_update ON audit_log; DROP FUNCTION IF EXISTS audit_log_append_only();');
        }
        foreach (['settings', 'audit_log', 'decisions', 'scores', 'response_files', 'responses', 'otp_challenges',
            'invitations', 'scoring_guides', 'questionnaire_versions', 'questionnaires', 'section_blocks'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
