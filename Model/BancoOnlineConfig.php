<?php

namespace FacturaScripts\Plugins\BancosOnline\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class BancoOnlineConfig extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var int */
    public $idempresa;

    /** @var string */
    public $enablebanking_app_id;

    /** @var string */
    public $enablebanking_key_path;

    /** @var string */
    public $enablebanking_redirect_url;

    /** @var string */
    public $sync_frecuencia;

    /** @var int */
    public $dias_historico;

    /** @var bool */
    public $activo;

    /** @var string */
    public $creation_date;

    /** @var string */
    public $last_sync;

    public function clear(): void
    {
        parent::clear();
        $this->idempresa = Tools::settings('default', 'idempresa', 1);
        $this->sync_frecuencia = '4 hours';
        $this->dias_historico = 90;
        $this->activo = true;
        $this->creation_date = date('Y-m-d H:i:s');
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'bancos_online_config';
    }

    public function test(): bool
    {
        $this->enablebanking_app_id = Tools::noHtml($this->enablebanking_app_id ?? '');
        $this->enablebanking_key_path = Tools::noHtml($this->enablebanking_key_path ?? '');
        $this->enablebanking_redirect_url = Tools::noHtml($this->enablebanking_redirect_url ?? '');

        if (empty($this->enablebanking_app_id)) {
            Tools::log()->warning('Debes introducir el App ID de Enable Banking.');
            return false;
        }

        if (empty($this->enablebanking_key_path)) {
            Tools::log()->warning('Debes introducir la ruta al fichero de clave privada (.pem).');
            return false;
        }

        if (!empty($this->enablebanking_key_path) && !file_exists($this->enablebanking_key_path)) {
            Tools::log()->warning('El fichero de clave privada no existe: ' . $this->enablebanking_key_path);
            return false;
        }

        if (empty($this->enablebanking_redirect_url)) {
            Tools::log()->warning('Debes introducir la URL de callback.');
            return false;
        }

        return parent::test();
    }

    /**
     * Carga la configuracion de la empresa actual o la indicada.
     */
    public static function current(?int $idempresa = null): self
    {
        $config = new self();
        $idempresa = $idempresa ?? Tools::settings('default', 'idempresa', 1);
        $where = [new DataBaseWhere('idempresa', $idempresa)];

        if ($config->loadFromCode('', $where)) {
            return $config;
        }

        $config->idempresa = $idempresa;
        return $config;
    }

    public function isConfigured(): bool
    {
        return !empty($this->enablebanking_app_id)
            && !empty($this->enablebanking_key_path)
            && !empty($this->enablebanking_redirect_url)
            && file_exists($this->enablebanking_key_path);
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return 'ConfigBancosOnline';
    }
}
