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
     * @var type
     */
    public $id;

    /**
     * Identificador del terminal. En la tabla cajas_terminales.
     * @var type
     */
    public $fs_id;

    /**
     * Codigo del agente que abre y usa la caja.
     * El agente asociado al usuario.
     * @var type
     */
    public $codagente;

    /**
     * Fecha de apertura (inicio) de la caja.
     * @var type
     */
    public $fecha_inicial;

    /**
     * @var int
     */
    public $dinero_inicial;

    /**
     * @var null
     */
    public $fecha_fin;

    /**
     * @var int
     */
    public $dinero_fin;

    /**
     * Numero de tickets emitidos en esta caja.
     * @var type
     */
    public $tickets;

    /**
     * Ultima IP del usuario de la caja.
     * @var type
     */
    public $ip;

    /**
     * El objeto agente asignado.
     * @var type
     */
    public $agente;

    /**
     * UN array con todos los agentes utilizados, para agilizar la carga.
     * @var type
     */
    private static $agentes;

    /**
     * @var facturascli_por_caja
     */
    protected $facturas;

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
        $this->dinero_fin = isset($data['d_fin']) ? floatval($data['d_fin']) : 0;
        $this->codagente = isset($data['codagente']) ? $data['codagente'] : null;
        $this->tickets = isset($data['tickets']) ? intval($data['tickets']) : 0;
        $this->ip = isset($data['ip']) ? $data['ip'] : null;

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
     * Return weather a caja should be closed or not according to $info
     * @return bool
     */
    public function is_usable() {
        $info = conf_caja::get_info();
        $dateStart = DateTime::createFromFormat('H:i:s', $info->getStartTime());
        $dateEnd = DateTime::createFromFormat('H:i:s', $info->getEndTime());
        $date2 = (new DateTime('NOW'));

        if($dateStart > $dateEnd && $dateEnd > $date2) {
            return true;
        } elseif ($dateStart < $dateEnd && $dateEnd > $date2) {
            var_dump($dateStart);
            var_dump($dateEnd);
            var_dump($date2);
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
            return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->id) .
                                     ";");
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

    public function test() {
        $status = false;
        $this->id = (int)$this->id;

        if ($this->findActiva() && !$this->edit) {
            $this->new_error_msg("Imposible agregar una nueva caja cuando hay una abierta");
        }


        if (!$this->get_errors()) {
            $status = true;
        }

        return $status;
    }

    protected function insert() {
        $sql = "INSERT INTO " . $this->table_name . " (fs_id,codagente,f_inicio,d_inicio,f_fin,d_fin,tickets,ip) VALUES
            (" . $this->var2str($this->fs_id) . "," . $this->var2str($this->codagente) . "," .
               $this->var2str($this->fecha_inicial) . "," . $this->var2str($this->dinero_inicial) . ",
            " . $this->var2str($this->fecha_fin) . "," . $this->var2str($this->dinero_fin) . ",
            " . $this->var2str($this->tickets) . "," . $this->var2str($this->ip) . ");";

        return $this->db->exec($sql);
    }

    protected function update() {
        $sql = "UPDATE " . $this->table_name . " SET fs_id = " . $this->var2str($this->fs_id) . ",
            codagente = " . $this->var2str($this->codagente) . ", ip = " . $this->var2str($this->ip) . ",
            f_inicio = " . $this->var2str($this->fecha_inicial) . ", d_inicio = " .
               $this->var2str($this->dinero_inicial) . ",
            f_fin = " . $this->var2str($this->fecha_fin) . ", d_fin = " . $this->var2str($this->dinero_fin) . ",
            tickets = " . $this->var2str($this->tickets) . " WHERE id = " . $this->var2str($this->id) . ";";

        return $this->db->exec($sql);
    }

    public function save() {
        $ret = false;

        if ($this->test()) {
            $this->clean_cache();
            if ($this->exists()) {
                $this->update();
            } else {
                $this->insert();
                $this->id = intval($this->db->lastval());
            }
        }

        if (!$this->get_errors()) {
            $ret = true;
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
    private function get_facturas($idcaja) {
        return pago_por_caja::getFacturasByCaja($idcaja);
    }

}
