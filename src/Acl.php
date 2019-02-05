<?php

namespace Framework;

use Main\DAO\RoleDAO;
use Main\DAO\RolePrivilegeDAO;

class Acl
{
    private $roles;
    private $rolePrivileges;
    private $allowedList;

    function __construct()
    {
        $roleDAO=new RoleDAO();
        $rolePrivilegeDAO=new RolePrivilegeDAO();
        $this->roles = $roleDAO->listAll();
        $this->rolePrivileges = $rolePrivilegeDAO->listAll();
        $this->setAllow();
    }

    private function setAllow()
    {
        $rolePrivileges = $this->rolePrivileges;
        $roles = $this->roles;
        foreach ($roles as $count => $role) {
            $privileges = array();
            $this->allowedList[$count]["role"] = $role;
            foreach ($rolePrivileges as $rolePrivilege) {
                if ($role->id == $rolePrivilege->roleId->id) {
                    $privilege = $rolePrivilege->privilegeId;
                    array_push($privileges, $privilege);
                }
            }
            $this->allowedList[$count]["privileges"] = $privileges;
        }
    }

    public function isAllowed($roleId, string $privilegeName)
    {
        $allowedList = $this->allowedList;
        if (count($allowedList) > 0) {
            foreach ($allowedList as $allowed) {
                $privileges = $allowed['privileges'];
                if ($allowed['role']->id === $roleId) {
                    foreach ($privileges as $privilege) {
                        if ($privilege->name === $privilegeName) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }
}