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

    /** @var string Contenido PEM de la clave privada RSA */
    public $enablebanking_key_pem;

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
        $this->enablebanking_redirect_url = Tools::noHtml($this->enablebanking_redirect_url ?? '');

        // No aplicar noHtml al PEM porque contiene caracteres especiales
        if (empty($this->enablebanking_app_id)) {
            Tools::log()->warning('Debes introducir el App ID de Enable Banking.');
            return false;
        }

        if (empty($this->enablebanking_key_pem)) {
            Tools::log()->warning('Debes pegar el contenido de la clave privada PEM.');
            return false;
        }

        // Verificar que el PEM es valido
        if (strpos($this->enablebanking_key_pem, '-----BEGIN') === false) {
            Tools::log()->warning('La clave PEM no parece valida. Debe empezar con -----BEGIN RSA PRIVATE KEY----- o -----BEGIN PRIVATE KEY-----');
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
            && !empty($this->enablebanking_key_pem)
            && !empty($this->enablebanking_redirect_url);
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return 'ConfigBancosOnline';
    }
}
