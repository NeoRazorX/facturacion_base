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
 * Clase que permite la compatibilidad con Eneboo.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class secuencia_ejercicio extends \fs_model
{

    /**
     * Clave primaria.
     * @var integer
     */
    public $id;
    public $nfacturacli;
    public $nalbarancli;
    public $npedidocli;
    public $npresupuestocli;
    public $nfacturaprov;
    public $nalbaranprov;
    public $npedidoprov;
    public $codejercicio;
    public $codserie;

    public function __construct($data = FALSE)
    {
        parent::__construct('secuenciasejercicios');
        if ($data) {
            $this->id = $this->intval($data['id']);
            $this->codejercicio = $data['codejercicio'];
            $this->codserie = $data['codserie'];
            $this->nalbarancli = $this->intval($data['nalbarancli']);
            $this->nalbaranprov = $this->intval($data['nalbaranprov']);
            $this->nfacturacli = $this->intval($data['nfacturacli']);
            $this->nfacturaprov = $this->intval($data['nfacturaprov']);
            $this->npedidocli = $this->intval($data['npedidocli']);
            $this->npedidoprov = $this->intval($data['npedidoprov']);
            $this->npresupuestocli = $this->intval($data['npresupuestocli']);
        } else {
            $this->id = NULL;
            $this->codejercicio = NULL;
            $this->codserie = NULL;
            $this->nalbarancli = 1;
            $this->nalbaranprov = 1;
            $this->nfacturacli = 1;
            $this->nfacturaprov = 1;
            $this->npedidocli = 1;
            $this->npedidoprov = 1;
            $this->npresupuestocli = 1;
        }
    }

    protected function install()
    {
        return '';
    }

    public function get($id)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($id) . ";");
        if ($data) {
            return new \secuencia_ejercicio($data[0]);
        }

        return FALSE;
    }

    public function get_by_params($codejercicio, $codserie)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($codejercicio)
            . " AND codserie = " . $this->var2str($codserie) . ";";

        $data = $this->db->select($sql);
        if ($data) {
            return new \secuencia_ejercicio($data[0]);
        }

        return FALSE;
    }

    public function check()
    {
        $ejercicio_model = new \ejercicio();
        $serie_model = new \serie();
        foreach ($ejercicio_model->all() as $eje) {
            $secuencias = $this->all_from_ejercicio($eje->codejercicio);
            foreach ($serie_model->all() as $serie) {
                $encontrada = FALSE;
                foreach ($secuencias as $sec) {
                    if ($sec->codserie == $serie->codserie) {
                        $encontrada = TRUE;
                        break;
                    }
                }
                if (!$encontrada) {
                    $nueva_sec = new \secuencia_ejercicio();
                    $nueva_sec->codejercicio = $eje->codejercicio;
                    $nueva_sec->codserie = $serie->codserie;
                    if (!$nueva_sec->save()) {
                        $this->new_error_msg("¡Imposible crear la secuencia para el ejercicio " .
                            $nueva_sec->codejercicio . " y la serie " . $nueva_sec->codserie . "!");
                    }
                }
            }
        }
    }

    public function exists()
    {
        if (is_null($this->id)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->id) . ";");
    }

    public function save()
    {
        if ($this->exists()) {
            $sql = "UPDATE " . $this->table_name . " SET codejercicio = " . $this->var2str($this->codejercicio) .
                ", codserie = " . $this->var2str($this->codserie) .
                ", nalbarancli = " . $this->var2str($this->nalbarancli) .
                ", nalbaranprov = " . $this->var2str($this->nalbaranprov) .
                ", nfacturacli = " . $this->var2str($this->nfacturacli) .
                ", nfacturaprov = " . $this->var2str($this->nfacturaprov) .
                ", npedidocli = " . $this->var2str($this->npedidocli) .
                ", npedidoprov = " . $this->var2str($this->npedidoprov) .
                ", npresupuestocli =" . $this->var2str($this->npresupuestocli) .
                "  WHERE id = " . $this->var2str($this->id) . ";";

            return $this->db->exec($sql);
        }

        $sql = "INSERT INTO " . $this->table_name . " (codejercicio,codserie,nalbarancli,
            nalbaranprov,nfacturacli,nfacturaprov,npedidocli,npedidoprov,npresupuestocli)
            VALUES (" . $this->var2str($this->codejercicio) .
            "," . $this->var2str($this->codserie) .
            "," . $this->var2str($this->nalbarancli) .
            "," . $this->var2str($this->nalbaranprov) .
            "," . $this->var2str($this->nfacturacli) .
            "," . $this->var2str($this->nfacturaprov) .
            "," . $this->var2str($this->npedidocli) .
            "," . $this->var2str($this->npedidoprov) .
            "," . $this->var2str($this->npresupuestocli) . ");";

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

    public function all_from_ejercicio($codejercicio)
    {
        $seclist = array();

        $secs = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($codejercicio) . ";");
        if ($secs) {
            foreach ($secs as $s) {
                $seclist[] = new \secuencia_ejercicio($s);
            }
        }

        return $seclist;
    }
}
