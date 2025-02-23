<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Models\InventoryLogModel;
use App\Utils\{Route, HttpException};
use App\Middlewares\AuthMiddleware;

class InventoryLog extends Controller {
    protected object $log;

    public function __construct($param) {
        $this->log = new InventoryLogModel();

        parent::__construct($param);
    }

    // ┌────────────────────────────────┐
    // | -------- READ METHODS -------- |
    // └────────────────────────────────┘

    /**
     * Get a specific log by ID.
     *
     * @return array The log data
     * @throws HttpException if log not found
     * @author Mathieu Chauvet
     */
    #[Route("GET", "/inventory-logs/:id", middlewares: [AuthMiddleware::class], allowedRoles:['admin', 'manager'])] 
    public function getLog() {
        try {
            $id = intval($this->params['id']);
            $log = $this->log->get($id);
            
            if (empty($log)) {
                throw new HttpException("Log not found", 404);
            }
            
            return $log;
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }

    /**
     * Get all logs with optional limit.
     * 
     * @param int|null $limit Optional parameter to limit the number of returned logs
     * @return array Array of log records
     * @author Mathieu Chauvet
     */
    #[Route("GET", "/inventory-logs", middlewares: [AuthMiddleware::class], allowedRoles:['admin', 'manager'])]
    public function getLogs() {
        try {
            $filters = [
                'date_from' => $this->query['date_from'] ?? null,
                'date_to' => $this->query['date_to'] ?? null,
                'limit' => $this->query['limit'] ?? null,
                'change_type' => $this->query['change_type'] ?? null,
                'product_id' => $this->query['product_id'] ?? null
            ];
    
            return $this->log->getAll(array_filter($filters));
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), 500);
        }
    }
}