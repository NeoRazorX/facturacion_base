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

/**
 * Combinación atributo-valor de un artículo.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class articulo_combinacion extends \fs_model
{
   /**
    * Clave primaria.
    * @var type 
    */
   public $id;
   public $codigo;
   public $referencia;
   public $idvalor;
   public $nombreatributo;
   public $valor;
   public $impactoprecio;
   
   public function __construct($c = FALSE)
   {
      parent::__construct('articulo_combinaciones');
      if($c)
      {
         $this->id = $this->intval($c['id']);
         $this->codigo = $c['codigo'];
         $this->referencia = $c['referencia'];
         $this->idvalor = $this->intval($c['idvalor']);
         $this->nombreatributo = $c['nombreatributo'];
         $this->valor = $c['valor'];
         $this->impactoprecio = floatval($c['impactoprecio']);
      }
      else
      {
         $this->id = NULL;
         $this->codigo = NULL;
         $this->referencia = NULL;
         $this->idvalor = NULL;
         $this->nombreatributo = NULL;
         $this->valor = NULL;
         $this->impactoprecio = 0;
      }
   }
   
   protected function install()
   {
      return '';
   }
   
   /**
    * Devuelve la combinación del artículo con id = $id
    * @param type $id
    * @return \FacturaScripts\model\articulo_combinacion|boolean
    */
   public function get($id)
   {
      $data = $this->db->select("SELECT * FROM articulo_combinaciones WHERE id = ".$this->var2str($id).";");
      if($data)
      {
         return new \articulo_combinacion($data[0]);
      }
      else
      {
         return FALSE;
      }
   }
   
   /**
    * Devuelve la combinación de artículo con codigo = $cod
    * @param type $cod
    * @return \FacturaScripts\model\articulo_combinacion|boolean
    */
   public function get_by_codigo($cod)
   {
      $data = $this->db->select("SELECT * FROM articulo_combinaciones WHERE codigo = ".$this->var2str($cod).";");
      if($data)
      {
         return new \articulo_combinacion($data[0]);
      }
      else
      {
         return FALSE;
      }
   }
   
   /**
    * Devuelve un nuevo código para una combinación de artículo
    * @return int
    */
   private function get_new_codigo()
   {
      $cod = $this->db->select("SELECT MAX(".$this->db->sql_to_int('codigo').") as cod FROM ".$this->table_name.";");
      if($cod)
      {
         return 1 + intval($cod[0]['cod']);
      }
      else
         return 1;
   }
   
   /**
    * Devuelve TRUE si la combinación de artículo existe en la base de datos
    * @return boolean
    */
   public function exists()
   {
      if( is_null($this->id) )
      {
         return FALSE;
      }
      else
      {
         return $this->db->select("SELECT * FROM articulo_combinaciones WHERE id = ".$this->var2str($this->id).";");
      }
   }
   
   /**
    * Guarda los datos en la base de datos
    * @return boolean
    */
   public function save()
   {
      if( $this->exists() )
      {
         $sql = "UPDATE articulo_combinaciones SET codigo = ".$this->var2str($this->codigo)
                 .", referencia = ".$this->var2str($this->referencia)
                 .", idvalor = ".$this->var2str($this->idvalor)
                 .", nombreatributo = ".$this->var2str($this->nombreatributo)
                 .", valor = ".$this->var2str($this->valor)
                 .", impactoprecio = ".$this->var2str($this->impactoprecio)
                 ."  WHERE id = ".$this->var2str($this->id).";";
         
         return $this->db->exec($sql);
      }
      else
      {
         if( is_null($this->codigo) )
         {
            $this->codigo = $this->get_new_codigo();
         }
         
         $sql = "INSERT INTO articulo_combinaciones (codigo,referencia,idvalor,nombreatributo,valor,impactoprecio) VALUES "
                 . "(".$this->var2str($this->codigo)
                 . ",".$this->var2str($this->referencia)
                 . ",".$this->var2str($this->idvalor)
                 . ",".$this->var2str($this->nombreatributo)
                 . ",".$this->var2str($this->valor)
                 . ",".$this->var2str($this->impactoprecio).");";
         
         if( $this->db->exec($sql) )
         {
            $this->id = $this->db->lastval();
            return TRUE;
         }
         else
         {
            return FALSE;
         }
      }
   }
   
   /**
    * Elimina la combinación de artículo
    * @return type
    */
   public function delete()
   {
      return $this->db->exec("DELETE FROM articulo_combinaciones WHERE id = ".$this->var2str($this->id).";");
   }
   
   /**
    * Elimina todas las combinaciones del artículo con referencia = $ref
    * @param type $ref
    * @return type
    */
   public function delete_from_ref($ref)
   {
      return $this->db->exec("DELETE FROM articulo_combinaciones WHERE referencia = ".$this->var2str($ref).";");
   }
   
   /**
    * Devuelve un array con todas las combinaciones del artículo con referencia = $ref
    * @param type $ref
    * @return \FacturaScripts\model\articulo_combinacion
    */
   public function all_from_ref($ref)
   {
      $lista = array();
      
      $sql = "SELECT * FROM articulo_combinaciones WHERE referencia = ".$this->var2str($ref)
              ." ORDER BY codigo ASC, nombreatributo ASC;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $lista[] = new \articulo_combinacion($d);
         }
      }
      
      return $lista;
   }
   
   /**
    * Devuelve un array con todas las combinaciones con código = $cod
    * @param type $cod
    * @return \FacturaScripts\model\articulo_combinacion
    */
   public function all_from_codigo($cod)
   {
      $lista = array();
      
      $sql = "SELECT * FROM articulo_combinaciones WHERE codigo = ".$this->var2str($cod)
              ." ORDER BY nombreatributo ASC;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $lista[] = new \articulo_combinacion($d);
         }
      }
      
      return $lista;
   }
}
