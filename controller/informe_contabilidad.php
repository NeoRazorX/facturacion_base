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

class informe_contabilidad extends fs_controller
{
   public $ejercicio;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Contabilidad', 'informes', FALSE, TRUE);
   }
   
   protected function process()
   {
      $this->ejercicio = new ejercicio();
      
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
      else if( isset($_POST['codejercicio']) )
      {
         $this->balance_situacion();
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
   
   private function libro_diario_csv($codeje)
   {
      $this->template = FALSE;
      header("content-type:application/csv;charset=UTF-8");
      header("Content-Disposition: attachment; filename=\"diario.csv\"");
      echo "asiento;fecha;subcuenta;concepto;debe;haber\n";
      
      $partida = new partida();
      $offset = 0;
      $partidas = $partida->full_from_ejercicio($codeje, $offset);
      while( count($partidas) > 0 )
      {
         foreach($partidas as $par)
         {
            echo $par['numero'].';'.$par['fecha'].';'.$par['codsubcuenta'].';'.$par['concepto'].';'.$par['debe'].';'.$par['haber']."\n";
            $offset++;
         }
         
         $partidas = $partida->full_from_ejercicio($codeje, $offset);
      }
   }
   
   private function balance_situacion()
   {
      $eje = $this->ejercicio->get($_POST['codejercicio']);
      if($eje)
      {
         $this->template = FALSE;
         $pdf_doc = new fs_pdf();
         $pdf_doc->pdf->addInfo('Title', 'Balance de situación de ' . $this->empresa->nombre);
         $pdf_doc->pdf->addInfo('Subject', 'Balance de situación de ' . $this->empresa->nombre);
         $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
         $pdf_doc->pdf->ezStartPageNumbers(580, 10, 10, 'left', '{PAGENUM} de {TOTALPAGENUM}');
         
         $excluir = FALSE;
         if( isset($eje->idasientocierre) AND isset($eje->idasientopyg) )
         {
            $excluir = array($eje->idasientocierre, $eje->idasientopyg);
         }
         
         $iba = new inventarios_balances();
         $iba->sumas_y_saldos($pdf_doc, $eje, 'de '.$_POST['desde'].' a '.$_POST['hasta'], $_POST['desde'], $_POST['hasta'], $excluir, FALSE);
         $pdf_doc->show();
      }
   }
}
