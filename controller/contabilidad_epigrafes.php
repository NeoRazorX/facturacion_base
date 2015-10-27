<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014-2015  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('cuenta.php');
require_model('ejercicio.php');
require_model('epigrafe.php');

class contabilidad_epigrafes extends fs_controller
{
   public $allow_delete;
   public $codejercicio;
   public $ejercicio;
   public $epigrafe;
   public $pgrupo;
   public $codpgrupo;
   public $grupo;
   public $resultados;
   public $super_epigrafes;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Tít. Cap. Rub.', 'contabilidad', FALSE, TRUE);
	  
   }
   
   protected function private_core()
   {
      $this->codejercicio = $this->empresa->codejercicio;
      $this->ejercicio = new ejercicio();
	  $pgrupo0= new pgrupo_epigrafes(); 
      $grupo0 = new grupo_epigrafes();
      $epi0 = new epigrafe();
      $this->super_epigrafes = array();;
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
	  
	  
	  
	    /* PGrupo    Título  */    
      if( isset($_POST['npgrupo']) ) /// nuevo grupo
      {
         $this->epigrafe = FALSE;
         $this->pgrupo = $pgrupo0->get_by_codigo($_GET['ngrupo'], $_POST['ejercicio']);
         if( !$this->pgrupo )
         {
            $this->pgrupo = new pgrupo_epigrafes();
            $this->pgrupo->codejercicio = $_POST['ejercicio'];
            $this->pgrupo->codpgrupo = $_POST['npgrupo'];
            $this->pgrupo->descripcion = $_POST['descripcion'];
			
            
            if( $this->pgrupo->save() )
               header( 'Location: '.$this->pgrupo->url() );
            else
            {
               $this->new_error_msg('Error al guardar el Título.');
               $this->pgrupo = FALSE;
            }
         }
      }
      else if( isset($_GET['pgrupo']) ) /// ver grupo
      {
         $this->epigrafe = FALSE;
         $this->pgrupo = $pgrupo0->get($_GET['pgrupo']);
         if($this->pgrupo AND isset($_POST['descripcion']) )
         {
            $this->pgrupo->descripcion = $_POST['descripcion'];
            if( $this->pgrupo->save() )
               $this->new_message('Título modificado correctamente.');
            else
               $this->new_error_msg('Error al modificar el Título.');
         }
      }
      else if( isset($_GET['deletepg']) ) /// eliminar ´título
      {
         $pgrupo1 = $pgrupo0->get($_GET['deletepg']);
         if($pgrupo1)
         {
            if( $pgrupo1->delete() )
               $this->new_message('Título eliminado correctamente.');
            else
               $this->new_error_msg('Error al eliminar el Título.');
         }
         else
            $this->new_error_msg('Título no encontrado.');
         
     //    $this->pgrupo = FALSE;
    //     $this->epigrafe = FALSE;
      }
	  
	  
	  
	  
  /*  Capítulo  */    
      if( isset($_POST['ngrupo']) ) /// nuevo Capítulo
      {
         $this->epigrafe = FALSE;
         $this->grupo = $grupo0->get_by_codigo($_GET['ngrupo'], $_POST['ejercicio']);
         if( !$this->grupo )
         {
            $this->grupo = new grupo_epigrafes();
            $this->grupo->codejercicio = $_POST['ejercicio'];
            $this->grupo->codgrupo = $_POST['ngrupo'];
			$this->grupo->idpgrupo = $_POST['idpgrupo'];
			$this->grupo->codpgrupo = $_POST['codpgrupo'];
            $this->grupo->descripcion = $_POST['descripcion'];
			
            
            if( $this->grupo->save() )
               header( 'Location: '.$this->grupo->url() );
            else
            {
               $this->new_error_msg('Error al guardar el Capítulo.');
               $this->grupo = FALSE;
            }
         }
      }
      else if( isset($_GET['grupo']) ) /// ver Capítulo
      {
         $this->epigrafe = FALSE;
         $this->grupo = $grupo0->get($_GET['grupo']);
         if($this->grupo AND isset($_POST['descripcion']) )
         {
            $this->grupo->descripcion = $_POST['descripcion'];
            if( $this->grupo->save() )
               $this->new_message('Capítulo modificado correctamente.');
            else
               $this->new_error_msg('Error al modificar el Capítulo.');
         }
      }
      else if( isset($_GET['deleteg']) ) /// eliminar Capítulo
      {
         $grupo1 = $grupo0->get($_GET['deleteg']);
         if($grupo1)
         {
            if( $grupo1->delete() )
               $this->new_message('Capítulo eliminado correctamente.');
            else
               $this->new_error_msg('Error al eliminar el Capítulo.');
         }
         else
            $this->new_error_msg('Capítulo no encontrado.');
         
         $this->grupo = FALSE;
         $this->epigrafe = FALSE;
      }
	  
	  
/*     Rubro  */	  
      else if( isset($_POST['nepigrafe']) ) /// nuevo Rubro
      {
         $this->epigrafe = $epi0->get_by_codigo($_POST['nepigrafe'], $_POST['ejercicio']);
         if( !$this->epigrafe )
         {
            $this->epigrafe = new epigrafe();
            $this->epigrafe->codejercicio = $_POST['ejercicio'];
            $this->epigrafe->codepigrafe = $_POST['nepigrafe'];
            
            if( isset($_POST['idpadre']) )
            {
               $this->epigrafe->idpadre = $_POST['idpadre'];
            }
            else
            {
               $this->epigrafe->codgrupo = $_POST['codgrupo'];
               $this->epigrafe->idgrupo = $_POST['idgrupo'];
               
               $this->grupo = $grupo0->get($_POST['idgrupo']);
            }
            
            $this->epigrafe->descripcion = $_POST['descripcion'];
            
            if( $this->epigrafe->save() )
            {
               header( 'Location: '.$this->epigrafe->url() );
            }
            else
               $this->new_error_msg('Error al guardar el Rubro.');
         }
      }
      else if( isset($_GET['epi']) ) /// ver Rubro
      {
         $this->grupo = FALSE;
         $this->epigrafe = $epi0->get($_GET['epi']);
         if($this->ejercicio AND isset($_POST['descripcion']) )
         {
            $this->epigrafe->descripcion = $_POST['descripcion'];
            if( $this->epigrafe->save() )
               $this->new_message('Rubro modificado correctamente.');
            else
               $this->new_error_msg('Error al modificar el Rubro.');
         }
      }
      else if( isset($_GET['deletee']) ) /// eliminar Rubro
      {
         $epi1 = $epi0->get($_GET['deletee']);
         if($epi1)
         {
            $this->grupo = $grupo0->get($epi1->idgrupo);
            
            if( $epi1->delete() )
               $this->new_message('Rubro eliminado correctamente.');
            else
               $this->new_error_msg('Error al eliminar el Rubro.');
         }
         else
         {
            $this->new_error_msg('Rubro no encontrado.');
            $this->grupo = FALSE;
         }
      }
	  
	  
	/*    Cuenta   */  
      else if( isset($_POST['ncuenta']) ) /// nueva cuenta
      {
         $this->grupo = FALSE;
         $this->epigrafe = FALSE;
         $cuenta0 = new cuenta();
         $cuenta1 = $cuenta0->get_by_codigo($_POST['ncuenta'], $_POST['ejercicio']);
         if($cuenta1)
            header( 'Location: '.$cuenta1->url() );
         else
         {
            $cuenta1 = new cuenta();
            $cuenta1->codcuenta = $_POST['ncuenta'];
            $cuenta1->codejercicio = $_POST['ejercicio'];
            $cuenta1->codepigrafe = $_POST['codepigrafe'];
            $cuenta1->descripcion = $_POST['descripcion'];
            $cuenta1->idepigrafe = $_POST['idepigrafe'];
            
            if( $cuenta1->save() )
               header( 'Location: '.$cuenta1->url() );
            else
               $this->new_error_msg('Error al guardar la cuenta.');
            
            $this->epigrafe = $epi0->get($_POST['idepigrafe']);
         }
      }
      else if( isset($_GET['deletec']) ) /// eliminar una cuenta
      {
         $this->grupo = FALSE;
         $this->epigrafe = FALSE;
         $cuenta0 = new cuenta();
         $cuenta1 = $cuenta0->get($_GET['deletec']);
         if($cuenta1)
         {
            $this->epigrafe = $epi0->get($cuenta1->idepigrafe);
            
            if( $cuenta1->delete() )
               $this->new_message('Cuenta eliminada correctamente.');
            else
               $this->new_error_msg('Error al eliminar la cuenta.');
         }
         else
            $this->new_error_msg('Cuenta no encontrada.');
      }
	  
	  
	  
/* /////////////////////////////////////////////////////
////////////////////////////////////////////////////////
////////////////////////////////////////////////////////
  */	  
	  
	  
      
      if($this->pgrupo)
      {
         $this->ppage = $this->page->get($this->page->name);
         $this->page->title = 'Título: '.$this->pgrupo->idpgrupo;
         $this->resultados = $this->pgrupo->get_grupo();
		 
      }
///////////////////////////////////////////////////////////////////	  
	 
	  
	     else if($this->grupo)
      {
         /// configuramos la página previa
         $this->ppage = $this->page->get($this->page->name);
  //       $this->grupo->codpgrupo='11';
         if( !is_null($this->grupo->idgrupo) )
         {
		 
            $this->ppage->title = 'Título: '.$this->grupo->codpgrupo;
            $this->ppage->extra_url = '&pgrupo='.$this->grupo->idpgrupo;
         }
         else 
         
         $this->page->title = 'Capítulo: '.$this->grupo->codgrupo;
         $this->resultados = $this->grupo->get_epigrafes();
      }  
	  
	  
////////////////////////////////////////////////////////////////////	  
      else if($this->epigrafe)
      {
         /// configuramos la página previa
         $this->ppage = $this->page->get($this->page->name);
         
         if( !is_null($this->epigrafe->idgrupo) )
         {
            $this->ppage->title = 'Capítulo: '.$this->epigrafe->codgrupo;
            $this->ppage->extra_url = '&grupo='.$this->epigrafe->idgrupo;
         }
         else if( !is_null($this->epigrafe->idpadre) )
         {
            $this->ppage->title = 'Padre';
            $this->ppage->extra_url = '&epi='.$this->epigrafe->idpadre;
         }
         
         $this->page->title = 'Rubro: '.$this->epigrafe->codepigrafe;
         $this->resultados = $this->epigrafe->get_cuentas();
      }
      else if( isset($_POST['ejercicio']) ) /// mostrar grupos de este ejercicio
      {
         $this->codejercicio = $_POST['ejercicio'];
         $this->grupo = FALSE;
         $this->epigrafe = FALSE;
         $this->resultados = $grupo0->all_from_ejercicio($this->codejercicio);
         $this->super_epigrafes = $epi0->super_from_ejercicio($this->codejercicio);
      }
      else
      {
         $this->grupo = FALSE;
         $this->epigrafe = FALSE;
         $this->resultados = $pgrupo0->all_from_ejercicio($this->empresa->codejercicio);
         $this->super_epigrafes = $epi0->super_from_ejercicio($this->empresa->codejercicio);

      }
   }
}
