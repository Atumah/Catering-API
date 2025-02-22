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
                return (new Status\BadRequest(['error' => 'All fields are required']))->send();
            }

            $queryCheck = "CALL GetFacilities()";
            $this->db->executeQuery($queryCheck);
            $facilities = $this->db->getStatement()->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($facilities as $facility) {
                if (strcasecmp($facility['facility_name'], $data['facility_name']) === 0) {
                    return (new Status\Conflict(['error' => 'The facility you are trying to add already exists']))->send();
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

            $this->db->executeQuery($query, $params);


            return (new Status\Created(['message' => 'Facility created successfully']))->send();
        } catch (\Exception $e) {
            return (new Status\InternalServerError(['error' => $e->getMessage()]))->send();
        }
    }




    public function getFacility($id) {
        try {
            if (empty($id) || !is_numeric($id)) {
                throw new Exceptions\BadRequest(['error' => 'Invalid Facility ID']);
            }
            $id = (int)$id;

            $query = "CALL GetFacility(:facility_id)";
            $params = [':facility_id' => $id];
            $this->db->executeQuery($query, $params);

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
            $errorMessage = $e->getMessage();
            if ($e instanceof Exceptions\BadRequest || $e instanceof Exceptions\NotFound) {
                $statusCode = ($e instanceof Exceptions\BadRequest) ? Status\BadRequest::class : Status\NotFound::class;
                return (new $statusCode(['error' => $errorMessage]))->send();
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
            $errorMessage = $e->getMessage();
            if ($e instanceof Exceptions\NotFound) {
                return (new Status\NotFound(['error' => $errorMessage]))->send();
            }
            return (new Status\InternalServerError(['error' => 'An unexpected error occurred']))->send();
        }
    }

    public function updateFacility($id)
    {
        try {
            if (empty($id) || !is_numeric($id)) {
                throw new Exceptions\BadRequest(['error' => 'Invalid Facility ID']);
            }
            $id = (int)$id;

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['facility_name'], $data['facility_creation_date'], $data['location_id'], $data['tags'])) {
                throw new Exceptions\BadRequest(['error' => 'All fields are required']);
            }

            $tags = is_array($data['tags']) ? implode(',', $data['tags']) : $data['tags'];

            $params = [
                ':facility_id' => (int)$id,
                ':facility_name' => $data['facility_name'],
                ':facility_creation_date' => $data['facility_creation_date'],
                ':location_id' => (int)$data['location_id'],
                ':tags' => $tags
            ];

            $query = "CALL UpdateFacility(:facility_id, :facility_name, :facility_creation_date, :location_id, :tags)";
            $success = $this->db->executeQuery($query, $params);
            if (!$success) {
                $error = $this->db->getLastErrorMessage();
                throw new Exceptions\InternalServerError(['error' => 'Failed to update facility: ' . $error]);
            }

            $result = $this->db->getStatement()->fetch(\PDO::FETCH_ASSOC);
            if (!$result) {
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
            if (empty($id) || !is_numeric($id)) {
                throw new Exceptions\BadRequest(['error' => 'Invalid Facility ID']);
            }
            $id = (int)$id;

            $query = "CALL DeleteFacility(:facility_id)";
            $params = [':facility_id' => $id];

            $this->db->executeQuery($query, $params);

            $rowCount = $this->db->getStatement()->rowCount();
            if ($rowCount === 0) {
                throw new Exceptions\NotFound(['error' => 'Facility not found']);
            }

            return (new Status\NoContent())->send();
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