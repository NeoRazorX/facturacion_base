<?php

/*
 * This file is part of facturacion_base
 * Copyright (C) 2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('cliente.php');
require_model('proveedor.php');

/**
 * Controlador extendido para el plugin facturacion_base.
 *
 * @author Carlos garcía Gómez
 */
class fbase_controller extends fs_controller
{
   /**
    * TRUE si el usuario tiene permisos para eliminar en la página.
    * @var type 
    */
   public $allow_delete;
   
   /**
    * TRUE si hay más de un almacén.
    * @var type 
    */
   public $multi_almacen;
   
   /**
    * Nombre de la clase del controlador que hereda de este.
    * @var type 
    */
   private $controller_name;
   
   public function __construct($name = '', $title = 'home', $folder = '', $admin = FALSE, $shmenu = TRUE, $important = FALSE)
   {
      /**
       * Nos guardamos el nombre de la clase, de la clase hija.
       */
      $this->controller_name = $name;
      
      parent::__construct($name, $title, $folder, $admin, $shmenu, $important);
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on($this->controller_name);
      
      /// ¿Hay más de un almacén?
      $fsvar = new fs_var();
      $this->multi_almacen = $fsvar->simple_get('multi_almacen');
   }
   
   /**
    * Vuelca en la salida estándar un json con el listado de clientes
    * que coinciden con la búsqueda. Ideal para usar con el autocomplete en js.
    * @param type $query
    */
   protected function fbase_buscar_cliente($query)
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $cli = new cliente();
      $json = array();
      foreach($cli->search($query) as $cli)
      {
         $nombre = $cli->nombre;
         if($cli->nombre != $cli->razonsocial)
         {
            $nombre .= ' ('.$cli->razonsocial.')';
         }
         
         $json[] = array('value' => $cli->nombre, 'data' => $cli->codcliente);
      }
      
      header('Content-Type: application/json');
      echo json_encode( array('query' => $query, 'suggestions' => $json) );
   }
   
   /**
    * Vuelca en la salida estándar un json con el listado de proveedores
    * que coinciden con la búsqueda. Ideal para usar con el autocomplete en js.
    * @param type $query
    */
   protected function fbase_buscar_proveedor($query)
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $prov = new proveedor();
      $json = array();
      foreach($prov->search($query) as $prov)
      {
         $nombre = $prov->nombre;
         if($prov->nombre != $prov->razonsocial)
         {
            $nombre .= ' ('.$prov->razonsocial.')';
         }
         
         $json[] = array('value' => $nombre, 'data' => $prov->codproveedor);
      }
      
      header('Content-Type: application/json');
      echo json_encode( array('query' => $query, 'suggestions' => $json) );
   }
   
   /**
    * Devuelve un array con los enlaces a las páginas en función de la url,
    * total y el offset proporcionado.
    * @param type $url
    * @param type $total
    * @param type $offset
    * @return string
    */
   protected function fbase_paginas($url, $total, $offset)
   {
      $paginas = array();
      $i = 0;
      $num = 0;
      $actual = 1;
      
      /// añadimos todas la página
      while($num < $total)
      {
         $paginas[$i] = array(
             'url' => $url."&offset=".($i*FS_ITEM_LIMIT),
             'num' => $i + 1,
             'actual' => ($num == $offset)
         );
         
         if($num == $offset)
         {
            $actual = $i;
         }
         
         $i++;
         $num += FS_ITEM_LIMIT;
      }
      
      /// ahora descartamos
      foreach($paginas as $j => $value)
      {
         $enmedio = intval($i/2);
         
         /**
          * descartamos todo excepto la primera, la última, la de enmedio,
          * la actual, las 5 anteriores y las 5 siguientes
          */
         if( ($j>1 AND $j<$actual-5 AND $j!=$enmedio) OR ($j>$actual+5 AND $j<$i-1 AND $j!=$enmedio) )
         {
            unset($paginas[$j]);
         }
      }
      
      if( count($paginas) > 1 )
      {
         return $paginas;
      }
      else
      {
         return array();
      }
   }
   
   /**
    * Devuelve un array con los valores distintos de la columna en la tabla.
    * Si se proporciona una columna2 y un valor, se filtran los valores
    * que coincidan con ese valor en la columna2.
    * @param type $tabla
    * @param type $columna
    * @param type $columna2
    * @param type $valor
    * @return type
    */
   public function fbase_sql_distinct($tabla, $columna, $columna2 = '', $valor = '')
   {
      $final = array();
      
      if( $this->db->table_exists($tabla) )
      {
         $sql = "SELECT DISTINCT ".$columna." FROM ".$tabla." ORDER BY ".$columna." ASC;";
         if($valor != '')
         {
            $sql = "SELECT DISTINCT ".$columna." FROM ".$tabla." WHERE lower(".$columna2.") = "
                    .$this->empresa->var2str($valor)." ORDER BY ".$columna." ASC;";
         }
         
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               if($d[$columna] != '')
               {
                  /// usamos las minúsculas para filtrar
                  $final[ mb_strtolower($d[$columna], 'UTF8') ] = $d[$columna];
               }
            }
         }
      }
      
      return $final;
   }
   
   /**
    * Devuelve el total de elementos en una tabla atendiendo a la columna.
    * @param type $tabla
    * @param type $columna
    * @param type $where
    * @return int
    */
   public function fbase_sql_total($tabla, $columna, $where = '')
   {
      $data = $this->db->select("SELECT COUNT(".$columna.") as total FROM ".$tabla.' '.$where.";");
      if($data)
      {
         return intval($data[0]['total']);
      }
      else
         return 0;
   }
}
