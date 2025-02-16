<?php

namespace App\Controllers;

use App\Plugins\Http\Response as Status;
use App\Plugins\Http\Exceptions;

class FacilityController extends BaseController
{
    public function createFacility()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['facility_name']) || empty($data['facility_creation_date']) ||
                empty($data['location_city']) || empty($data['location_address']) ||
                empty($data['location_zip_code']) || empty($data['location_country_code']) ||
                empty($data['location_phone_number']) || empty($data['tags'])) {
                throw new Exceptions\BadRequest(['error' => 'All fields are required']);
            }

            $tags = implode(',', $data['tags']);

            $query = "CALL CreateFacility(:facility_name, :facility_creation_date, :location_city, :location_address, :location_zip_code, :location_country_code, :location_phone_number, :tags)";
            $params = [
                ':facility_name' => $data['facility_name'],
                ':facility_creation_date' => $data['facility_creation_date'],
                ':location_city' => $data['location_city'],
                ':location_address' => $data['location_address'],
                ':location_zip_code' => $data['location_zip_code'],
                ':location_country_code' => $data['location_country_code'],
                ':location_phone_number' => $data['location_phone_number'],
                ':tags' => $tags
            ];

            $result = $this->db->executeQuery($query, $params);

            if ($result === false) {
                $errorCode = $this->db->getLastErrorCode();
                $errorMessage = $this->db->getLastErrorMessage();

                switch ($errorCode) {
                    case 1062:
                        throw new Exceptions\Conflict(['error' => 'A record with similar details already exists']);
                    case 1048:
                        throw new Exceptions\BadRequest(['error' => 'Missing required fields']);
                    case 1452:
                        throw new Exceptions\BadRequest(['error' => 'Invalid location or foreign key constraint violation']);
                    default:
                        throw new Exceptions\InternalServerError(['error' => $errorMessage]);
                }
            }

            return (new Status\Created(['message' => 'Facility created successfully']))->send();
        } catch (\Exception $e) {
            if ($e instanceof Exceptions\BadRequest) {
                return (new Status\BadRequest(['error' => $e->getMessage()]))->send();
            } elseif ($e instanceof Exceptions\Conflict) {
                return (new Status\Conflict(['error' => $e->getMessage()]))->send();
            } elseif ($e instanceof Exceptions\InternalServerError) {
                return (new Status\InternalServerError(['error' => $e->getMessage()]))->send();
            } else {
                return (new Status\InternalServerError(['error' => 'An unexpected error occurred']))->send();
            }
        }
    }

    public function getFacility($facilityId)
    {
        try {
            if (!is_numeric($facilityId)) {
                throw new Exceptions\BadRequest(['error' => 'Invalid facility ID']);
            }

            $query = "CALL GetFacility(:facility_id)";
            $params = [':facility_id' => (int)$facilityId];

            $result = $this->db->executeQuery($query, $params);

            // Add debug logging
            error_log("Query result: " . print_r($result, true));

            if ($result === false) {
                throw new Exceptions\InternalServerError(['error' => 'Database query failed']);
            }

            if (empty($result)) {
                throw new Exceptions\NotFound(['error' => 'Facility not found']);
            }

            $facility = $result[0];

            // Handle tags
            $facility['tags'] = !empty($facility['tags']) ?
                array_map('trim', explode(',', $facility['tags'])) : [];

            return (new Status\OK(['data' => $facility]))->send();

        } catch (\Exception $e) {
            if ($e instanceof Exceptions\NotFound) {
                return (new Status\NotFound(['error' => $e->getMessage()]))->send();
            }
            if ($e instanceof Exceptions\BadRequest) {
                return (new Status\BadRequest(['error' => $e->getMessage()]))->send();
            }
            return (new Status\InternalServerError(['error' => $e->getMessage()]))->send();
        }
    }

}