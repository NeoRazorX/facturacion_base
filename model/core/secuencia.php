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
 * Estos tres modelos (secuencia, secuencia_contabilidad y secuencia_ejercicio)
 * existen para mantener compatibilidad con eneboo, porque maldita la gana que
 * yo tengo de usar TRES tablas para algo tan simple...
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class secuencia extends \fs_model
{

    /**
     * Clave primaria.
     * @var integer
     */
    public $idsec;
    public $id;
    public $valorout;
    public $valor;
    public $descripcion;
    public $nombre;

    public function __construct($data = FALSE)
    {
        parent::__construct('secuencias');
        if ($data) {
            $this->idsec = $this->intval($data['idsec']);
            $this->id = $this->intval($data['id']);
            $this->valorout = $this->intval($data['valorout']);
            $this->valor = $this->intval($data['valor']);
            $this->descripcion = $data['descripcion'];
            $this->nombre = $data['nombre'];
        } else {
            $this->idsec = NULL;
            $this->id = NULL;
            $this->valorout = 0;
            $this->valor = 1;
            $this->descripcion = NULL;
            $this->nombre = NULL;
        }
    }

    protected function install()
    {
        /// necesitamos comprobar este modelo para que cree la tabla, para la clave ajena
        new \secuencia_ejercicio();

        return '';
    }

    public function get($idsec)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idsec = " . $this->var2str($idsec) . ";");
        if ($data) {
            return new \secuencia($data[0]);
        }

        return FALSE;
    }

    public function get_by_params($id, $nombre)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($id)
            . " AND nombre = " . $this->var2str($nombre) . ";";

        $data = $this->db->select($sql);
        if ($data) {
            return new \secuencia($data[0]);
        }

        return FALSE;
    }

    public function get_by_params2($eje, $serie, $nombre)
    {
        $sece = new \secuencia_ejercicio();
        $sece->check();
        $aux = $sece->get_by_params($eje, $serie);
        if ($aux) {
            $sec = $this->get_by_params($aux->id, $nombre);
            if ($sec) {
                return $sec;
            }
            $newsec = new \secuencia();
            $newsec->id = $aux->id;
            $newsec->nombre = $nombre;
            $newsec->descripcion = 'Secuencia del ejercicio ' . $eje . ' y la serie ' . $serie;
            return $newsec;
        }

        $this->new_error_msg("¡Secuencia de ejercicio no encontrada!");
        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->idsec)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idsec = " . $this->var2str($this->idsec) . ";");
    }

    public function save()
    {
        if ($this->exists()) {
            $sql = "UPDATE " . $this->table_name . " SET id = " . $this->var2str($this->id) .
                ", valorout = " . $this->var2str($this->valorout) .
                ", valor = " . $this->var2str($this->valor) .
                ", descripcion = " . $this->var2str($this->descripcion) .
                ", nombre = " . $this->var2str($this->nombre) .
                "  WHERE idsec = " . $this->var2str($this->idsec) . ";";

            return $this->db->exec($sql);
        }

        $sql = "INSERT INTO " . $this->table_name . " (id,valorout,valor,descripcion,nombre) VALUES
                  (" . $this->var2str($this->id) .
            "," . $this->var2str($this->valorout) .
            "," . $this->var2str($this->valor) .
            "," . $this->var2str($this->descripcion) .
            "," . $this->var2str($this->nombre) . ");";

        if ($this->db->exec($sql)) {
            $this->idsec = $this->db->lastval();
            return TRUE;
        }

        return FALSE;
    }

    public function delete()
    {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE idsec = " . $this->var2str($this->idsec) . ";");
    }
}
