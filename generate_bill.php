<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['latest_bill_id'])) {
    die("No bill found.");
}

$type = isset($_GET['type']) ? $_GET['type'] : 'txt';
$bill_id = $_SESSION['latest_bill_id'];
$items = [];
$total = 0;
$stmt = $conn->prepare(
    "SELECT p.name, p.price, bi.quantity
    FROM bill_items bi
    JOIN products p ON bi.product_id = p.id
    WHERE bi.bill_id = ?"
);
$stmt->bind_param("i", $bill_id);
$stmt->execute();
$stmt->bind_result($pname, $pprice, $pqty);
while ($stmt->fetch()) {
    $items[] = [
        "name" => $pname,
        "price" => $pprice,
        "qty" => $pqty,
        "subtotal" => $pprice * $pqty
    ];
    $total += $pprice * $pqty;
}
$stmt->close();

if ($type === 'txt') {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="your-bill.txt"');
    echo "BILL\n";
    echo "-----------------------------\n";
    foreach ($items as $item) {
        echo $item['name'] . " x" . $item['qty'] . " - $" . number_format($item['subtotal'], 2) . "\n";
    }
    echo "-----------------------------\n";
    echo "Total: $" . number_format($total, 2) . "\n";
    echo "Thank you for your purchase with Se Pos!\n";
    exit;
} elseif ($type === 'pdf') {
    require('fpdf.php');
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(40, 10, "BILL");
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, "-----------------------------", 0, 1);
    foreach ($items as $item) {
        $pdf->Cell(0, 10, $item['name'] . " x" . $item['qty'] . " - $" . number_format($item['subtotal'], 2), 0, 1);
    }
    $pdf->Cell(0, 10, "-----------------------------", 0, 1);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, "Total: $" . number_format($total, 2), 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, "Thank you for your purchase Se Pos!", 0, 1);
    $pdf->Output('D', 'your-bill.pdf');
    exit;
} else {
    die("Invalid bill type.");
}
?>