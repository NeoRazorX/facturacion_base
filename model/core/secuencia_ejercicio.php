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

require_model('ejercicio.php');
require_model('serie.php');

/**
 * Clase que permite la compatibilidad con Eneboo.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class secuencia_ejercicio extends \fs_model
{

    /**
     * Clave primaria.
     * @var type 
     */
    public $idsecuenciaejer;
    public $nfacturacli;
    public $nalbarancli;
    public $npedidocli;
    public $npresupuestocli;
    public $nfacturaprov;
    public $nalbaranprov;
    public $npedidoprov;
    public $codejercicio;
    public $codserie;

    public function __construct($aux = FALSE)
    {
        parent::__construct('secuenciasejercicios');
        if ($aux) {
            $this->idsecuenciaejer = $this->intval($aux['id']);
            $this->codejercicio = $aux['codejercicio'];
            $this->codserie = $aux['codserie'];
            $this->nalbarancli = $this->intval($aux['nalbarancli']);
            $this->nalbaranprov = $this->intval($aux['nalbaranprov']);
            $this->nfacturacli = $this->intval($aux['nfacturacli']);
            $this->nfacturaprov = $this->intval($aux['nfacturaprov']);
            $this->npedidocli = $this->intval($aux['npedidocli']);
            $this->npedidoprov = $this->intval($aux['npedidoprov']);
            $this->npresupuestocli = $this->intval($aux['npresupuestocli']);
        } else {
            $this->idsecuenciaejer = NULL;
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

    public function get($getid)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($getid) . ";");
        if ($data) {
            return new \secuencia_ejercicio($data[0]);
        }

        return FALSE;
    }

    public function get_by_params($eje, $serie)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($eje)
            . " AND codserie = " . $this->var2str($serie) . ";";

        $data = $this->db->select($sql);
        if ($data) {
            return new \secuencia_ejercicio($data[0]);
        }

        return FALSE;
    }

    public function check()
    {
        $eje = new \ejercicio();
        $serie = new \serie();
        foreach ($eje->all() as $e) {
            $secs = $this->all_from_ejercicio($e->codejercicio);
            foreach ($serie->all() as $serie) {
                $encontrada = FALSE;
                foreach ($secs as $s) {
                    if ($s->codserie == $serie->codserie) {
                        $encontrada = TRUE;
                    }
                }
                if (!$encontrada) {
                    $aux = new \secuencia_ejercicio();
                    $aux->codejercicio = $e->codejercicio;
                    $aux->codserie = $serie->codserie;
                    if (!$aux->save()) {
                        $this->new_error_msg("¡Imposible crear la secuencia para el ejercicio " .
                            $aux->codejercicio . " y la serie " . $aux->codserie . "!");
                    }
                }
            }
        }
    }

    public function exists()
    {
        if (is_null($this->idsecuenciaejer)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->idsecuenciaejer) . ";");
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
                "  WHERE id = " . $this->var2str($this->idsecuenciaejer) . ";";

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
            $this->idsecuenciaejer = $this->db->lastval();
            return TRUE;
        }

        return FALSE;
    }

    public function delete()
    {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->idsecuenciaejer) . ";");
    }

    public function all_from_ejercicio($eje)
    {
        $seclist = array();

        $secs = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($eje) . ";");
        if ($secs) {
            foreach ($secs as $s) {
                $seclist[] = new \secuencia_ejercicio($s);
            }
        }

        return $seclist;
    }
}
