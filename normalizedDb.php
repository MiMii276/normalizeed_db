<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "NormalizedDB";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function clearDatabase($conn) {
    $conn->query("DELETE FROM Orders");
    $conn->query("DELETE FROM Customers");
    $conn->query("DELETE FROM Products");
}

function normalizeData($rawData) {
    $lines = explode("\n", trim($rawData));
    $headers = explode(",", array_shift($lines));
    $data = [];

    foreach ($lines as $line) {
        $row = str_getcsv($line);
        if (count($row) !== count($headers)) {
            throw new Exception("Row column count doesn't match header.");
        }
        $data[] = array_combine($headers, $row);
    }

    return [$headers, $data];
}

function printTable($title, $rows) {
    if (empty($rows)) return;
    echo "<h3>$title</h3><table border='1' cellpadding='5'><tr>";
    foreach (array_keys($rows[0]) as $key) {
        echo "<th>" . htmlspecialchars($key) . "</th>";
    }
    echo "</tr>";
    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($row as $cell) {
            echo "<td>" . htmlspecialchars($cell) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table><br>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Normalization App</title>
    <style>
        body { font-family: Arial; margin: 40px; }
        textarea { width: 100%; height: 200px; }
        input[type=submit] { padding: 10px 20px; }
    </style>
</head>
<body>
    <h2>Normalization with MySQL (3NF)</h2>
    <form method="post">
        <label>Enter CSV data:</label><br>
        <textarea name="rawData" placeholder="OrderID,CustomerName,ProductID,ProductName
1,Alice,101,Keyboard
2,Bob,102,Mouse
3,Alice,101,Keyboard"></textarea><br><br>
        <input type="submit" value="Normalize & Save">
    </form>
    <hr>

<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $raw = $_POST['rawData'];
        list($headers, $data) = normalizeData($raw);

        echo "<h2>Input Parsed (1NF)</h2>";
        printTable("Raw Table", $data);

        // Normalize to 3NF
        $productTable = [];
        $customerTable = [];
        $orderTable = [];

        foreach ($data as $row) {
            // Unique Products
            $productTable[$row['ProductID']] = [
                'ProductID' => $row['ProductID'],
                'ProductName' => $row['ProductName']
            ];

            // Unique Customers
            $customerTable[$row['CustomerName']] = [
                'CustomerName' => $row['CustomerName']
            ];

            // Orders
            $orderTable[] = [
                'OrderID' => $row['OrderID'],
                'CustomerName' => $row['CustomerName'],
                'ProductID' => $row['ProductID']
            ];
        }

        // Clear DB before inserting new data
        clearDatabase($conn);

        // Insert into Customers
        foreach ($customerTable as $cust) {
            $stmt = $conn->prepare("INSERT INTO Customers (CustomerName) VALUES (?)");
            $stmt->bind_param("s", $cust['CustomerName']);
            $stmt->execute();
            $stmt->close();
        }

        // Insert into Products
        foreach ($productTable as $prod) {
            $stmt = $conn->prepare("INSERT INTO Products (ProductID, ProductName) VALUES (?, ?)");
            $stmt->bind_param("is", $prod['ProductID'], $prod['ProductName']);
            $stmt->execute();
            $stmt->close();
        }

        // Insert into Orders
        foreach ($orderTable as $ord) {
            $stmt = $conn->prepare("INSERT INTO Orders (OrderID, CustomerName, ProductID) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $ord['OrderID'], $ord['CustomerName'], $ord['ProductID']);
            $stmt->execute();
            $stmt->close();
        }

        echo "<h2>Saved to Database (3NF)</h2>";
        printTable("Orders", $orderTable);
        printTable("Customers", array_values($customerTable));
        printTable("Products", array_values($productTable));

    } catch (Exception $e) {
        echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>
</body>
</html>
