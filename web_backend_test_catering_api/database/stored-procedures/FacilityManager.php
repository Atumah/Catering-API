<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "Ogheneruemu";
$dbname = "facility_management";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL to create stored procedures
$procedures = [
    "CreateFacilityWithTags" => "
    CREATE PROCEDURE CreateFacilityWithTags(
        IN p_name VARCHAR(100),
        IN p_creation_date DATE,
        IN p_location_id INT,
        IN p_tags VARCHAR(255)
    )
    BEGIN
        DECLARE facility_id INT;
        INSERT INTO Facility (name, creation_date, location_id)
        VALUES (p_name, p_creation_date, p_location_id);
        SET facility_id = LAST_INSERT_ID();
        
        INSERT IGNORE INTO Tag (name)
        SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(p_tags, ',', n.n), ',', -1)) AS name
        FROM (
            SELECT 1 + units.i + tens.i * 10 AS n
            FROM (SELECT 0 AS i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) units,
                 (SELECT 0 AS i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) tens
            ORDER BY n
        ) n
        WHERE n.n <= 1 + (LENGTH(p_tags) - LENGTH(REPLACE(p_tags, ',', '')));
        
        INSERT INTO FacilityTag (facility_id, tag_id)
        SELECT facility_id, Tag.id
        FROM Tag
        WHERE Tag.name IN (
            SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(p_tags, ',', n.n), ',', -1)) AS name
            FROM (
                SELECT 1 + units.i + tens.i * 10 AS n
                FROM (SELECT 0 AS i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) units,
                     (SELECT 0 AS i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) tens
                ORDER BY n
            ) n
            WHERE n.n <= 1 + (LENGTH(p_tags) - LENGTH(REPLACE(p_tags, ',', '')))
        );
    END",

    "ReadFacility" => "
    CREATE PROCEDURE ReadFacility(IN p_facility_id INT)
    BEGIN
        SELECT f.*, l.*, GROUP_CONCAT(t.name) AS tags
        FROM Facility f
        JOIN Location l ON f.location_id = l.id
        LEFT JOIN FacilityTag ft ON f.id = ft.facility_id
        LEFT JOIN Tag t ON ft.tag_id = t.id
        WHERE f.id = p_facility_id
        GROUP BY f.id;
    END",

    "ReadFacilities" => "
    CREATE PROCEDURE ReadFacilities()
    BEGIN
        SELECT f.*, l.*, GROUP_CONCAT(t.name) AS tags
        FROM Facility f
        JOIN Location l ON f.location_id = l.id
        LEFT JOIN FacilityTag ft ON f.id = ft.facility_id
        LEFT JOIN Tag t ON ft.tag_id = t.id
        GROUP BY f.id;
    END",

    "UpdateFacilityWithTags" => "
    CREATE PROCEDURE UpdateFacilityWithTags(
        IN p_facility_id INT,
        IN p_name VARCHAR(100),
        IN p_creation_date DATE,
        IN p_location_id INT,
        IN p_tags VARCHAR(255)
    )
    BEGIN
        UPDATE Facility
        SET name = p_name, creation_date = p_creation_date, location_id = p_location_id
        WHERE id = p_facility_id;
        
        DELETE FROM FacilityTag WHERE facility_id = p_facility_id;
        
        INSERT IGNORE INTO Tag (name)
        SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(p_tags, ',', n.n), ',', -1)) AS name
        FROM (
            SELECT 1 + units.i + tens.i * 10 AS n
            FROM (SELECT 0 AS i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) units,
                 (SELECT 0 AS i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) tens
            ORDER BY n
        ) n
        WHERE n.n <= 1 + (LENGTH(p_tags) - LENGTH(REPLACE(p_tags, ',', '')));
        
        INSERT INTO FacilityTag (facility_id, tag_id)
        SELECT p_facility_id, Tag.id
        FROM Tag
        WHERE Tag.name IN (
            SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(p_tags, ',', n.n), ',', -1)) AS name
            FROM (
                SELECT 1 + units.i + tens.i * 10 AS n
                FROM (SELECT 0 AS i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) units,
                     (SELECT 0 AS i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) tens
                ORDER BY n
            ) n
            WHERE n.n <= 1 + (LENGTH(p_tags) - LENGTH(REPLACE(p_tags, ',', '')))
        );
    END",

    "DeleteFacility" => "
    CREATE PROCEDURE DeleteFacility(IN p_facility_id INT)
    BEGIN
        DELETE FROM FacilityTag WHERE facility_id = p_facility_id;
        DELETE FROM Facility WHERE id = p_facility_id;
    END"
];

foreach ($procedures as $name => $sql) {
    // Drop the procedure if it exists
    $conn->query("DROP PROCEDURE IF EXISTS $name");

    // Create the procedure
    if ($conn->multi_query($sql)) {
        echo "Stored procedure $name created successfully.<br>";
        // Clear the result set
        while ($conn->more_results() && $conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
    } else {
        echo "Error creating stored procedure $name: " . $conn->error . "<br>";
    }
}

$conn->close();
?>
