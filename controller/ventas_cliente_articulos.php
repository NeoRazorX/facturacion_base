<?php

/*
 * This file is part of FacturaScripts
 * Copyright (C) 2016 Joe Nilson             <joenilson at gmail.com>
 * Copyright (C) 2016 Carlos García Gómez    <neorazorx at gmail.com>
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
require_model('linea_factura_cliente.php');

/**
 * Description of ventas_cliente_articulos
 *
 * @author Joe Nilson <joenilson@gmail.com>
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class ventas_cliente_articulos extends fs_controller
{
   public $cliente;
   public $observaciones;
   public $offset;
   public $resultados;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Articulos vendidos al cliente', 'ventas', FALSE, FALSE, FALSE);
   }

   protected function private_core()
   {
      $this->share_extensions();
      
      $this->cliente = FALSE;
      $this->resultados = array();
      
      /// recibimos el código del cliente
      if( isset($_REQUEST['cod']) AND !empty($_REQUEST['cod']) )
      {
         $cli0 = new cliente();
         $this->cliente = $cli0->get($_REQUEST['cod']);
      }
      
      $this->observaciones = '';
      if( isset($_REQUEST['observaciones']) )
      {
         $this->observaciones = $_REQUEST['observaciones'];
      }
      
      $this->offset = 0;
      if( isset($_REQUEST['offset']) )
      {
         $this->offset = $_REQUEST['offset'];
      }
      
      if($this->cliente)
      {
         $lineafacturacli = new linea_factura_cliente();
         $this->resultados = $lineafacturacli->search_from_cliente2($this->cliente->codcliente, $this->query, $this->observaciones, $this->offset);
      }
   }

   // Agregamos el tab a ventas_cliente
   public function share_extensions()
   {
      $fsxet = new fs_extension();
      $fsxet->name = 'tab_ventas_cliente_articulos';
      $fsxet->from = __CLASS__;
      $fsxet->to = 'ventas_cliente';
      $fsxet->type = 'tab';
      $fsxet->text = '<span class="glyphicon glyphicon-list" aria-hidden="true"></span> &nbsp; Artículos';
      $fsxet->save();
   }
   
   public function url()
   {
      if($this->cliente)
      {
         return parent::url().'&cod='.$this->cliente->codcliente;
      }
      else
      {
         parent::url();
      }
   }
}
