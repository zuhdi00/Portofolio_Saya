<?php
include '../connection.php';

// Check if the ID is provided in the URL
if (isset($_GET['id_pengunduran'])) {
    $id_pengunduran = $_GET['id_pengunduran'];

    // Delete the resignation record
    $delete_sql = "DELETE FROM pengunduran_diri WHERE id_pengunduran = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $id_pengunduran);

    if ($delete_stmt->execute()) {
        header("Location: index.php?status=success");
        exit();
    } else {
        echo "Error deleting record: " . $conn->error;
    }
} else {
    echo "ID not specified!";
    exit();
}
?>
