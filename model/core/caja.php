<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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
 * Estructura para almacenar los datos de estado de una caja registradora (TPV).
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class caja extends \fs_model
{

    /**
     * Clave primaria.
     * @var integer 
     */
    public $id;

    /**
     * Identificador del terminal. En la tabla cajas_terminales.
     * @var integer 
     */
    public $fs_id;

    /**
     * Codigo del agente que abre y usa la caja.
     * El agente asociado al usuario.
     * @var string 
     */
    public $codagente;

    /**
     * Fecha de apertura (inicio) de la caja.
     * @var string 
     */
    public $fecha_inicial;
    public $dinero_inicial;
    public $fecha_fin;
    public $dinero_fin;

    /**
     * Numero de tickets emitidos en esta caja.
     * @var integer 
     */
    public $tickets;

    /**
     * Ultima IP del usuario de la caja.
     * @var string 
     */
    public $ip;

    /**
     * El objeto agente asignado.
     * @var \agente
     */
    public $agente;

    /**
     * UN array con todos los agentes utilizados, para agilizar la carga.
     * @var array
     */
    private static $agentes;

    public function __construct($data = FALSE)
    {
        parent::__construct('cajas');

        if (!isset(self::$agentes)) {
            self::$agentes = array();
        }

        if ($data) {
            $this->id = $this->intval($data['id']);
            $this->fs_id = $this->intval($data['fs_id']);
            $this->fecha_inicial = Date('d-m-Y H:i:s', strtotime($data['f_inicio']));
            $this->dinero_inicial = floatval($data['d_inicio']);

            if (is_null($data['f_fin'])) {
                $this->fecha_fin = NULL;
            } else
                $this->fecha_fin = Date('d-m-Y H:i:s', strtotime($data['f_fin']));

            $this->dinero_fin = floatval($data['d_fin']);
            $this->codagente = $data['codagente'];
            $this->tickets = intval($data['tickets']);

            $this->ip = NULL;
            if (isset($data['ip'])) {
                $this->ip = $data['ip'];
            }

            foreach (self::$agentes as $ag) {
                if ($ag->codagente == $this->codagente) {
                    $this->agente = $ag;
                    break;
                }
            }

            if (!isset($this->agente)) {
                $ag = new \agente();
                $this->agente = $ag->get($this->codagente);
                self::$agentes[] = $this->agente;
            }
        } else {
            $this->id = NULL;
            $this->fs_id = NULL;
            $this->codagente = NULL;
            $this->fecha_inicial = Date('d-m-Y H:i:s');
            $this->dinero_inicial = 0;
            $this->fecha_fin = NULL;
            $this->dinero_fin = 0;
            $this->tickets = 0;

            $this->ip = NULL;
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $this->ip = $_SERVER['REMOTE_ADDR'];
            }

            $this->agente = NULL;
        }
    }

    protected function install()
    {
        return '';
    }

    public function abierta()
    {
        return is_null($this->fecha_fin);
    }

    public function show_fecha_fin()
    {
        if (is_null($this->fecha_fin)) {
            return '-';
        }

        return $this->fecha_fin;
    }

    public function diferencia()
    {
        return ($this->dinero_fin - $this->dinero_inicial);
    }

    public function exists()
    {
        if (is_null($this->id)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->id) . ";");
    }

    public function get($id)
    {
        if (isset($id)) {
            $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($id) . ";");
            if ($data) {
                return new \caja($data[0]);
            }
        }

        return FALSE;
    }

    public function save()
    {
        if ($this->exists()) {
            $sql = "UPDATE " . $this->table_name . " SET fs_id = " . $this->var2str($this->fs_id)
                . ", codagente = " . $this->var2str($this->codagente)
                . ", ip = " . $this->var2str($this->ip)
                . ", f_inicio = " . $this->var2str($this->fecha_inicial)
                . ", d_inicio = " . $this->var2str($this->dinero_inicial)
                . ", f_fin = " . $this->var2str($this->fecha_fin)
                . ", d_fin = " . $this->var2str($this->dinero_fin)
                . ", tickets = " . $this->var2str($this->tickets)
                . "  WHERE id = " . $this->var2str($this->id) . ";";

            return $this->db->exec($sql);
        }

        $sql = "INSERT INTO " . $this->table_name . " (fs_id,codagente,f_inicio,d_inicio,f_fin,d_fin,tickets,ip) VALUES
                   (" . $this->var2str($this->fs_id)
            . "," . $this->var2str($this->codagente)
            . "," . $this->var2str($this->fecha_inicial)
            . "," . $this->var2str($this->dinero_inicial)
            . "," . $this->var2str($this->fecha_fin)
            . "," . $this->var2str($this->dinero_fin)
            . "," . $this->var2str($this->tickets)
            . "," . $this->var2str($this->ip) . ");";

        if ($this->db->exec($sql)) {
            $this->id = $this->db->lastval();
            return TRUE;
        }

        return FALSE;
    }

    public function delete()
    {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->id) . ";");
    }

    private function all_from($sql, $offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $cajalist = array();
        $data = $this->db->select_limit($sql, $limit, $offset);
        if ($data) {
            foreach ($data as $a) {
                $cajalist[] = new \caja($a);
            }
        }

        return $cajalist;
    }

    public function all($offset = 0, $limit = FS_ITEM_LIMIT)
    {
        return $this->all_from("SELECT * FROM " . $this->table_name . " ORDER BY id DESC", $offset, $limit);
    }

    public function all_by_agente($codagente, $offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codagente = "
            . $this->var2str($codagente) . " ORDER BY id DESC";

        return $this->all_from($sql, $offset, $limit);
    }
}
