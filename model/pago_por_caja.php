<?php

require_model('factura_cliente.php');
require_model('recibo_cliente.php');
require_model('pago.php');
require_model('caja.php');

/**
 * Created by IntelliJ IDEA.
 * User: ggarcia
 * Date: 30/06/2016
 * Time: 07:29 PM
 */

class pago_por_caja extends fs_model {

    /**
     * @var int
     */
    protected $id;

    /**
     * @var int
     */
    protected $idfactura;

    /**
     * @var factura_cliente
     */
    protected $factura;

    /**
     * @var int
     */
    protected $idrecibo;

    /**
     * @var recibo_cliente
     */
    protected $recibo_cliente;

    /**
     * @var int
     */
    protected $idpago;

    /**
     * @var pago
     */
    protected $pago;

    /**
     * @var int
     */
    protected $idcaja;

    /**
     * @var caja
     */
    protected $caja;

    private $edit = false;

    const CACHE_KEY_ALL = 'facturascli_por_caja_all';
    const CACHE_KEY_SINGLE = 'facturascli_por_caja_{id}';

    const DATE_FORMAT = 'd-m-Y';
    const DATE_FORMAT_FULL = 'd-m-Y H:i:s';

    /**
     * conf_caja constructor.
     * @param array $data
     */
    public function __construct($data = array()) {
        parent::__construct('pago_por_caja', 'plugins/facturacion_base/');

        $this->setValues($data);
    }

    /**
     * @param array $data
     */
    public function setValues($data = array()) {

        $this->setId($data);
        $this->idfactura = (isset($data['idfactura'])) ? $data['idfactura'] : null;
        $this->idrecibo = (isset($data['idrecibo'])) ? $data['idrecibo'] : null;
        $this->idpago = (isset($data['idpago'])) ? $data['idpago'] : null;
        $this->idcaja = (isset($data['idcaja'])) ? $data['idcaja'] : null;
    }

    /**
     * @param boolean $edit
     */
    public function setEdit($edit) {
        $this->edit = $edit;
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param mixed $data
     * @return conf_caja
     */
    public function setId($data) {
        // This is an ugly thing use an Hydrator insted
        if(is_int($data)) {
            $this->id = $data;
        }

        if(is_array($data)) {
            if(isset($data['idconfcaja'])) {
                $this->id = $data['idconfcaja'];
            }

            if(isset($data['id'])) {
                $this->id = $data['id'];
            }
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getIdFactura() {
        return $this->idfactura;
    }

    /**
     * @param int $idfactura
     * @return facturascli_por_caja
     */
    public function setIdFactura($idfactura) {
        $this->idfactura = $idfactura;
        return $this;
    }

    /**
     * @return factura_cliente
     */
    public function getFactura() {
        if(!$this->factura) {
            $this->factura = self::get_factura($this->getIdFactura());
        }
        return $this->factura;
    }

    /**
     * @param factura_cliente $factura
     * @return facturascli_por_caja
     */
    public function setFactura(factura_cliente $factura) {
        $this->idfactura = $factura->idfactura;
        $this->factura = $factura;
        return $this;
    }

    /**
     * @return int
     */
    public function getIdRecibo() {
        return $this->idrecibo;
    }

    /**
     * @param int $idrecibo
     * @return pago_por_caja
     */
    public function setIdrecibo($idrecibo) {
        $this->idrecibo = $idrecibo;
        return $this;
    }

    /**
     * @return recibo_cliente
     */
    public function getReciboCliente() {
        return $this->recibo_cliente;
    }

    /**
     * @param recibo_cliente $recibo_cliente
     * @return pago_por_caja
     */
    public function setReciboCliente($recibo_cliente) {
        $this->recibo_cliente = $recibo_cliente;
        return $this;
    }
    
    /**
     * @return int
     */
    public function getIdPago() {
        return $this->idpago;
    }

    /**
     * @param int $idpago
     * @return facturascli_por_caja
     */
    public function setIdPago($idpago) {
        $this->idpago = $idpago;
        return $this;
    }

    /**
     * @return pago
     */
    public function getPago() {
        return $this->pago;
    }

    /**
     * @param pago $pago
     * @return facturascli_por_caja
     */
    public function setPago(pago $pago) {
        $this->idpago = $pago->id;
        $this->pago = $pago;
        return $this;
    }

    /**
     * @return int
     */
    public function getIdCaja() {
        return $this->idcaja;
    }

    /**
     * @param int $idcaja
     * @return facturascli_por_caja
     */
    public function setIdCaja($idcaja) {
        $this->idcaja = $idcaja;
        return $this;
    }

    /**
     * @return void
     */
    private function clean_cache() {
        $this->cache->delete(self::CACHE_KEY_ALL);
    }

    /**
     * @return string
     */
    protected function install() {
        return '';
    }

    /**
     * @param int $id
     *
     * @return bool|pago_por_caja
     */
    public static function get($id = 0) {
        $pago_por_caja = new self();

        return $pago_por_caja->fetch($id);
    }

    /**
     * @param int $id
     *
     * @return bool|facturascli_por_caja
     */
    public function fetch($id) {
        $pago_por_caja = $this->cache->get(str_replace('{id}',$id,self::CACHE_KEY_SINGLE));
        if($id && !$pago_por_caja) {
            $pago_por_caja = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . (int) $id . ";");
            $this->cache->set(str_replace('{id}',$id,self::CACHE_KEY_SINGLE), $pago_por_caja);
        }
        if($pago_por_caja) {
            return new self($pago_por_caja[0]);
        } else {
            return false;
        }
    }

    /**
     * @return conf_caja[]
     */
    public function fetchAll() {
        $conf_cajalist = array();
        $conf_cajas = $this->cache->get(self::CACHE_KEY_ALL);
        if(!$conf_cajalist) {
            $conf_cajas = $this->db->select("SELECT * FROM " . $this->table_name . " ORDER BY create_date ASC;");
            $this->cache->set(self::CACHE_KEY_ALL, $conf_cajas);
        }
        foreach($conf_cajas as $conf_caja) {
            $conf_cajalist[] = new self($conf_caja);
        }

        return $conf_cajalist;
    }

    /**
     * @return bool|array
     */
    public function exists() {
        if(is_null($this->id)) {
            return false;
        } else {
            return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . (int) $this->id . ";");
        }
    }

    public function test() {
        $status = false;
        $this->id = (int) $this->id;


        if(!$this->get_errors()) {
            $status = true;
        }

        return $status;
    }

    protected function insert() {
        $sql = 'INSERT ' . $this->table_name .
               ' SET ' .
               'idfactura = ' . $this->var2str($this->getIdFactura()) . ',' .
               'idrecibo = ' . $this->var2str($this->getIdRecibo()) . ',' .
               'idpago = ' . $this->var2str($this->getIdPago()) . ',' .
               'idcaja = ' . $this->var2str($this->getIdCaja()) .
               ';';

        $ret = $this->db->exec($sql);

        return $ret;
    }

    protected function update() {
        $sql = 'UPDATE ' . $this->table_name .
               ' SET ' .
               'idfactura = ' . $this->var2str($this->getIdFactura()) . ',' .
               'idrecibo = ' . $this->var2str($this->getIdRecibo()) . ',' .
               'idpago = ' . $this->var2str($this->getIdPago()) . ',' .
               'idcaja = ' . $this->var2str($this->getIdCaja()) ;
        $sql .= ' WHERE id = ' . $this->getId() . ';';

        $ret = $this->db->exec($sql);

        return $ret;
    }

    /**
     * @return boolean
     */
    public function save() {
        $ret = false;

        if($this->test()) {
            $this->clean_cache();
            if($this->exists()) {
                $ret = $this->update();
            } else {
                $ret = $this->insert();
                $this->setId(intval($this->db->lastval()));
            }
        }

        return $ret;
    }

    /**
     * @return mixed
     */
    public function delete() {
        // TODO: Implement delete() method.
    }

    /**
     * @param caja $caja
     * @param $recibo_cliente $recibo_cliente
     * @return boolean
     */
    public static function save_recibo(caja $caja, recibo_cliente $recibo_cliente) {
        $obj = new self(array(
            'idfactura' => $recibo_cliente->idfactura,
            'idrecibo' => $recibo_cliente->idrecibo,
            'idcaja' => $caja->id,
        ));

        return $obj->save() && $caja->sumar_importe(floatval($recibo_cliente->importe));
    }

    private static function get_factura($idFactura) {
        $factura = new factura_cliente();

        return $factura->get($idFactura);
    }

    /**
     * @param int $idcaja
     *
     * @return factura_cliente[]
     */
    public static function getFacturasByCaja($idcaja) {
        if(intval($idcaja) > 0) {
            $pago_por_caja = new self();

            return $pago_por_caja->fetchFacturasByCaja($idcaja);
        } else {
            return array();
        }
    }

    /**
     * @param int $idcaja
     *
     * @return pago_por_caja[]
     */
    private function fetchAllByCaja($idcaja) {
        $facporcajlist = $this->cache->get_array(str_replace('{id}','r'.$idcaja,self::CACHE_KEY_SINGLE));
        if(!$idcaja || !$facporcajlist) {
            $facsporcaj = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idcaja = " . (int)$idcaja . " ORDER BY id ASC;");
            if ($facsporcaj) {
                foreach ($facsporcaj as $facporcaj) {
                    $facporcajlist[] = new self($facporcaj);
                }
                $this->cache->set(str_replace('{id}','r'.$idcaja,self::CACHE_KEY_SINGLE), $facporcajlist);
            }
        }
        return $facporcajlist;
    }

    /**
     * @param int $idcaja
     *
     * @return factura_cliente[]
     */
    private function fetchFacturasByCaja($idcaja) {
        $facturas = array();
        foreach ($this->fetchAllByCaja($idcaja) as $facturascli_por_caja) {
            $facturas[] = $facturascli_por_caja->getFactura();
        }
        return $facturas;
    }


}