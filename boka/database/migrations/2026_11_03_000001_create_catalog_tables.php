<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('role');
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('invited_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('origins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->string('district');
            $table->string('destination_id')->nullable();
            $table->string('contact')->nullable();
            $table->json('couriers')->nullable();
            $table->boolean('pickup_enabled')->default(false);
            $table->string('pickup_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('local_courier_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_id')->constrained()->cascadeOnDelete();
            $table->string('destination_district');
            $table->unsignedBigInteger('fee');
            $table->unique(['origin_id', 'destination_district']);
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50);
            $table->string('recipient');
            $table->string('phone', 20);
            $table->string('destination_id');
            $table->string('district');
            $table->string('destination_label');
            $table->text('full_address');
            $table->string('postal_code', 10)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('producers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 20); // karang_taruna | umkm
            $table->string('village');
            $table->string('district');
            $table->json('story')->nullable();
            $table->string('photo')->nullable();
            $table->string('contact')->nullable();
            $table->foreignId('default_origin_id')->nullable()->constrained('origins');
            $table->foreignId('user_id')->nullable()->constrained(); // diisi saat naik menjadi akun penjual
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories');
            $table->json('name');
            $table->string('slug')->unique();
            $table->string('code', 5);
            $table->string('icon', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_id')->nullable()->constrained();
            $table->foreignId('category_id')->nullable()->constrained();
            $table->foreignId('origin_id')->nullable()->constrained();
            $table->json('name');
            $table->string('name_normalized')->index();
            $table->string('slug')->unique();
            $table->json('description')->nullable();
            $table->string('status', 12)->default('draf');
            $table->boolean('is_featured')->default(false);
            $table->boolean('similarity_resolved')->default(false);
            $table->unsignedInteger('sold_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'category_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 40)->unique();
            $table->string('barcode', 40)->nullable()->unique();
            $table->json('options')->nullable();
            $table->string('options_key')->default('');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('compare_price')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('weight_gram');
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->unsignedInteger('max_per_order')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'options_key']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_path')->nullable();
            $table->json('variants')->nullable();
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->string('dominant_color', 9)->nullable();
            $table->boolean('is_cover')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 12)->default('siap');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });

        Schema::create('product_tag', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'tag_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants');
            $table->string('type', 12); // masuk|keluar|opname|checkout|restore|retur
            $table->integer('qty_change');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->string('reason')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['variant_id', 'created_at']);
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_id')->constrained();
            $table->string('status', 12)->default('terbuka');
            $table->foreignId('started_by')->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants');
            $table->unsignedInteger('system_qty_at_start');
            $table->unsignedInteger('counted_qty')->nullable();
            $table->integer('difference')->nullable();
        });

        Schema::create('product_similarity_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('candidate_product_id')->constrained('products');
            $table->decimal('score', 4, 3);
            $table->string('decision', 16); // tambah_stok|varian_baru|terpisah
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->unique(['product_id', 'candidate_product_id'], 'similarity_pair_unique');
        });

        Schema::create('product_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_slug')->unique();
            $table->foreignId('product_id')->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['product_redirects', 'product_similarity_decisions', 'stock_opname_items', 'stock_opnames', 'stock_movements', 'product_tag', 'tags', 'product_images', 'product_variants', 'products', 'categories', 'producers', 'addresses', 'local_courier_rates', 'origins', 'admin_invitations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
