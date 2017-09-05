<?php
/*
 * @author Carlos García Gómez      neorazorx@gmail.com
 * @copyright 2016-2017, Carlos García Gómez. All Rights Reserved.
 */
namespace FacturaScripts\model;

/**
 * Description of linea_transferencia_stock
 *
 * @author Carlos García Gómez
 */
class linea_transferencia_stock extends \fs_model
{

    /// clave primaria. integer
    public $idlinea;
    public $idtrans;
    public $referencia;
    public $cantidad;
    public $descripcion;
    private $fecha;
    private $hora;

    public function __construct($data = FALSE)
    {
        parent::__construct('lineastransstock');
        if ($data) {
            $this->idlinea = $this->intval($data['idlinea']);
            $this->idtrans = $this->intval($data['idtrans']);
            $this->referencia = $data['referencia'];
            $this->cantidad = floatval($data['cantidad']);
            $this->descripcion = $data['descripcion'];

            $this->fecha = NULL;
            if (isset($data['fecha'])) {
                $this->fecha = date('d-m-Y', strtotime($data['fecha']));
            }

            $this->hora = NULL;
            if (isset($data['hora'])) {
                $this->hora = $data['hora'];
            }
        } else {
            /// valores predeterminados
            $this->idlinea = NULL;
            $this->idtrans = NULL;
            $this->referencia = NULL;
            $this->cantidad = 0;
            $this->descripcion = NULL;
            $this->fecha = NULL;
            $this->hora = NULL;
        }
    }

    public function install()
    {
        /// forzamos la comprobación de la tabla de transferencias de stock
        new \transferencia_stock();

        return '';
    }

    public function fecha()
    {
        return $this->fecha;
    }

    public function hora()
    {
        return $this->hora;
    }

    public function exists()
    {
        if (is_null($this->idlinea)) {
            return FALSE;
        }

        return $this->db->select('SELECT * FROM lineastransstock WHERE idlinea = ' . $this->var2str($this->idlinea) . ';');
    }

    public function save()
    {
        if ($this->exists()) {
            $sql = "UPDATE lineastransstock SET idtrans = " . $this->var2str($this->idtrans)
                . ", referencia = " . $this->var2str($this->referencia)
                . ", cantidad = " . $this->var2str($this->cantidad)
                . ", descripcion = " . $this->var2str($this->descripcion)
                . "  WHERE idlinea = " . $this->var2str($this->idlinea) . ";";

            return $this->db->exec($sql);
        }

        $sql = "INSERT INTO lineastransstock (idtrans,referencia,cantidad,descripcion) VALUES "
            . "(" . $this->var2str($this->idtrans)
            . "," . $this->var2str($this->referencia)
            . "," . $this->var2str($this->cantidad)
            . "," . $this->var2str($this->descripcion) . ");";

        if ($this->db->exec($sql)) {
            $this->idlinea = $this->db->lastval();
            return TRUE;
        }

        return FALSE;
    }

    public function delete()
    {
        return $this->db->exec('DELETE FROM lineastransstock WHERE idlinea = ' . $this->var2str($this->idlinea) . ';');
    }

    private function all_from($sql)
    {
        $list = array();
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $a) {
                $list[] = new \linea_transferencia_stock($a);
            }
        }

        return $list;
    }

    public function all_from_transferencia($id)
    {
        return $this->all_from("SELECT * FROM lineastransstock WHERE idtrans = " . $this->var2str($id) . " ORDER BY referencia ASC;");
    }

    public function all_from_referencia($ref, $codalmaorigen = '', $codalmadestino = '', $desde = '', $hasta = '')
    {
        $sql = "SELECT l.idlinea,l.idtrans,l.referencia,l.cantidad,l.descripcion,t.fecha,t.hora FROM lineastransstock l"
            . " LEFT JOIN transstock t ON l.idtrans = t.idtrans"
            . " WHERE l.referencia = " . $this->var2str($ref);
        if ($codalmaorigen) {
            $sql .= " AND t.codalmaorigen = " . $this->var2str($codalmaorigen);
        }
        if ($codalmadestino) {
            $sql .= " AND t.codalmadestino = " . $this->var2str($codalmadestino);
        }
        if ($desde) {
            $sql .= " AND t.fecha >= " . $this->var2str($desde);
        }
        if ($hasta) {
            $sql .= " AND t.fecha >= " . $this->var2str($hasta);
        }
        $sql .= " ORDER BY t.fecha ASC, t.hora ASC;";

        return $this->all_from($sql);
    }
}
