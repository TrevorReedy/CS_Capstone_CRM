<section class="card">
    <div class="page-header">
        <h1>Inventory</h1>
        <div class="header-actions">
            <a href="/modules/inventory/products.php?page=ledger" class="btn">Inventory Ledger</a>
            <a href="/modules/inventory/products.php?page=detail" class="btn btn-primary">+ Add Product</a>
        </div>
    </div>

    <table class="table rfq-list-table js-dt"
           data-dt-url="/modules/inventory/products_data.php"
           data-dt-export="/modules/inventory/products_export.php">
        <thead>
            <tr class="dt-title">
                <th data-col="sku">SKU</th>
                <th data-col="product_name">Product Name</th>
                <th data-col="price">Price</th>
                <th data-col="available">Available</th>
                <th data-col="reserved">Reserved</th>
                <th data-col="status" data-orderable="false" data-searchable="false">Status</th>
                <th data-col="actions" data-orderable="false" data-searchable="false">Actions</th>
            </tr>
            <tr class="dt-filter">
                <th data-filter="text"></th>
                <th data-filter="text"></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<?php include __DIR__ . '/../../../Shared/datatables_assets.php'; ?>


