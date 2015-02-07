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

/**
 * Description of regularizacion_stock
 *
 * @author carlos
 */
class regularizacion_stock extends fs_model
{
   public $id; /// pkey
   public $idstock;
   public $cantidadini;
   public $cantidadfin;
   public $fecha;
   public $hora;
   public $motivo;
   public $nick;
   
   public function __construct($r = FALSE)
   {
      parent::__construct('lineasregstocks', 'plugins/facturacion_base/');
      if($r)
      {
         $this->id = $this->intval($r['id']);
         $this->idstock = $this->intval($r['idstock']);
         $this->cantidadini = floatval($r['cantidadini']);
         $this->cantidadfin = floatval($r['cantidadfin']);
         $this->fecha = date('d-m-Y', strtotime($r['fecha']));
         $this->hora = $r['hora'];
         $this->motivo = $r['motivo'];
         $this->nick = $r['nick'];
      }
      else
      {
         $this->id = NULL;
         $this->idstock = NULL;
         $this->cantidadini = 0;
         $this->cantidadfin = 0;
         $this->fecha = date('d-m-Y');
         $this->hora = date('H:i:s');
         $this->motivo = '';
         $this->nick = NULL;
      }
   }
   
   protected function install()
   {
      return '';
   }
   
   public function exists()
   {
      if( is_null($this->id) )
      {
         return FALSE;
      }
      else
         $this->db->select("SELECT * FROM lineasregstocks WHERE id = ".$this->var2str($this->id).";");
   }
   
   public function save()
   {
      if( $this->exists() )
      {
         $sql = "UPDATE lineasregstocks SET idstock = ".$this->var2str($this->idstock).",
            cantidadini = ".$this->var2str($this->cantidadini).", cantidadfin = ".$this->var2str($this->cantidadfin).",
            fecha = ".$this->var2str($this->fecha).", hora = ".$this->var2str($this->hora).",
            motivo = ".$this->var2str($this->motivo).", nick = ".$this->var2str($this->nick)."
            WHERE id = ".$this->var2str($this->id).";";
         
         return $this->db->exec($sql);
      }
      else
      {
         $sql = "INSERT INTO lineasregstocks (idstock,cantidadini,cantidadfin,fecha,hora,motivo,nick) VALUES
            (".$this->var2str($this->idstock).",".$this->var2str($this->cantidadini).",
             ".$this->var2str($this->cantidadfin).",".$this->var2str($this->fecha).",
             ".$this->var2str($this->hora).",".$this->var2str($this->motivo).",".$this->var2str($this->nick).");";
         
         if( $this->db->exec($sql) )
         {
            $this->id = $this->db->lastval();
            return TRUE;
         }
         else
            return FALSE;
      }
   }
   
   public function delete()
   {
      $this->db->exec("DELETE FROM lineasregstocks WHERE id = ".$this->var2str($this->id).";");
   }
   
   public function all_from_articulo($ref)
   {
      $rlist = array();
      
      $data = $this->db->select("SELECT * FROM lineasregstocks WHERE idstock IN
         (SELECT idstock FROM stocks WHERE referencia = ".$this->var2str($ref).") ORDER BY fecha DESC, hora DESC;");
      if($data)
      {
         foreach($data as $d)
            $rlist[] = new regularizacion_stock($d);
      }
      
      return $rlist;
   }
}
