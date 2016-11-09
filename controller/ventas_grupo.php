<?php

/*
 * This file is part of FacturaScripts
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

require_model('cliente.php');
require_model('factura_cliente.php');
require_model('grupo_clientes.php');
require_model('tarifa.php');

/**
 * Description of ventas_grupo
 *
 * @author carlos
 */
class ventas_grupo extends fs_controller
{
   public $grupo;
   public $mostrar;
   public $offset;
   public $resultados;
   public $tarifa;
   public $total_clientes;
   public $total_facturas;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Grupo', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->mostrar = 'clientes';
      if( isset($_REQUEST['mostrar']) )
      {
         $this->mostrar = $_REQUEST['mostrar'];
      }
      
      $this->offset = 0;
      if( isset($_REQUEST['offset']) )
      {
         $this->offset = intval($_REQUEST['offset']);
      }
      
      $this->grupo = FALSE;
      $this->tarifa = FALSE;
      if( isset($_REQUEST['cod']) )
      {
         $grupo = new grupo_clientes();
         $this->grupo = $grupo->get($_REQUEST['cod']);
      }
      
      if($this->grupo)
      {
         $tar0 = new tarifa();
         $this->tarifa = $tar0->get($this->grupo->codtarifa);
         $this->total_clientes = 0;
         $this->total_facturas = 0;
         
         if($this->mostrar == 'clientes')
         {
            $this->resultados = $this->clientes_from_grupo($this->grupo->codgrupo, $this->offset);
         }
         else
         {
            $this->resultados = $this->facturas_from_grupo($this->grupo->codgrupo, $this->offset);
         }
      }
      else
      {
         $this->new_error_msg('Grupo no encontrado.', 'error', FALSE, FALSE);
      }
   }
   
   private function clientes_from_grupo($cod, $offset)
   {
      $clist = array();
      $sql = "FROM clientes WHERE codgrupo = ".$this->grupo->var2str($cod);
      
      $data = $this->db->select('SELECT COUNT(*) as total '.$sql);
      if($data)
      {
         $this->total_clientes = intval($data[0]['total']);
         
         $data = $this->db->select_limit('SELECT * '.$sql." ORDER BY nombre ASC", FS_ITEM_LIMIT, $offset);
         if($data)
         {
            foreach($data as $d)
               $clist[] = new cliente($d);
         }
      }
      
      return $clist;
   }
   
   private function facturas_from_grupo($cod, $offset)
   {
      $clist = array();
      $sql = "FROM facturascli WHERE codcliente IN (SELECT codcliente FROM clientes WHERE codgrupo = ".$this->grupo->var2str($cod).")";
      
      $data = $this->db->select('SELECT COUNT(*) as total '.$sql);
      if($data)
      {
         $this->total_facturas = intval($data[0]['total']);
         
         $data = $this->db->select_limit('SELECT * '.$sql.' ORDER BY fecha DESC, idfactura DESC', FS_ITEM_LIMIT, $offset);
         if($data)
         {
            foreach($data as $d)
               $clist[] = new factura_cliente($d);
         }
      }
      
      return $clist;
   }
   
   public function paginas()
   {
      $url = $this->url()."&cod=".$this->grupo->codgrupo."&mostrar=".$this->mostrar;
      
      $paginas = array();
      $i = 0;
      $num = 0;
      $actual = 1;
      
      if($this->mostrar == 'clientes')
      {
         $total = $this->total_clientes;
      }
      else
      {
         $total = $this->total_facturas;
      }
      
      /// añadimos todas la página
      while($num < $total)
      {
         $paginas[$i] = array(
             'url' => $url."&offset=".($i*FS_ITEM_LIMIT),
             'num' => $i + 1,
             'actual' => ($num == $this->offset)
         );
         
         if($num == $this->offset)
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
}
