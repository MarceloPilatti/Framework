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

    public function getInsertSQL($entity)
    {
        $columnNames = $this->getColumnNames();
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

    public function getColumnNames()
    {
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='" . Config::getDBName() . "' AND TABLE_NAME = '$this->tableName'";
        $stmt = $this->dBConnection->prepare($sql);
        try {
            $columnNames = [];
            $result = $stmt->execute();
            if ($result) {
                $raw_column_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($raw_column_data as $array) {
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
        } else {
            if ($value === 'NULL') {
                $type = PDO::PARAM_NULL;
            } else {
                if (is_string($value)) {
                    $type = PDO::PARAM_STR;
                } else {
                    if (is_null($value)) {
                        $type = PDO::PARAM_NULL;
                    }
                }
            }
        }
        return $type;
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

    public function getById($id, $returnEntity = true)
    {
        try {
            return $this->getBy(['id' => $id], null, null, null, false, true, $returnEntity);
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao recuperar 1 registro do banco: " . $t->getMessage());
            return null;
        }
    }

    public function getBy($criteria, $orderBy = null, $limit = null, $offset = null, $count = null, $oneRegister = false, $returnEntity = true, $column = '*')
    {
        try {
            $tableName = $this->tableName;
            $entity = $this->entity;
            $params = [];
            $sql = 'SELECT ' . $column;
            if ($count) {
                $sql = 'SELECT COUNT(' . $column . ')';
            }
            $sql .= " FROM $tableName WHERE ";
            foreach ($criteria as $columnName => $columnValue) {
                if (!is_numeric($columnName)) {
                    $columnName = strtoupper(preg_replace('/(?<=\\w)(?=[A-Z])/', "_$1", $columnName));
                    $sql .= $columnName . '=?';
                    array_push($params, $columnValue);
                } else {
                    $sql .= ' ' . $columnValue . ' ';
                }
            }
            if ($orderBy) {
                $sql .= ' ORDER BY ';
                foreach ($orderBy as $key => $value) {
                    $columnName = strtoupper(preg_replace('/(?<=\\w)(?=[A-Z])/', "_$1", $key));
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
            if ($count) {
                return reset($rows[0]);
            }
            $justId = false;
            $isFk = substr($column, -3) == '_ID';
            if ($isFk) {
                $newTableName = substr($column, 0, strpos($column, '_ID'));
                $newEntity = 'Main\\Entity\\' . ucfirst(strtolower($newTableName));
                $this->entity = $newEntity;
                $this->tableName = $newTableName;
                $justId = true;
            }
            $rows = $this->getData($rows, $returnEntity, $justId);
            if ($oneRegister) {
                $rows = reset($rows);
            }
            $this->entity = $entity;
            $this->tableName = $tableName;
            return $rows;
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao recuperar 1 registro do banco: " . $t->getMessage());
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
                    foreach ($row as $cell) {
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
                        $name = substr($name, 0, strpos($name, 'Id'));
                        $tableName = $name;
                        if (strpos($name, 'parent') !== false) {
                            $tableName = strtolower(substr($name, 6));
                        }
                        $tableNameTemp = $this->tableName;
                        $entityTemp = $this->entity;
                        $this->entity = 'Main\\Entity\\' . ucfirst($tableName);
                        $this->tableName = strtoupper(preg_replace('/(?<=\\w)(?=[A-Z])/', "_$1", $tableName));
                        $value = $this->getById($data, true);
                        $this->tableName = $tableNameTemp;
                        $this->entity = $entityTemp;
                        if ($value) {
                            $value = $value->getAttrs();
                        }
                    } else {
                        if ($d && $d->format($format) === $data) {
                            $value = $d->format('d/m/Y H:i:s');
                        } else {
                            if ($data != $dataHtml) {
                                $value = $dataHtml;
                            } else {
                                if (is_numeric($data) && is_float($data + 0)) {
                                    $value = number_format($data, 2, ',', '.');
                                } else {
                                    $value = $data;
                                }
                            }
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

    public function update($entity)
    {
        $stmt = $this->getUpdateSQL($entity);
        return $this->executeSql($stmt, false);
    }

    public function getUpdateSQL($entity)
    {
        $columnNames = $this->getColumnNames();
        $objectArray = $entity->getAttrs();
        $idAttrVal = reset($objectArray);
        $idAttr = key($objectArray);
        array_shift($objectArray);
        $objectArray["$idAttr"] = $idAttrVal;
        $sql = $this->makeUpdateSql($columnNames);
        $stmt = $this->dBConnection->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt = $this->setParams($objectArray, $stmt);
        return $stmt;
    }

    public function makeUpdateSql($columnNames)
    {
        $idColumn = reset($columnNames);
        array_shift($columnNames);
        $tableName = $this->tableName;
        $sql = "UPDATE " . $tableName . " SET ";
        foreach ($columnNames as $count => $columnName) {
            $sql .= $columnName . " = ?";
            if (count($columnNames) !== $count + 1) {
                $sql .= ",";
            }
        }
        $sql .= " WHERE $idColumn = ?;";
        return $sql;
    }

    public function delete($id)
    {
        try {
            return $this->deleteBy(['id'=>$id]);
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao remover um registro do banco: " . $t->getMessage());
            return null;
        }
    }

    public function deleteBy($criteria)
    {
        try {
            $tableName = $this->tableName;
            $params = [];
            $sql = "DELETE FROM $tableName WHERE ";
            foreach ($criteria as $columnName => $columnValue) {
                if (!is_numeric($columnName)) {
                    $columnName = strtoupper(preg_replace('/(?<=\\w)(?=[A-Z])/', "_$1", $columnName));
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

    public function deleteAll($ids = null)
    {
        try {
            $columnNames = $this->getColumnNames();
            $idColumn = reset($columnNames);
            $tableName = $this->tableName;
            $sql = "DELETE FROM " . $tableName;
            if ($ids) {
                $idsCount = count($ids);
                $sql .= " WHERE " . $idColumn . " in (";
                for ($count = 0; $count < $idsCount; $count++) {
                    if ($count == $idsCount - 1) {
                        $sql .= "?";
                    } else {
                        $sql .= "?,";
                    }
                }
                $sql .= ")";
            } else {
                $sql .= " WHERE " . $idColumn . " > 0";
            }
            $stmt = $this->dBConnection->prepare($sql);
            if (!$stmt) {
                return null;
            }
            if ($ids) {
                $stmt = $this->setParams($ids, $stmt);
            }
            return $this->executeSql($stmt, false);
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao remover registros do banco: " . $t->getMessage());
            return null;
        }
    }

    public function listAll($limit = null, $offset = null, $orderBy=null, $count = false, $returnEntity = true)
    {
        try {
            $tableName = $this->tableName;
            $sql = 'SELECT *';
            if ($count) {
                $sql = 'SELECT COUNT(*)';
            }
            $sql .= ' FROM ' . $tableName;
            if($orderBy){
                $sql .= ' ORDER BY ';
                foreach ($orderBy as $key => $value) {
                    $columnName = strtoupper(preg_replace('/(?<=\\w)(?=[A-Z])/', "_$1", $key));
                    $sql .= $columnName . ' ' . $value;
                }
            } else if ($offset || $limit) {
                $sql .= " ORDER BY ID LIMIT " . $limit . " OFFSET " . $offset;
            }
            $stmt = $this->dBConnection->query($sql);
            if (!$stmt) {
                return false;
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                return null;
            }
            $stmt->closeCursor();
            if ($count) {
                return reset($rows[0]);
            }
            $rows = $this->getData($rows, $returnEntity);
            return $rows;
        } catch (\Throwable $t) {
            Logger::log($t->getFile() . " (" . $t->getLine() . ") Erro ao recuperar registros do banco: " . $t->getMessage());
            return null;
        }
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