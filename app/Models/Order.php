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
        return OrderItem::sourceTableName();
    }

    public static function sourceLinkTableName(): string
    {
        return WorkOrderOrderItemLink::sourceTableName();
    }

    public static function qualifiedSourceTableName(): string
    {
        return self::sourceSchema() . '.' . self::sourceTableName();
    }

    public static function qualifiedItemTableName(): string
    {
        return OrderItem::qualifiedSourceTableName();
    }

    public static function qualifiedLinkTableName(): string
    {
        return WorkOrderOrderItemLink::qualifiedSourceTableName();
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
        return OrderItem::sourceColumns();
    }

    public static function linkColumns(): array
    {
        return WorkOrderOrderItemLink::sourceColumns();
    }

    public static function newSourceQuery(): QueryBuilder
    {
        return self::db()->table(self::qualifiedSourceTableName());
    }

    public static function newItemQuery(): QueryBuilder
    {
        return OrderItem::newSourceQuery();
    }

    public static function newLinkQuery(): QueryBuilder
    {
        return WorkOrderOrderItemLink::newSourceQuery();
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
