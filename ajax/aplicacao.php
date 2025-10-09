<?php
require '../main.inc.php';

if (empty($user->rights->safra->aplicacao->read)) {
    accessforbidden();
}

$action = GETPOST('action', 'alpha');

dol_syslog(__FILE__.' action='.$action, LOG_DEBUG);

if ($action === 'linkedoptions') {
    $productId = GETPOSTINT('product_id');
    $result = array(
        'product' => null,
        'tecnico' => array(),
        'formulado' => array(),
    );

    if ($productId > 0) {
        require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
        dol_include_once('/safra/class/safra_product_link.class.php');

        $product = new Product($db);
        if ($product->fetch($productId) > 0) {
            $result['product'] = array(
                'id' => $product->id,
                'ref' => $product->ref,
                'label' => $product->label,
                'stock' => $product->stock_reel,
                'status' => $product->status,
            );

            $linksTecnico = SafraProductLink::fetchLinks($db, $product->id, SafraProductLink::TYPE_TECNICO);
            foreach ($linksTecnico as $link) {
                $result['tecnico'][] = array(
                    'id' => (int) $link->target_id,
                    'ref' => $link->ref,
                    'label' => $link->label,
                );
            }

            $linksFormulado = SafraProductLink::fetchLinks($db, $product->id, SafraProductLink::TYPE_FORMULADO);
            foreach ($linksFormulado as $link) {
                $result['formulado'][] = array(
                    'id' => (int) $link->target_id,
                    'ref' => $link->ref,
                    'label' => $link->label,
                );
            }
        }
    }

    header('Content-Type: application/json');
    print json_encode($result);
    exit;
}

if ($action === 'productstock') {
    $productId = GETPOSTINT('product_id');
    $warehouseId = GETPOSTINT('warehouse_id');

    $qty = 0;
    if ($productId > 0) {
        require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
        $product = new Product($db);
        if ($product->fetch($productId) > 0) {
            if ($warehouseId > 0) {
                $qty = $product->getBatchStock(array($warehouseId));
            } else {
                $qty = $product->stock_reel;
            }
        }
    }

    header('Content-Type: application/json');
    print json_encode(array('qty' => (float) $qty));
    exit;
}

http_response_code(400);
print json_encode(array('error' => 'Invalid request'));
