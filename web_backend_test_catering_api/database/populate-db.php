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

// Populate Location table
$locations = [
    ['New York', '123 Broadway', '10001', 'US', '+1-212-555-1234'],
    ['London', '45 Oxford Street', 'W1D 1BS', 'GB', '+44-20-7123-4567'],
    ['Paris', '78 Avenue des Champs-Élysées', '75008', 'FR', '+33-1-2345-6789'],
    ['Tokyo', '1-1-2 Oshiage, Sumida', '131-8634', 'JP', '+81-3-1234-5678'],
    ['Sydney', '32 George Street', '2000', 'AU', '+61-2-8765-4321'],
    ['Berlin', '123 Unter den Linden', '10117', 'DE', '+49-30-1234-5678'],
    ['Toronto', '290 Yonge Street', 'M5B 2C3', 'CA', '+1-416-555-7890'],
    ['Dubai', '1 Sheikh Mohammed bin Rashid Blvd', '00000', 'AE', '+971-4-123-4567'],
    ['Singapore', '10 Bayfront Avenue', '018956', 'SG', '+65-6123-4567'],
    ['Moscow', '3 Red Square', '109012', 'RU', '+7-495-123-4567'],
    ['Rio de Janeiro', '1 Avenida Atlântica', '22010-000', 'BR', '+55-21-3123-4567'],
    ['Amsterdam', '78 Dam Square', '1012 NP', 'NL', '+31-20-123-4567'],
    ['Mumbai', '24 Nariman Point', '400021', 'IN', '+91-22-6123-4567'],
    ['Cape Town', '19 Long Street', '8001', 'ZA', '+27-21-123-4567'],
    ['Seoul', '300 Olympic-ro', '05540', 'KR', '+82-2-1234-5678']
];

$stmt = $conn->prepare("INSERT INTO Location (city, address, zip_code, country_code, phone_number) VALUES (?, ?, ?, ?, ?)");

foreach ($locations as $location) {
    $stmt->bind_param("sssss", $location[0], $location[1], $location[2], $location[3], $location[4]);
    $stmt->execute();
}

echo "Locations inserted successfully<br>";

// Populate Facility table
$facilities = [
    ['Central Park Office', '2023-01-15', 1],
    ['Piccadilly Circus Hub', '2023-02-20', 2],
    ['Eiffel Tower Center', '2023-03-10', 3],
    ['Skytree Workspace', '2023-04-05', 4],
    ['Harbor Bridge Complex', '2023-05-12', 5],
    ['Brandenburg Gate Office', '2023-06-18', 6],
    ['CN Tower Facility', '2023-07-22', 7],
    ['Burj Khalifa Center', '2023-08-30', 8],
    ['Marina Bay Office', '2023-09-14', 9],
    ['Kremlin Workspace', '2023-10-25', 10],
    ['Copacabana Beach Hub', '2023-11-05', 11],
    ['Rijksmuseum Center', '2023-12-12', 12],
    ['Gateway of India Office', '2024-01-18', 13],
    ['Table Mountain Facility', '2024-02-22', 14],
    ['Gangnam District Hub', '2024-03-30', 15]
];

$stmt = $conn->prepare("INSERT INTO Facility (name, creation_date, location_id) VALUES (?, ?, ?)");

foreach ($facilities as $facility) {
    $stmt->bind_param("ssi", $facility[0], $facility[1], $facility[2]);
    $stmt->execute();
}

echo "Facilities inserted successfully<br>";

// Populate Tag table
$tags = [
    'Office', 'Coworking', 'Meeting Room', 'Conference Center', 'Training Facility',
    'Research Lab', 'Data Center', 'Creative Studio', 'Warehouse', 'Manufacturing',
    'Retail Space', 'Restaurant', 'Gym', 'Medical Facility', 'Educational Center'
];

$stmt = $conn->prepare("INSERT INTO Tag (name) VALUES (?)");

foreach ($tags as $tag) {
    $stmt->bind_param("s", $tag);
    $stmt->execute();
}

echo "Tags inserted successfully<br>";

// Populate FacilityTag table
for ($i = 1; $i <= 15; $i++) {
    $numTags = rand(1, 3);  // Each facility gets 1 to 3 tags
    $usedTags = [];

    for ($j = 0; $j < $numTags; $j++) {
        do {
            $tagId = rand(1, 15);
        } while (in_array($tagId, $usedTags));

        $usedTags[] = $tagId;

        $stmt = $conn->prepare("INSERT INTO FacilityTag (facility_id, tag_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $i, $tagId);
        $stmt->execute();
    }
}

echo "FacilityTags inserted successfully<br>";

$conn->close();

?>
