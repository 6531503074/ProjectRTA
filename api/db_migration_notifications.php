<?php
include "../config/db.php";

$sql = "ALTER TABLE group_chat_members ADD COLUMN last_read_message_id INT(11) DEFAULT 0";

if ($conn->query($sql) === TRUE) {
    echo "Column last_read_message_id created successfully";
} else {
    echo "Error creating column: " . $conn->error;
}

$conn->close();
?>
