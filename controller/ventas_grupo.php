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
         
         if($this->mostrar == 'clientes')
         {
            $this->resultados = $this->clientes_from_grupo($this->grupo->codgrupo, $this->offset);
         }
         else if($this->mostrar == 'facturas')
         {
            $this->resultados = $this->facturas_from_grupo($this->grupo->codgrupo, $this->offset);
         }
      }
      else
         $this->new_error_msg('Grupo no encontrado.');
   }
   
   private function clientes_from_grupo($cod, $offset)
   {
      $clist = array();
      $sql = "SELECT * FROM clientes WHERE codgrupo = ".$this->grupo->var2str($cod)." ORDER BY nombre ASC";
      
      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
            $clist[] = new cliente($d);
      }
      
      return $clist;
   }
   
   private function facturas_from_grupo($cod, $offset)
   {
      $clist = array();
      $sql = "SELECT * FROM facturascli WHERE codcliente IN (SELECT codcliente FROM clientes WHERE codgrupo = ".
              $this->grupo->var2str($cod).") ORDER BY fecha DESC, idfactura DESC";
      
      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
            $clist[] = new factura_cliente($d);
      }
      
      return $clist;
   }
}
