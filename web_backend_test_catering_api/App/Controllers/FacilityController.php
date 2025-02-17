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
            if (empty($data['facility_name']) || empty($data['facility_creation_date'])
                || !isset($data['location_id']) || empty($data['tags'])) {
                throw new Exceptions\BadRequest(['error' => 'All fields are required']);
            }

            $tags = implode(',', $data['tags']);

            $query = "CALL UpdateFacility(:facility_id, :facility_name, :facility_creation_date, :location_id, :tags)";
            $params = [
                ':facility_id' => $id,
                ':facility_name' => $data['facility_name'],
                ':facility_creation_date' => $data['facility_creation_date'],
                ':location_id' => $data['location_id'],
                ':tags' => $tags
            ];

            $success = $this->db->executeQuery($query, $params);
            if (!$success) {
                throw new Exceptions\NotFound(['error' => 'Facility not found']);
            }

            return (new Status\OK(['message' => 'Facility updated successfully']))->send();
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