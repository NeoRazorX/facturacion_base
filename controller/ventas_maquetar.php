<?php

/*
 * This file is part of FacturaScripts
 * Copyright (C) 2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('albaran_cliente.php');
require_model('factura_cliente.php');

/**
 * Description of ventas_maquetar
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class ventas_maquetar extends fs_controller
{
   public $documento;
   public $editable;
   public $lineas;
   public $titulo;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Maquetar', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->share_extensions();
      
      $this->documento = FALSE;
      $this->editable = FALSE;
      if( isset($_REQUEST['albaran']) )
      {
         $alb0 = new albaran_cliente();
         $this->documento = $alb0->get($_REQUEST['id']);
         if($this->documento)
         {
            $this->titulo = FS_ALBARAN.' '.$this->documento->codigo;
            $this->lineas = $this->documento->get_lineas();
            $this->editable = $this->documento->ptefactura;
            
            if( isset($_POST['idlinea']) )
            {
               if($this->editable)
               {
                  $orden = 1 + count($_POST['idlinea']);
                  foreach($_POST['idlinea'] as $idl)
                  {
                     foreach($this->lineas as $lin)
                     {
                        if($lin->idlinea == $idl)
                        {
                           $lin->orden = $orden;
                           $lin->save();
                           break;
                        }
                     }
                     
                     $orden--;
                  }
                  
                  $this->new_message('Datos guardados correctamente.');
                  $this->lineas = $this->documento->get_lineas();
               }
               else
               {
                  $this->new_error_msg('El documento ya no es editable.');
               }
            }
         }
      }
      else if( isset($_REQUEST['factura']) )
      {
         $fact0 = new factura_cliente();
         $this->documento = $fact0->get($_REQUEST['id']);
         if($this->documento)
         {
            $this->titulo = 'Factura '.$this->documento->codigo;
            $this->lineas = $this->documento->get_lineas();
            $this->editable = TRUE;
            
            if( isset($_POST['idlinea']) )
            {
               if($this->editable)
               {
                  $orden = 1 + count($_POST['idlinea']);
                  foreach($_POST['idlinea'] as $idl)
                  {
                     foreach($this->lineas as $lin)
                     {
                        if($lin->idlinea == $idl)
                        {
                           $lin->orden = $orden;
                           $lin->save();
                           break;
                        }
                     }
                     
                     $orden--;
                  }
                  
                  $this->new_message('Datos guardados correctamente.');
                  $this->lineas = $this->documento->get_lineas();
               }
               else
               {
                  $this->new_error_msg('El documento ya no es editable.');
               }
            }
         }
      }
   }
   
   private function share_extensions()
   {
      $fsext = new fs_extension();
      $fsext->name = 'maquetar_albaran';
      $fsext->from = __CLASS__;
      $fsext->to = 'ventas_albaran';
      $fsext->type = 'pdf';
      $fsext->text = '<i class="fa fa-magic"></i>&nbsp; Maquetar';
      $fsext->params = '&albaran=TRUE';
      $fsext->save();
      
      $fsext2 = new fs_extension();
      $fsext2->name = 'maquetar_factura';
      $fsext2->from = __CLASS__;
      $fsext2->to = 'ventas_factura';
      $fsext2->type = 'pdf';
      $fsext2->text = '<i class="fa fa-magic"></i>&nbsp; Maquetar';
      $fsext2->params = '&factura=TRUE';
      $fsext2->save();
   }
   
   public function url()
   {
      switch( get_class_name($this->documento) )
      {
         case 'albaran_cliente':
            return 'index.php?page='.__CLASS__.'&albaran=TRUE&id='.$this->documento->idalbaran;
            break;
         
         case 'factura_cliente':
            return 'index.php?page='.__CLASS__.'&factura=TRUE&id='.$this->documento->idfactura;
            break;
         
         default:
            return parent::url();
            break;
      }
   }
}
