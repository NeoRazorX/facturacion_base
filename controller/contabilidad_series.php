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

require_model('serie.php');

class contabilidad_series extends fs_controller
{
   public $allow_delete;
   public $serie;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Series', 'contabilidad', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      $this->serie = new serie();
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      if( isset($_POST['codserie']) )
      {
         $serie = $this->serie->get($_POST['codserie']);
         if( !$serie )
         {
            $serie = new serie();
            $serie->codserie = $_POST['codserie'];
         }
         $serie->descripcion = $_POST['descripcion'];
         $serie->siniva = isset($_POST['siniva']);
         $serie->irpf = floatval($_POST['irpf']);
         if( $serie->save() )
         {
            $this->new_message("Serie ".$serie->codserie." guardada correctamente");
         }
         else
            $this->new_error_msg("¡Imposible guardar la serie!");
      }
      else if( isset($_GET['delete']) )
      {
         if(!$this->user->admin)
         {
            $this->new_error_msg('Sólo un administrador puede eliminar series.');
         }
         else
         {
            $serie = $this->serie->get($_GET['delete']);
            if($serie)
            {
               if( $serie->delete() )
               {
                  $this->new_message('Serie eliminada correctamente.');
               }
               else
                  $this->new_error_msg("¡Imposible eliminar la serie!");
            }
            else
               $this->new_error_msg("Serie no encontrada.");
         }
      }
   }
}
