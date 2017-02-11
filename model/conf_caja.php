<?php

/**
 * Created by IntelliJ IDEA.
 * User: ggarcia
 * Date: 30/06/2016
 * Time: 07:29 PM
 */

class conf_caja extends fs_model {

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $nombre;

    /**
     * @var string
     */
    protected $descripcion;

    /**
     * @var int
     */
    protected $start_time;

    /**
     * @var int
     */
    protected $end_time;

    /**
     * @var string
     */
    protected $create_date;

    /**
     * @var string
     */
    protected $update_date;

    private $edit = false;

    const CACHE_KEY_ALL = 'conf_caja_all';
    const CACHE_KEY_SINGLE = 'conf_caja_{id}';

    const DATE_FORMAT = 'd-m-Y';
    const DATE_FORMAT_FULL = 'd-m-Y H:i:s';

    /**
     * conf_caja constructor.
     * @param array $data
     */
    public function __construct($data = array()) {
        parent::__construct('conf_caja', 'plugins/facturacion_base/');

        $this->setValues($data);
    }

    /**
     * @param array $data
     */
    public function setValues($data = array()) {

        $this->setId($data);
        $this->nombre = (isset($data['nombre'])) ? $data['nombre'] : null;
        $this->descripcion = (isset($data['descripcion'])) ? $data['descripcion'] : null;
        $this->setStartTime($data);
        $this->setEndTime($data);

        if(isset($data['create_date']) && !$this->create_date) {
            $this->create_date = $data['create_date'];
        } elseif (!isset($data['create_date']) && !$this->create_date) {
            $this->create_date = date('Y-m-d H:i:s');;
        }

        if(isset($data['update_date'])) {
            $this->update_date = $data['update_date'];
        }

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
     * @return string
     */
    public function getNombre() {
        return $this->nombre;
    }

    /**
     * @param string $nombre
     * @return conf_caja
     */
    public function setNombre($nombre) {
        $this->nombre = $nombre;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescripcion() {
        return $this->descripcion;
    }

    /**
     * @param string $descripcion
     * @return conf_caja
     */
    public function setDescripcion($descripcion) {
        $this->descripcion = $descripcion;
        return $this;
    }

    /**
     * @return string
     */
    public function getStartTime() {
        return date("H:i:s", strtotime($this->start_time));
    }

    /**
     * @param array $data
     * @return conf_caja
     */
    public function setStartTime($data) {
        //Get time
        $start_time = isset($data['start_time']) ? $data['start_time'] : '';

        //Convert it if conains pm or am
        if(stripos($start_time,'am') !== false || stripos($start_time,'pm') !== false) {
            $start_time = date("H:i:s", strtotime($start_time));
        }

        $this->start_time = $start_time;
        return $this;
    }

    /**
     * @return string
     */
    public function getEndTime() {
        return date("H:i:s", strtotime($this->end_time));
    }

    /**
     * @param array $data
     * @return conf_caja
     */
    public function setEndTime($data) {
        //Get time
        $end_time = isset($data['end_time']) ? $data['end_time'] : '';

        //Convert it if conains pm or am
        if(stripos($end_time,'am') !== false || stripos($end_time,'pm') !== false) {
            $end_time = date("H:i:s", strtotime($end_time));
        }

        $this->end_time = $end_time;
        return $this;
    }

    /**
     * @param bool $full_date
     * @return string
     */
    public function getCreateDate($full_date = false) {
        $ret = null;
        if($this->create_date) {
            $fecha = new DateTime($this->create_date);
            $format = self::DATE_FORMAT;
            if($full_date) {
                $format = self::DATE_FORMAT_FULL;
            }
            $ret = $fecha->format($format);
        }

        return $ret;
    }

    /**
     * @param string $create_date
     * @return conf_caja
     */
    public function setCreateDate($create_date) {
        $this->create_date = $create_date;
        return $this;
    }

    /**
     * @param bool $full_date
     * @return string
     */
    public function getUpdateDate($full_date = false) {
        $ret = null;
        if($this->update_date) {
            $fecha = new DateTime($this->update_date);
            $format = self::DATE_FORMAT;
            if($full_date) {
                $format = self::DATE_FORMAT_FULL;
            }
            $ret = $fecha->format($format);
        }

        return $ret;
    }

    /**
     * @param string $update_date
     * @return conf_caja
     */
    public function setUpdateDate($update_date) {
        $this->update_date = $update_date;
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
     * @return bool|conf_caja
     */
    public static function get($id = 0) {
        $conf_caja = new self();

        return $conf_caja->fetch($id);
    }

    /**
     * @param int $id
     *
     * @return bool|conf_caja
     */
    public function fetch($id) {
        $conf_caja = $this->cache->get(str_replace('{id}',$id,self::CACHE_KEY_SINGLE));
        if($id && !$conf_caja) {
            $conf_caja = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . (int) $id . ";");
            $this->cache->set(str_replace('{id}',$id,self::CACHE_KEY_SINGLE), $conf_caja);
        }
        if($conf_caja) {
            return new self($conf_caja[0]);
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
     * @param string $time
     * @return bool|conf_caja
     */
    public function findByTime($time = '') {
        if(!$time) {
            $time = date('H:i:s');
        }

        $sql = <<<SQL
SELECT * FROM conf_caja WHERE IF(
    start_time < end_time,
    CAST('$time' AS TIME) BETWEEN start_time AND end_time,
    (CAST('$time' AS TIME) BETWEEN CAST('00:00:00' AS TIME) AND end_time) OR 
    (CAST('$time' AS TIME) BETWEEN start_time AND CAST('24:00:00' AS TIME))
) = 1;
SQL;

        $conf_cajas = $this->db->select($sql);

        if($conf_cajas && count($conf_cajas) === 1) {
            return new self($conf_cajas[0]);
        } else {
            return false;
        }
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
               'nombre = ' . $this->var2str($this->getNombre()) . ',' .
               'descripcion = ' . $this->var2str($this->getDescripcion()) . ',' .
               'start_time = ' . $this->var2str($this->getStartTime()) . ',' .
               'end_time = ' . $this->var2str($this->getEndTime()) . ',' .
               'create_date = ' . $this->var2str($this->getCreateDate(true)) .
               ';';

        $ret = $this->db->exec($sql);

        return $ret;
    }

    protected function update() {
        $sql = 'UPDATE ' . $this->table_name .
               ' SET ' .
               'nombre = ' . $this->var2str($this->getNombre()) . ',' .
               'descripcion = ' . $this->var2str($this->getDescripcion()) . ',' .
               'start_time = ' . $this->var2str($this->getStartTime()) . ',' .
               'end_time = ' . $this->var2str($this->getEndTime()) . ',' .
               'update_date = ' . $this->var2str(date('Y-m-d H:i:s')) ;
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
                $this->update();
            } else {
                $this->insert();
                $this->setId(intval($this->db->lastval()));
            }
        }

        if(!$this->get_errors()) {
            $ret = true;
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
     * @param string $time Time to use instead of now
     * @return bool|conf_caja
     */
    public static function get_info($time = '') {
        $conf_caja = new self();

        return $conf_caja->findByTime($time);
    }

}