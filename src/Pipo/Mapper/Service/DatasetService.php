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
     * Fetch csvs with dataset
     * @param $id
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getCsvs($id)
    {
        $stmt = $this->db->executeQuery('
          SELECT c.*, d.use_csv_id FROM csvfiles c
          INNER JOIN datasets d ON d.id = c.dataset_id
          WHERE dataset_id = :setid ORDER BY created_on DESC
          ', array(
            'setid' => (string)$id
        ));
        return $stmt->fetchAll();
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

    /**
     * Save the provided mapping or update if it already exists
     * Also update the status of the dataset to mapped
     *
     * @param array $data
     * @return int
     */
    public function storeDescription($data)
    { 
        //die(print_r($data));
        return $this->db->update('datasets', $data, array(
            'id' => $data['id']
        ));
    }

    /**
     * Fetch a csv by id
     *
     * @param $id
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getCsv($id)
    {
        $stmt = $this->db->executeQuery('SELECT * FROM csvfiles WHERE id = :id', array(
            'id' => (string)$id
        ));
        return $stmt->fetch();
    }

    /**
     * Delete a Csv adn remove the file
     *
     * @param $id
     * @return int
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     */
    public function deleteCsv($id, $uploadDir)
    {
        $csv = $this->getCsv($id);
        $file = $uploadDir . DIRECTORY_SEPARATOR . $csv['filename'];
        unlink($file);

        return $this->db->delete('csvfiles', array('id' => $id));
    }

    /**
     * Set a particular csv to use for the dataset
     *
     * @param $id
     * @param $datasetId
     * @return int
     */
    public function chooseCsv($id, $datasetId)
    {
        return $this->db->update('datasets', array('use_csv_id' => $id), array(
            'id' => $datasetId
        ));
    }


    /**
     * Fetch csvs with dataset
     * @param $id
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getMappings($id)
    {
        $stmt = $this->db->executeQuery('
          SELECT * FROM fieldmappings WHERE dataset_id = :setid', array(
            'setid' => (string)$id
        ));
        return $stmt->fetchAll();
    }



}