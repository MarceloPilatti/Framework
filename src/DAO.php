<?php

namespace Framework;

use PDO;

abstract class DAO
{
    public $tableName;
    public $dBConnection;
    public $entity;

    public function __construct()
    {
        $this->dBConnection = ConnectionFactory::getFactory()->getConnection();
    }

    public function insert($entity)
    {
        $stmt = $this->getInsertSQL($entity);
        return $this->executeSql($stmt, true);
    }

    public function update($options)
    {
        $tableName=$options['tableName']??$this->tableName;
        $set=$options['set']??null;
        $where=$options['where']??null;
        $entity=$options['entity']??null;

        $columnNames=null;
        $sql = "UPDATE " . $tableName . " SET ";
        if($entity){
            $columnNames = $this->toSnakeCases();
            $params = $entity->getAttrs();
            $idAttrVal = reset($params);
            array_shift($params);
            $params["ID"] = $idAttrVal;
            array_shift($columnNames);
            $whereSql='ID = ?';
        }else{
            $whereSql = '';
            $columnNames = array_keys($set);
            $params = array_values($set);
            foreach ($where as $columnName => $columnValue){
                if(is_array($columnValue)) {
                    $columnValueCount = count($columnValue);
                    $whereSql .= SQL::toSnakeCase($columnName)." IN (";
                    foreach ($columnValue as $count=>$value){
                        array_push($params, $value);
                        if ($count == $columnValueCount - 1) {
                            $whereSql .= "?";
                            continue;
                        }
                        $whereSql .= "?,";
                    }
                    $whereSql .= ")";
                } else if (!is_numeric($columnName)) {
                    $columnName = SQL::toSnakeCase($columnName);
                    $whereSql .= $columnName . '=?';
                    array_push($params, $columnValue);
                } else {
                    $whereSql .= ' ' . $columnValue . ' ';
                }
            }
        }

        foreach ($columnNames as $count => $columnName) {
            $columnName = SQL::toSnakeCase($columnName);
            $sql .= $columnName . " = ?";
            if (count($columnNames) !== $count + 1) {
                $sql .= ",";
            }
        }
        $sql .= ' WHERE '. $whereSql;

        $stmt = $this->dBConnection->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt = $this->setParams($params, $stmt);
        return $this->executeSql($stmt, false);
    }

    public function delete($where)
    {
        try {
            $tableName = $this->tableName;
            $params = [];
            $sql = "DELETE FROM ".$tableName." WHERE ";
            if(!$where){
                $sql.='ID>0';
            }
            foreach ($where as $columnName => $columnValue) {
                if(is_array($columnValue)) {
                    $columnValueCount = count($columnValue);
                    $sql .= SQL::toSnakeCase($columnName)." IN (";
                    foreach ($columnValue as $count=>$value){
                        array_push($params, $value);
                        if ($count == $columnValueCount - 1) {
                            $sql .= "?";
                            continue;
                        }
                        $sql .= "?,";
                    }
                    $sql .= ")";
                } else if (!is_numeric($columnName)) {
                    $columnName = SQL::toSnakeCase($columnName);
                    $sql .= $columnName . '=?';
                    array_push($params, $columnValue);
                } else {
                    $sql .= ' ' . $columnValue . ' ';
                }
            }
            $stmt = $this->dBConnection->prepare($sql);
            if (!$stmt) {
                return false;
            }
            $stmt = $this->setParams($params, $stmt);
            return $this->executeSql($stmt, false);
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao remover registros do banco: " . $t->getMessage());
            return null;
        }
    }

    public function get($options=[])
    {
        try {
            $select=$options['select']??"*";
            $from=isset($options['from'])?SQL::toSnakeCase($options['from']):$this->tableName;
            $where=$options['where']??null;
            $orderBy = $options['orderBy']??null;
            $limit = $options['limit']??null;
            $offset = $options['offset']??null;
            $onlyId = $options['onlyId']??false;
            $onlyOne = $options['onlyOne']??false;
            $isEntity = $options['isEntity']??true;
            $this->entity = $options['entity']??$this->entity;

            $tableName = $this->tableName;
            $entity = $this->entity;
            $params = [];
            $isCount=false;
            if (strpos(strtoupper($select), 'COUNT') !== false) {
                $selectTemp=str_replace(['COUNT',' ','(',')'], '', $select);
                $isCount=true;
                $select='COUNT('.SQL::toSnakeCase($selectTemp).')';
            }else if (strpos(strtoupper($select), 'DISTINCT') !== false) {
                $selectTemp=str_replace(['DISTINCT',' ','(',')'], '', $select);
                $isCount=true;
                $select='DISTINCT('.SQL::toSnakeCase($selectTemp).')';
            }else{
                $select=SQL::toSnakeCase($select);
            }
            $sql = 'SELECT ' . $select ." FROM " . $from;
            if($where) {
                foreach ($where as $columnName => $columnValue) {
                    if ($columnValue === reset($where)) {
                        $sql .= " WHERE ";
                    }
                    if (!is_numeric($columnName)) {
                        $columnName = SQL::toSnakeCase($columnName);
                        $sql .= $columnName . '=?';
                        array_push($params, $columnValue);
                    } else {
                        $sql .= ' ' . SQL::toSnakeCase($columnValue). ' ';
                    }
                }
            }
            if ($orderBy) {
                $sql .= ' ORDER BY ';
                foreach ($orderBy as $key => $value) {
                    $columnName = SQL::toSnakeCase($key);
                    $sql .= $columnName . ' ' . $value;
                }
            }
            if ($offset || $limit) {
                $sql .= " LIMIT " . $limit . " OFFSET " . $offset;
            }
            $stmt = $this->dBConnection->prepare($sql);
            if (!$stmt) {
                return false;
            }
            $stmt = $this->setParams($params, $stmt);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                return null;
            }
            $stmt->closeCursor();
            if($onlyId){
                $rows = array_column($rows, $select);
                return $rows;
            }
            if ($isCount) {
                return reset($rows[0]);
            }
            $justId = false;
            if (strpos($select, "_ID") !== false) {
                $newTableName=str_replace(['DISTINCT',' ','(',')', '_ID'], '', $select);
                $newEntity = 'Main\\Entity\\' . ucfirst(strtolower($newTableName));
                $this->entity = $newEntity;
                $this->tableName = $newTableName;
                $justId = true;
            }
            $rows = $this->getData($rows, $isEntity, $justId);
            if ($onlyOne) {
                $rows = reset($rows);
            }
            $this->entity = $entity;
            $this->tableName = $tableName;
            return $rows;
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao recuperar registros do banco: " . $t->getMessage());
            return null;
        }
    }

    public function deleteById($ids){
        try{
            return $this->delete([
                'id'=>$ids
            ]);
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao remover registros do banco: " . $t->getMessage());
            return null;
        }
    }

    public function getById($id, $isEntity = true)
    {
        try {
            return $this->get(
                [
                    'where'=> [
                        'id' => $id
                    ],
                    'onlyOne'=>true,
                    'isEntity'=>$isEntity
                ]
            );
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao recuperar 1 registro do banco: " . $t->getMessage());
            return null;
        }
    }

    public function listAll($options=[])
    {
        try {
            $limit = $options['limit']??null;
            $offset = $options['offset']??null;
            $orderBy = $options['orderBy']??null;
            $isEntity = $options['isEntity']??true;
            $onlyCount = $options['onlyCount']??false;
            $select=null;
            if($onlyCount){
                $select="COUNT(*)";
            }
            return $this->get(
                [
                    'select'=>$select,
                    'limit'=>$limit,
                    'offset'=>$offset,
                    'orderBy'=>$orderBy,
                    'isEntity'=>$isEntity,
                ]
            );
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao recuperar todos os registros do banco: " . $t->getMessage());
            return null;
        }
    }

    public function getData($rows, $returnEntity = true, $justId = false)
    {
        if ($returnEntity) {
            $data = $this->getEntities($rows, $justId);
        } else {
            $data = $this->getArray($rows, $justId);
        }
        return $data;
    }

    public function getEntities($rows, $justId = false)
    {
        try {
            $entities = [];
            if ($rows) {
                foreach ($rows as $row) {
                    if ($justId) {
                        $id = reset($row);
                        $entity = $this->getById($id);
                    } else {
                        $entity = new $this->entity();
                    }
                    $reflectionObject = new \ReflectionObject($entity);
                    $objectProperties = $reflectionObject->getProperties();
                    $count = 0;
                    foreach ($row as $key=>$cell) {
                        $objectProperties[$count]->setAccessible(true);
                        $objectProperties[$count]->setValue($entity, $cell);
                        $count++;
                    }
                    array_push($entities, $entity);
                }
            }
            return $entities;
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao listar as entidades: " . $t->getMessage());
            return null;
        }
    }

    public function getArray($rows, $justId = false)
    {
        try {
            if (!$rows) {
                return null;
            }
            $entityList = [];
            foreach ($rows as $count => $row) {
                if ($justId) {
                    $id = reset($row);
                    $row = $this->getById($id, false);
                }
                $entityAttrs = [];
                foreach ($row as $key => $data) {
                    $dataHtml = html_entity_decode($data);
                    $name = lcfirst(str_replace('_', '', ucwords(strtolower($key), '_')));
                    $format = 'Y-m-d H:i:s';
                    $d = \DateTime::createFromFormat($format, $data);
                    $isFk = substr($name, -2) == 'Id';
                    if ($isFk) {
                        $nameTranslated=$this->entity::translates($name);
                        if($nameTranslated){
                            $name=$nameTranslated;
                        }
                        $name = substr($name, 0, strpos($name, 'Id'));
                        $tableName = $name;
                        if (strpos($name, 'parent') !== false) {
                            if(!$data || $data==$row['ID']){
                                $entityAttrs[$name] = $data;
                                continue;
                            }
                            $tableName = strtolower(substr($name, 6));
                        }
                        $tableNameTemp = $this->tableName;
                        $entityTemp = $this->entity;
                        $this->entity = 'Main\\Entity\\' . ucfirst($tableName);
                        $this->tableName = SQL::toSnakeCase($tableName);
                        $value = $this->getById($data, false);
                        $this->tableName = $tableNameTemp;
                        $this->entity = $entityTemp;
                    } else {
                        if ($d && $d->format($format) === $data) {
                            $value = $d->format('d/m/Y H:i:s');
                        } else if ($data != $dataHtml) {
                            $value = $dataHtml;
                        } else if (is_numeric($data) && is_float($data + 0)) {
                            $value = number_format($data, 2, ',', '.');
                        } else {
                            $value = $data;
                        }
                    }
                    $entityAttrs[$name] = $value;
                }
                $entityList[$count] = $entityAttrs;
            }
            return $entityList;
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao listar as entidades: " . $t->getMessage());
            return null;
        }
    }

    public function executeSql($stmt, $insert)
    {
        try {
            $result = $stmt->execute();
            if ($result) {
                if ($insert) {
                    $stmt->closeCursor();
                    $id = $this->dBConnection->lastInsertId();
                    if ($id) {
                        $entity = $this->getById($id);
                        return $entity;
                    } else {
                        return true;
                    }
                }
                return true;
            }
            return false;
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao executar a sql: " . $t->getMessage());
            return false;
        }
    }

    public function getInsertSQL($entity)
    {
        $columnNames = $this->toSnakeCases();
        array_shift($columnNames);
        if (is_array($entity)) {
            $sql = $this->makeInsertAllSql($columnNames, $entity);
            $objectArray = [];
            foreach ($entity as $e) {
                $values = $e->getAttrs();
                array_shift($values);
                foreach ($values as $v) {
                    array_push($objectArray, $v);
                }
            }
        } else {
            $objectArray = $entity->getAttrs();
            array_shift($objectArray);
            $sql = $this->makeInsertSql($columnNames);
        }
        $stmt = $this->dBConnection->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt = $this->setParams($objectArray, $stmt);
        return $stmt;
    }

    public function makeInsertAllSql($columnNames, $entities)
    {
        $values = "";
        $tableName = $this->tableName;
        $sql = "INSERT INTO " . $tableName . " (";
        $entities = array_keys($entities);
        foreach ($entities as $key) {
            foreach ($columnNames as $count => $columnName) {
                if ($key == 0) {
                    $sql .= $columnName;
                    if (count($columnNames) !== $count + 1) {
                        $sql .= ",";
                    }
                }
                if ($key == 0 && $count == 0) {
                    $values .= ") VALUES(";
                }
                $values .= "?";
                if (count($columnNames) !== $count + 1) {
                    $values .= ", ";
                } else {
                    if (count($entities) !== $key + 1) {
                        $values .= "),(";
                    } else {
                        $values .= ")";
                    }
                }
            }
        }
        $sql .= $values . ";";
        return $sql;
    }

    public function makeInsertSql($columnNames)
    {
        $values = "";
        $tableName = $this->tableName;
        $sql = "INSERT INTO " . $tableName . " (";
        foreach ($columnNames as $count => $columnName) {
            $sql .= $columnName;
            $values .= "?";
            if (count($columnNames) !== $count + 1) {
                $sql .= ",";
                $values .= ", ";
            }
        }
        $sql .= ") VALUES (" . $values . ");";
        return $sql;
    }

    public function toSnakeCases()
    {
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='" . Config::getDBName('main') . "' AND TABLE_NAME = '$this->tableName'";
        $stmt = $this->dBConnection->prepare($sql);
        try {
            $columnNames = [];
            $result = $stmt->execute();
            if ($result) {
                $rawColumnData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rawColumnData as $array) {
                    foreach ($array as $inner_key => $value) {
                        if (!(int)$inner_key) {
                            $columnNames[] = $value;
                        }
                    }
                }
            }
            return $columnNames;
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao recuperar os nomes das tabelas do banco: " . $t->getMessage());
            return false;
        }
    }

    public function setParams($objectArray, $stmt)
    {
        $count = 1;
        if (!is_array($objectArray)) {
            $objectArray = [
                $objectArray
            ];
        }
        foreach ($objectArray as $value) {
            $type = $this->getColumnType($value);
            $stmt->bindValue($count, $value, $type);
            $count++;
        }
        return $stmt;
    }

    public function getColumnType($value)
    {
        $type = PDO::PARAM_STR;
        if (is_int($value)) {
            $type = PDO::PARAM_INT;
        } else if ($value === 'NULL') {
            $type = PDO::PARAM_NULL;
        } else if (is_string($value)) {
            $type = PDO::PARAM_STR;
        } else if (is_null($value)) {
            $type = PDO::PARAM_NULL;
        }
        return $type;
    }

    public function begin()
    {
        return $this->dBConnection->beginTransaction();
    }

    public function commit()
    {
        return $this->dBConnection->commit();
    }

    public function rollback()
    {
        return $this->dBConnection->rollBack();
    }

    public function inTransaction()
    {
        return $this->dBConnection->inTransaction();
    }

    public function __get($name)
    {
        return $this->$name;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }
}