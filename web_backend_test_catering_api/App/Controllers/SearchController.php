<?php

namespace App\Controllers;

use App\Plugins\Http\Response as Status;
use App\Plugins\Http\Exceptions;

class SearchController extends BaseController
{
    public function searchFacilities()
    {
        try {
            $facilityName = $_GET['facility_name'] ?? null;
            $tagName = $_GET['tag_name'] ?? null;
            $city = $_GET['city'] ?? null;

            if ($facilityName === null && $tagName === null && $city === null) {
                throw new Exceptions\BadRequest(['error' => 'At least one search parameter is required']);
            }

            // Sanitize inputs to prevent XSS and other attacks
            $facilityName = $this->sanitizeString($facilityName);
            $tagName = $this->sanitizeString($tagName);
            $city = $this->sanitizeString($city);

            $query = "CALL SearchFacilities(:facility_name, :tag_name, :city)";
            $params = [
                ':facility_name' => $facilityName,
                ':tag_name' => $tagName,
                ':city' => $city
            ];

            $success = $this->db->executeQuery($query, $params);
            if (!$success) {
                throw new Exceptions\InternalServerError(['error' => 'Failed to execute search query']);
            }

            $results = $this->db->getStatement()->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($results)) {
                throw new Exceptions\NotFound(['error' => 'No facilities found matching the search criteria']);
            }

            return (new Status\OK(['data' => $results]))->send();

        } catch (\PDOException $e) {
            return (new Status\InternalServerError(['error' => 'Database error: ' . $e->getMessage()]))->send();
        } catch (\Exception $e) {
            if ($e instanceof Exceptions\BadRequest) {
                return (new Status\BadRequest(['error' => $e->getMessage()]))->send();
            } elseif ($e instanceof Exceptions\NotFound) {
                return (new Status\NotFound(['error' => $e->getMessage()]))->send();
            }
            return (new Status\InternalServerError(['error' => 'An unexpected error occurred']))->send();
        }
    }

    private function sanitizeString($input)
    {
        if ($input === null) {
            return null;
        }

        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}
