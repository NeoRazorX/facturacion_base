<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2014-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('ejercicio.php');
require_model('epigrafe.php');
require_once 'plugins/facturacion_base/extras/inventarios_balances.php';

class informe_contabilidad extends fs_controller
{
   private $balance;
   private $balance_cuenta_a;
   
   public $cuentas;
   public $ejercicio;
   public $epigrafes;
   public $grupos;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Contabilidad', 'informes', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      $this->balance = new balance();
      $this->balance_cuenta_a = new balance_cuenta_a();
      $this->ejercicio = new ejercicio();
      
      if( isset($_REQUEST['buscar_subcuenta']) )
      {
         /// esto es para el autocompletar las subcuentas de la vista
         $this->buscar_subcuenta();
      }
      else if( isset($_GET['diario']) )
      {
         $this->libro_diario_csv($_GET['diario']);
      }
      else if( isset($_GET['balance']) AND isset($_GET['eje']) )
      {
         $this->template = FALSE;
         $iba = new inventarios_balances($this->db);
         
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
         else if($_POST['informe'] == 'perdidasyg')
         {
            $this->balance_perdidasyg();
         }
         else if($_POST['informe'] == 'librom')
         {
            if($_POST['filtro'] == '')
            {
               $this->libro_diario_csv(
                       $_POST['codejercicio'],
                       $_POST['desde'],
                       $_POST['hasta']
               );
            }
            else if( isset($_POST['codgrupo']) )
            {
               $this->libro_mayor_csv(
                       $_POST['codejercicio'],
                       $_POST['desde'],
                       $_POST['hasta'],
                       $_POST['codgrupo']
               );
            }
            else if( isset($_POST['codepigrafe']) )
            {
               $this->libro_mayor_csv(
                       $_POST['codejercicio'],
                       $_POST['desde'],
                       $_POST['hasta'],
                       FALSE,
                       $_POST['codepigrafe']
               );
            }
            else if( isset($_POST['codcuenta']) )
            {
               $this->libro_mayor_csv(
                       $_POST['codejercicio'],
                       $_POST['desde'],
                       $_POST['hasta'],
                       FALSE,
                       FALSE,
                       $_POST['codcuenta']
               );
            }
            else if( isset($_POST['codsubcuenta']) )
            {
               $this->libro_mayor_csv(
                       $_POST['codejercicio'],
                       $_POST['desde'],
                       $_POST['hasta'],
                       FALSE,
                       FALSE,
                       FALSE,
                       $_POST['codsubcuenta']
               );
            }
            else
            {
               $this->template = 'ajax/informe_libro_mayor';
               
               $this->grupos = FALSE;
               $this->epigrafes = FALSE;
               $this->cuentas = FALSE;
               
               if($_POST['filtro'] == 'grupo')
               {
                  $ge = new grupo_epigrafes();
                  $this->grupos = $ge->all_from_ejercicio($_POST['codejercicio']);
               }
               else if($_POST['filtro'] == 'epigrafe')
               {
                  $ep = new epigrafe();
                  $this->epigrafes = $ep->all_from_ejercicio($_POST['codejercicio']);
               }
               else if($_POST['filtro'] == 'cuenta')
               {
                  $cuenta = new cuenta();
                  $this->cuentas = $cuenta->full_from_ejercicio($_POST['codejercicio']);
               }
            }
         }
      }
   }
   
   private function buscar_subcuenta()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $subcuenta = new subcuenta();
      $eje0 = new ejercicio();
      $ejercicio = $eje0->get($_REQUEST['codejercicio']);
      $json = array();
      foreach($subcuenta->search_by_ejercicio($ejercicio->codejercicio, $_REQUEST['buscar_subcuenta']) as $subc)
      {
         $json[] = array(
             'value' => $subc->codsubcuenta,
             'data' => $subc->descripcion,
             'saldo' => $subc->saldo,
             'link' => $subc->url()
         );
      }
      
      header('Content-Type: application/json');
      echo json_encode( array('query' => $_REQUEST['buscar_subcuenta'], 'suggestions' => $json) );
   }
   
   public function existe_libro_diario($codeje)
   {
      return file_exists('tmp/'.FS_TMP_NAME.'libro_diario/'.$codeje.'.pdf');
   }
   
   public function existe_libro_inventarios($codeje)
   {
      return file_exists('tmp/'.FS_TMP_NAME.'inventarios_balances/'.$codeje.'.pdf');
   }
   
   private function libro_diario_csv($codeje, $desde=FALSE, $hasta=FALSE)
   {
      $this->template = FALSE;
      header("content-type:application/csv;charset=UTF-8");
      header("Content-Disposition: attachment; filename=\"diario.csv\"");
      echo "asiento;fecha;subcuenta;concepto;debe;haber;saldo\n";
      
      /// obtenemos el saldo inicial
      $saldo = 0;
      if($desde)
      {
         $sql = "SELECT SUM(p.debe) as debe, SUM(p.haber) as haber"
                 . " FROM co_asientos a, co_partidas p"
                 . " WHERE a.codejercicio = ".$this->empresa->var2str($codeje)
                 . " AND p.idasiento = a.idasiento"
                 . " AND a.fecha < ".$this->empresa->var2str($desde);
         
         $data = $this->db->select($sql);
         if($data)
         {
            $saldo = floatval($data[0]['debe']) - floatval($data[0]['haber']);
            echo '-;'.date('d-m-Y', strtotime($desde)).';-;Saldo inicial;0;0;'.number_format($saldo, FS_NF0, ',', '')."\n";
         }
      }
      
      /// ahora las líneas
      $sql = "SELECT a.numero,a.fecha,p.codsubcuenta,p.concepto,p.debe,p.haber"
              . " FROM co_asientos a, co_partidas p"
              . " WHERE a.codejercicio = ".$this->empresa->var2str($codeje)
              . " AND p.idasiento = a.idasiento";
      
      if($desde AND $hasta)
      {
         $sql .= " AND a.fecha >= ".$this->empresa->var2str($desde);
         $sql .= " AND a.fecha <= ".$this->empresa->var2str($hasta);
      }
      
      $sql .= " ORDER BY a.numero ASC";
      
      $offset = 0;
      $data = $this->db->select_limit($sql, 1000, $offset);
      while($data)
      {
         foreach($data as $par)
         {
            $saldo += floatval($par['debe']) - floatval($par['haber']);
            
            echo $par['numero'].';'
                    .date('d-m-Y', strtotime($par['fecha'])).';'
                    .$this->fix_html($par['codsubcuenta']).';'
                    .$this->fix_html($par['concepto']).';'
                    .number_format($par['debe'], FS_NF0, ',', '').';'
                    .number_format($par['haber'], FS_NF0, ',', '').';'
                    .number_format($saldo, FS_NF0, ',', '')."\n";
            $offset++;
         }
         
         $data = $this->db->select_limit($sql, 1000, $offset);
      }
   }
   
   private function fix_html($txt)
   {
      $newt = str_replace('&lt;', '<', $txt);
      $newt = str_replace('&gt;', '>', $newt);
      $newt = str_replace('&quot;', '"', $newt);
      $newt = str_replace('&#39;', "'", $newt);
      return $newt;
   }
   
   private function libro_mayor_csv($codeje, $desde, $hasta, $codgrupo=FALSE, $codepi=FALSE, $codcuenta=FALSE, $codsubc=FALSE)
   {
      $this->template = FALSE;
      
      header("content-type:application/csv;charset=UTF-8");
      header("Content-Disposition: attachment; filename=\"mayor.csv\"");
      echo "asiento;fecha;subcuenta;concepto;debe;haber;saldo\n";
      
      /// ahora las líneas
      $sql = "SELECT a.numero,a.fecha,p.codsubcuenta,p.concepto,p.debe,p.haber"
              . " FROM co_asientos a, co_partidas p"
              . " WHERE a.codejercicio = ".$this->empresa->var2str($codeje)
              . " AND p.idasiento = a.idasiento"
              . " AND a.fecha >= ".$this->empresa->var2str($desde)
              . " AND a.fecha <= ".$this->empresa->var2str($hasta);
      
      if($codgrupo)
      {
         $sql .= " AND p.codsubcuenta LIKE '".$this->empresa->no_html($codgrupo)."%'";
      }
      else if($codepi)
      {
         $sql .= " AND p.codsubcuenta LIKE '".$this->empresa->no_html($codepi)."%'";
      }
      else if($codcuenta)
      {
         $sql .= " AND p.codsubcuenta LIKE '".$this->empresa->no_html($codcuenta)."%'";
      }
      else if($codsubc)
      {
         $sql .= " AND p.codsubcuenta IN ('".join("','", $codsubc)."')";
      }
      
      $sql .= " ORDER BY p.codsubcuenta ASC, a.numero ASC";
      
      $codsubcuenta = FALSE;
      $offset = 0;
      $data = $this->db->select_limit($sql, 1000, $offset);
      while($data)
      {
         foreach($data as $par)
         {
            if($codsubcuenta != $par['codsubcuenta'])
            {
               if($codsubcuenta)
               {
                  echo ";;;;;;\n";
               }
               
               /// obtenemos el saldo inicial
               $codsubcuenta = $par['codsubcuenta'];
               $saldo = 0;
               $sql = "SELECT SUM(p.debe) as debe, SUM(p.haber) as haber"
                       . " FROM co_asientos a, co_partidas p"
                       . " WHERE a.codejercicio = ".$this->empresa->var2str($codeje)
                       . " AND p.idasiento = a.idasiento"
                       . " AND a.fecha < ".$this->empresa->var2str($desde)
                       . " AND p.codsubcuenta = ".$this->empresa->var2str($codsubcuenta);
               
               $data = $this->db->select($sql);
               if($data)
               {
                  $saldo = floatval($data[0]['debe']) - floatval($data[0]['haber']);
                  echo '-;'.date('d-m-Y', strtotime($desde)).';'.$codsubcuenta.';Saldo inicial;0;0;'.number_format($saldo, FS_NF0, ',', '')."\n";
               }
            }
            
            $saldo += floatval($par['debe']) - floatval($par['haber']);
            
            echo $par['numero'].';'
                    .date('d-m-Y', strtotime($par['fecha'])).';'
                    .$this->fix_html($par['codsubcuenta']).';'
                    .$this->fix_html($par['concepto']).';'
                    .number_format($par['debe'], FS_NF0, ',', '').';'
                    .number_format($par['haber'], FS_NF0, ',', '').';'
                    .number_format($saldo, FS_NF0, ',', '')."\n";
            $offset++;
         }
         
         $data = $this->db->select_limit($sql, 1000, $offset);
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
            
            $excluir = FALSE;
            if( isset($eje->idasientocierre) AND isset($eje->idasientopyg) )
            {
               $excluir = array($eje->idasientocierre, $eje->idasientopyg);
            }
            
            if($_POST['formato'] == 'csv')
            {
               header("content-type:application/csv;charset=UTF-8");
               header("Content-Disposition: attachment; filename=\"sumasysaldos.csv\"");
               echo "cuenta;descripcion;debe;haber;saldo\n";
               
               $pdf_doc = FALSE;
               if($_POST['tipo'] == '3')
               {
                  $this->sumas_y_saldos($pdf_doc, $eje, 3, 'de '.$_POST['desde'].' a '.$_POST['hasta'], $_POST['desde'], $_POST['hasta'], $excluir);
               }
               else
               {
                  $this->sumas_y_saldos($pdf_doc, $eje, 10, 'de '.$_POST['desde'].' a '.$_POST['hasta'], $_POST['desde'], $_POST['hasta'], $excluir);
               }
            }
            else
            {
               $pdf_doc = new fs_pdf();
               $pdf_doc->pdf->addInfo('Title', 'Balance de situación de ' . $this->empresa->nombre);
               $pdf_doc->pdf->addInfo('Subject', 'Balance de situación de ' . $this->empresa->nombre);
               $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
               $pdf_doc->pdf->ezStartPageNumbers(580, 10, 10, 'left', '{PAGENUM} de {TOTALPAGENUM}');
               
               if($_POST['tipo'] == '3')
               {
                  $this->sumas_y_saldos($pdf_doc, $eje, 3, 'de '.$_POST['desde'].' a '.$_POST['hasta'], $_POST['desde'], $_POST['hasta'], $excluir);
               }
               else
               {
                  $this->sumas_y_saldos($pdf_doc, $eje, 10, 'de '.$_POST['desde'].' a '.$_POST['hasta'], $_POST['desde'], $_POST['hasta'], $excluir);
               }
               
               $pdf_doc->show();
            }
         }
      }
   }
   
   /**
    * Función auxiliar para generar el balance de sumas y saldos, en su versión de 3 dígitos.
    */
   public function sumas_y_saldos(&$pdf_doc, &$eje, $tipo=3, $titulo, $fechaini, $fechafin, $excluir=FALSE)
   {
      $ge0 = new grupo_epigrafes();
      $epi0 = new epigrafe();
      $cuenta0 = new cuenta();
      $subcuenta0 = new subcuenta();
      
      $lineas = array();
      
      $sql = "SELECT p.codsubcuenta, SUM(p.debe) as debe, SUM(p.haber) as haber".
              " FROM co_partidas p, co_asientos a WHERE p.idasiento = a.idasiento".
              " AND a.codejercicio = ".$this->empresa->var2str($eje->codejercicio).
              " AND a.fecha >= ".$this->empresa->var2str($fechaini).
              " AND fecha <= ".$this->empresa->var2str($fechafin);
      
      if($excluir)
      {
         foreach($excluir as $exc)
         {
            $sql .= " AND p.idasiento != ".$this->empresa->var2str($exc);
         }
      }
      
      $sql .= " GROUP BY p.codsubcuenta ORDER BY codsubcuenta ASC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         $grupos = $ge0->all_from_ejercicio($eje->codejercicio);
         $epigrafes = $epi0->all_from_ejercicio($eje->codejercicio);
         
         for($i = 1; $i < 10; $i++)
         {
            $debe = 0;
            $haber = 0;
            foreach($data as $d)
            {
               if( substr($d['codsubcuenta'], 0, 1) == (string)$i )
               {
                  $debe += floatval($d['debe']);
                  $haber += floatval($d['haber']);
               }
            }
            
            /// añadimos el grupo
            foreach($grupos as $ge)
            {
               if($ge->codgrupo == $i)
               {
                  $lineas[] = array(
                      'cuenta' => $i,
                      'descripcion' => $ge->descripcion,
                      'debe' => $debe,
                      'haber' => $haber
                  );
                  break;
               }
            }
            
            for($j = 0; $j < 10; $j++)
            {
               $debe = 0;
               $haber = 0;
               foreach($data as $d)
               {
                  if( substr($d['codsubcuenta'], 0, 2) == (string)$i.$j )
                  {
                     $debe += floatval($d['debe']);
                     $haber += floatval($d['haber']);
                  }
               }
               
               /// añadimos el epígrafe
               foreach($epigrafes as $ep)
               {
                  if($ep->codepigrafe == (string)$i.$j )
                  {
                     $lineas[] = array(
                         'cuenta' => $i.$j,
                         'descripcion' => $ep->descripcion,
                         'debe' => $debe,
                         'haber' => $haber
                     );
                     break;
                  }
               }
               
               for($k = 0; $k < 10; $k++)
               {
                  $debe = 0;
                  $haber = 0;
                  foreach($data as $d)
                  {
                     if( substr($d['codsubcuenta'], 0, 3) == (string)$i.$j.$k )
                     {
                        $debe += floatval($d['debe']);
                        $haber += floatval($d['haber']);
                     }
                  }
                  
                  /// añadimos la cuenta
                  if($debe != 0 OR $haber != 0)
                  {
                     $cuenta = $cuenta0->get_by_codigo($i.$j.$k, $eje->codejercicio);
                     if($cuenta)
                     {
                        $lineas[] = array(
                            'cuenta' => $i.$j.$k,
                            'descripcion' => $cuenta->descripcion,
                            'debe' => $debe,
                            'haber' => $haber
                        );
                     }
                     else
                     {
                        $lineas[] = array(
                            'cuenta' => $i.$j.$k,
                            'descripcion' => '-',
                            'debe' => $debe,
                            'haber' => $haber
                        );
                     }
                  }
                  
                  if($tipo == 10)
                  {
                     /// añadimos las subcuentas
                     foreach($data as $d)
                     {
                        if( substr($d['codsubcuenta'], 0, 3) == (string)$i.$j.$k )
                        {
                           $desc = '';
                           $subc = $subcuenta0->get_by_codigo($d['codsubcuenta'], $eje->codejercicio);
                           if($subc)
                           {
                              $desc = $subc->descripcion;
                           }
                           
                           $lineas[] = array(
                               'cuenta' => $d['codsubcuenta'],
                               'descripcion' => $desc,
                               'debe' => floatval($d['debe']),
                               'haber' => floatval($d['haber'])
                           );
                        }
                     }
                  }
               }
            }
         }
      }
      
      /// a partir de la lista generamos el documento
      $linea = 0;
      $tdebe = 0;
      $thaber = 0;
      while( $linea < count($lineas) )
      {
         if($pdf_doc)
         {
            if($linea > 0)
            {
               $pdf_doc->pdf->ezNewPage();
            }
            
            $pdf_doc->pdf->ezText($this->empresa->nombre." - Balance de sumas y saldos ".$eje->year().' '.$titulo.".\n\n", 12);
            
            /// Creamos la tabla con las lineas
            $pdf_doc->new_table();
            $pdf_doc->add_table_header(
               array(
                   'cuenta' => '<b>Cuenta</b>',
                   'descripcion' => '<b>Descripción</b>',
                   'debe' => '<b>Debe</b>',
                   'haber' => '<b>Haber</b>',
                   'saldo' => '<b>Saldo</b>'
               )
            );
            
            for($i=$linea; $i<min( array($linea+48, count($lineas)) ); $i++)
            {
               if( strlen($lineas[$i]['cuenta']) == 1 )
               {
                  $a = '<b>';
                  $b = '</b>';
                  $tdebe += $lineas[$i]['debe'];
                  $thaber += $lineas[$i]['haber'];
               }
               else if( strlen($lineas[$i]['cuenta']) == 2 )
               {
                  $a = $b = '';
               }
               else
               {
                  $a = '<i>';
                  $b = '</i>';
               }
               
               $pdf_doc->add_table_row(
                  array(
                      'cuenta' => $a.$lineas[$i]['cuenta'].$b,
                      'descripcion' => $a.substr($lineas[$i]['descripcion'], 0, 50).$b,
                      'debe' => $a.$this->show_numero($lineas[$i]['debe']).$b,
                      'haber' => $a.$this->show_numero($lineas[$i]['haber']).$b,
                      'saldo' => $a.$this->show_numero( floatval($lineas[$i]['debe']) - floatval($lineas[$i]['haber']) ).$b
                  )
               );
            }
            $linea += 48;
            
            /// añadimos las sumas de la línea actual
            $desc = 'Suma y sigue';
            if( $linea >= count($lineas) )
            {
               $desc = 'Totales';
            }
            $pdf_doc->add_table_row(
               array(
                   'cuenta' => '',
                   'descripcion' => '<b>'.$desc.'</b>',
                   'debe' => '<b>'.$this->show_numero($tdebe).'</b>',
                   'haber' => '<b>'.$this->show_numero($thaber).'</b>',
                   'saldo' => '<b>'.$this->show_numero($tdebe-$thaber).'</b>'
               )
            );
            $pdf_doc->save_table(
               array(
                   'fontSize' => 9,
                   'cols' => array(
                       'debe' => array('justification' => 'right'),
                       'haber' => array('justification' => 'right'),
                       'saldo' => array('justification' => 'right')
                   ),
                   'width' => 540,
                   'shaded' => 0
               )
            );
         }
         else
         {
            for($i=$linea; $i<min( array($linea+48, count($lineas)) ); $i++)
            {
               if( strlen($lineas[$i]['cuenta']) == 1 )
               {
                  $tdebe += $lineas[$i]['debe'];
                  $thaber += $lineas[$i]['haber'];
               }
               
               echo $lineas[$i]['cuenta'].';'.
                       substr( $this->fix_html($lineas[$i]['descripcion']), 0, 50).';'.
                       number_format($lineas[$i]['debe'], FS_NF0, ',', '').';'.
                       number_format($lineas[$i]['haber'], FS_NF0, ',', '').';'.
                       number_format( floatval($lineas[$i]['debe']) - floatval($lineas[$i]['haber']) , FS_NF0, ',', '')."\n";
            }
            $linea += 48;
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
   
   private function balance_perdidasyg()
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
            $pdf_doc->pdf->addInfo('Title', 'Balance de pérdidas y ganancias de ' . $this->empresa->nombre);
            $pdf_doc->pdf->addInfo('Subject', 'Balance de pérdidas y ganancias de ' . $this->empresa->nombre);
            $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
            $pdf_doc->pdf->ezStartPageNumbers(580, 10, 10, 'left', '{PAGENUM} de {TOTALPAGENUM}');
            
            $this->perdidas_y_ganancias($pdf_doc, $eje);
            
            $pdf_doc->show();
         }
      }
   }
   
   /**
    * Función auxiliar para generar el balance de pérdidas y ganancias.
    * Este informe se confecciona a partir de las cuentas que señalan los códigos
    * de balance que empiezan por PG.
    */
   private function perdidas_y_ganancias(&$pdf_doc, &$eje)
   {
      $pdf_doc->pdf->ezText($this->empresa->nombre." - Cuenta de pérdidas y ganancias abreviada de "
                 .$_POST['desde']." a ".$_POST['hasta'].".\n\n", 13);
      
      /// creamos las cabeceras de la tabla
      $pdf_doc->new_table();
      $pdf_doc->add_table_header(
              array(
                  'descripcion' => '<b>Descripción</b>',
                  'actual' => '<b>'.$eje->year().'</b>'
              )
      );
      
      $balances = $this->balance->all();
      $num = 1;
      $continuar = TRUE;
      $totales = array( $eje->year() => array('a' => 0, 'b' => 0, 'c' => 0, 'd' => 0) );
      while($continuar)
      {
         if($num == 12)
         {
            $pdf_doc->add_table_row(
                  array(
                      'descripcion' => "\n<b>A) RESULTADOS DE EXPLOTACIÓN (1+2+3+4+5+6+7+8+9+10+11)</b>",
                      'actual' => "\n<b>".$this->show_numero($totales[$eje->year()]['a']).'</b>'
                  )
            );
         }
         else if($num == 17)
         {
            $pdf_doc->add_table_row(
                  array(
                      'descripcion' => "\n<b>B) RESULTADO FINANCIERO (12+13+14+15+16)</b>",
                      'actual' => "\n<b>".$this->show_numero($totales[$eje->year()]['b']).'</b>'
                  )
            );
            $pdf_doc->add_table_row(
                  array(
                      'descripcion' => "<b>C) RESULTADO ANTES DE IMPUESTOS (A+B)</b>",
                      'actual' => '<b>'.$this->show_numero($totales[$eje->year()]['c']).'</b>'
                  )
            );
         }
         
         $encontrado = FALSE;
         foreach($balances as $bal)
         {
            if($bal->naturaleza == 'PG' AND strstr($bal->codbalance, 'PG-A-'.$num.'-') )
            {
               $saldo1 = $this->get_saldo_balance('PG-A-'.$num.'-', $eje);
               
               /// añadimos la fila
               $pdf_doc->add_table_row(
                     array(
                         'descripcion' => $bal->descripcion2,
                         'actual' => $this->show_numero($saldo1)
                     )
               );
               
               /// sumamos donde corresponda
               if($num <= 11)
               {
                  $totales[$eje->year()]['a'] += $saldo1;
               }
               else if($num <= 16)
               {
                  $totales[$eje->year()]['b'] += $saldo1;
                  $totales[$eje->year()]['c'] = $totales[$eje->year()]['a'] + $totales[$eje->year()]['b'];
               }
               else if($num == 17)
               {
                  $totales[$eje->year()]['d'] = $totales[$eje->year()]['c'] + $saldo1;
               }
               
               $encontrado = TRUE;
               $num++;
               break;
            }
         }
         
         $continuar = $encontrado;
      }
      
      $pdf_doc->add_table_row(
            array(
                'descripcion' => "\n<b>D) RESULTADO DEL EJERCICIO (C+17)</b>",
                'actual' => "\n<b>".$this->show_numero($totales[$eje->year()]['d']).'</b>'
            )
      );
      
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
   
   private function get_saldo_balance($codbalance, &$ejercicio)
   {
      $total = 0;
      
      foreach($this->balance_cuenta_a->search_by_codbalance($codbalance) as $bca)
      {
         $total += $bca->saldo($ejercicio, $_POST['desde'], $_POST['hasta']);
      }
      
      return $total;
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
