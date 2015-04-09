<?php

namespace Pipo\Mapper\Service;


/**
 * Service or manager for the Dataset data
 *
 * @package Pipo\Mapper\Service
 */
class DatasetService {

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Fetch a dataset by id
     * @param $id
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getDataset($id)
    {
        $stmt = $this->db->executeQuery('SELECT * FROM datasets WHERE id = :id', array(
            'id' => (string)$id
        ));
        return $stmt->fetch();
    }

    /**
     * Fetch a dataset by id
     * @param $id
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getCsvs($id)
    {
        $stmt = $this->db->executeQuery('SELECT * FROM csvfiles WHERE dataset_id = :id', array(
            'id' => (string)$id
        ));
        return $stmt->fetch();
    }

    /**
     * Gets all datasets
     *
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getAllSets()
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM datasets d");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

}