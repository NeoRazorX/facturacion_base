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

require_model('ejercicio.php');
require_once 'plugins/facturacion_base/extras/inventarios_balances.php';
require_model('subcuenta.php');
require_model('recibo_proveedor.php');

class informe_contabilidad extends fs_controller
{
   public $ejercicio;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Contabilidad', 'informes', FALSE, TRUE);
   }
   
   protected function private_core()
   {
   		$this->subcuenta = new subcuenta();
      $this->ejercicio = new ejercicio();
	  $this->ppage = $this->page->get('libro_mayor_generar');
	if( isset($_REQUEST['buscar_subcuenta'])) 
      {

  	  $this->buscar_subcuenta();
      }
	  if( isset($_POST['codejer']) AND isset($_POST['query']) )
      {
         $this->new_search();
      }
      
      if( isset($_GET['diario']) )
      {
         $this->libro_diario_csv($_GET['diario']);
      }
      else if( isset($_GET['balance']) AND isset($_GET['eje']) )
      {
         $this->template = FALSE;
         $iba = new inventarios_balances();
         
         if($_GET['balance'] == 'pyg')
         {
            $iba->generar_pyg($_GET['eje']);
         }
         else
            $iba->generar_sit($_GET['eje']);
      }
      else if( isset($_POST['balance_ss']) )
      {
         if(isset($_POST['codejercicio'])) $this->balance_sumas_y_saldos();
      }
	  else if( isset($_POST['libro_diario']) )
      {
         if(isset($_POST['codejercicio'])) $this->libro_diario_csv($_POST['codejercicio'],$_POST['desde'],$_POST['hasta']);
      }
   }
   
   public function existe_libro_diario($codeje)
   {
      return file_exists('tmp/'.FS_TMP_NAME.'libro_diario/'.$codeje.'.pdf');
   }
   
   public function existe_libro_inventarios($codeje)
   {
      return file_exists('tmp/'.FS_TMP_NAME.'inventarios_balances/'.$codeje.'.pdf');
   }
   
   private function libro_diario_csv($codeje,$desde,$hasta)
   {
      $this->template = FALSE;
      header("content-type:application/csv;charset=UTF-8");
      header("Content-Disposition: attachment; filename=\"diario.csv\"");
      echo "asiento;fecha;subcuenta;concepto;debe;haber\n";
      
      $partida = new partida();
      $offset = 0;
	  $debD = 0;
	  $habD = 0;
	  $debT = 0;
	  $habT = 0;
      $partidas = $partida->subcuentas_por_fecha($codeje,$desde,$hasta, $offset);
	  
	  $asien_fecha = $partidas[0]['fecha'];
      while( count($partidas) > 0 )
      {
	  
         foreach($partidas as $par)
         {
		 if ( $asien_fecha == $par['fecha'])
		 {
            echo $par['numero'].';'.$par['fecha'].';'.$par['codsubcuenta'].';'.$par['concepto'].';'.$par['debe'].';'.$par['haber']."\n";
            $offset++;
			$debD = $debD + $par['debe'];
			$habD = $habD + $par['haber'];
			$debT = $debT + $par['debe'];
			$habT = $habT + $par['haber'];
		 }	
		else
		{
			echo ' ; ; ;Total diario  ;'. $debD.';'.$habD."\n\n";
			
			$debD = 0;
	  		$habD = 0;
			echo $par['numero'].';'.$par['fecha'].';'.$par['codsubcuenta'].';'.$par['concepto'].';'.$par['debe'].';'.$par['haber']."\n";
            $offset++;
			$debD = $debD + $par['debe'];
			$habD = $habD + $par['haber'];
			$debT = $debT + $par['debe'];
			$habT = $habT + $par['haber'];
			$asien_fecha = $par['fecha'];
		}	
			
         }
         
         $partidas = $partida->subcuentas_por_fecha($codeje,$desde,$hasta, $offset);
		 
      }
	  echo ' ; ; ;Total diario  ;'. $debD.';'.$habD."\n\n";
	  
	  echo ' ;  ; ;Totales ;'. $debT.';'.$habT;
   }
  
   
      private function new_search()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/subcuentas_listado';
      
      $this->cod_ejer = $_POST['codejer'];
         $this->resultados = $this->subcuenta->search_by_ejercicio($this->cod_ejer, $this->query);
		 
   
   }
   
   
    public function url()
   {
      
         return 'index.php?page=informe_contabilidad&hab_subc=1';
   }
   
   
   private function balance_sumas_y_saldos()
   {
      $eje = $this->ejercicio->get($_POST['codejercicio']);
      if($eje)
      {
         if( strtotime($_POST['desde']) < strtotime($eje->fechainicio) OR strtotime($_POST['hasta']) > strtotime($eje->fechafin) )
         {
            $this->new_error_msg('La fecha está fuera del rango del ejercicio.');
         }
         else
         {
            $this->template = FALSE;
            $pdf_doc = new fs_pdf();
            $pdf_doc->pdf->addInfo('Title', 'Balance de situación de ' . $this->empresa->nombre);
            $pdf_doc->pdf->addInfo('Subject', 'Balance de situación de ' . $this->empresa->nombre);
            $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
            $pdf_doc->pdf->ezStartPageNumbers(570, 800, 10, 'left', '{PAGENUM} de {TOTALPAGENUM}');
            
            $excluir = FALSE;
            if( isset($eje->idasientocierre) AND isset($eje->idasientopyg) )
            {
               $excluir = array($eje->idasientocierre, $eje->idasientopyg);
            }
            
            $iba = new inventarios_balances();
            
            if($_POST['tipo'] == '3')
            {
               $iba->sumas_y_saldos3($this->db, $pdf_doc, $eje, 'de '.$_POST['desde'].' a '.$_POST['hasta'], $_POST['desde'], $_POST['hasta'], $excluir, FALSE);
            }
            else if($_POST['tipo'] == '4')
            {
               $iba->sumas_y_saldos_all($pdf_doc, $eje, 'de '.$_POST['desde'].' a '.$_POST['hasta'], $_POST['desde'], $_POST['hasta'], $excluir, FALSE);
            }
			else
			{
			$iba->sumas_y_saldos($pdf_doc, $eje, 'de '.$_POST['desde'].' a '.$_POST['hasta'], $_POST['desde'], $_POST['hasta'], $excluir, FALSE);
			}
            
            $pdf_doc->show();
         }
      }
   }
}
