<?php

/**
 * This file is part of FacturaScripts
 * Copyright (C) 2016-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('balance.php');

/**
 * Description of editar_balances
 *
 * @author Carlos Garcia Gomez
 */
class editar_balances extends fs_controller
{
   public $allow_delete;
   public $balance;
   public $cuentas;
   public $cuentas_a;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Editar balances', 'informes', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $this->share_extensions();
      
      $this->balance = FALSE;
      if( isset($_REQUEST['cod']) )
      {
         $balance = new balance();
         $this->balance = $balance->get($_REQUEST['cod']);
      }
      else if( isset($_POST['ncodbalance']) )
      {
         $this->nuevo_balance();
      }
      else if( isset($_GET['delete']) )
      {
         $this->delete_balance();
      }
      
      if($this->balance)
      {
         $bc0 = new balance_cuenta();
         $bca0 = new balance_cuenta_a();
         if( isset($_POST['nueva_cuenta']) OR isset($_POST['nueva_cuenta_a']) )
         {
            if($_POST['nueva_cuenta'])
            {
               $bc0->codbalance = $this->balance->codbalance;
               $bc0->codcuenta = $_POST['nueva_cuenta'];
               if( $bc0->save() )
               {
                  $this->new_message('Datos guardados correctamente');
               }
               else
               {
                  $this->new_error_msg('Error al guardar los datos.');
               }
            }
            else if($_POST['nueva_cuenta_a'])
            {
               $bca0->codbalance = $this->balance->codbalance;
               $bca0->codcuenta = $_POST['nueva_cuenta_a'];
               if( $bca0->save() )
               {
                  $this->new_message('Datos guardados correctamente');
               }
               else
               {
                  $this->new_error_msg('Error al guardar los datos.');
               }
            }
         }
         else if( isset($_GET['rm_cuenta']) )
         {
            $balance = $bc0->get($_GET['rm_cuenta']);
            if($balance)
            {
               if( $balance->delete() )
               {
                  $this->new_message('Datos eliminados correctamente');
               }
               else
               {
                  $this->new_error_msg('Error al eliminar los datos.');
               }
            }
            else
            {
               $this->new_error_msg('datos no encontrados.');
            }
         }
         else if( isset($_GET['rm_cuenta_a']) )
         {
            $balance = $bca0->get($_GET['rm_cuenta_a']);
            if($balance)
            {
               if( $balance->delete() )
               {
                  $this->new_message('Datos eliminados correctamente');
               }
               else
               {
                  $this->new_error_msg('Error al eliminar los datos.');
               }
            }
            else
            {
               $this->new_error_msg('datos no encontrados.');
            }
         }
         else if( isset($_POST['descripcion']) )
         {
            $this->editar_balance();
         }
         
         $this->cuentas = $bc0->all_from_codbalance($this->balance->codbalance);
         $this->cuentas_a = $bca0->all_from_codbalance($this->balance->codbalance);
      }
   }
   
   private function share_extensions()
   {
      $fsext = new fs_extension();
      $fsext->name = 'btn_balances';
      $fsext->from = __CLASS__;
      $fsext->to = 'informe_contabilidad';
      $fsext->type = 'button';
      $fsext->text = '<span class="glyphicon glyphicon-wrench"></span>'
              . '<span class="hidden-xs">&nbsp; Balances</a>';
      $fsext->save();
   }
   
   public function all_balances()
   {
      $balance = new balance();
      return $balance->all();
   }
   
   public function all_naturalezas()
   {
      return array(
          'A' => 'A = Activo',
          'P' => 'P = Pasivo',
          'PG' => 'PG = Pérdidas y ganancias',
          'IG' => 'IG = Ingresos y gastos',
      );
   }
   
   private function nuevo_balance()
   {
      $balance = new balance();
      $this->balance = $balance->get($_POST['ncodbalance']);
      if(!$this->balance)
      {
         $this->balance = new balance();
         $this->balance->codbalance = $_POST['ncodbalance'];
         $this->balance->naturaleza = $_POST['naturaleza'];
         $this->balance->descripcion1 = $_POST['descripcion'];
         
         if( $this->balance->save() )
         {
            $this->new_message('Datos guardados correctamente.');
         }
         else
         {
            $this->new_error_msg('Error al guardar los datos.');
         }
      }
   }
   
   private function editar_balance()
   {
      $this->balance->naturaleza = $_POST['naturaleza'];
      $this->balance->descripcion1 = $_POST['descripcion'];
      $this->balance->descripcion2 = $_POST['descripcion2'];
      $this->balance->descripcion3 = $_POST['descripcion3'];
      
      if( $this->balance->save() )
      {
         $this->new_message('Datos guardados correctamente.');
      }
      else
      {
         $this->new_error_msg('Error al guardar los datos.');
      }
   }
   
   private function delete_balance()
   {
      $balance = new balance();
      $balance2 = $balance->get($_REQUEST['delete']);
      if($balance2)
      {
         if( $balance2->delete() )
         {
            $this->new_message('Balance '.$balance2->codbalance.' eliminado correctamente.', TRUE);
         }
         else
         {
            $this->new_error_msg('Error al eliminar el balance');
         }
      }
      else
      {
         $this->new_error_msg('Balance no encontrado.');
      }
   }
}
