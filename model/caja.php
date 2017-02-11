<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2015  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('agente.php');
require_model('conf_caja.php');

/**
 * Estructura para almacenar los datos de estado de una caja registradora (TPV).
 */
class caja extends fs_model {

    /**
     * Clave primaria.
     * @var int
     */
    public $id;

    /**
     * Identificador del terminal. En la tabla cajas_terminales.
     * @var int
     */
    public $fs_id;

    /**
     * Codigo del agente que abre y usa la caja.
     * El agente asociado al usuario.
     * @var string
     */
    public $codagente;

    /**
     * @var int
     */
    public $idconf_caja;

    /**
     * @var conf_caja
     */
    protected $conf_caja;

    /**
     * Fecha de apertura (inicio) de la caja.
     * @var string
     */
    public $fecha_inicial;

    /**
     * @var float
     */
    public $dinero_inicial;

    /**
     * @var null|string
     */
    public $fecha_fin;

    /**
     * @var float
     */
    public $dinero_fin;

    /**
     * Numero de tickets emitidos en esta caja.
     * @var int
     */
    public $tickets;

    /**
     * Ultima IP del usuario de la caja.
     * @var string
     */
    public $ip;

    /**
     * El objeto agente asignado.
     * @var agente
     */
    public $agente;

    /**
     * UN array con todos los agentes utilizados, para agilizar la carga.
     * @var agente[]
     */
    private static $agentes;

    /**
     * @var factura_cliente[]
     */
    protected $facturas;

    /**
     * @var recibo_cliente[]
     */
    protected $recibos;

    /**
     * @var pago_por_caja[]
     */
    protected $pagos;

    /**
     * @var int
     */
    protected $idasiento;

    /**
     * @var asiento
     */
    protected $asiento;

    /**
     * @var bool
     */
    protected $edit;


    const CACHE_KEY_ALL = 'facturascli_por_caja_all';
    const CACHE_KEY_SINGLE = 'facturascli_por_caja_{id}';

    const DATE_FORMAT = 'd-m-Y';
    const DATE_FORMAT_FULL = 'd-m-Y H:i:s';

    /**
     * caja constructor.
     * @param array $data
     */
    public function __construct($data = array()) {
        parent::__construct('cajas', 'plugins/facturacion_base/');

        $this->setValues($data);
    }

    /**
     * @param array $data
     * @return caja
     */
    public function setValues($data = array()) {

        if (!isset(self::$agentes)) {
            self::$agentes = array();
        }

        $this->id = isset($data['id']) ? $this->intval($data['id']) : null;
        $this->fs_id = isset($data['fs_id']) ? $this->intval($data['fs_id']) : null;
        $this->fecha_inicial = isset($data['f_inicio']) ? date(self::DATE_FORMAT_FULL, strtotime($data['f_inicio'])) :
            date(self::DATE_FORMAT_FULL);
        $this->dinero_inicial = isset($data['d_inicio']) ? floatval($data['d_inicio']) : 0;
        $this->fecha_fin = (isset($data['f_fin']) && !is_null($data['f_fin'])) ?
            date(self::DATE_FORMAT_FULL, strtotime($data['f_fin'])) : null;
        $this->dinero_fin = isset($data['d_fin']) && floatval($data['d_fin']) > 0.0 ? $data['d_fin'] : $this->dinero_inicial;
        $this->codagente = isset($data['codagente']) ? $data['codagente'] : null;
        $this->tickets = isset($data['tickets']) ? intval($data['tickets']) : 0;
        $this->ip = isset($data['ip']) ? $data['ip'] : null;
        $this->idconf_caja = isset($data['idconf_caja']) ? (int) $data['idconf_caja'] : null;
        $this->idasiento = isset($data['idasiento']) ? intval($data['idasiento']) : null;

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $this->ip = $_SERVER['REMOTE_ADDR'];
        }

        foreach (self::$agentes as $ag) {
            if ($ag && ($ag->codagente == $this->codagente)) {
                $this->agente = $ag;
                break;
            }
        }

        if (!isset($this->agente)) {
            $ag = new agente();
            $this->agente = $ag->get($this->codagente);
            self::$agentes[] = $this->agente;
        }

        return $this;
    }

    /**
     * @param boolean $edit
     */
    public function setEdit($edit = true) {
        $this->edit = $edit;
    }

    /**
     * @return int
     */
    public function getIdConfCaja()
    {
        return $this->idconf_caja;
    }

    /**
     * @param int $idconf_caja
     *
     * @return caja
     */
    public function setIdConfCaja($idconf_caja)
    {
        $this->idconf_caja = $idconf_caja;

        return $this;
    }

    /**
     * @return conf_caja
     */
    public function getConfCaja()
    {
        if (!$this->conf_caja && $this->idconf_caja) {
            $this->conf_caja = $this->get_conf_caja($this->idconf_caja);
        }
        return $this->conf_caja;
    }

    /**
     * @param conf_caja $conf_caja
     *
     * @return caja
     */
    public function setConfCaja(conf_caja $conf_caja)
    {
        $this->conf_caja = $conf_caja;
        $this->idconf_caja = $conf_caja->getId();
        return $this;
    }



    /**
     * @return int
     */
    public function getIdAsiento() {
        return $this->idasiento;
    }

    /**
     * @param int $idasiento
     * @return caja
     */
    public function setIdAsiento($idasiento) {
        $this->idasiento = $idasiento;
        return $this;
    }

    /**
     * @return asiento
     */
    public function getAsiento() {
        if(!$this->asiento && $this->idasiento) {
            $obj = new asiento();
            $this->asiento = $obj->get($this->getIdAsiento());
        }
        return $this->asiento;
    }

    /**
     * @param asiento $asiento
     * @return caja
     */
    public function setAsiento(asiento $asiento) {
        $this->asiento = $asiento;
        $this->setIdAsiento($asiento->idasiento);
        return $this;
    }

    public function url() {
        return '#';
    }

    /**
     * Return weather a caja should be closed or not according to information on configuration page
     * @return bool
     */
    public function is_usable() {
        $info = conf_caja::get_info();
        $confDateStart =  DateTime::createFromFormat('H:i:s', $info->getStartTime());
        $confDateEnd = DateTime::createFromFormat('H:i:s', $info->getEndTime());
        $cajaStart = DateTime::createFromFormat('d-m-Y H:i:s', $this->fecha_inicial);
        $now = new DateTime();

        if($confDateEnd < $confDateStart && $now > $confDateEnd) {
            // Si la hora de fin es menor a la hora de apertura y ahora es mayor a la hora de cierre le agrego un día
            // de esa forma siempre va a fallar cuando se cambie el día al siguente
            // por ejemplo $confDateStart = 6PM $confDateEnd = 7AM y $now = 8PM
        	$confDateEnd->modify("+1 day");
        } elseif ($now < $confDateStart) {
            // En cambio si la hora de apertura de caja no ocurrio todavía es porque la configuracion cambia de día
            // y el start en realidad es de ayer
            // por ejemplo $confDateStart = 6PM $confDateEnd = 7AM y $now = 3AM
            $confDateStart->modify("-1 day");
        }

        if($cajaStart > $confDateStart && $cajaStart < $confDateEnd) {
            return true;
        } else {
        	return false;
        }
    }

    public function cerrar() {
        $this->fecha_fin = date('d-m-Y H:i:s');
        $this->save();
    }

    /**
     * @return void
     */
    private function clean_cache() {
        $this->cache->delete(self::CACHE_KEY_ALL);
    }

    protected function install() {
        return '';
    }

    public function abierta() {
        return is_null($this->fecha_fin);
    }

    public function show_fecha_fin() {
        if (is_null($this->fecha_fin)) {
            return '-';
        } else {
            return $this->fecha_fin;
        }
    }

    public function diferencia() {
        return ($this->dinero_fin - $this->dinero_inicial);
    }

    /**
     * @param float $total
     * @return boolean
     */
    public function sumar_importe($total) {
        /// actualizamos la caja
        $ret = true;
        $this->setEdit();
        $this->dinero_fin += $total;
        $this->tickets += 1;
        if (!$this->save()) {
            $ret = false;
            $this->new_error_msg("¡Imposible actualizar la caja!");
        }
        return $ret;
    }

    public function exists() {
        if (is_null($this->id)) {
            return FALSE;
        } else {
            return $this->db->select("SELECT * FROM " . $this->table_name .
                " WHERE id = " . $this->var2str($this->id) . ";");
        }
    }

    /**
     * @param int $id
     *
     * @return bool|caja
     */
    public static function get($id = 0) {
        $caja = new self();

        return $caja->fetch($id);
    }

    public function fetch($id) {
        $caja = $this->cache->get(str_replace('{id}', $id, self::CACHE_KEY_SINGLE));
        if ($id && !$caja) {
            $sql = "SELECT * FROM " . $this->table_name . " WHERE id = " . (int)$id . ";";
            $caja = $this->db->select($sql);
            $this->cache->set(str_replace('{id}', $id, self::CACHE_KEY_SINGLE), $caja);
        }
        if ($caja) {
            return new caja($caja[0]);
        } else {
            return false;
        }
    }

    /**
     * @return bool|caja
     */
    public function findActiva() {
        $caja = $this->db->select("SELECT * FROM `cajas` WHERE f_fin IS NULL");

        if ($caja) {
            return new self($caja[0]);
        } else {
            return false;
        }
    }

    /**
     * @return factura_cliente[]
     */
    public function findFacturas() {
        if (!$this->facturas) {
            $this->facturas = $this->get_facturas($this->id);
        }

        return $this->facturas;
    }

    /**
     * @return recibo_cliente[]
     */
    public function findRecibos() {
        if (!$this->recibos) {
            $this->recibos = $this->get_recibos($this->id);
        }

        return $this->recibos;
    }

    /**
     * @return pago_por_caja[]
     */
    public function findPagos() {
        if (!$this->pagos) {
            $this->pagos= $this->get_pagos($this->id);
        }

        return $this->pagos;
    }

    public function test() {
        $status = false;
        $this->id = (int)$this->id;

        if ($this->findActiva() && !$this->edit) {
            $this->new_error_msg("Imposible agregar una nueva caja cuando hay una abierta");
        }

        if (!$this->getIdConfCaja()) {
            $this->new_error_msg("La caja no tiene una asignacion del turno en el que fue abierta");
        }


        if (!$this->get_errors()) {
            $status = true;
        }

        return $status;
    }

    protected function insert() {
        $sql = 'INSERT '. $this->table_name .
               ' SET ' .
               'fs_id = '. $this->var2str($this->fs_id) . ',' .
               'codagente = ' . $this->var2str($this->codagente) . ',' .
               'f_inicio = ' . $this->var2str($this->fecha_inicial) .',' .
               'd_inicio = ' . $this->var2str($this->dinero_inicial) . ',' .
               'f_fin = ' . $this->var2str($this->fecha_fin) . ',' .
               'd_fin = ' . $this->var2str($this->dinero_fin) . ',' .
               'tickets = ' . $this->var2str($this->tickets) . ',' .
               'ip = ' . $this->var2str($this->ip) . ',' .
               'idconf_caja = ' . $this->var2str($this->getIdConfCaja()) . ',' .
               'idasiento = ' . $this->var2str($this->getIdAsiento()) .
               ';';
        return $this->db->exec($sql);
    }

    protected function update() {
        $sql = 'UPDATE '. $this->table_name .
               ' SET ' .
                   'fs_id = ' . $this->var2str($this->fs_id) . ',' .
                   'codagente = ' . $this->var2str($this->codagente) . ',' .
                   'ip = ' . $this->var2str($this->ip) . ',' .
                   'f_inicio = ' . $this->var2str($this->fecha_inicial) . ',' .
                   'd_inicio = ' . $this->var2str($this->dinero_inicial) . ',' .
                   'f_fin = ' . $this->var2str($this->fecha_fin) . ',' .
                   'd_fin = ' . $this->var2str($this->dinero_fin) . ',' .
                   'tickets = ' . $this->var2str($this->tickets) . ',' .
                   'idconf_caja = ' . $this->var2str($this->getIdConfCaja()) . ',' .
                   'idasiento = ' . $this->var2str($this->getIdAsiento()) .
               ' WHERE id = ' . $this->var2str($this->id) .
               ';';

        return $this->db->exec($sql);
    }

    public function save() {
        $ret = false;

        if ($this->test()) {
            $this->clean_cache();
            if ($this->exists()) {
                $ret = $this->update();
            } else {
                $ret = $this->insert();
                $this->id = (int) $this->db->lastval();
            }
        }

        return $ret;
    }

    public function delete() {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->id) . ";");
    }

    public function all($offset = 0, $limit = FS_ITEM_LIMIT) {
        $cajalist = array();

        $cajas = $this->db->select_limit("SELECT * FROM " . $this->table_name . " ORDER BY id DESC", $limit, $offset);
        if ($cajas) {
            foreach ($cajas as $c) {
                $cajalist[] = new caja($c);
            }
        }

        return $cajalist;
    }

    public function all_by_agente($codagente, $offset = 0, $limit = FS_ITEM_LIMIT) {
        $cajalist = array();

        $cajas = $this->db->select_limit("SELECT * FROM " . $this->table_name . " WHERE codagente = " .
                                         $this->var2str($codagente) . " ORDER BY id DESC", $limit, $offset);
        if ($cajas) {
            foreach ($cajas as $c) {
                $cajalist[] = new caja($c);
            }
        }

        return $cajalist;
    }

    /**
     * @return caja[]
     */
    public function findCajasSinAsiento() {
        $cajalist = array();

        $cajas = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idasiento IS NULL AND f_fin IS NOT NULL ORDER BY f_fin");

        if ($cajas) {
            foreach ($cajas as $c) {
                $cajalist[] = new caja($c);
            }
        }

        return $cajalist;
    }

    /**
     * @return caja
     */
    public static function get_caja_activa() {
        $obj = new self();
        return $obj->findActiva();
    }

    /**
     * @param int $idcaja
     *
     * @return factura_cliente[]
     */
    public function get_facturas($idcaja = 0) {
        if(!$idcaja && $this->id) {
            $idcaja = $this->id;
        }
        return pago_por_caja::getFacturasByCaja($idcaja);
    }

    /**
     * @param int $idcaja
     *
     * @return recibo_cliente[]
     */
    public function get_recibos($idcaja = 0) {
        if(!$idcaja && $this->id) {
            $idcaja = $this->id;
        }
        return pago_por_caja::getRecibosByCaja($idcaja);
    }

    /**
     * @param int $idcaja
     *
     * @return pago_por_caja[]
     */
    public function get_pagos($idcaja = 0) {
        if(!$idcaja && $this->id) {
            $idcaja = $this->id;
        }
        return pago_por_caja::getPagosByCaja($idcaja);
    }

    public function get_conf_caja($idconf_caja = 0) {
        return conf_caja::get($idconf_caja);
    }

}
