<?php

namespace Lib;

class Acl
{
    private $roles;
    private $rolePrivileges;
    private $allowedList;

    function __construct(array $roles, array $rolePrivileges)
    {
        $this->roles = $roles;
        $this->rolePrivileges = $rolePrivileges;
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
                if ($role->id == $rolePrivilege->role->id) {
                    $privilege = $rolePrivilege->privilege;
                    array_push($privileges, $privilege);
                }
            }
            $this->allowedList[$count]["privileges"] = $privileges;
        }
    }

    public function isAllowed(Role $role, string $privilegeName): bool
    {
        $allowedList = $this->allowedList;
        if (count($allowedList) > 0) {
            foreach ($allowedList as $allowed) {
                $privileges = $allowed['privileges'];
                if ($allowed['role']->id === $role->id) {
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