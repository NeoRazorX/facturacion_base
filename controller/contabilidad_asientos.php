<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('asiento.php');
require_model('agente.php');

class contabilidad_asientos extends fs_controller
{
   public $asiento;
   public $resultados;
   public $offset;
   public $desde;
   public $hasta;
   public $agente;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Asientos', 'contabilidad', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      $this->asiento = new asiento();
      $this->mostrar = 'todo';
      $this->order = 'fecha DESC';
      $this->offset = 0;
      $this->desde = '';
      $this->hasta = '';
      $this->num_resultados = '';
      $this->total_resultados = array();
      $this->total_resultados_txt = '';
      
      if( isset($_REQUEST['mostrar']) )
      {
         $this->mostrar = $_REQUEST['mostrar'];
         setcookie('co_asientos_mostrar', $this->mostrar, time()+FS_COOKIES_EXPIRE);
      }
      else if( isset($_COOKIE['co_asientos_mostrar']) )
      {
         $this->mostrar = $_COOKIE['co_asientos_mostrar'];
      }
      
      if( isset($_REQUEST['order']) )
      {
         if($_REQUEST['order'] == 'fecha_desc')
         {
            $this->order = 'fecha DESC';
         }
         else if($_REQUEST['order'] == 'fecha_asc')
         {
            $this->order = 'fecha ASC';
         }
         else if($_REQUEST['order'] == 'codigo_desc')
         {
            $this->order = 'codigo DESC';
         }
         else if($_REQUEST['order'] == 'codigo_asc')
         {
            $this->order = 'codigo ASC';
         }
         else if($_REQUEST['order'] == 'total_desc')
         {
            $this->order = 'total DESC';
            
           setcookie('co_asientos_order', $this->order, time()+FS_COOKIES_EXPIRE);
      }
      else if( isset($_COOKIE['co_asientos_order']) )
      {
         $this->order = $_COOKIE['co_asientos_order'];
      }
            
      }
      $order2 = '';
         if( substr($this->order, -4) == 'DESC' )
         {
            $order2 = ', fecha DESC';
         }
         else
         {
            $order2 = ', fecha ASC';
         }
      if( isset($_GET['delete']) )
      {
         $asiento = $this->asiento->get($_GET['delete']);
         if($asiento)
         {
            if( $asiento->delete() )
            {
               $this->new_message("Asiento eliminado correctamente.");
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
      
      if( isset($_GET['descuadrados']) )
      {
         $this->resultados = $this->asiento->descuadrados();
      }
      else if($this->mostrar == 'buscar')
      {
            if( isset($_REQUEST['desde']) or isset($_REQUEST['hasta']))
            {
               $this->desde = $_REQUEST['desde'];
               $this->hasta = $_REQUEST['hasta'];
               $this->buscar($order2);
            }else{
                $this->resultados = $this->asiento->all($this->offset);
            }
      }
      else if($this->query)
      {
         $this->resultados = $this->asiento->search($this->query, $this->offset);
      }
      else
         $this->resultados = $this->asiento->all($this->offset);
   }
   private function buscar($order2)
   {
      $this->resultados = array();
      $this->num_resultados = 0;
      $query = $this->empresa->no_html( strtolower($this->query) );
      $sql = " FROM co_asientos ";
      $where = 'WHERE ';
      
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
         
         $data2 = $this->db->select_limit("SELECT *".$sql." ORDER BY ".$this->order.$order2, FS_ITEM_LIMIT, $this->offset);
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
         $url = $this->url()."&query=".$this->query."&offset=".($this->offset-FS_ITEM_LIMIT);
      }
      else if($this->query == '' AND $this->offset > 0)
      {
         $url = $this->url()."&offset=".($this->offset-FS_ITEM_LIMIT);
      }
      
      return $url;
   }
   
   public function siguiente_url()
   {
      $url = '';
      
      if($this->query != '' AND count($this->resultados) == FS_ITEM_LIMIT)
      {
         $url = $this->url()."&query=".$this->query."&offset=".($this->offset+FS_ITEM_LIMIT);
      }
      else if($this->query == '' AND count($this->resultados) == FS_ITEM_LIMIT)
      {
         $url = $this->url()."&offset=".($this->offset+FS_ITEM_LIMIT);
      }
      
      return $url;
   }
   public function url($busqueda = FALSE)
   {
      if($busqueda)
      {         
         $url = $this->url()."&mostrar=".$this->mostrar
                 ."&desde=".$this->desde
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
      $data = $this->db->select("SELECT COUNT(idasiento) as total FROM co_asientos;");
      if($data)
      {
         return intval($data[0]['total']);
      }
      else
         return 0;
   }
   
}
