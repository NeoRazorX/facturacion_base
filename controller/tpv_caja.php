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

require_model('almacen.php');
require_model('caja.php');
require_model('serie.php');
require_model('terminal_caja.php');

class tpv_caja extends fs_controller
{
   public $allow_delete;
   public $almacen;
   public $caja;
   public $offset;
   public $resultados;
   public $serie;
   public $terminal;
   public $terminales;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Arqueos y terminales', 'TPV', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $this->almacen = new almacen();
      $this->caja = new caja();
      $this->serie = new serie();
      $this->terminal = new terminal_caja();
      $terminal = new terminal_caja();
      
      if( isset($_POST['nuevot']) ) /// nuevo terminal
      {
         $terminal->codalmacen = $_POST['codalmacen'];
         $terminal->codserie = $_POST['codserie'];
         
         $terminal->codcliente = NULL;
         if($_POST['codcliente'] != '')
         {
            $terminal->codcliente = $_POST['codcliente'];
         }
         
         $terminal->anchopapel = intval($_POST['anchopapel']);
         $terminal->comandoapertura = $_POST['comandoapertura'];
         $terminal->comandocorte = $_POST['comandocorte'];
         $terminal->num_tickets = intval($_POST['num_tickets']);
         $terminal->sin_comandos = isset($_POST['sin_comandos']);
         
         if( $terminal->save() )
         {
            $this->new_message('Terminal añadido correctamente.');
            header('Location: index.php?page=tpv_recambios');
         }
         else
            $this->new_error_msg('Error al guardar los datos.');
      }
      else if( isset($_POST['idt']) ) /// editar terminal
      {
         $t2 = $terminal->get($_POST['idt']);
         if($t2)
         {
            $t2->codalmacen = $_POST['codalmacen'];
            $t2->codserie = $_POST['codserie'];
            
            $t2->codcliente = NULL;
            if($_POST['codcliente'] != '')
            {
               $t2->codcliente = $_POST['codcliente'];
            }
            
            $t2->anchopapel = intval($_POST['anchopapel']);
            $t2->comandoapertura = $_POST['comandoapertura'];
            $t2->comandocorte = $_POST['comandocorte'];
            $t2->num_tickets = intval($_POST['num_tickets']);
            $t2->sin_comandos = isset($_POST['sin_comandos']);
            
            if( $t2->save() )
            {
               $this->new_message('Datos guardados correctamente.');
            }
            else
               $this->new_error_msg('Error al guardar los datos.');
         }
         else
            $this->new_error_msg('Terminal no encontrado.');
      }
      else if( isset($_GET['deletet']) ) /// eliminar terminal
      {
         if($this->user->admin)
         {
            $t2 = $terminal->get($_GET['deletet']);
            if($t2)
            {
               if( $t2->delete() )
               {
                  $this->new_message('Terminal eliminado correctamente.');
               }
               else
                  $this->new_error_msg('Error al eliminar el terminal.');
            }
            else
               $this->new_error_msg('Terminal no encontrado.');
         }
         else
            $this->new_error_msg("Tienes que ser administrador para poder eliminar terminales.");
      }
      else if( isset($_GET['delete']) ) /// eliminar caja
      {
         if($this->user->admin)
         {
            $caja2 = $this->caja->get($_GET['delete']);
            if($caja2)
            {
               if( $caja2->delete() )
               {
                  $this->new_message("Arqueo eliminado correctamente.");
               }
               else
                  $this->new_error_msg("¡Imposible eliminar el arqueo!");
            }
            else
               $this->new_error_msg("Arqueo no encontrado.");
         }
         else
            $this->new_error_msg("Tienes que ser administrador para poder eliminar arqueos.");
      }
      else if( isset($_GET['cerrar']) )
      {
         if($this->user->admin)
         {
            $caja2 = $this->caja->get($_GET['cerrar']);
            if($caja2)
            {
               $caja2->fecha_fin = Date('d-m-Y H:i:s');
               if( $caja2->save() )
               {
                  $this->new_message("Arqueo cerrado correctamente.");
               }
               else
                  $this->new_error_msg("¡Imposible cerrar el arqueo!");
            }
            else
               $this->new_error_msg("Arqueo no encontrado.");
         }
         else
            $this->new_error_msg("Tienes que ser administrador para poder cerrar arqueos.");
      }
      
      $this->offset = 0;
      if( isset($_GET['offset']) )
      {
         $this->offset = intval($_GET['offset']);
      }
      
      $this->resultados = $this->caja->all($this->offset);
      $this->terminales = $terminal->all();
   }
   
   public function anterior_url()
   {
      $url = '';
      
      if($this->offset > 0)
      {
         $url = $this->url()."&offset=".($this->offset-FS_ITEM_LIMIT);
      }
      
      return $url;
   }
   
   public function siguiente_url()
   {
      $url = '';
      
      if( count($this->resultados) == FS_ITEM_LIMIT )
      {
         $url = $this->url()."&offset=".($this->offset+FS_ITEM_LIMIT);
      }
      
      return $url;
   }
}
