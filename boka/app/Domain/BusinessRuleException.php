<?php

namespace App\Domain;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis. Pesannya aman ditampilkan ke pengguna.
 * Web menampilkannya sebagai pesan galat; API mengembalikannya dengan kode HTTP dan kode galat.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $errorCode = 'business_rule',
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
