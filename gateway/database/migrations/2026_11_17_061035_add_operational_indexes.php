<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes that match how the gateway actually reads: lists sorted by id within a merchant and mode
     * (InnoDB appends the primary key, so (merchant_id, livemode) serves "latest id first"), the dashboard
     * totals by completion time, and the reconciliation and expiry sweeps by status and time.
     *
     * @var array<string, list<list<string>>>
     */
    private const INDEXES = [
        'customers' => [['merchant_id', 'livemode']],
        'payment_links' => [['merchant_id', 'livemode']],
        'checkout_sessions' => [['merchant_id', 'livemode'], ['payment_status', 'expire_at']],
        'payment_intents' => [['merchant_id', 'livemode'], ['status', 'expire_at']],
        'payments' => [['merchant_id', 'livemode'], ['merchant_id', 'livemode', 'status', 'completed_at'], ['livemode', 'status', 'completed_at'], ['status', 'created_at']],
        'refunds' => [['merchant_id', 'livemode'], ['status', 'created_at']],
        'webhook_deliveries' => [['merchant_id', 'livemode'], ['status', 'next_attempt_at']],
        'hosted_payment_attempts' => [['status', 'expires_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $indexes) {
            Schema::table($name, function (Blueprint $table) use ($name, $indexes) {
                foreach ($indexes as $columns) {
                    $table->index($columns, $this->indexName($name, $columns));
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $name => $indexes) {
            Schema::table($name, function (Blueprint $table) use ($name, $indexes) {
                foreach ($indexes as $columns) {
                    $table->dropIndex($this->indexName($name, $columns));
                }
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function indexName(string $table, array $columns): string
    {
        return substr($table.'_'.implode('_', $columns).'_ops_index', 0, 64);
    }
};
