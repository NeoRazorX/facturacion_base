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
     * @return pago_por_caja
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
     * @return pago_por_caja
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
        if(!$this->recibo_cliente) {
            $this->recibo_cliente = self::get_recibo($this->getIdRecibo());
        }
        return $this->recibo_cliente;
    }

    /**
     * @param recibo_cliente $recibo_cliente
     * @return pago_por_caja
     */
    public function setReciboCliente(recibo_cliente $recibo_cliente) {
        $this->recibo_cliente = $recibo_cliente;
        $this->idrecibo = $recibo_cliente->idrecibo;
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
     * @return pago_por_caja
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
     * @return pago_por_caja
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
     * @return pago_por_caja
     */
    public function setIdCaja($idcaja) {
        $this->idcaja = $idcaja;
        return $this;
    }

    /**
     * @return caja
     */
    public function getCaja() {
        if(!$this->caja) {
            $this->caja = self::get_caja($this->getIdCaja());
        }
        return $this->caja;
    }

    /**
     * @param caja $caja
     *
     * @return pago_por_caja
     */
    public function setCaja(caja $caja) {
        $this->idcaja = $caja->id;
        $this->caja = $caja;
        return $this;
    }

    /**
     * @return void
     */
    private function clean_cache() {
        $this->cache->delete(str_replace('{id}','byc'.$this->getIdCaja(),self::CACHE_KEY_SINGLE));
        $this->cache->delete(str_replace('{id}',$this->getId(),self::CACHE_KEY_SINGLE));
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
     * @return bool|pago_por_caja
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
     * @param int $idcaja
     * @param int $idfactura
     * @param int $idrecibo
     *
     * @return bool|pago_por_caja
     */
    public function fetchByCajaYFacturaYRecibo($idcaja, $idfactura, $idrecibo) {
        $pago_por_caja = $this->db->select("SELECT * FROM " . $this->table_name
            ." WHERE idcaja = ". (int) $idcaja
            ." AND idfactura = " . (int) $idfactura
            ." AND idrecibo = ". (int) $idrecibo . ";");
        if($pago_por_caja) {
            return new self($pago_por_caja[0]);
        } else {
            return false;
        }
    }

    /**
     * @param int $idfactura
     *
     * @return bool|pago_por_caja[]
     */
    public function fetchAllByFactura($idfactura) {
        $res = array();
        $pagos = $this->db->select("SELECT * FROM " . $this->table_name ." WHERE idfactura = " . (int) $idfactura . ";");
        if($pagos) {
            foreach ($pagos as $pago) {
                $res[] = new pago_por_caja($pago);
            }
        } else {
            return false;
        }
        return $res;
    }

    /**
     * @param int $idrecibo
     *
     * @return bool|pago_por_caja[]
     */
    public function fetchAllByRecibo($idrecibo) {
        $res = array();
        $pagos = $this->db->select("SELECT * FROM " . $this->table_name ." WHERE idrecibo = " . (int) $idrecibo . ";");
        if($pagos) {
            foreach ($pagos as $pago) {
                $res[] = new pago_por_caja($pago);
            }
        } else {
            return false;
        }
        return $res;
    }

    /**
     * @param int $idcaja
     *
     * @return pago_por_caja[]
     */
    public function fetchAllByCaja($idcaja) {
        $facporcajlist = $this->cache->get_array(str_replace('{id}','byc'.$idcaja,self::CACHE_KEY_SINGLE));
        if(!$idcaja || !$facporcajlist) {
            $facsporcaj = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idcaja = " . (int)$idcaja . " ORDER BY id ASC;");
            if ($facsporcaj) {
                foreach ($facsporcaj as $facporcaj) {
                    $facporcajlist[] = new self($facporcaj);
                }
                $this->cache->set(str_replace('{id}','byc'.$idcaja,self::CACHE_KEY_SINGLE), $facporcajlist);
            }
        }
        return $facporcajlist;
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
                $this->setId( (int) $this->db->lastval());
            }
        }

        return $ret;
    }

    /**
     * @return mixed
     */
    public function delete() {
        $this->clean_cache();

        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE id = " . (int)$this->id . ";");
    }

    /**
     * @param caja $caja
     * @param $recibo_cliente $recibo_cliente
     * @return boolean
     */
    public static function save_recibo(caja $caja, recibo_cliente $recibo_cliente) {
        //Obtenemos la factura
        $factura = $recibo_cliente->getFactura();

        //Si el recibo ya está en alguna caja no guardarlo de nuevo
        //Si la factura ya está marcada como paga no guardar el recibo en una caja
        if($factura->pagada || self::getByCajaYRecibo($caja, $recibo_cliente)) {
            return true;
        }

        //Si el saldo de la factura es pagado por el valor de este recibo
        if($recibo_cliente->importe >= $factura->getSaldo()) {
            //Ahora agreo un recibo a la caja como valor negativo
            $monto_rec = (float) - $factura->getMontoPago();
            //TODO: HARCODED FORMA DE PAGO
            $fake_recibo = self::create_recibo('ANT', $monto_rec, $factura);
            $ret = $fake_recibo->save();
            $obj = new self( array(
                'idfactura' => $recibo_cliente->idfactura,
                'idrecibo'  => $fake_recibo->idrecibo,
                'idcaja'    => $caja->id,
            ));
            $ret = $ret && $obj->save() && $caja->sumar_importe($monto_rec);

            //Agrego un recibo con el total de la factura a la caja
            $obj = new self( array(
                'idfactura' => $recibo_cliente->idfactura,
                'idcaja'    => $caja->id,
            ));
            //Sumo el total de la factura a la caja
            $ret = $ret && $obj->save() && $caja->sumar_importe($factura->total);
            //Le pongo la fecha y hora de cuando se está pagando
            $factura->fecha = date('d-m-Y');
            $factura->hora = date('h:i:s');
            //Marco la factura como pagada
            $factura->pagada = true;
            //Le cambio la forma de pago al recibo que obtuve
            $factura->codpago = $recibo_cliente->codpago;
            $ret = $ret && $factura->save();
        } else {
            //El sando no alcanza para pagar el saldo
            //Agrego el recibo actual a la caja
            $obj = new self( array(
                'idfactura' => $recibo_cliente->idfactura,
                'idrecibo'  => $recibo_cliente->idrecibo,
                'idcaja'    => $caja->id,
            ));
            //Sumo el valor del recibo a la caja
            $ret = $obj->save() && $caja->sumar_importe($recibo_cliente->importe);
        }
        return $ret;
    }

    private static function get_factura($idFactura) {
        $factura = new factura_cliente();

        return $factura->get($idFactura);
    }

    private static function get_recibo($idRecibo) {
        $recibo = new recibo_cliente();

        return $recibo->get($idRecibo);
    }

    /**
     * @param int $id_factura_cliente
     *
     * @return bool|recibo_cliente[]
     */
    public static function getRecibosByFactura($id_factura_cliente) {
        $obj = new self();

        return $obj->fetchRecibosByFactura($id_factura_cliente);
    }

    /**
     * @param recibo_cliente $recibo_cliente
     *
     * @return bool
     */
    public static function delete_all_by_recibo(recibo_cliente $recibo_cliente) {
        $ret = true;
        $pagos = self::getByRecibo($recibo_cliente->idrecibo);
        if($pagos) {
            foreach ($pagos as $pago) {
                $ret = $ret && $pago->delete();
            }
        }
        return $ret;
    }

    /**
     *
     */
    public static function getByCajaYRecibo(caja $caja, recibo_cliente $recibo_cliente) {
        $obj = new self();
        return $obj->fetchByCajaYFacturaYRecibo($caja->id, $recibo_cliente->idfactura, $recibo_cliente->idrecibo);
    }

    /**
     * @param int $idcaja
     *
     * @return factura_cliente[]
     */
    public static function getFacturasByCaja($idcaja) {
        if((int) $idcaja > 0) {
            $pago_por_caja = new self();

            return $pago_por_caja->fetchFacturasByCaja($idcaja);
        } else {
            return array();
        }
    }

    /**
     * @param int $idcaja
     *
     * @return recibo_cliente[]
     */
    public static function getRecibosByCaja($idcaja) {
        if((int) $idcaja > 0) {
            $pago_por_caja = new self();

            return $pago_por_caja->fetchRecibosByCaja($idcaja);
        } else {
            return array();
        }
    }

    /**
     * @param $idrecibo
     *
     * @return pago_por_caja[]
     */
    public static function getByRecibo($idrecibo) {
        $obj = new self();

        return $obj->fetchAllByRecibo($idrecibo);

    }

    /**
     * @param $idcaja
     *
     * @return pago_por_caja[]
     */
    public static function getPagosByCaja($idcaja) {
        $obj = new self();

        return $obj->fetchAllByCaja($idcaja);
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

    /**
     * @param int $idcaja
     *
     * @return recibo_cliente[]
     */
    private function fetchRecibosByCaja($idcaja) {
        $recibos = array();
        foreach ($this->fetchAllByCaja($idcaja) as $pago_por_caja) {
            $recibos[] = $pago_por_caja->getReciboCliente();
        }
        return $recibos;
    }

    /**
     * @param int $id_factura_cliente
     *
     * @return recibo_cliente[]|array
     */
    private function fetchRecibosByFactura($id_factura_cliente) {
        $recibos = array();
        $pagos = $this->fetchAllByFactura($id_factura_cliente);
        if ($pagos) {
            foreach($pagos as $pago_por_caja) {
                $recibos[] = $pago_por_caja->getReciboCliente();
            }
        }
        return $recibos;
    }

    //TODO: NEGRADA WARNING
    /**
     * @param $forma_pago
     * @param $importe
     * @param factura_cliente $factura_cliente
     *
     * @return recibo_cliente
     */
    private static function create_recibo($forma_pago, $importe, factura_cliente $factura_cliente) {
        $recibo = new recibo_cliente();
        $recibo->idfactura = $factura_cliente->idfactura;
        $recibo->codpago = $forma_pago;
        $recibo->apartado = $factura_cliente->apartado;
        $recibo->cifnif = $factura_cliente->cifnif;
        $recibo->ciudad = $factura_cliente->ciudad;
        $recibo->codcliente = $factura_cliente->codcliente;
        $recibo->coddir = $factura_cliente->coddir;
        $recibo->coddivisa = $factura_cliente->coddivisa;
        $recibo->numero = $recibo->new_numero($factura_cliente->idfactura);
        $recibo->codigo = $factura_cliente->codigo.'-'.sprintf('%02s', $recibo->numero);
        $recibo->codpais = $factura_cliente->codpais;
        $recibo->codpostal = $factura_cliente->codpostal;
        $recibo->direccion = $factura_cliente->direccion;
        $recibo->estado = 'Pagado';
        $recibo->fecha = date('d-m-Y');
        $recibo->fechav = date('d-m-Y');
        $recibo->importe = (float) $importe;
        $recibo->importeeuros = $recibo->importe * $factura_cliente->tasaconv;
        $recibo->nombrecliente = $factura_cliente->nombrecliente;
        $recibo->provincia = $factura_cliente->provincia;

        return $recibo;
    }

}