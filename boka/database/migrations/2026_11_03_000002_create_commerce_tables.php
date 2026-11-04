<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('price_when_added');
            $table->timestamps();
            $table->unique(['cart_id', 'variant_id']);
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('notify_restock')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('account_number', 40);
            $table->string('account_holder');
            $table->boolean('is_active')->default(true);
            $table->dateTime('active_from');
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('type', 10); // belanja|ongkir
            $table->string('discount_type', 10); // persen|nominal
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedBigInteger('min_spend')->default(0);
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedInteger('used')->default(0);
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->json('scope')->nullable(); // {category_ids:[], product_ids:[], new_buyer_only:bool}
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('status', 24);
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('voucher_discount')->default(0);
            $table->unsignedBigInteger('shipping_cost')->default(0);
            $table->unsignedBigInteger('shipping_discount')->default(0);
            $table->unsignedBigInteger('service_fee')->default(0);
            $table->unsignedSmallInteger('unique_code');
            $table->unsignedBigInteger('grand_total');
            $table->timestamp('payment_deadline_at')->nullable();
            $table->foreignId('bank_account_id')->constrained();
            $table->json('bank_snapshot');
            $table->json('address_snapshot');
            $table->unsignedTinyInteger('rejection_count')->default(0);
            $table->boolean('has_flash_sale')->default(false);
            $table->string('idempotency_key', 64)->unique();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'payment_deadline_at']);
        });

        Schema::create('order_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('origin_id')->constrained();
            $table->json('origin_snapshot');
            $table->string('method', 10); // ekspedisi|lokal|ambil
            $table->string('courier')->nullable();
            $table->string('service')->nullable();
            $table->unsignedInteger('weight_gram');
            $table->unsignedBigInteger('cost');
            $table->unsignedBigInteger('shipping_discount')->default(0);
            $table->string('tracking_number')->nullable();
            $table->string('status', 16)->default('menunggu');
            $table->timestamp('shipped_at')->nullable();
            $table->string('pickup_code_hash')->nullable();
            $table->unsignedTinyInteger('pickup_attempts')->default(0);
            $table->timestamp('pickup_locked_until')->nullable();
            $table->string('delivery_photo')->nullable();
            $table->string('receiver_name')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('order_shipments');
            $table->foreignId('variant_id')->constrained('product_variants');
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('qty');
            $table->unsignedInteger('weight_gram');
            $table->foreignId('flash_sale_item_id')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->unsignedBigInteger('amount');
            $table->string('bank_name');
            $table->string('account_number', 40);
            $table->string('account_holder');
            $table->string('reason');
            $table->string('status', 16)->default('diajukan');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('proof_path')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_sha256', 64)->index();
            $table->string('sender_bank');
            $table->string('sender_name');
            $table->date('transferred_at');
            $table->string('status', 16)->default('menunggu');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamp('mutation_datetime')->nullable();
            $table->foreignId('second_reviewed_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('voucher_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12)->default('dipesan'); // dipesan|dipakai|dikembalikan
            $table->timestamps();
        });

        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
        });

        Schema::create('flash_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants');
            $table->unsignedBigInteger('sale_price');
            $table->unsignedInteger('quota');
            $table->unsignedInteger('sold')->default(0);
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('subtitle')->nullable();
            $table->string('image_desktop')->nullable();
            $table->string('image_mobile')->nullable();
            $table->string('link')->nullable();
            $table->string('position', 20)->default('hero');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->default('berita'); // berita|dokumentasi
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('excerpt')->nullable();
            $table->json('body');
            $table->string('cover')->nullable();
            $table->json('gallery')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('title');
            $table->json('body');
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->json('photos')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->text('reply')->nullable();
            $table->timestamps();
        });

        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 64)->nullable();
            $table->string('query');
            $table->unsignedInteger('results_count');
            $table->foreignId('clicked_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('product_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('search_hits')->default(0);
            $table->unsignedInteger('sold')->default(0);
            $table->unique(['product_id', 'date']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('report');
            $table->json('filters');
            $table->string('format', 8);
            $table->unsignedInteger('row_count');
            $table->boolean('contains_personal_data')->default(false);
            $table->string('status', 12)->default('selesai');
            $table->string('file_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['report_exports', 'notifications', 'settings', 'product_daily_stats', 'search_logs', 'reviews', 'pages', 'posts', 'banners', 'flash_sale_items', 'flash_sales', 'voucher_usages', 'payment_proofs', 'order_refunds', 'order_status_histories', 'order_items', 'order_shipments', 'orders', 'vouchers', 'bank_accounts', 'wishlists', 'cart_items', 'carts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
