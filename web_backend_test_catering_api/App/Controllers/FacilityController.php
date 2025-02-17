<?php

namespace App\Controllers;

use App\Plugins\Http\Response as Status;
use App\Plugins\Http\Exceptions;
use App\Plugins\Http\Response\Conflict;  // Make sure to import the Conflict class


class FacilityController extends BaseController
{
// At the top of your controller file

    public function createFacility()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (empty($data['facility_name']) || empty($data['facility_creation_date']) ||
                empty($data['location_city']) || empty($data['location_address']) ||
                empty($data['location_zip_code']) || empty($data['location_country_code']) ||
                empty($data['location_phone_number']) || empty($data['tags'])) {
                return (new Status\BadRequest(['error' => 'All fields are required']))->send();
            }

            // Check for duplicate facility name
            $queryCheck = "CALL GetFacilities()";
            $this->db->executeQuery($queryCheck);
            $facilities = $this->db->getStatement()->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($facilities as $facility) {
                if (strcasecmp($facility['facility_name'], $data['facility_name']) === 0) {
                    return (new Conflict(['error' => 'Facility with the same name already exists']))->send();  // Return Conflict
                }
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

            $this->db->executeQuery($query);

            return (new Status\Created(['message' => 'Facility created successfully']))->send();
        } catch (\PDOException $e) {
            return (new Status\InternalServerError(['error' => 'Database error: ' . $e->getMessage()]))->send();
        } catch (\Exception $e) {
            return (new Status\InternalServerError(['error' => 'An unexpected error occurred']))->send();
        }
    }




    public function getFacility($id) {
    try {
        if (empty($id)) {
            throw new Exceptions\BadRequest(['error' => 'Facility ID is required']);
        }

        $query = "CALL GetFacility(:facility_id)";
        $params = [':facility_id' => $id];

        $success = $this->db->executeQuery($query, $params);
        if (!$success) {
            throw new Exceptions\NotFound(['error' => 'Facility not found']);
        }

        $data = $this->db->getStatement()->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($data)) {
            throw new Exceptions\NotFound(['error' => 'Facility not found']);
        }

        return (new Status\OK(['data' => $data]))->send();
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'SQLSTATE[45000]') !== false) {
            return (new Status\NotFound(['error' => 'Facility not found']))->send();
        }
        return (new Status\InternalServerError(['error' => 'An unexpected error occurred']))->send();
    } catch (\Exception $e) {
        if ($e instanceof Exceptions\BadRequest) {
            return (new Status\BadRequest(['error' => $e->getMessage()]))->send();
        } elseif ($e instanceof Exceptions\NotFound) {
            return (new Status\NotFound(['error' => $e->getMessage()]))->send();
        }
        return (new Status\InternalServerError(['error' => 'An unexpected error occurred']))->send();
    }
}

public function getFacilities() {
    try {
        $query = "CALL GetFacilities()";
        $success = $this->db->executeQuery($query);
        if (!$success) {
            throw new Exceptions\NotFound(['error' => 'Facility not found']);
        }
        $data = $this->db->getStatement()->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($data)) {
            throw new Exceptions\NotFound(['error' => 'No facilities found']);
        }
        return (new Status\OK(['data' => $data]))->send();
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'SQLSTATE[45000]') !== false) {
            return (new Status\NotFound(['error' => 'Facility not found']))->send();
        }
        return (new Status\InternalServerError(['error' => 'An unexpected error occurred']))->send();
    } catch (\Exception $e) {
        if ($e instanceof Exceptions\BadRequest) {
            return (new Status\BadRequest(['error' => $e->getMessage()]))->send();
        } elseif ($e instanceof Exceptions\NotFound) {
            return (new Status\NotFound(['error' => $e->getMessage()]))->send();
        }
        return (new Status\InternalServerError(['error' => 'An unexpected error occurred']))->send();
    }
}

    public function updateFacility($id)
    {
        try {
            if (empty($id)) {
                throw new Exceptions\BadRequest(['error' => 'Facility ID is required']);
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Validate input data
            if (!isset($data['facility_name']) || !isset($data['facility_creation_date'])
                || !isset($data['location_id']) || !isset($data['tags'])) {
                throw new Exceptions\BadRequest(['error' => 'All fields are required']);
            }

            // Convert tags array to comma-separated string
            $tags = is_array($data['tags']) ? implode(',', $data['tags']) : $data['tags'];

            $query = "CALL UpdateFacility(:facility_id, :facility_name, :facility_creation_date, :location_id, :tags)";
            $params = [
                ':facility_id' => (int)$id,
                ':facility_name' => $data['facility_name'],
                ':facility_creation_date' => $data['facility_creation_date'],
                ':location_id' => (int)$data['location_id'],
                ':tags' => $tags
            ];

            $success = $this->db->executeQuery($query, $params);
            if (!$success) {
                $error = $this->db->getLastErrorMessage();
                throw new Exceptions\InternalServerError(['error' => 'Failed to update facility: ' . $error]);
            }

            $result = $this->db->getStatement()->fetch(\PDO::FETCH_ASSOC);

            if ($result === false) {
                throw new Exceptions\NotFound(['error' => 'Facility not found']);
            }

            return (new Status\OK(['message' => 'Facility updated successfully']))->send();

        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'SQLSTATE[45000]') !== false) {
                return (new Status\NotFound(['error' => 'Facility not found']))->send();
            }
            return (new Status\InternalServerError(['error' => 'Database error: ' . $e->getMessage()]))->send();
        } catch (\Exception $e) {
            if ($e instanceof Exceptions\BadRequest) {
                return (new Status\BadRequest(['error' => $e->getMessage()]))->send();
            } elseif ($e instanceof Exceptions\NotFound) {
                return (new Status\NotFound(['error' => $e->getMessage()]))->send();
            }
            return (new Status\InternalServerError(['error' => $e->getMessage()]))->send();
        }
    }

    public function deleteFacility($id)
    {
        try {
            if (empty($id)) {
                throw new Exceptions\BadRequest(['error' => 'Facility ID is required']);
            }

            $query = "CALL DeleteFacility(:facility_id)";
            $params = [':facility_id' => $id];

            $this->db->executeQuery($query, $params);

            // Check if any rows were affected
            $rowCount = $this->db->getStatement()->rowCount();
            if ($rowCount === 0) {
                throw new Exceptions\NotFound(['error' => 'Facility not found']);
            }

            return (new Status\OK(['message' => 'Facility deleted successfully']))->send();
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'SQLSTATE[45000]') !== false) {
                return (new Status\NotFound(['error' => 'Facility not found']))->send();
            }
            return (new Status\InternalServerError(['error' => 'Database error: ' . $e->getMessage()]))->send();
        } catch (\Exception $e) {
            if ($e instanceof Exceptions\BadRequest) {
                return (new Status\BadRequest(['error' => $e->getMessage()]))->send();
            } elseif ($e instanceof Exceptions\NotFound) {
                return (new Status\NotFound(['error' => $e->getMessage()]))->send();
            }
            return (new Status\InternalServerError(['error' => $e->getMessage()]))->send();
        }
    }

}