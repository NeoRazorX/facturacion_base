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
 * Description of articulo_propiedad
 *
 * @author carlos
 */
class articulo_propiedad extends fs_model
{
   public $name;
   public $referencia;
   public $text;
   
   public function __construct($a = FALSE)
   {
      parent::__construct('articulo_propiedades', 'plugins/facturacion_base/');
      if($a)
      {
         $this->name = $a['name'];
         $this->referencia = $a['referencia'];
         $this->text = $a['text'];
      }
      else
      {
         $this->name = NULL;
         $this->referencia = NULL;
         $this->text = NULL;
      }
   }
   
   protected function install()
   {
      return '';
   }
   
   public function exists()
   {
      if( is_null($this->name) OR is_null($this->referencia) )
      {
         return FALSE;
      }
      else
      {
         return $this->db->select("SELECT * FROM articulo_propiedades WHERE name = ".
                 $this->var2str($this->name)." AND referencia = ".$this->var2str($this->referencia).";");
      }
   }
   
   public function save()
   {
      if( $this->exists() )
      {
         $sql = "UPDATE articulo_propiedades SET text = ".$this->var2str($this->text)." WHERE name = ".
                 $this->var2str($this->name)." AND referencia = ".$this->var2str($this->referencia).";";
      }
      else
      {
         $sql = "INSERT INTO articulo_propiedades (name,referencia,text) VALUES
            (".$this->var2str($this->name).",".$this->var2str($this->referencia).",".$this->var2str($this->text).");";
      }
      
      return $this->db->exec($sql);
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM articulo_propiedades WHERE name = ".
                 $this->var2str($this->name)." AND referencia = ".$this->var2str($this->referencia).";");
   }
   
   /**
    * Devuelve un array con los pares name => text para una referencia dada.
    * @param type $ref
    * @return type
    */
   public function array_get($ref)
   {
      $vlist = array();
      
      $data = $this->db->select("SELECT * FROM articulo_propiedades WHERE referencia = ".$this->var2str($ref).";");
      if($data)
      {
         foreach($data as $d)
            $vlist[ $d['name'] ] = $d['text'];
      }
      
      return $vlist;
   }
   
   public function array_save($ref, $values)
   {
      $done = TRUE;
      
      foreach($values as $key => $value)
      {
         $aux = new articulo_propiedad();
         $aux->name = $key;
         $aux->referencia = $ref;
         $aux->text = $value;
         if( !$aux->save() )
         {
            $done = FALSE;
            break;
         }
      }
      
      return $done;
   }
   
   public function simple_get($ref, $name)
   {
      $data = $this->db->select("SELECT * FROM articulo_propiedades WHERE referencia = ".$this->var2str($ref)." AND name = ".$this->var2str($name).";");
      if($data)
      {
         return $data[0]['text'];
      }
      else
         return FALSE;
   }
   
   public function simple_delete($ref, $name)
   {
      return $this->db->exec("DELETE FROM articulo_propiedades WHERE referencia = ".$this->var2str($ref)." AND name = ".$this->var2str($name).";");
   }
}
