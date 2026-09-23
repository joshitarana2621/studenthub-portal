<?php

if (isset($_POST['download_excel'])) {
    
   
    $prod_name = $_POST['product_name'];
    $prod_price = $_POST['product_price'];
    $prod_qty = $_POST['product_quantity'];

   
    $filename = "User_Product_Report.csv";

    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=\"$filename\"");

    $output = fopen("php://output", "w");

   
    fputcsv($output, array('Product Name', 'Price per Unit', 'Quantity Ordered'));

   
    fputcsv($output, array($prod_name, $prod_price, $prod_qty));

    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Excel Generator Form</title>
</head>
<body>
    <h2>Create Your Excel Spreadsheet</h2>
    
  
    <form action="generate.php" method="POST">
        <label>Product Name:</label><br>
        <input type="text" name="product_name" required><br><br>

        <label>Price ($):</label><br>
        <input type="text" name="product_price" required><br><br>

        <label>Quantity:</label><br>
        <input type="number" name="product_quantity" required><br><br>

        <button type="submit" name="download_excel">Generate & Download Excel</button>
    </form>
</body>
</html>
