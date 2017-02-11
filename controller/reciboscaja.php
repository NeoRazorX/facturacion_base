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
     * @var pago_por_caja[]
     */
    protected $pagos;

    /**
     * @var pago_por_caja[]
     */
    protected $ingresos;

    /**
     * @var pago_por_caja[]
     */
    protected $egresos;

    /**
     * @var forma_pago
     */
    protected $forma_pago;

    /**
     * @var bool
     */
    public $allow_delete;

    protected $fecha = null;

    const DATE_FORMAT = 'd-m-Y';
    const DATE_FORMAT_FULL = 'd-m-Y H:i:s';

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
    
    public function url_caja() {
        return 'index.php?page=tpv_caja';
    }

    public function indexAction() {
        if(!$this->idcaja) {
            $this->new_error_msg("Caja incorrecta");
        }

        $this->caja = caja::get($this->idcaja);
        if ($this->caja) {
            $this->pagos = $this->caja->findPagos();
            $this->ingresos = array();
            $this->egresos = array();
            foreach ($this->pagos as $pago) {
                if($pago->getIdRecibo()) {
                    $importe = $pago->getReciboCliente()->importe;
                    if($importe < 0) {
                        $this->egresos[] = $pago;
                    } else {
                        $this->ingresos[] = $pago;
                    }
                } else {
                    $this->ingresos[] = $pago;
                }
            }
        } else { 
            $this->pagos = array();
        }

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
     * @return pago_por_caja[]
     */
    public function getPagos() {
        return $this->pagos;
    }

    /**
     * @return pago_por_caja[]
     */
    public function getIngresos() {
        return $this->ingresos;
    }

    /**
     * @return pago_por_caja[]
     */
    public function getEgresos() {
        return $this->egresos;
    }

    /**
     * @return forma_pago
     */
    public function getFormaPago() {
        return $this->forma_pago;
    }

    public function getTotalIngresos() {
        $total = 0.0;

        foreach ($this->getIngresos() as $pago) {
            if($pago->getIdRecibo()) {
                $recibo = $pago->getReciboCliente();
                $total += (float) $recibo->importe;
            } else {
                $factura = $pago->getFactura();
                $total += (float) $factura->total;
            }
        }

        return $total;
    }

    public function getTotalEgresos() {
        $total = 0.0;

        foreach ($this->getEgresos() as $pago) {
            if($pago->getIdRecibo()) {
                $recibo = $pago->getReciboCliente();
                $total += (float) $recibo->importe;
            } else {
                $factura = $pago->getFactura();
                $total += (float) $factura->total;
            }
        }

        return $total;
    }

    /**
     * @param forma_pago $forma_pago
     * @return float
     */
    public function getTotal(forma_pago $forma_pago) {
        $total = 0.0;

        foreach ($this->getPagos() as $pago) {
            if($pago->getIdRecibo()) {
                $recibo = $pago->getReciboCliente();
                if($forma_pago->codpago === $recibo->codpago) {
                    $total += (float) $recibo->importe;
                }
            } else {
                $factura = $pago->getFactura();
                if($forma_pago->codpago === $factura->codpago) {
                    $total += (float) $factura->total;
                }
            }
        }

        return $total;
    }

    /**
     * @return string
     */
    public function getFecha($full_date = false) {
        if(!$this->fecha) {
            $this->fecha = new DateTime();
        } elseif(is_string($this->fecha)) {
            $this->fecha = new DateTime($this->fecha);
        }

        $format = self::DATE_FORMAT;
        if($full_date) {
            $format = self::DATE_FORMAT_FULL;
        }

        return $this->fecha->format($format);
    }


}