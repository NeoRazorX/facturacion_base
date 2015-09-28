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
   private $balance;
   private $balance_cuenta_a;
   public $ejercicio;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Contabilidad', 'informes', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      $this->balance = new balance();
      $this->balance_cuenta_a = new balance_cuenta_a();
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
      else if( isset($_POST['informe']) )
      {
         if($_POST['informe'] == 'sumasysaldos')
         {
            $this->balance_sumas_y_saldos();
         }
         else if($_POST['informe'] == 'situacion')
         {
            $this->balance_situacion();
         }
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
            $pdf_doc->pdf->ezStartPageNumbers(580, 10, 10, 'left', '{PAGENUM} de {TOTALPAGENUM}');
            
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
            else
            {
               $iba->sumas_y_saldos($pdf_doc, $eje, 'de '.$_POST['desde'].' a '.$_POST['hasta'], $_POST['desde'], $_POST['hasta'], $excluir, FALSE);
            }
            
            $pdf_doc->show();
         }
      }
   }
   
   private function balance_situacion()
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
            $pdf_doc->pdf->ezStartPageNumbers(580, 10, 10, 'left', '{PAGENUM} de {TOTALPAGENUM}');
            
            $this->situacion($pdf_doc, $eje);
            
            $pdf_doc->show();
         }
      }
   }
   
   /**
    * Función auxiliar para generar el informe de situación.
    * Para generar este informe hay que leer los códigos de balance con naturaleza A o P
    * en orden. Pero como era demasiado sencillo, los hijos de puta de facturalux decidieron
    * añadir números romanos, para que no puedas ordenarlos fácilemnte.
    */
   private function situacion(&$pdf_doc, &$eje)
   {
      $nivel0 = array('A', 'P');
      $nivel1 = array('A', 'B', 'C');
      $nivel2 = array('', '1', '2', '3', '4', '5', '6', '7', '8', '9');
      $nivel3 = array('', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X');
      $nivel4 = array('', '1', '2', '3', '4', '5', '6', '7', '8', '9');
      $balances = $this->balance->all();
      
      $np = FALSE;
      foreach($nivel0 as $nv0)
      {
         if($np)
         {
            $pdf_doc->pdf->ezNewPage();
         }
         else
            $np = TRUE;
         
         $pdf_doc->pdf->ezText($this->empresa->nombre." - Balance de situación de "
                 .$_POST['desde']." a ".$_POST['hasta'].".\n\n", 13);
         
         /// creamos las cabeceras de la tabla
         $pdf_doc->new_table();
         $pdf_doc->add_table_header(
            array(
                'descripcion' => '<b>Descripción</b>',
                'actual' => '<b>'.$eje->year().'</b>'
            )
         );
         
         $desc1 = '';
         $desc2 = '';
         $desc3 = '';
         $desc4 = '';
         foreach($nivel1 as $nv1)
         {
            foreach($nivel2 as $nv2)
            {
               foreach($nivel3 as $nv3)
               {
                  foreach($nivel4 as $nv4)
                  {
                     foreach($balances as $bal)
                     {
                        if($bal->naturaleza == $nv0 AND $bal->nivel1 == $nv1 AND $bal->nivel2 == $nv2 AND $bal->nivel3 == $nv3 AND $bal->nivel4 == $nv4)
                        {
                           if($bal->descripcion1 != $desc1 AND $bal->descripcion1 != '')
                           {
                              $pdf_doc->add_table_row(
                                 array(
                                     'descripcion' => "\n<b>".$bal->descripcion1.'</b>',
                                     'actual' => "\n<b>".$this->get_saldo_balance2($nv0.'-'.$nv1.'-', $eje, $nv0).'</b>'
                                 )
                              );
                              
                              $desc1 = $bal->descripcion1;
                           }
                           
                           if($bal->descripcion2 != $desc2 AND $bal->descripcion2 != '')
                           {
                              $pdf_doc->add_table_row(
                                 array(
                                     'descripcion' => ' <b>'.$bal->descripcion2.'</b>',
                                     'actual' => $this->get_saldo_balance2($nv0.'-'.$nv1.'-'.$nv2.'-', $eje, $nv0)
                                 )
                              );
                              
                              $desc2 = $bal->descripcion2;
                           }
                           
                           if($bal->descripcion3 != $desc3 AND $bal->descripcion3 != '')
                           {
                              $pdf_doc->add_table_row(
                                 array(
                                     'descripcion' => '  '.$bal->descripcion3,
                                     'actual' => $this->get_saldo_balance2($nv0.'-'.$nv1.'-'.$nv2.'-'.$nv3.'-', $eje, $nv0)
                                 )
                              );
                              
                              $desc3 = $bal->descripcion3;
                           }
                           
                           break;
                        }
                     }
                  }
               }
            }
         }
         
         if($nv0 == 'A')
         {
            $pdf_doc->add_table_row(
               array(
                   'descripcion' => "\n<b>TOTAL ACTIVO (A+B)</b>",
                   'actual' => "\n<b>".$this->get_saldo_balance2($nv0.'-', $eje, $nv0).'</b>'
               )
            );
         }
         else if($nv0 == 'P')
         {
            $pdf_doc->add_table_row(
               array(
                   'descripcion' => "\n<b>TOTAL PATRIMONIO NETO (A+B+C)</b>",
                   'actual' => "\n<b>".$this->get_saldo_balance2($nv0.'-', $eje, $nv0).'</b>'
               )
            );
         }
         
         $pdf_doc->save_table(
            array(
                'fontSize' => 12,
                'cols' => array(
                    'actual' => array('justification' => 'right')
                ),
                'width' => 540,
                'shaded' => 0
            )
         );
      }
   }
   
   private function get_saldo_balance2($codbalance, $ejercicio, $naturaleza='A')
   {
      $total = 0;
      
      foreach($this->balance_cuenta_a->search_by_codbalance($codbalance) as $bca)
      {
         $total += $bca->saldo($ejercicio, $_POST['desde'], $_POST['hasta']);
      }
      
      if($naturaleza == 'A')
      {
         return $this->show_numero(0 - $total);
      }
      else
         return $this->show_numero($total);
   }
}
