<?php

namespace App\Model;

use Nette,
    Nette\Security\Permission;


/**
 * Users ACL.
 */

class Authorizator implements Nette\Security\IAuthorizator
{
    use Nette\SmartObject;

    /** @var Nette\Security\Permission */
    private $acl;

    public function __construct()
    {
        //předání třídy Permission do parametru $this->acl
        $this->acl = new Permission();
        

        // definujeme role
        $this->acl->addRole('guest');
        $this->acl->addRole('registered', 'guest'); // registered dědí od guest
        $this->acl->addRole('komise', 'registered'); // a od něj dědí administrator
        $this->acl->addRole('admin', 'komise'); // a od něj dědí administrator
        $this->acl->addRole('supervisor', 'komise'); // supervisor zdědí všechno

        //definice zdrojů
        $this->acl->addResource('aktualni-vr');
        $this->acl->addResource('admin/druhy');
        $this->acl->addResource('admin/kola');
        $this->acl->addResource('admin/terminy');
        $this->acl->addResource('admin/prihlasky');
        $this->acl->addResource('admin/uzivatele');
        $this->acl->addResource('admin/oris');
        $this->acl->addResource('admin/content');

        //nastavení povolení
        $this->acl->allow('komise', Permission::ALL, array('view', 'detail'));
        $this->acl->allow('admin', Permission::ALL, array('view', 'detail', 'edit'));
        $this->acl->allow('supervisor', Permission::ALL, array('view', 'detail', 'edit', 'add'));
    }


    /**
     * Check if user is allowed to do stuf.
     * @param string|Permission::ALL|IRole
     * @param string|Permission::ALL|IResource
     * @param string|Permission::ALL
     * @return bool
     */
    function isAllowed($role, $resource, $privilege)
    {
        return $this->acl->isAllowed($role, $resource, $privilege);
    }

}