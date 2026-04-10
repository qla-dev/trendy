<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    private static ?array $sourceColumnsCache = null;
    private static ?array $itemColumnsCache = null;
    private static ?array $linkColumnsCache = null;
    private static ?string $linkTableCache = null;

    protected $connection = 'sqlsrv';
    protected $primaryKey = 'acKey';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    public function getTable(): string
    {
        return self::qualifiedSourceTableName();
    }

    public static function sourceSchema(): string
    {
        return (string) config('workorders.schema', 'dbo');
    }

    public static function sourceTableName(): string
    {
        return (string) config('workorders.orders_table', 'tHE_Order');
    }

    public static function sourceItemTableName(): string
    {
        return (string) config('workorders.order_items_table', 'tHE_OrderItem');
    }

    public static function sourceLinkTableName(): string
    {
        if (self::$linkTableCache !== null) {
            return self::$linkTableCache;
        }

        $configuredTable = trim((string) config('workorders.work_order_order_item_link_table', 'vHF_LinkWOExOrderItem'));
        $candidates = array_values(array_unique(array_filter([
            $configuredTable,
            'vHF_LinkWOExOrderItem',
            'tHF_LinkWOExOrderItem',
        ])));

        foreach ($candidates as $candidate) {
            $exists = self::db()
                ->table('INFORMATION_SCHEMA.COLUMNS')
                ->where('TABLE_SCHEMA', self::sourceSchema())
                ->where('TABLE_NAME', $candidate)
                ->exists();

            if ($exists) {
                self::$linkTableCache = (string) $candidate;

                return self::$linkTableCache;
            }
        }

        self::$linkTableCache = $configuredTable !== '' ? $configuredTable : 'tHF_LinkWOExOrderItem';

        return self::$linkTableCache;
    }

    public static function qualifiedSourceTableName(): string
    {
        return self::sourceSchema() . '.' . self::sourceTableName();
    }

    public static function qualifiedItemTableName(): string
    {
        return self::sourceSchema() . '.' . self::sourceItemTableName();
    }

    public static function qualifiedLinkTableName(): string
    {
        return self::sourceSchema() . '.' . self::sourceLinkTableName();
    }

    public static function sourceColumns(): array
    {
        if (self::$sourceColumnsCache !== null) {
            return self::$sourceColumnsCache;
        }

        self::$sourceColumnsCache = self::resolveTableColumns(self::sourceTableName());

        return self::$sourceColumnsCache;
    }

    public static function itemColumns(): array
    {
        if (self::$itemColumnsCache !== null) {
            return self::$itemColumnsCache;
        }

        self::$itemColumnsCache = self::resolveTableColumns(self::sourceItemTableName());

        return self::$itemColumnsCache;
    }

    public static function linkColumns(): array
    {
        if (self::$linkColumnsCache !== null) {
            return self::$linkColumnsCache;
        }

        self::$linkColumnsCache = self::resolveTableColumns(self::sourceLinkTableName());

        return self::$linkColumnsCache;
    }

    public static function newSourceQuery(): QueryBuilder
    {
        return self::db()->table(self::qualifiedSourceTableName());
    }

    public static function newItemQuery(): QueryBuilder
    {
        return self::db()->table(self::qualifiedItemTableName());
    }

    public static function newLinkQuery(): QueryBuilder
    {
        return self::db()->table(self::qualifiedLinkTableName());
    }

    private static function resolveTableColumns(string $tableName): array
    {
        return self::db()
            ->table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', self::sourceSchema())
            ->where('TABLE_NAME', $tableName)
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();
    }

    private static function db()
    {
        return DB::connection((new static())->getConnectionName());
    }
}
