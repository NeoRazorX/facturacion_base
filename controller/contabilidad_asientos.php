<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('asiento.php');

class contabilidad_asientos extends fs_controller
{
   public $asiento;
   public $desde;
   public $hasta;
   public $mostrar;
   public $offset;
   public $resultados;
   
   private $num_resultados;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Asientos', 'contabilidad', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      $this->asiento = new asiento();
      $this->mostrar = 'todo';
      $this->offset = 0;
      $this->desde = '';
      $this->hasta = '';
      
      if( isset($_GET['mostrar']) )
      {
         $this->mostrar = $_GET['mostrar'];
      }
      
      if( isset($_GET['delete']) )
      {
         $asiento = $this->asiento->get($_GET['delete']);
         if($asiento)
         {
            if( $asiento->delete() )
            {
               $this->new_message("Asiento eliminado correctamente. (ID: ".$asiento->idasiento.", ".$asiento->fecha.")", TRUE);
               $this->clean_last_changes();
            }
            else
               $this->new_error_msg("¡Imposible eliminar el asiento!");
         }
         else
            $this->new_error_msg("¡Asiento no encontrado!");
      }
      else if( isset($_GET['renumerar']) )
      {
         if( $this->asiento->renumerar() )
         {
            $this->new_message("Asientos renumerados.");
         }
      }
      
      $this->offset = 0;
      if( isset($_GET['offset']) )
      {
         $this->offset = intval($_GET['offset']);
      }
      
      if($this->mostrar == 'descuadrados')
      {
         $this->resultados = $this->asiento->descuadrados();
      }
      else
      {
         if( isset($_REQUEST['desde']) OR isset($_REQUEST['hasta']) )
         {
            $this->desde = $_REQUEST['desde'];
            $this->hasta = $_REQUEST['hasta'];
         }
         
         $this->buscar();
      }
   }
   
   private function buscar()
   {
      $this->resultados = array();
      $this->num_resultados = 0;
      $query = $this->empresa->no_html( mb_strtolower($this->query) );
      $sql = " FROM co_asientos ";
      $where = 'WHERE ';
      
      if($query == '')
      {
         /// nada
      }
      else if( is_numeric($query) )
      {
         $aux_sql = '';
         if( strtolower(FS_DB_TYPE) == 'postgresql' )
         {
            $aux_sql = '::TEXT';
         }
         
         $sql .= $where."(numero".$aux_sql." LIKE '%".$query."%' OR concepto LIKE '%".$query
                 ."%' OR importe BETWEEN ".($query-.01)." AND ".($query+.01).')';
         $where = ' AND ';
      }
      else
      {
         $sql .= $where."(lower(concepto) LIKE '%".$buscar = str_replace(' ', '%', $query)."%')";
         $where = ' AND ';
      }
      
      if($this->desde != '')
      {
         $sql .= $where."fecha >= ".$this->empresa->var2str($this->desde);
         $where = ' AND ';
      }
      
      if($this->hasta != '')
      {
         $sql .= $where."fecha <= ".$this->empresa->var2str($this->hasta);
         $where = ' AND ';
      }
      
      $data = $this->db->select("SELECT COUNT(idasiento) as total".$sql);
      if($data)
      {
         $this->num_resultados = intval($data[0]['total']);
         
         $data2 = $this->db->select_limit("SELECT *".$sql." ORDER BY fecha DESC, numero DESC", FS_ITEM_LIMIT, $this->offset);
         if($data2)
         {
            foreach($data2 as $d)
            {
               $this->resultados[] = new asiento($d);
            }
         }
      }
   }
   
   public function anterior_url()
   {
      $url = '';
      
      if($this->query != '' AND $this->offset > 0)
      {
         $url = $this->url(TRUE)."&query=".$this->query."&offset=".($this->offset-FS_ITEM_LIMIT);
      }
      else if($this->query == '' AND $this->offset > 0)
      {
         $url = $this->url(TRUE)."&offset=".($this->offset-FS_ITEM_LIMIT);
      }
      
      return $url;
   }
   
   public function siguiente_url()
   {
      $url = '';
      
      if($this->query != '' AND count($this->resultados) == FS_ITEM_LIMIT)
      {
         $url = $this->url(TRUE)."&query=".$this->query."&offset=".($this->offset+FS_ITEM_LIMIT);
      }
      else if($this->query == '' AND count($this->resultados) == FS_ITEM_LIMIT)
      {
         $url = $this->url(TRUE)."&offset=".($this->offset+FS_ITEM_LIMIT);
      }
      
      return $url;
   }
   
   public function url($busqueda = FALSE)
   {
      if($busqueda)
      {         
         $url = $this->url()."&desde=".$this->desde
                 ."&hasta=".$this->hasta;
         
         return $url;
      }
      else
      {
         return parent::url();
      }
   }
   
   public function total_asientos()
   {
      if( isset($this->num_resultados) )
      {
         return $this->num_resultados;
      }
      else
      {
         $data = $this->db->select("SELECT COUNT(idasiento) as total FROM co_asientos;");
         if($data)
         {
            return intval($data[0]['total']);
         }
         else
            return 0;
      }
   }
}
