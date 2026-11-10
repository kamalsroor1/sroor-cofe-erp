<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\ImportType;
use App\Services\Import\Handlers\AbstractImportHandler;
use App\Services\Import\Handlers\CategoriesImportHandler;
use App\Services\Import\Handlers\CustomersImportHandler;
use App\Services\Import\Handlers\ItemsImportHandler;
use App\Services\Import\Handlers\SuppliersImportHandler;
use Illuminate\Contracts\Container\Container;

final class ImportHandlerFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(ImportType $type): AbstractImportHandler
    {
        return $this->container->make(match ($type) {
            ImportType::Items => ItemsImportHandler::class,
            ImportType::Customers => CustomersImportHandler::class,
            ImportType::Suppliers => SuppliersImportHandler::class,
            ImportType::Categories => CategoriesImportHandler::class,
        });
    }
}
