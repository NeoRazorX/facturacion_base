<?php

/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2015  Carlos Garcia Gomez  neorazorx@gmail.com
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
 * Description of cliente_propiedad
 *
 * @author carlos
 */
class cliente_propiedad extends fs_model
{
   public $name;
   public $codcliente;
   public $text;
   
   public function __construct($a = FALSE)
   {
      parent::__construct('cliente_propiedades', 'plugins/facturacion_base/');
      if($a)
      {
         $this->name = $a['name'];
         $this->codcliente = $a['codcliente'];
         $this->text = $a['text'];
      }
      else
      {
         $this->name = NULL;
         $this->codcliente = NULL;
         $this->text = NULL;
      }
   }
   
   protected function install()
   {
      return '';
   }
   
   public function exists()
   {
      if( is_null($this->name) OR is_null($this->codcliente) )
      {
         return FALSE;
      }
      else
      {
         return $this->db->select("SELECT * FROM cliente_propiedades WHERE name = ".
                 $this->var2str($this->name)." AND codcliente = ".$this->var2str($this->codcliente).";");
      }
   }
   
   public function save()
   {
      if( $this->exists() )
      {
         $sql = "UPDATE cliente_propiedades SET text = ".$this->var2str($this->text)." WHERE name = ".
                 $this->var2str($this->name)." AND codcliente = ".$this->var2str($this->codcliente).";";
      }
      else
      {
         $sql = "INSERT INTO cliente_propiedades (name,codcliente,text) VALUES
            (".$this->var2str($this->name).",".$this->var2str($this->codcliente).",".$this->var2str($this->text).");";
      }
      
      return $this->db->exec($sql);
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM cliente_propiedades WHERE name = ".
                 $this->var2str($this->name)." AND codcliente = ".$this->var2str($this->codcliente).";");
   }
   
   /**
    * Devuelve un array con los pares name => text para una codcliente dado.
    * @param type $cod
    * @return type
    */
   public function array_get($cod)
   {
      $vlist = array();
      
      $data = $this->db->select("SELECT * FROM cliente_propiedades WHERE codcliente = ".$this->var2str($cod).";");
      if($data)
      {
         foreach($data as $d)
            $vlist[ $d['name'] ] = $d['text'];
      }
      
      return $vlist;
   }
   
   public function array_save($cod, $values)
   {
      $done = TRUE;
      
      foreach($values as $key => $value)
      {
         $aux = new cliente_propiedad();
         $aux->name = $key;
         $aux->codcliente = $cod;
         $aux->text = $value;
         if( !$aux->save() )
         {
            $done = FALSE;
            break;
         }
      }
      
      return $done;
   }
}
