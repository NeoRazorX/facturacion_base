<?php

/*
 * This file is part of FacturaScripts
 * Copyright (C) 2015-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('atributo_valor.php');

/**
 * Un atributo para artículos.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class atributo extends \fs_model
{
   /**
    * Clave primaria.
    * @var type 
    */
   public $codatributo;
   public $nombre;
   
   public function __construct($a = FALSE)
   {
      parent::__construct('atributos');
      if($a)
      {
         $this->codatributo = $a['codatributo'];
         $this->nombre = $a['nombre'];
      }
      else
      {
         $this->codatributo = NULL;
         $this->nombre = NULL;
      }
   }
   
   protected function install()
   {
      return '';
   }
   
   public function url()
   {
      return 'index.php?page=ventas_atributos&cod='.$this->codatributo;
   }
   
   public function valores()
   {
      $valor0 = new \atributo_valor();
      return $valor0->all_from_atributo($this->codatributo);
   }
   
   public function get($cod)
   {
      $data = $this->db->select("SELECT * FROM atributos WHERE codatributo = ".$this->var2str($cod).";");
      if($data)
      {
         return new \atributo($data[0]);
      }
      else
      {
         return FALSE;
      }
   }
   
   public function exists()
   {
      if( is_null($this->codatributo) )
      {
         return FALSE;
      }
      else
      {
         return $this->db->select("SELECT * FROM atributos WHERE codatributo = ".$this->var2str($this->codatributo).";");
      }
   }
   
   public function save()
   {
      $this->nombre = $this->no_html($this->nombre);
      
      if( $this->exists() )
      {
         $sql = "UPDATE atributos SET nombre = ".$this->var2str($this->nombre)
                 ." WHERE codatributo = ".$this->var2str($this->codatributo).";";
      }
      else
      {
         $sql = "INSERT INTO atributos (codatributo,nombre) VALUES "
                 . "(".$this->var2str($this->codatributo)
                 . ",".$this->var2str($this->nombre).");";
      }
      
      return $this->db->exec($sql);
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM atributos WHERE codatributo = ".$this->var2str($this->codatributo).";");
   }
   
   public function all()
   {
      $lista = array();
      
      $data = $this->db->select("SELECT * FROM atributos ORDER BY nombre DESC;");
      if($data)
      {
         foreach($data as $d)
         {
            $lista[] = new \atributo($d);
         }
      }
      
      return $lista;
   }
}
