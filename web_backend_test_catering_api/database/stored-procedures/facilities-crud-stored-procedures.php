<?php

require_once '../vendor/autoload.php';
require_once 'path/to/your/services.php';  // Adjust this path as needed

use App\Plugins\Di\Factory;

class FacilitiesManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function createStoredProcedures() {
        $conn = $this->db->getConnection();

        $procedures = [
            "CREATE PROCEDURE CreateFacilityWithTags(
                IN p_name VARCHAR(255),
                IN p_description TEXT,
                IN p_location_id INT,
                IN p_tags JSON
            )
            BEGIN
                DECLARE facility_id INT;
                
                -- Insert facility
                INSERT INTO facilities (name, description, location_id)
                VALUES (p_name, p_description, p_location_id);
                
                SET facility_id = LAST_INSERT_ID();
                
                -- Insert tags
                INSERT INTO facility_tags (facility_id, tag_id)
                SELECT facility_id, tag_id
                FROM JSON_TABLE(p_tags, '$[*]' COLUMNS (tag_id INT PATH '$')) AS tags;
            END",
            "CREATE PROCEDURE ReadFacility(IN p_facility_id INT)
            BEGIN
                SELECT 
                    f.*,
                    l.name AS location_name,
                    l.address,
                    GROUP_CONCAT(t.name) AS tags
                FROM facilities f
                LEFT JOIN locations l ON f.location_id = l.id
                LEFT JOIN facility_tags ft ON f.id = ft.facility_id
                LEFT JOIN tags t ON ft.tag_id = t.id
                WHERE f.id = p_facility_id
                GROUP BY f.id;
            END",
            "CREATE PROCEDURE ReadFacilities()
            BEGIN
                SELECT 
                    f.*,
                    l.name AS location_name,
                    l.address,
                    GROUP_CONCAT(t.name) AS tags
                FROM facilities f
                LEFT JOIN locations l ON f.location_id = l.id
                LEFT JOIN facility_tags ft ON f.id = ft.facility_id
                LEFT JOIN tags t ON ft.tag_id = t.id
                GROUP BY f.id;
            END",
            "CREATE PROCEDURE UpdateFacilityWithTags(
                IN p_facility_id INT,
                IN p_name VARCHAR(255),
                IN p_description TEXT,
                IN p_location_id INT,
                IN p_tags JSON
            )
            BEGIN
                -- Update facility details
                UPDATE facilities 
                SET name = p_name,
                    description = p_description,
                    location_id = p_location_id
                WHERE id = p_facility_id;
                
                -- Delete existing tags
                DELETE FROM facility_tags WHERE facility_id = p_facility_id;
                
                -- Insert new tags
                INSERT INTO facility_tags (facility_id, tag_id)
                SELECT p_facility_id, tag_id
                FROM JSON_TABLE(p_tags, '$[*]' COLUMNS (tag_id INT PATH '$')) AS tags;
            END",
            "CREATE PROCEDURE DeleteFacility(IN p_facility_id INT)
        BEGIN
            -- Delete facility tags
            DELETE FROM facility_tags WHERE facility_id = p_facility_id;
            
            -- Delete facility
            DELETE FROM facilities WHERE id = p_facility_id;
        END"
        ];

        foreach ($procedures as $procedure) {
            if ($conn->multi_query($procedure) === TRUE) {
                do {
                    if ($result = $conn->store_result()) {
                        $result->free();
                    }
                } while ($conn->more_results() && $conn->next_result());
            } else {
                echo "Error creating procedure: " . $conn->error;
            }
        }
    }
}

$di = Factory::getDi();

$db = $di->get('db');

// Create DatabaseSetup object
$dbSetup = new FacilitiesManager($db);

// Create stored procedures
$dbSetup->createStoredProcedures();
