<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('key')->index();
            $table->string('acronym', 20)->nullable()->index();
            $table->string('country', 80)->nullable()->index();
            $table->string('city', 120)->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });

        Schema::create('institution_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->string('key')->unique();
            $table->timestamps();
        });

        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('category', 30)->index();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('program_key');
            $table->string('acronym', 20)->nullable();
            $table->string('dedupe_key')->unique();
            $table->string('country', 80)->nullable()->index();
            $table->string('city', 120)->nullable();
            $table->string('degree', 60)->nullable();
            $table->string('language', 60)->nullable();
            $table->string('nc_status', 10)->default('unknown')->index();
            $table->string('intake', 60)->nullable();
            $table->string('deadline')->nullable();
            $table->date('deadline_date')->nullable()->index();
            $table->string('tuition')->nullable();
            $table->decimal('tuition_amount', 12, 2)->nullable();
            $table->string('application_fee')->nullable();
            $table->decimal('ielts', 3, 1)->nullable();
            $table->unsignedSmallInteger('toefl')->nullable();
            $table->string('gre', 20)->default('unknown');
            $table->string('portal', 20)->nullable();
            $table->string('entrance')->nullable();
            $table->string('ects')->nullable();
            $table->string('gpa')->nullable();
            $table->text('requirements')->nullable();
            $table->text('documents')->nullable();
            $table->text('funding')->nullable();
            $table->string('website', 500)->nullable();
            $table->unsignedInteger('sources_count')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('study_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category', 30)->index();
            $table->string('visibility', 10)->default('private')->index();
            $table->timestamps();
        });

        Schema::create('list_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_list_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('label', 120);
            $table->string('type', 20)->default('text');
            $table->string('field', 30)->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['study_list_id', 'key']);
        });

        Schema::create('list_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $table->json('data');
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->index(['study_list_id', 'position']);
        });

        Schema::create('list_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_edit')->default(false);
            $table->timestamps();
            $table->unique(['study_list_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_shares');
        Schema::dropIfExists('list_rows');
        Schema::dropIfExists('list_columns');
        Schema::dropIfExists('study_lists');
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('institution_aliases');
        Schema::dropIfExists('institutions');
    }
};
