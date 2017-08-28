<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2017  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\model;

/**
 * Una regularización de IVA.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class regularizacion_iva extends \fs_model
{

    /**
     * Clave primaria.
     * @var integer
     */
    public $idregiva;

    /**
     * ID del asiento generado.
     * @var integer
     */
    public $idasiento;
    public $codejercicio;
    public $fechaasiento;
    public $fechafin;
    public $fechainicio;
    public $periodo;

    public function __construct($data = FALSE)
    {
        parent::__construct('co_regiva');
        if ($data) {
            $this->idregiva = $this->intval($data['idregiva']);
            $this->idasiento = $this->intval($data['idasiento']);
            $this->codejercicio = $data['codejercicio'];
            $this->fechaasiento = Date('d-m-Y', strtotime($data['fechaasiento']));
            $this->fechafin = Date('d-m-Y', strtotime($data['fechafin']));
            $this->fechainicio = Date('d-m-Y', strtotime($data['fechainicio']));
            $this->periodo = $data['periodo'];
        } else {
            $this->idregiva = NULL;
            $this->idasiento = NULL;
            $this->codejercicio = NULL;
            $this->fechaasiento = NULL;
            $this->fechafin = NULL;
            $this->fechainicio = NULL;
            $this->periodo = NULL;
        }
    }

    protected function install()
    {
        return '';
    }

    public function url()
    {
        if (is_null($this->idregiva)) {
            return 'index.php?page=contabilidad_regusiva';
        }

        return 'index.php?page=contabilidad_regusiva&id=' . $this->idregiva;
    }

    public function asiento_url()
    {
        if (is_null($this->idasiento)) {
            return 'index.php?page=contabilidad_asientos';
        }

        return 'index.php?page=contabilidad_asiento&id=' . $this->idasiento;
    }

    public function ejercicio_url()
    {
        if (is_null($this->codejercicio)) {
            return 'index.php?page=contabilidad_ejercicios';
        }

        return 'index.php?page=contabilidad_ejercicio&cod=' . $this->codejercicio;
    }

    public function get_partidas()
    {
        if (isset($this->idasiento)) {
            $partida = new \partida();
            return $partida->all_from_asiento($this->idasiento);
        }

        return FALSE;
    }

    /**
     * Devuelve la regularización de IVA correspondiente a esa fecha,
     * es decir, la regularización cuya fecha de inicio sea anterior
     * a la fecha proporcionada y su fecha de fin sea posterior a la fecha
     * proporcionada. Así puedes saber si el periodo sigue abierto para poder
     * facturar.
     * @param type $fecha
     * @return boolean|\regularizacion_iva
     */
    public function get_fecha_inside($fecha)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE fechainicio <= " . $this->var2str($fecha)
            . " AND fechafin >= " . $this->var2str($fecha) . ";";

        $data = $this->db->select($sql);
        if ($data) {
            return new \regularizacion_iva($data[0]);
        }

        return FALSE;
    }

    public function get($id)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idregiva = " . $this->var2str($id) . ";");
        if ($data) {
            return new \regularizacion_iva($data[0]);
        }

        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->idregiva)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name
                . " WHERE idregiva = " . $this->var2str($this->idregiva) . ";");
    }

    public function test()
    {
        return TRUE;
    }

    public function save()
    {
        if ($this->exists()) {
            $sql = "UPDATE " . $this->table_name . " SET codejercicio = " . $this->var2str($this->codejercicio)
                . ", fechaasiento = " . $this->var2str($this->fechaasiento)
                . ", fechafin = " . $this->var2str($this->fechafin)
                . ", fechainicio = " . $this->var2str($this->fechainicio)
                . ", idasiento = " . $this->var2str($this->idasiento)
                . ", periodo = " . $this->var2str($this->periodo)
                . "  WHERE idregiva = " . $this->var2str($this->idregiva) . ";";

            return $this->db->exec($sql);
        }

        $sql = "INSERT INTO " . $this->table_name . " (codejercicio,fechaasiento,fechafin,
            fechainicio,idasiento,periodo) VALUES (" . $this->var2str($this->codejercicio)
            . "," . $this->var2str($this->fechaasiento)
            . "," . $this->var2str($this->fechafin)
            . "," . $this->var2str($this->fechainicio)
            . "," . $this->var2str($this->idasiento)
            . "," . $this->var2str($this->periodo) . ");";

        if ($this->db->exec($sql)) {
            $this->idregiva = $this->db->lastval();
            return TRUE;
        }

        return FALSE;
    }

    public function delete()
    {
        if ($this->db->exec("DELETE FROM " . $this->table_name . " WHERE idregiva = " . $this->var2str($this->idregiva) . ";")) {
            /// si hay un asiento asociado lo eliminamos
            if (isset($this->idasiento)) {
                $asiento = new \asiento();
                $as0 = $asiento->get($this->idasiento);
                if ($as0) {
                    $as0->delete();
                }
            }

            return TRUE;
        }

        return FALSE;
    }

    private function all_from($sql)
    {
        $reglist = array();
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $a) {
                $reglist[] = new \regularizacion_iva($a);
            }
        }

        return $reglist;
    }

    /**
     * Devuelve todas las regularizaciones.
     * @return \regularizacion_iva
     */
    public function all()
    {
        return $this->all_from("SELECT * FROM " . $this->table_name . " ORDER BY fechafin DESC;");
    }

    /**
     * Devuelve todas las regularizaciones del ejercicio.
     * @param type $codejercicio
     * @return \regularizacion_iva
     */
    public function all_from_ejercicio($codejercicio)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($codejercicio)
            . " ORDER BY fechafin DESC;";

        return $this->all_from($sql);
    }
}
