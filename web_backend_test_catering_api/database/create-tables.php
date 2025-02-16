<?php

$servername = "localhost";
$username = "root";
$password = "Ogheneruemu";
$dbname = "facility_management";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL to create Location table
$sql = "CREATE TABLE IF NOT EXISTS Location (
    id INT AUTO_INCREMENT PRIMARY KEY,
    city VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    zip_code VARCHAR(20) NOT NULL,
    country_code CHAR(2) NOT NULL,
    phone_number VARCHAR(20) NOT NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Location table created successfully<br>";
} else {
    echo "Error creating Location table: " . $conn->error . "<br>";
}

// SQL to create Facility table
$sql = "CREATE TABLE IF NOT EXISTS Facility (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    creation_date DATE NOT NULL,
    location_id INT NOT NULL,
    FOREIGN KEY (location_id) REFERENCES Location(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Facility table created successfully<br>";
} else {
    echo "Error creating Facility table: " . $conn->error . "<br>";
}

// SQL to create Tag table
$sql = "CREATE TABLE IF NOT EXISTS Tag (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
)";

if ($conn->query($sql) === TRUE) {
    echo "Tag table created successfully<br>";
} else {
    echo "Error creating Tag table: " . $conn->error . "<br>";
}

// SQL to create FacilityTag junction table
$sql = "CREATE TABLE IF NOT EXISTS FacilityTag (
    facility_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (facility_id, tag_id),
    FOREIGN KEY (facility_id) REFERENCES Facility(id),
    FOREIGN KEY (tag_id) REFERENCES Tag(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "FacilityTag table created successfully<br>";
} else {
    echo "Error creating FacilityTag table: " . $conn->error . "<br>";
}

$conn->close();
