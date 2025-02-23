-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 22, 2025 at 04:47 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

CREATE DATABASE IF NOT EXISTS facility_management;
USE facility_management;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `facility_management`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CreateFacility` (IN `facility_name` VARCHAR(100), IN `facility_creation_date` DATE, IN `location_city` VARCHAR(100), IN `location_address` VARCHAR(255), IN `location_zip_code` VARCHAR(20), IN `location_country_code` CHAR(2), IN `location_phone_number` VARCHAR(20), IN `tags` VARCHAR(1000))   BEGIN
    DECLARE facility_id INT;
    DECLARE location_id INT;
    DECLARE tag_name VARCHAR(100);
    DECLARE tag_id INT;

    -- Start transaction
    START TRANSACTION;

    -- Check if Location details are provided
    IF location_city = '' OR location_address = '' OR location_zip_code = '' OR 
       location_country_code = '' OR location_phone_number = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Location details cannot be empty';
    END IF;

    -- Check if Facility name and creation date already exist
    IF EXISTS (SELECT 1 FROM facility WHERE name = facility_name AND creation_date = facility_creation_date) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Facility with the same name and creation date already exists';
    END IF;

    -- Insert Location
    INSERT INTO location (city, address, zip_code, country_code, phone_number)
    VALUES (location_city, location_address, location_zip_code, location_country_code, location_phone_number);
    
    SET location_id = LAST_INSERT_ID();

    -- Insert Facility with new Location ID
    INSERT INTO facility (name, creation_date, location_id) 
    VALUES (facility_name, facility_creation_date, location_id);
    
    SET facility_id = LAST_INSERT_ID();

    -- Process Tags
    SET @tag_list = tags;
    
    -- Loop through tags and insert them
    WHILE CHAR_LENGTH(@tag_list) > 0 DO
        SET @tag_name = TRIM(SUBSTRING_INDEX(@tag_list, ',', 1));
        SET @tag_list = SUBSTRING(@tag_list, LENGTH(@tag_name) + 2);
        
        -- Insert tag if it doesn't exist (using INSERT IGNORE to handle duplicates)
        INSERT IGNORE INTO tag (name) VALUES (@tag_name);

        -- Get the tag_id of the inserted or existing tag
        SET tag_id = (SELECT id FROM tag WHERE name = @tag_name LIMIT 1);
        
        -- Insert into FacilityTag junction table
        INSERT INTO facilitytag (facility_id, tag_id) VALUES (facility_id, tag_id);
    END WHILE;

    -- Commit transaction
    COMMIT;

    -- Return the new facility_id
    SELECT facility_id AS new_facility_id;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `DeleteFacility` (IN `facility_id` INT)   BEGIN
    START TRANSACTION;

    DELETE FROM facility WHERE id = facility_id;

    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetFacilities` ()   BEGIN
    SELECT 
        f.id AS facility_id, 
        f.name AS facility_name, 
        f.creation_date, 
        l.city, 
        l.address, 
        l.zip_code, 
        l.country_code, 
        l.phone_number,
        GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') AS tags
    FROM facility f
    JOIN location l ON f.location_id = l.id
    LEFT JOIN facilitytag ft ON f.id = ft.facility_id
    LEFT JOIN tag t ON ft.tag_id = t.id
    GROUP BY f.id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetFacility` (IN `facility_id` INT)   BEGIN
    DECLARE facility_count INT;

    -- Check if facility exists
    SELECT COUNT(*) INTO facility_count
    FROM facility
    WHERE id = facility_id;

    IF facility_count = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Facility not found';
    ELSE
        -- Return facility details with joined data
        SELECT 
            f.id,
            f.name,
            f.creation_date,
            l.city,
            l.address,
            l.zip_code,
            l.country_code,
            l.phone_number,
            COALESCE(GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', '), '') as tags
        FROM facility f
        INNER JOIN location l ON f.location_id = l.id
        LEFT JOIN facilitytag ft ON f.id = ft.facility_id
        LEFT JOIN tag t ON ft.tag_id = t.id
        WHERE f.id = facility_id
        GROUP BY 
            f.id, 
            f.name, 
            f.creation_date, 
            l.city, 
            l.address, 
            l.zip_code, 
            l.country_code, 
            l.phone_number;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `SearchFacilities` (IN `p_facility_name` VARCHAR(255), IN `p_tag_name` VARCHAR(255), IN `p_city` VARCHAR(255))   BEGIN
    DECLARE sql_query TEXT;
    DECLARE param_count INT DEFAULT 0;

    -- Base query
    SET sql_query = 'SELECT DISTINCT f.id, f.name, f.creation_date, 
                        l.city, l.address, l.zip_code, l.country_code, l.phone_number,
                        GROUP_CONCAT(t.name ORDER BY t.name ASC SEPARATOR \', \') AS tags
                    FROM facility f
                    JOIN location l ON f.location_id = l.id
                    LEFT JOIN facilitytag ft ON f.id = ft.facility_id
                    LEFT JOIN tag t ON ft.tag_id = t.id
                    WHERE 1=1';

    -- Applying filters based on input (adding wildcards for partial matching)
    IF p_facility_name IS NOT NULL AND p_facility_name != '' THEN
        SET sql_query = CONCAT(sql_query, ' AND f.name LIKE ?');
        SET p_facility_name = CONCAT('%', p_facility_name, '%');
        SET param_count = param_count + 1;
    END IF;

    IF p_tag_name IS NOT NULL AND p_tag_name != '' THEN
        SET sql_query = CONCAT(sql_query, ' AND EXISTS (SELECT 1 FROM facilitytag ft2 
                                                        JOIN tag t2 ON ft2.tag_id = t2.id 
                                                        WHERE ft2.facility_id = f.id 
                                                        AND t2.name LIKE ?)');
        SET p_tag_name = CONCAT('%', p_tag_name, '%'); 
        SET param_count = param_count + 1;
    END IF;

    IF p_city IS NOT NULL AND p_city != '' THEN
        SET sql_query = CONCAT(sql_query, ' AND l.city LIKE ?');
        SET p_city = CONCAT('%', p_city, '%');
        SET param_count = param_count + 1;
    END IF;

    -- Grouping clause
    SET sql_query = CONCAT(sql_query, ' GROUP BY f.id, f.name, f.creation_date, 
                                          l.city, l.address, l.zip_code, l.country_code, l.phone_number');

    -- Prepare statement
    PREPARE stmt FROM sql_query;

    -- Execute with correct bindings
    IF param_count = 3 THEN
        EXECUTE stmt USING p_facility_name, p_tag_name, p_city;
    ELSEIF param_count = 2 THEN
        IF p_facility_name IS NOT NULL AND p_facility_name != '' THEN
            IF p_tag_name IS NOT NULL AND p_tag_name != '' THEN
                EXECUTE stmt USING p_facility_name, p_tag_name;
            ELSE
                EXECUTE stmt USING p_facility_name, p_city;
            END IF;
        ELSE
            EXECUTE stmt USING p_tag_name, p_city;
        END IF;
    ELSEIF param_count = 1 THEN
        IF p_facility_name IS NOT NULL AND p_facility_name != '' THEN
            EXECUTE stmt USING p_facility_name;
        ELSEIF p_tag_name IS NOT NULL AND p_tag_name != '' THEN
            EXECUTE stmt USING p_tag_name;
        ELSE
            EXECUTE stmt USING p_city;
        END IF;
    ELSE
        EXECUTE stmt;
    END IF;

    DEALLOCATE PREPARE stmt;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateFacility` (IN `p_facility_id` INT, IN `p_facility_name` VARCHAR(100), IN `p_facility_creation_date` DATE, IN `p_location_id` INT, IN `p_tags` TEXT)   BEGIN
    DECLARE v_tag_name VARCHAR(100);
    
    START TRANSACTION;
    
    -- Check if facility exists
    IF NOT EXISTS (SELECT 1 FROM facility WHERE id = p_facility_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Facility not found';
    END IF;

    -- Check if location exists
    IF NOT EXISTS (SELECT 1 FROM location WHERE id = p_location_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Location not found';
    END IF;

    -- Update facility
    UPDATE facility 
    SET name = p_facility_name,
        creation_date = p_facility_creation_date,
        location_id = p_location_id
    WHERE id = p_facility_id;

    -- Delete existing tags
    DELETE FROM facilitytag WHERE facility_id = p_facility_id;

    -- Process new tags
    WHILE p_tags != '' DO
        SET v_tag_name = TRIM(SUBSTRING_INDEX(p_tags, ',', 1));
        
        -- Insert tag if not exists
        INSERT IGNORE INTO tag (name) VALUES (v_tag_name);
        
        -- Link tag to facility
        INSERT INTO facilitytag (facility_id, tag_id)
        SELECT p_facility_id, id FROM tag WHERE name = v_tag_name;

        IF LOCATE(',', p_tags) > 0 THEN
            SET p_tags = SUBSTRING(p_tags, LOCATE(',', p_tags) + 1);
        ELSE
            SET p_tags = '';
        END IF;
    END WHILE;

    COMMIT;

    -- Return updated facility data
    SELECT f.id, f.name, f.creation_date,
           l.city, l.address, l.zip_code, l.country_code, l.phone_number,
           GROUP_CONCAT(t.name ORDER BY t.name ASC SEPARATOR ', ') AS tags
    FROM facility f
    JOIN location l ON f.location_id = l.id
    LEFT JOIN facilitytag ft ON f.id = ft.facility_id
    LEFT JOIN tag t ON ft.tag_id = t.id
    WHERE f.id = p_facility_id
    GROUP BY f.id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `facility`
--

CREATE TABLE `facility` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `creation_date` date NOT NULL,
  `location_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `facility`:
--   `location_id`
--       `location` -> `id`
--

--
-- Dumping data for table `facility`
--

INSERT INTO `facility` (`id`, `name`, `creation_date`, `location_id`) VALUES
(1, 'New Facility Name', '2023-11-01', 2),
(3, 'Newww Facilitwy Name', '2023-11-01', 10),
(4, 'Skytree Workspace', '2023-04-05', 4),
(6, 'Brandenburg Gate Office', '2023-06-18', 6),
(7, 'CN Tower Facility', '2023-07-22', 7),
(8, 'Burj Khalifa Center', '2023-08-30', 8),
(9, 'Marina Bay Office', '2023-09-14', 9),
(10, 'Kremlin Workspace', '2023-10-25', 10),
(11, 'Copacabana Beach Hub', '2023-11-05', 11),
(12, 'Rijksmuseum Center', '2023-12-12', 12),
(13, 'Gateway of India Office', '2024-01-18', 13),
(14, 'Table Mountain Facility', '2024-02-22', 14),
(15, 'Gangnam District Hub', '2024-03-30', 15),
(16, 'Example Facility', '2023-10-01', 16),
(17, 'Test Facility', '2024-03-20', 17),
(18, 'Test Facility', '2024-03-20', 18),
(19, 'Test Facility', '2024-03-20', 19),
(20, 'Test Facility', '2024-03-20', 20),
(21, 'Test Facility', '2024-03-20', 21),
(22, 'Test Facility', '2024-03-20', 22),
(23, 'Test Facility', '2024-03-20', 23),
(24, 'Test Facility', '2024-03-20', 24),
(25, 'Test Facility1', '2024-03-20', 25),
(26, 'Test Facility2', '2024-03-20', 26),
(27, 'Test Facility5', '2024-03-20', 27),
(28, 'Test Facilityd', '2024-03-20', 28),
(29, 'cruyff arena', '2024-03-20', 29),
(31, 'thuishaven', '2024-03-20', 31),
(32, 'thuisaven', '2024-03-20', 32),
(33, 'thuisven', '2024-03-20', 33),
(34, 'thusven', '2024-03-20', 34);

-- --------------------------------------------------------

--
-- Table structure for table `facilitytag`
--

CREATE TABLE `facilitytag` (
  `facility_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `facilitytag`:
--   `facility_id`
--       `facility` -> `id`
--   `tag_id`
--       `tag` -> `id`
--

--
-- Dumping data for table `facilitytag`
--

INSERT INTO `facilitytag` (`facility_id`, `tag_id`) VALUES
(3, 28),
(3, 29),
(31, 12),
(31, 17),
(31, 18),
(32, 12),
(32, 17),
(32, 18),
(33, 12),
(33, 17),
(33, 18),
(34, 12),
(34, 17),
(34, 18);

-- --------------------------------------------------------

--
-- Table structure for table `location`
--

CREATE TABLE `location` (
  `id` int(11) NOT NULL,
  `city` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `zip_code` varchar(20) NOT NULL,
  `country_code` char(2) NOT NULL,
  `phone_number` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `location`:
--

--
-- Dumping data for table `location`
--

INSERT INTO `location` (`id`, `city`, `address`, `zip_code`, `country_code`, `phone_number`) VALUES
(1, 'New York', '123 Broadway', '10001', 'US', '+1-212-555-1234'),
(2, 'London', '45 Oxford Street', 'W1D 1BS', 'GB', '+44-20-7123-4567'),
(3, 'Paris', '78 Avenue des Champs-Élysées', '75008', 'FR', '+33-1-2345-6789'),
(4, 'Tokyo', '1-1-2 Oshiage, Sumida', '131-8634', 'JP', '+81-3-1234-5678'),
(5, 'Sydney', '32 George Street', '2000', 'AU', '+61-2-8765-4321'),
(6, 'Berlin', '123 Unter den Linden', '10117', 'DE', '+49-30-1234-5678'),
(7, 'Toronto', '290 Yonge Street', 'M5B 2C3', 'CA', '+1-416-555-7890'),
(8, 'Dubai', '1 Sheikh Mohammed bin Rashid Blvd', '00000', 'AE', '+971-4-123-4567'),
(9, 'Singapore', '10 Bayfront Avenue', '018956', 'SG', '+65-6123-4567'),
(10, 'Moscow', '3 Red Square', '109012', 'RU', '+7-495-123-4567'),
(11, 'Rio de Janeiro', '1 Avenida Atlântica', '22010-000', 'BR', '+55-21-3123-4567'),
(12, 'Amsterdam', '78 Dam Square', '1012 NP', 'NL', '+31-20-123-4567'),
(13, 'Mumbai', '24 Nariman Point', '400021', 'IN', '+91-22-6123-4567'),
(14, 'Cape Town', '19 Long Street', '8001', 'ZA', '+27-21-123-4567'),
(15, 'Seoul', '300 Olympic-ro', '05540', 'KR', '+82-2-1234-5678'),
(16, 'Example City', '123 Example Street', '12345', 'EX', '123-456-7890'),
(17, 'Amsterdam', '123 Test Street', '1234 AB', 'NL', '+31612345678'),
(18, 'Amsterdam', '123 Test Street', '1234 AB', 'NL', '+31612345678'),
(19, 'Amsterdam', '123 Test Street', '1234 AB', 'NL', '+31612345678'),
(20, 'Amsterdam', '123 Test Street', '1234 AB', 'NL', '+31612345678'),
(21, 'Amsterdam', '123 Test Street', '1234 AB', 'NL', '+31612345678'),
(22, 'Amsterdam', '123 Test Street', '1234 AB', 'NL', '+31612345678'),
(23, 'Amsterdam', '123 Test Street', '1234 AB', 'NL', '+31612345678'),
(24, 'Amsterdam', '123 Test Street', '1234 AB', 'NL', '+31612345678'),
(25, 'Amsterdam', '123 Test Street', '1234 AB', 'NL', '+31612345678'),
(26, 'Amsterdam', '123 Test Street', '1234 AB', 'NL', '+31612345678'),
(27, 'Amsterdam', '123 Test Street', '1234 AB', 'NL', '+31612345678'),
(28, 'Amsterdam', '123 Test Street', '1234 AB', 'NL', '+31612345678'),
(29, 'Amsterdam', 'Schipol', '1234 AB', 'NL', '+31612345678'),
(30, 'Amsterdam', 'Schipol', '1234 AB', 'NL', '+31612345678'),
(31, 'Amsterdam', 'Schipol', '1234 AB', 'NL', '+31612345678'),
(32, 'Amsterdam', 'Schipol', '1234 AB', 'NL', '+31612345678'),
(33, 'Amsterdam', 'Schipol', '1234 AB', 'NL', '+31612345678'),
(34, 'Amsterdam', 'Schipol', '1234 AB', 'NL', '+31612345678');

-- --------------------------------------------------------

--
-- Table structure for table `tag`
--

CREATE TABLE `tag` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `tag`:
--

--
-- Dumping data for table `tag`
--

INSERT INTO `tag` (`id`, `name`) VALUES
(4, 'Conference Center'),
(2, 'Coworking'),
(8, 'Creative Studio'),
(7, 'Data Center'),
(17, 'delivery'),
(15, 'Educational Center'),
(13, 'Gym'),
(10, 'Manufacturing'),
(14, 'Medical Facility'),
(3, 'Meeting Room'),
(1, 'Office'),
(6, 'Research Lab'),
(12, 'Restaurant'),
(11, 'Retail Space'),
(28, 'tag1'),
(29, 'tag2'),
(5, 'Training Facility'),
(18, 'vegetarian'),
(9, 'Warehouse');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `facility`
--
ALTER TABLE `facility`
  ADD PRIMARY KEY (`id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `facilitytag`
--
ALTER TABLE `facilitytag`
  ADD PRIMARY KEY (`facility_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `location`
--
ALTER TABLE `location`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tag`
--
ALTER TABLE `tag`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `facility`
--
ALTER TABLE `facility`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `location`
--
ALTER TABLE `location`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `tag`
--
ALTER TABLE `tag`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `facility`
--
ALTER TABLE `facility`
  ADD CONSTRAINT `facility_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `location` (`id`);

--
-- Constraints for table `facilitytag`
--
ALTER TABLE `facilitytag`
  ADD CONSTRAINT `facilitytag_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facility` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `facilitytag_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
