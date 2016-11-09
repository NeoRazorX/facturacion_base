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

require_model('atributo.php');
require_model('atributo_valor.php');

/**
 * Description of ventas_atributos
 *
 * @author carlos
 */
class ventas_atributos extends fs_controller
{
   public $allow_delete;
   public $atributo;
   public $resultados;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Atributod de artículos', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->share_extensions();
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $this->atributo = FALSE;
      $atr1 = new atributo();
      if( isset($_POST['nuevo']) )
      {
         $atr1->codatributo = substr($_POST['nuevo'], 0, 8);
         $atr1->nombre = $_POST['nuevo'];
         if( $atr1->save() )
         {
            $this->new_message('Atributo guardado correctamente.');
            $this->atributo = $atr1;
         }
         else
         {
            $this->new_error_msg('Errro al crear el atributo.');
         }
      }
      else if( isset($_REQUEST['cod']) )
      {
         $this->atributo = $atr1->get($_REQUEST['cod']);
         $this->modificar();
      }
      else if( isset($_GET['delete']) )
      {
         $atributo = $atr1->get($_GET['delete']);
         if($atributo)
         {
            if( $atributo->delete() )
            {
               $this->new_message('Atributo eliminado correctamente.');
            }
            else
            {
               $this->new_error_msg('Imposible eliminar el atributo.');
            }
         }
      }
      
      if($this->atributo)
      {
         $this->template = 'ventas_atributo';
         
         $valor = new atributo_valor();
         $this->resultados = $valor->all_from_atributo($this->atributo->codatributo);
      }
      else
      {
         $this->resultados = $atr1->all();
      }
   }
   
   private function share_extensions()
   {
      $fsext = new fs_extension();
      $fsext->name = 'btn_atributos';
      $fsext->from = __CLASS__;
      $fsext->to = 'ventas_articulos';
      $fsext->type = 'button';
      $fsext->text = '<span class="glyphicon glyphicon-list-alt" aria-hidden="true"></span>'
              . '<span class="hidden-xs"> &nbsp; Atributos</span>';
      $fsext->save();
   }
   
   private function modificar()
   {
      if( isset($_POST['nombre']) )
      {
         $this->atributo->nombre = $_POST['nombre'];
         
         if( $this->atributo->save() )
         {
            $this->new_message('Datos guardados correctamente.');
         }
         else
         {
            $this->new_error_msg('Error al guardar los datos.');
         }
      }
      else if( isset($_POST['nuevo_valor']) )
      {
         $valor = new atributo_valor();
         $valor->codatributo = $this->atributo->codatributo;
         $valor->valor = $_POST['nuevo_valor'];
         
         if( $valor->save() )
         {
            $this->new_message('Datos guardados correctamente.');
         }
         else
         {
            $this->new_error_msg('Error al guardar los datos.');
         }
      }
      else if( isset($_POST['id']) )
      {
         $val0 = new atributo_valor();
         $valor = $val0->get($_POST['id']);
         if($valor)
         {
            $valor->valor = $_POST['valor'];
            
            if( $valor->save() )
            {
               $this->new_message('Datos guardados correctamente.');
            }
            else
            {
               $this->new_error_msg('Error al guardar los datos.');
            }
         }
      }
      else if( isset($_GET['delete_val']) )
      {
         $val0 = new atributo_valor();
         $valor = $val0->get($_GET['delete_val']);
         if($valor)
         {
            if( $valor->delete() )
            {
               $this->new_message('Valor eliminado correctamente.');
            }
            else
            {
               $this->new_error_msg('Error al eliminar el valor.');
            }
         }
      }
   }
}
