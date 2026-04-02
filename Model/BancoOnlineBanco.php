<?php

namespace FacturaScripts\Plugins\BancosOnline\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;

class BancoOnlineBanco extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $nombre;

    /** @var string */
    public $color;

    /** @var string */
    public $logo_url;

    public function clear(): void
    {
        parent::clear();
        $this->color = '#6366f1';
        $this->logo_url = '';
    }

    public static function primaryColumn(): string
    {
        return 'nombre';
    }

    public static function tableName(): string
    {
        return 'bancos_online_bancos';
    }

    /**
     * Inserta o actualiza la configuracion de un banco.
     */
    public static function upsert(string $nombre, ?string $color = null, ?string $logoUrl = null): self
    {
        $banco = new self();

        if (!$banco->loadFromCode($nombre)) {
            $banco->nombre = $nombre;
        }

        if (null !== $color) {
            $banco->color = $color;
        }

        if (null !== $logoUrl) {
            $banco->logo_url = $logoUrl;
        }

        $banco->save();
        return $banco;
    }
}
