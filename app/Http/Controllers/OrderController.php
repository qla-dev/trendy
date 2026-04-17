<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends WorkOrderController
{
    public function ordersLinkageIndex(Request $request)
    {
        return parent::ordersLinkageIndex($request);
    }

    public function ordersLinkageData(Request $request): JsonResponse
    {
        return parent::ordersLinkageData($request);
    }

    public function ordersLinkagePositions(Request $request)
    {
        return parent::ordersLinkagePositions($request);
    }

    public function ordersLinkageWorkOrders(Request $request)
    {
        return parent::ordersLinkageWorkOrders($request);
    }

    public function ordersLinkageWorkOrdersApi(Request $request): JsonResponse
    {
        return parent::ordersLinkageWorkOrdersApi($request);
    }

    public function destroyLinkedOrder(Request $request): JsonResponse
    {
        return parent::destroyLinkedOrder($request);
    }

    protected function orderTableName(): string
    {
        return Order::sourceTableName();
    }

    protected function orderItemTableName(): string
    {
        return Order::sourceItemTableName();
    }

    protected function workOrderOrderItemLinkTableName(): string
    {
        return Order::sourceLinkTableName();
    }

    protected function orderTableColumns(): array
    {
        return Order::sourceColumns();
    }

    protected function orderItemTableColumns(): array
    {
        return Order::itemColumns();
    }

    protected function workOrderOrderItemLinkTableColumns(): array
    {
        return Order::linkColumns();
    }

    protected function newOrderTableQuery(): Builder
    {
        return Order::newSourceQuery();
    }

    protected function newOrderItemTableQuery(): Builder
    {
        return Order::newItemQuery();
    }

    protected function newWorkOrderOrderItemLinkTableQuery(): Builder
    {
        return Order::newLinkQuery();
    }

    protected function qualifiedOrderTableName(): string
    {
        return Order::qualifiedSourceTableName();
    }

    protected function qualifiedOrderItemTableName(): string
    {
        return Order::qualifiedItemTableName();
    }

    protected function qualifiedWorkOrderOrderItemLinkTableName(): string
    {
        return Order::qualifiedLinkTableName();
    }
}
