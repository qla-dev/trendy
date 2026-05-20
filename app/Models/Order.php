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
    private static ?array $sourceColumnMetadataCache = null;
    private static ?array $sourceNonInsertableColumnsCache = null;
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

    public static function sourceColumnMetadata(): array
    {
        if (self::$sourceColumnMetadataCache !== null) {
            return self::$sourceColumnMetadataCache;
        }

        self::$sourceColumnMetadataCache = self::db()
            ->table('INFORMATION_SCHEMA.COLUMNS')
            ->select([
                'COLUMN_NAME',
                'DATA_TYPE',
                'CHARACTER_MAXIMUM_LENGTH',
                'NUMERIC_PRECISION',
                'NUMERIC_SCALE',
            ])
            ->where('TABLE_SCHEMA', self::sourceSchema())
            ->where('TABLE_NAME', self::sourceTableName())
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    (string) $row->COLUMN_NAME => [
                        'data_type' => (string) $row->DATA_TYPE,
                        'length' => $row->CHARACTER_MAXIMUM_LENGTH !== null ? (int) $row->CHARACTER_MAXIMUM_LENGTH : null,
                        'precision' => $row->NUMERIC_PRECISION !== null ? (int) $row->NUMERIC_PRECISION : null,
                        'scale' => $row->NUMERIC_SCALE !== null ? (int) $row->NUMERIC_SCALE : null,
                    ],
                ];
            })
            ->all();

        return self::$sourceColumnMetadataCache;
    }

    public static function sourceStringLengths(): array
    {
        return collect(self::sourceColumnMetadata())
            ->map(function (array $metadata) {
                return $metadata['length'] ?? null;
            })
            ->all();
    }

    public static function sourceNonInsertableColumns(): array
    {
        if (self::$sourceNonInsertableColumnsCache !== null) {
            return self::$sourceNonInsertableColumnsCache;
        }

        if (self::db()->getDriverName() !== 'sqlsrv') {
            self::$sourceNonInsertableColumnsCache = [];

            return self::$sourceNonInsertableColumnsCache;
        }

        self::$sourceNonInsertableColumnsCache = self::db()
            ->table('sys.columns as c')
            ->join('sys.tables as t', 'c.object_id', '=', 't.object_id')
            ->join('sys.schemas as s', 't.schema_id', '=', 's.schema_id')
            ->where('s.name', self::sourceSchema())
            ->where('t.name', self::sourceTableName())
            ->where(function ($query) {
                $query->where('c.is_identity', 1)
                    ->orWhere('c.is_computed', 1);
            })
            ->pluck('c.name')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();

        return self::$sourceNonInsertableColumnsCache;
    }

    public static function sourceInsertableColumns(): array
    {
        return array_values(array_diff(self::sourceColumns(), self::sourceNonInsertableColumns()));
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
