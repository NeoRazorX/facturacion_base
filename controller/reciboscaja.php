<?php

require_model('caja.php');
require_model('pago_por_caja.php');
require_model('forma_pago.php');

/**
 * Created by IntelliJ IDEA.
 * User: ggarcia
 * Date: 30/06/2016
 * Time: 06:53 PM
 */

class reciboscaja extends fs_controller {

    /**
     * @var int
     */
    protected $idcaja;

    /**
     * @var caja
     */
    protected $caja;

    /**
     * @var recibo_cliente[]
     */
    protected $recibos;

    /**
     * @var forma_pago
     */
    protected $forma_pago;

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
        $this->forma_pago = new forma_pago();

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
        $this->recibos = $this->caja->findRecibos();

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
     * @return recibo_cliente[]
     */
    public function getRecibos() {
        return $this->recibos;
    }

    /**
     * @return forma_pago
     */
    public function getFormaPago() {
        return $this->forma_pago;
    }

    /**
     * @param forma_pago $forma_pago
     * @return float
     */
    public function getTotal(forma_pago $forma_pago) {
        $total = 0.0;

        foreach ($this->getRecibos() as $recibo_cliente) {
            if($forma_pago->codpago == $recibo_cliente->codpago) {
                $total += floatval($recibo_cliente->importe);
            }
        }

        return $total;
    }

}