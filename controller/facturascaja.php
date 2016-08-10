<?php

require_model('caja.php');
require_model('pago_por_caja.php');

/**
 * Created by IntelliJ IDEA.
 * User: ggarcia
 * Date: 30/06/2016
 * Time: 06:53 PM
 */

class facturascaja extends fs_controller {

    /**
     * @var int
     */
    protected $idcaja;

    /**
     * @var caja
     */
    protected $caja;

    /**
     * @var factura_cliente[]
     */
    protected $facturas;

    /**
     * @var bool
     */
    public $allow_delete;

    public function __construct() {
        parent::__construct(__CLASS__, 'Listado de Facturas', 'tpv', true, false);
    }

    protected function private_core() {
        $action = (string) isset($_GET['action']) ? $_GET['action'] : 'list';
        $this->idcaja = (int) isset($_GET['idcaja']) ? $_GET['idcaja'] : '0';

        switch($action) {
            default:
            case 'list':
                $this->indexAction();
                break;
            case 'find':
                $this->findAction();
                break;
        }
    }

    public function indexAction() {
        if(!$this->idcaja) {
            $this->new_error_msg("Caja incorrecta");
        }

        $this->caja = caja::get($this->idcaja);
        $this->facturas = $this->caja->findFacturas();

    }

    public function findAction() {
        $this->page->extra_url = '&action=find';

    }

    /**
     * @return caja
     */
    public function getCaja() {
        return $this->caja;
    }

    /**
     * @return factura_cliente[]
     */
    public function getFacturas() {
        return $this->facturas;
    }

}