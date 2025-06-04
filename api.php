<?php
header('Content-Type: application/json');

try {
    $db = new PDO('sqlite:se_pos.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tables if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            name TEXT NOT NULL,
            price REAL NOT NULL
        );
        CREATE TABLE IF NOT EXISTS bills (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            total REAL NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS bill_items (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            bill_id INTEGER,
            product_id INTEGER,
            quantity INTEGER,
            FOREIGN KEY (bill_id) REFERENCES bills(id),
            FOREIGN KEY (product_id) REFERENCES products(id)
        );
    ");

    $action = $_GET['action'] ?? '';

    if ($action === 'add_product') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['name']) || !isset($data['price']) || $data['price'] <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid product data']);
            exit;
        }
        $stmt = $db->prepare('INSERT INTO products (name, price) VALUES (?, ?)');
        $stmt->execute([$data['name'], $data['price']]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'get_products') {
        $stmt = $db->query('SELECT * FROM products');
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($action === 'get_product') {
        $id = $_GET['id'] ?? 0;
        $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($product ?: []);
    } elseif ($action === 'delete_product') {
        $id = $_GET['id'] ?? 0;
        $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['success' => $stmt->rowCount() > 0]);
    } elseif ($action === 'generate_bill') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['items'])) {
            echo json_encode(['success' => false, 'error' => 'No items in bill']);
            exit;
        }

        $db->beginTransaction();
        try {
            $total = 0;
            foreach ($data['items'] as $item) {
                $stmt = $db->prepare('SELECT price FROM products WHERE id = ?');
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$product) throw new Exception('Invalid product ID');
                $total += $product['price'] * $item['quantity'];
            }

            $stmt = $db->prepare('INSERT INTO bills (total) VALUES (?)');
            $stmt->execute([$total]);
            $bill_id = $db->lastInsertId();

            $stmt = $db->prepare('INSERT INTO bill_items (bill_id, product_id, quantity) VALUES (?, ?, ?)');
            foreach ($data['items'] as $item) {
                $stmt->execute([$bill_id, $item['product_id'], $item['quantity']]);
            }

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } elseif ($action === 'get_bills') {
        $stmt = $db->query('
            SELECT b.id, b.total, b.created_at,
                   GROUP_CONCAT(p.name || " (x" || bi.quantity || ")") as items
            FROM bills b
            LEFT JOIN bill_items bi ON b.id = bi.bill_id
            LEFT JOIN products p ON bi.product_id = p.id
            GROUP BY b.id
        ');
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($bills as &$bill) {
            $bill['items'] = explode(',', $bill['items']);
        }
        echo json_encode($bills);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>