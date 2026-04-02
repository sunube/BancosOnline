<?php

namespace FacturaScripts\Plugins\BancosOnline\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class BancoOnlineAuth extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $state;

    /** @var string */
    public $authorization_id;

    /** @var string */
    public $banco;

    /** @var int */
    public $idempresa;

    /** @var string */
    public $creation_date;

    public function clear(): void
    {
        parent::clear();
        $this->creation_date = date('Y-m-d H:i:s');
    }

    public static function primaryColumn(): string
    {
        return 'state';
    }

    public static function tableName(): string
    {
        return 'bancos_online_auth';
    }

    /**
     * Busca un registro de autorizacion pendiente por state.
     */
    public static function findByState(string $state): ?self
    {
        $auth = new self();
        if ($auth->loadFromCode($state)) {
            return $auth;
        }
        return null;
    }
}
