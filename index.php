<?php
session_start();
require_once "db.php";

// Add new product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['product_name']);
    $price = floatval($_POST['product_price']);
    if ($name && $price > 0) {
        $stmt = $conn->prepare("INSERT INTO products (name, price) VALUES (?,?)");
        $stmt->bind_param("sd", $name, $price);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: index.php");
    exit();
}

// Add product to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = intval($_POST['product_id']);
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    if (!isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] = 1;
    } else {
        $_SESSION['cart'][$product_id]++;
    }
    header("Location: index.php");
    exit();
}

// Checkout and save bill
if (isset($_GET['bill']) && isset($_SESSION['cart']) && count($_SESSION['cart'])) {
    $conn->begin_transaction();
    $conn->query("INSERT INTO bills DEFAULT VALUES");
    $bill_id = $conn->insert_id;
    $stmt = $conn->prepare("INSERT INTO bill_items (bill_id, product_id, quantity) VALUES (?, ?, ?)");
    foreach ($_SESSION['cart'] as $pid => $qty) {
        $stmt->bind_param("iii", $bill_id, $pid, $qty);
        $stmt->execute();
    }
    $stmt->close();
    $conn->commit();
    $_SESSION['cart'] = [];
    header("Location: index.php?billed=1");
    exit();
}

// Fetch products
$products = [];
$res = $conn->query("SELECT id, name, price FROM products ORDER BY id DESC");
while ($row = $res->fetch_assoc()) {
    $products[] = $row;
}

// Fetch cart details
$cart_details = [];
$total = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $pid => $qty) {
        $stmt = $conn->prepare("SELECT name, price FROM products WHERE id=?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $stmt->bind_result($name, $price);
        if ($stmt->fetch()) {
            $cart_details[] = [
                "name" => $name,
                "price" => $price,
                "qty" => $qty,
                "subtotal" => $price * $qty
            ];
            $total += $price * $qty;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS System with Database</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>POS System</h1>
    <div class="flex-row">
        <div class="panel">
            <h2>Add Product</h2>
            <form method="POST" class="form">
                <input type="text" name="product_name" placeholder="Product Name" required>
                <input type="number" name="product_price" placeholder="Price" min="0.01" step="0.01" required>
                <button type="submit" name="add_product">Add Product</button>
            </form>
            <hr>
            <h2>Products</h2>
            <ul>
                <?php foreach ($products as $p): ?>
                    <li>
                        <?= htmlspecialchars($p['name']) ?> - $<?= number_format($p['price'], 2) ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <button type="submit" name="add_to_cart">Add to Bill</button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if (!$products): ?>
                    <li>No products yet.</li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="panel">
            <h2>Bill</h2>
            <ul>
                <?php foreach ($cart_details as $item): ?>
                    <li>
                        <?= htmlspecialchars($item['name']) ?> x<?= $item['qty'] ?>
                        <span>$<?= number_format($item['subtotal'], 2) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (!count($cart_details)): ?>
                    <li>No products in bill.</li>
                <?php endif; ?>
            </ul>
            <div class="total">Total: $<?= number_format($total, 2) ?></div>
            <form method="GET">
                <button type="submit" name="bill" <?= !count($cart_details) ? 'disabled' : '' ?>>Bill/Checkout</button>
            </form>
            <?php if (isset($_GET['billed'])): ?>
                <div class="success">Bill completed!</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>